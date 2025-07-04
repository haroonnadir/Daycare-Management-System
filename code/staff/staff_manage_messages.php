<?php
session_start();
include '../db_connect.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit();
}

$staff_id = $_SESSION['user_id'];

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = "Invalid CSRF token";
        header("Location: staff_manage_messages.php");
        exit();
    }

    if (isset($_POST['send_reply'])) {
        handleSendReply();
    } elseif (isset($_POST['start_conversation'])) {
        handleStartConversation();
    }
}

function handleStartConversation() {
    global $conn, $staff_id;

    if (!isset($_POST['parent_id']) || empty($_POST['parent_id'])) {
        $_SESSION['error_msg'] = "Please select a parent to start a conversation with.";
        header("Location: staff_manage_messages.php");
        exit();
    }
    if (!isset($_POST['subject']) || empty($_POST['subject'])) {
        $_SESSION['error_msg'] = "Please enter a subject for the new conversation.";
        header("Location: staff_manage_messages.php");
        exit();
    }
    if (!isset($_POST['message']) || empty($_POST['message'])) {
        $_SESSION['error_msg'] = "Please enter an initial message.";
        header("Location: staff_manage_messages.php");
        exit();
    }

    $parent_id = (int)$_POST['parent_id'];
    $subject = htmlspecialchars(trim($_POST['subject']));
    $message_content = htmlspecialchars(trim($_POST['message']));

    $check_stmt = $conn->prepare("SELECT c.id FROM conversations c
                                  JOIN conversation_participants cp1 ON c.id = cp1.conversation_id AND cp1.user_id = ?
                                  JOIN conversation_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id = ?");
    $check_stmt->bind_param("ii", $staff_id, $parent_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $existing_conversation = $check_result->fetch_assoc();
        $conversation_id = $existing_conversation['id'];
        $_SESSION['success_msg'] = "Existing conversation found. Redirecting...";
        header("Location: staff_manage_messages.php?view_conversation=" . $conversation_id);
        exit();
    }

    $insert_conversation_stmt = $conn->prepare("INSERT INTO conversations (subject, last_message_at) VALUES (?, NOW())");
    $insert_conversation_stmt->bind_param("s", $subject);
    if ($insert_conversation_stmt->execute()) {
        $conversation_id = $conn->insert_id;
        $insert_participant_stmt = $conn->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)");
        $insert_participant_stmt->bind_param("iiii", $conversation_id, $staff_id, $conversation_id, $parent_id);
        $insert_participant_stmt->execute();

        $media_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $media_path = handleFileUpload($_FILES['attachment']);
        }

        $insert_message_stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, content, sent_at, is_read, message_type, media_path) VALUES (?, ?, ?, NOW(), 0, 'regular', ?)");
        $insert_message_stmt->bind_param("iiss", $conversation_id, $staff_id, $message_content, $media_path);
        $insert_message_stmt->execute();

        $_SESSION['success_msg'] = "Conversation started and message sent successfully!";
        header("Location: staff_manage_messages.php?view_conversation=" . $conversation_id);
        exit();

    } else {
        $_SESSION['error_msg'] = "Failed to start new conversation.";
        header("Location: staff_manage_messages.php");
        exit();
    }
}


function handleSendReply() {
    global $conn, $staff_id;

    if (!isset($_POST['conversation_id']) || empty($_POST['conversation_id'])) {
        $_SESSION['error_msg'] = "Conversation ID is missing.";
        header("Location: staff_manage_messages.php");
        exit();
    }

    $conversation_id = (int)$_POST['conversation_id'];
    $message = trim($_POST['message']);

    if (empty($message)) {
        $_SESSION['error_msg'] = "Reply message cannot be empty.";
        header("Location: staff_manage_messages.php?view_conversation=" . $conversation_id);
        exit();
    }

    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    $media_path = null;
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $media_path = handleFileUpload($_FILES['media']);
        if ($media_path === false) {
            header("Location: staff_manage_messages.php?view_conversation=" . $conversation_id);
            exit();
        }
    }

    $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, content, is_read, message_type, media_path, sent_at)
                            VALUES (?, ?, ?, 0, 'regular', ?, NOW())");
    $stmt->bind_param("iiss", $conversation_id, $staff_id, $message, $media_path);

    if ($stmt->execute()) {
        $update_conv = $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?");
        $update_conv->bind_param("i", $conversation_id);
        $update_conv->execute();
        $_SESSION['success_msg'] = "Reply sent successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to send reply.";
    }

    header("Location: staff_manage_messages.php?view_conversation=" . $conversation_id);
    exit();
}

function handleFileUpload($file) {
    $upload_dir = '../uploads/messages/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'audio/mpeg', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $file_type = $file['type'];
    $file_size = $file['size'];

    if (in_array($file_type, $allowed_types) && $file_size <= $max_size && $file['error'] === UPLOAD_ERR_OK) {
        $filename = uniqid() . '_' . basename($file['name']);
        $target_path = $upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            return $target_path;
        } else {
            $_SESSION['error_msg'] = "Failed to upload file.";
            return false;
        }
    } else {
        $_SESSION['error_msg'] = "Invalid file type or size.";
        return false;
    }
}

function getConversationsForStaff($staff_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT c.id, c.last_message_at,
                                (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1) AS last_message,
                                u.id AS parent_id, u.name AS parent_name,
                                (SELECT COUNT(id) FROM messages WHERE conversation_id = c.id AND is_read = 0 AND sender_id != ?) AS unread_count
                            FROM conversations c
                            JOIN conversation_participants cp_staff ON c.id = cp_staff.conversation_id AND cp_staff.user_id = ?
                            JOIN conversation_participants cp_parent ON c.id = cp_parent.conversation_id AND cp_parent.user_id != ?
                            JOIN users u ON cp_parent.user_id = u.id
                            WHERE u.role = 'parent'
                            ORDER BY c.last_message_at DESC");
    $stmt->bind_param("iii", $staff_id, $staff_id, $staff_id);
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

function getParents() {
    global $conn;
    return $conn->query("SELECT id, name FROM users WHERE role = 'parent' ORDER BY name");
}

$conversations = getConversationsForStaff($staff_id);
$view_conversation_id = isset($_GET['view_conversation']) ? (int)$_GET['view_conversation'] : null;
$conversation_messages = [];
if ($view_conversation_id) {
    $conversation_messages = getMessagesForConversation($view_conversation_id);
    // Mark messages as read by staff
    $stmt_read = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?");
    $stmt_read->bind_param("ii", $view_conversation_id, $staff_id);
    $stmt_read->execute();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Message Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Styles (You can adjust these) */
        :root {
            --primary-color: #28a745; /* Green */
            --secondary-color: #6c757d; /* Gray */
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
        }
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--secondary-color);
        }
        .container-fluid { padding: 0; }
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
        .dashboard-back-btn:hover { background-color:rgb(3, 11, 19); text-decoration: none; color: white; transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .d-flex.justify-content-between.flex-wrap { padding: 1rem 0; border-bottom: 1px solid var(--border-color); }
        .h2 { color: var(--secondary-color); font-weight: 600; }
        .card { border: none; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { border-radius: 8px 8px 0 0 !important; font-weight: 600; background-color: var(--primary-color) !important; color: white; display: flex; align-items: center; gap: 0.5rem; }
        .message-card { border: 1px solid var(--border-color); margin-bottom: 1rem; cursor: pointer; transition: all 0.2s; border-radius: 6px; overflow: hidden; text-decoration: none; color: inherit; }
        .message-card:hover { transform: translateY(-2px); box-shadow: 0 3px 8px rgba(0,0,0,0.08); }
        .message-card .card-body { padding: 1.25rem; }
        .message-card .card-title { font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--secondary-color); }
        .message-card .card-text { color: #495057; margin-bottom: 0.75rem; }
        .message-card .text-muted { font-size: 0.85rem; }
        .unread-count { background-color: var(--accent-color); color: var(--secondary-color); border-radius: 5px; padding: 2px 5px; font-size: 0.8rem; margin-left: 5px; }
        .form-label { font-weight: 500; margin-bottom: 0.5rem; }
        .form-control, .form-select { border-radius: 6px; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); }
        .btn { border-radius: 6px; font-weight: 500; padding: 0.5rem 1rem; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
        .btn-primary:hover { background-color: #1e7e34; border-color: #1e7e34; }
        .alert { border-radius: 6px; padding: 0.75rem 1.25rem; }
        .conversation-area { background-color: #fff; border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; margin-top: 15px; overflow-y: auto; max-height: 400px; }
        .message { padding: 8px 12px; border-radius: 6px; margin-bottom: 8px; clear: both; }
        .message.sent { background-color: #d4edda; color: #155724; float: right; }
        .message.received { background-color: #e9ecef; color: #495057; float: left; }
        .reply-form-container { margin-top: 15px; padding: 15px; background-color: #f8f9fa; border: 1px solid var(--border-color); border-radius: 8px; }
        .start-conversation-container { margin-bottom: 15px; padding: 15px; background-color: #f8f9fa; border: 1px solid var(--border-color); border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Message Center</h1>
                    <a href="staff_dashboard.php" class="dashboard-back-btn">
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
                                <i class="fas fa-plus-circle"></i> Start New Conversation
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="start_conversation" value="true">
                                    <div class="mb-3">
                                        <label for="parent_id" class="form-label">Select Parent</label>
                                        <select class="form-select" id="parent_id" name="parent_id" required>
                                            <option value="">-- Select Parent --</option>
                                            <?php $parents = getParents(); while ($parent = $parents->fetch_assoc()): ?>
                                                <option value="<?= $parent['id'] ?>"><?= htmlspecialchars($parent['name']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Subject</label>
                                        <input type="text" class="form-control" id="subject" name="subject" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="message" class="form-label">Initial Message</label>
                                        <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="attachment" class="form-label">Attachment (Optional)</label>
                                        <input type="file" class="form-control" id="attachment" name="attachment" accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Start Conversation</button>
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
                                        <a href="staff_manage_messages.php?view_conversation=<?= $conv['id'] ?>" class="message-card <?= $conv['unread_count'] > 0 ? 'unread' : '' ?>" style="display: block; margin-bottom: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; text-decoration: none; color: inherit;">
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
                                                <div class="message <?= $msg['sender_role'] === 'staff' ? 'sent' : 'received' ?>">
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
        document.getElementById('attachment').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('file-preview');
            preview.innerHTML = '';
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let element;
                    if (file.type.startsWith('image/')) {
                        element = document.createElement('img');
                        element.className = 'img-thumbnail';
                    } else if (file.type.startsWith('video/')) {
                        element = document.createElement('video');
                        element.controls = true;
                    } else if (file.type.startsWith('audio/')) {
                        element = document.createElement('audio');
                        element.controls = true;
                    } else {
                        element = document.createElement('p');
                        element.textContent = 'Attached file: ' + file.name;
                    }
                    element.src = e.target.result;
                    preview.appendChild(element);
                };
                reader.readAsDataURL(file);
            }
        });

        // Media preview for reply
        document.getElementById('mediaInputReply').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('reply-file-preview');
            preview.innerHTML = '';
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let element;
                    if (file.type.startsWith('image/')) {
                        element = document.createElement('img');
                        element.className = 'img-thumbnail';
                    } else if (file.type.startsWith('video/')) {
                        element = document.createElement('video');
                        element.controls = true;
                    } else if (file.type.startsWith('audio/')) {
                        element = document.createElement('audio');
                        element.controls = true;
                    } else {
                        element = document.createElement('p');
                        element.textContent = 'Attached file: ' + file.name;
                    }
                    element.src = e.target.result;
                    preview.appendChild(element);
                };
                reader.readAsDataURL(file);
            }
        });


        // Scroll to bottom of conversation area
        const conversationArea = document.querySelector('.conversation-area');
        if (conversationArea) {
            conversationArea.scrollTop = conversationArea.scrollHeight;
        }
    </script>
</body>
</html>