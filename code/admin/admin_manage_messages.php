<?php
session_start();
include '../db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = "Invalid CSRF token";
        header("Location: admin_manage_messages.php");
        exit();
    }

    if (isset($_POST['send_message'])) {
        handleSendMessage();
    } elseif (isset($_POST['send_reply'])) {
        handleSendReply();
    }
}

function handleSendMessage() {
    global $conn, $admin_id;
    $recipient_ids = [];

    // Determine recipient selection type
    if ($_POST['recipient_type'] === 'single') {
        if (!empty($_POST['recipient_id'])) {
            $recipient_ids[] = $_POST['recipient_id'];
        }
    } else { // bulk send to all
        $parents = getParents();
        while ($parent = $parents->fetch_assoc()) {
            $recipient_ids[] = $parent['id'];
        }
    }

    if (empty($recipient_ids)) {
        $_SESSION['error_msg'] = "No recipients selected";
        header("Location: admin_manage_messages.php");
        exit();
    }

    $message = trim($_POST['message']);
    $is_announcement = isset($_POST['is_announcement']) ? 1 : 0;

    // Validate message
    if (empty($message)) {
        $_SESSION['error_msg'] = "Message cannot be empty";
        header("Location: admin_manage_messages.php");
        exit();
    }

    // Sanitize message
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    // Handle file upload
    $media_path = null;
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/messages/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif',
            'video/mp4', 'audio/mpeg',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file_type = $_FILES['media']['type'];
        $file_size = $_FILES['media']['size'];

        if (in_array($file_type, $allowed_types)) {
            if ($file_size <= $max_size) {
                $filename = uniqid() . '_' . basename($_FILES['media']['name']);
                $target_path = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['media']['tmp_name'], $target_path)) {
                    $media_path = $target_path;
                } else {
                    $_SESSION['error_msg'] = "Failed to upload file";
                }
            } else {
                $_SESSION['error_msg'] = "File size exceeds 5MB limit";
            }
        } else {
            $_SESSION['error_msg'] = "Invalid file type";
        }
    }

    // Send message to each recipient
    $success_count = 0;
    $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, content, is_read, message_type, media_path, sent_at)
                            VALUES (?, ?, ?, 0, ?, ?, NOW())");
    $message_type = $is_announcement ? 'announcement' : 'regular';

    foreach ($recipient_ids as $recipient_id) {
        $conversation_id = getConversationId($admin_id, $recipient_id);
        $stmt->bind_param("iisss", $conversation_id, $admin_id, $message, $message_type, $media_path);

        if ($stmt->execute()) {
            $success_count++;
            // Update last message time
            $update_conv = $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?");
            $update_conv->bind_param("i", $conversation_id);
            $update_conv->execute();
        }
    }

    if ($success_count > 0) {
        $_SESSION['success_msg'] = "Message sent to $success_count recipient(s)!";
    } else {
        $_SESSION['error_msg'] = "Failed to send message to any recipients";
    }

    header("Location: admin_manage_messages.php");
    exit();
}

function handleSendReply() {
    global $conn, $admin_id;

    if (!isset($_POST['conversation_id']) || empty($_POST['conversation_id'])) {
        $_SESSION['error_msg'] = "Conversation ID is missing.";
        header("Location: admin_manage_messages.php");
        exit();
    }

    $conversation_id = (int)$_POST['conversation_id'];
    $message = trim($_POST['message']);

    if (empty($message)) {
        $_SESSION['error_msg'] = "Reply message cannot be empty.";
        header("Location: admin_manage_messages.php?view_conversation=" . $conversation_id);
        exit();
    }

    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    $media_path = null;
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/messages/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $filename = uniqid() . '_' . basename($_FILES['media']['name']);
        $target_path = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['media']['tmp_name'], $target_path)) {
            $media_path = $target_path;
        } else {
            $_SESSION['error_msg'] = "Failed to upload reply attachment.";
            header("Location: admin_manage_messages.php?view_conversation=" . $conversation_id);
            exit();
        }
    }

    $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, content, is_read, message_type, media_path, sent_at)
                            VALUES (?, ?, ?, 0, 'regular', ?, NOW())");
    $stmt->bind_param("iiss", $conversation_id, $admin_id, $message, $media_path);

    if ($stmt->execute()) {
        $update_conv = $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?");
        $update_conv->bind_param("i", $conversation_id);
        $update_conv->execute();
        $_SESSION['success_msg'] = "Reply sent successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to send reply.";
    }

    header("Location: admin_manage_messages.php?view_conversation=" . $conversation_id);
    exit();
}

function getConversationId($user1, $user2) {
    global $conn;

    $stmt = $conn->prepare("SELECT conversation_id FROM conversation_participants
                            WHERE user_id = ? AND conversation_id IN
                            (SELECT conversation_id FROM conversation_participants WHERE user_id = ?)");
    $stmt->bind_param("ii", $user1, $user2);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['conversation_id'];
    } else {
        $conn->query("INSERT INTO conversations (subject, created_at, last_message_at)
                    VALUES ('', NOW(), NOW())");
        $conversation_id = $conn->insert_id;

        $conn->query("INSERT INTO conversation_participants (conversation_id, user_id)
                    VALUES ($conversation_id, $user1), ($conversation_id, $user2)");
        return $conversation_id;
    }
}

function getParents() {
    global $conn;
    return $conn->query("SELECT id, name FROM users WHERE role = 'parent' ORDER BY name");
}

function getConversationsForAdmin($admin_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT c.id, c.last_message_at,
                                (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1) AS last_message,
                                u.id AS parent_id, u.name AS parent_name,
                                (SELECT COUNT(id) FROM messages WHERE conversation_id = c.id AND is_read = 0 AND sender_id != ?) AS unread_count
                            FROM conversations c
                            JOIN conversation_participants cp_admin ON c.id = cp_admin.conversation_id AND cp_admin.user_id = ?
                            JOIN conversation_participants cp_parent ON c.id = cp_parent.conversation_id AND cp_parent.user_id != ?
                            JOIN users u ON cp_parent.user_id = u.id
                            WHERE u.role = 'parent'
                            ORDER BY c.last_message_at DESC");
    $stmt->bind_param("iii", $admin_id, $admin_id, $admin_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getMessagesForConversation($conversation_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT m.*, u.name AS sender_name, u.role AS sender_role
                            FROM messages m
                            JOIN users u ON m.sender_id = u.id
                            WHERE m.conversation_id = ?
                            ORDER BY m.sent_at ASC");
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    return $stmt->get_result();
}

$conversations = getConversationsForAdmin($admin_id);
$view_conversation_id = isset($_GET['view_conversation']) ? (int)$_GET['view_conversation'] : null;
$conversation_messages = [];
if ($view_conversation_id) {
    $conversation_messages = getMessagesForConversation($view_conversation_id);
    // Mark messages as read by admin
    $stmt_read = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?");
    $stmt_read->bind_param("ii", $view_conversation_id, $admin_id);
    $stmt_read->execute();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Message Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #ffc107;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--secondary-color);
        }

        .container-fluid {
            padding: 0;
        }

        .dashboard-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background-color: #1a252f;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .dashboard-back-btn:hover {
            background-color:rgb(3, 11, 19);
            text-decoration: none;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        /* Header Styles */
        .d-flex.justify-content-between.flex-wrap {
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .h2 {
            color: var(--secondary-color);
            font-weight: 600;
        }

        .h2 a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 1rem;
            font-weight: normal;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .h2 a:hover {
            text-decoration: underline;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .card-header {
            border-radius: 8px 8px 0 0 !important;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .bg-primary {
            background-color: var(--primary-color) !important;
        }

        /* Message Cards */
        .message-card {
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            border-radius: 6px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
        }

        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.08);
        }

        .message-card .card-body {
            padding: 1.25rem;
        }

        .message-card .card-title {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
        }

        .message-card .card-text {
            color: #495057;
            margin-bottom: 0.75rem;
        }

        .message-card .text-muted {
            font-size: 0.85rem;
        }

        .announcement {
            border-left-color: var(--accent-color);
        }

        .unread-count {
            background-color: var(--accent-color);
            color: var(--secondary-color);
            border-radius: 5px;
            padding: 2px 5px;
            font-size: 0.8rem;
            margin-left: 5px;
        }

        /* Form Elements */
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* Media Preview */
        .media-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        /* Animations */
        #recipientSelectContainer {
            transition: all 0.3s ease;
            overflow: hidden;
        }

        /* Badges */
        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
        }

        .bg-warning {
            background-color: var(--accent-color) !important;
        }

        /* Buttons */
        .btn {
            border-radius: 6px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        /* Alerts */
        .alert {
            border-radius: 6px;
            padding: 0.75rem 1.25rem;
        }

        /* Conversation Area */
        .conversation-area {
            background-color: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            overflow-y: auto;
            max-height: 400px;
        }

        .message {
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            clear: both;
        }

        .message.sent {
            background-color: #d4edda;
            color: #155724;
            float: right;
        }

        .message.received {
            background-color: #e9ecef;
            color: #495057;
            float: left;
        }

        .reply-form-container {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Message Center</h1>
                    <a href="admin_dashboard.php" class="dashboard-back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-paper-plane"></i> Send New Message
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Recipient Selection</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recipient_type" id="singleRecipient" value="single" checked>
                                            <label class="form-check-label" for="singleRecipient">Single Parent</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recipient_type" id="allRecipients" value="all">
                                            <label class="form-check-label" for="allRecipients">All Parents</label>
                                        </div>
                                    </div>

                                    <div class="mb-3" id="recipientSelectContainer">
                                        <label class="form-label">Select Parent</label>
                                        <select class="form-select" name="recipient_id" id="recipientSelect">
                                            <option value="">Select Parent</option>
                                            <?php $parents = getParents(); while ($parent = $parents->fetch_assoc()): ?>
                                                <option value="<?= $parent['id'] ?>"><?= htmlspecialchars($parent['name']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Message</label>
                                        <textarea class="form-control" name="message" rows="3" required></textarea>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_announcement" name="is_announcement">
                                        <label class="form-check-label" for="is_announcement">Send as announcement</label>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Media Attachment (Optional)</label>
                                        <input type="file" class="form-control" name="media" id="mediaInput" accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                                        <small class="text-muted">Max size: 5MB. Supported: images, videos, audio, PDF, Word</small>
                                        <div id="mediaPreview" class="mt-2"></div>
                                    </div>

                                    <button type="submit" name="send_message" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Send
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-comments"></i> Conversations
                            </div>
                            <div class="card-body" style="overflow-y: auto; max-height: 400px;">
                                <?php if ($conversations->num_rows > 0): ?>
                                    <?php while ($conv = $conversations->fetch_assoc()): ?>
                                        <a href="admin_manage_messages.php?view_conversation=<?= $conv['id'] ?>" class="message-card <?= $conv['unread_count'] > 0 ? 'unread' : '' ?>" style="display: block; margin-bottom: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; text-decoration: none; color: inherit;">
                                            <h6 class="card-title"><?= htmlspecialchars($conv['parent_name']) ?>
                                                <?php if ($conv['unread_count'] > 0): ?>
                                                    <span class="unread-count"><?= $conv['unread_count'] ?></span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="card-text text-muted" style="font-size: 0.9em;">
                                                <?= htmlspecialchars(substr($conv['last_message'], 0, 50)) ?><?= strlen($conv['last_message']) > 50 ? '...' : '' ?>
                                            </p>
                                            <small class="text-muted"><?= date('M j, Y g:i a', strtotime($conv['last_message_at'])) ?></small>
                                        </a>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">No conversations found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-inbox"></i> Conversation with Parent
                            </div>
                            <div class="card-body">
                                <?php if ($view_conversation_id): ?>
                                    <div class="conversation-area">
                                        <?php if ($conversation_messages->num_rows > 0): ?>
                                            <?php while ($msg = $conversation_messages->fetch_assoc()): ?>
                                                <div class="message <?= $msg['sender_role'] === 'admin' ? 'sent' : 'received' ?>">
                                                    <small><strong><?= htmlspecialchars($msg['sender_name']) ?>:</strong></small>
                                                    <p><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                                                    <?php if (!empty($msg['media_path'])): ?>
                                                        <?php
                                                        $file_ext = strtolower(pathinfo($msg['media_path'], PATHINFO_EXTENSION));
                                                        $image_exts = ['jpg', 'jpeg', 'png', 'gif'];
                                                        $video_exts = ['mp4', 'webm'];
                                                        $audio_exts = ['mp3', 'wav'];
                                                        ?>
                                                        <?php if (in_array($file_ext, $image_exts)): ?>
                                                            <img src="<?= htmlspecialchars($msg['media_path']) ?>" class="media-preview img-thumbnail">
                                                        <?php elseif (in_array($file_ext, $video_exts)): ?>
                                                            <video controls class="media-preview"><source src="<?= htmlspecialchars($msg['media_path']) ?>" type="video/<?= $file_ext ?>"></video>
                                                        <?php elseif (in_array($file_ext, $audio_exts)): ?>
                                                            <audio controls class="media-preview"><source src="<?= htmlspecialchars($msg['media_path']) ?>" type="audio/<?= $file_ext ?>"></audio>
                                                        <?php else: ?>
                                                            <a href="<?= htmlspecialchars($msg['media_path']) ?>" class="btn btn-sm btn-secondary" download><i class="fas fa-download"></i> Download Attachment</a>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <small class="text-muted float-end"><?= date('M j, Y g:i a', strtotime($msg['sent_at'])) ?></small>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <div class="alert alert-info">No messages in this conversation.</div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="reply-form-container">
                                        <h5>Reply to Parent</h5>
                                        <form method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="conversation_id" value="<?= $view_conversation_id ?>">
                                            <div class="mb-3">
                                                <label for="message" class="form-label">Your Reply</label>
                                                <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label for="mediaInputReply" class="form-label">Media Attachment (Optional)</label>
                                                <input type="file" class="form-control" name="media" id="mediaInputReply" accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                                                <small class="text-muted">Max size: 5MB. Supported: images, videos, audio, PDF, Word</small>
                                            </div>
                                            <button type="submit" name="send_reply" class="btn btn-primary">
                                                <i class="fas fa-reply"></i> Send Reply
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">Select a conversation from the left to view and reply.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Media preview functionality for new message
        document.getElementById('mediaInput').addEventListener('change', function(e) {
            const preview = document.getElementById('mediaPreview');
            preview.innerHTML = '';
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                let mediaElement;
                if (file.type.startsWith('image/')) {
                    mediaElement = document.createElement('img');
                    mediaElement.className = 'media-preview img-thumbnail';
                } else if (file.type.startsWith('video/')) {
                    mediaElement = document.createElement('video');
                    mediaElement.controls = true;
                    mediaElement.className = 'media-preview';
                } else if (file.type.startsWith('audio/')) {
                    mediaElement = document.createElement('audio');
                    mediaElement.controls = true;
                    mediaElement.className = 'media-preview';
                } else {
                    mediaElement = document.createElement('p');
                    mediaElement.textContent = 'File: ' + file.name;
                }
                mediaElement.src = e.target.result;
                preview.appendChild(mediaElement);
            }
            reader.readAsDataURL(file);
        });

        // Toggle recipient select based on radio buttons
        document.addEventListener('DOMContentLoaded', function() {
            const singleRecipientRadio = document.getElementById('singleRecipient');
            const allRecipientsRadio = document.getElementById('allRecipients');
            const recipientSelectContainer = document.getElementById('recipientSelectContainer');

            function toggleRecipientSelect() {
                if (singleRecipientRadio.checked) {
                    recipientSelectContainer.style.display = 'block';
                    document.getElementById('recipientSelect').setAttribute('required', 'required');
                } else {
                    recipientSelectContainer.style.display = 'none';
                    document.getElementById('recipientSelect').removeAttribute('required');
                }
            }

            singleRecipientRadio.addEventListener('change', toggleRecipientSelect);
            allRecipientsRadio.addEventListener('change', toggleRecipientSelect);

            // Initialize on page load
            toggleRecipientSelect();
        });

        // Scroll to bottom of conversation area
        const conversationArea = document.querySelector('.conversation-area');
        if (conversationArea) {
            conversationArea.scrollTop = conversationArea.scrollHeight;
        }
    </script>
</body>
</html>