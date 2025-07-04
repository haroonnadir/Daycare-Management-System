<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../index.php");
    exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = "Invalid CSRF token";
        header("Location: parent_messages.php");
        exit();
    }

    if (isset($_POST['send_reply'])) {
        handleSendReply();
    } elseif (isset($_POST['update_message'])) {
        handleUpdateMessage();
    } elseif (isset($_POST['update_media'])) {
        handleUpdateMedia();
    } elseif (isset($_POST['delete_media'])) {
        handleDeleteMedia();
    } elseif (isset($_POST['start_new_conversation'])) {
        handleStartNewConversation();
    }
}

// Handle starting a new conversation (moved here)
function handleStartNewConversation() {
    global $conn;

    $recipient_id = (int)$_POST['recipient'];
    $subject = trim($_POST['subject']);
    $message_content = trim($_POST['message']);

    if (empty($subject) || empty($message_content)) {
        $_SESSION['error_msg'] = "Subject and message are required.";
        header("Location: parent_messages.php");
        exit();
    }

    // Check if a conversation already exists with this recipient
    $check_stmt = $conn->prepare("SELECT c.id FROM conversations c
                                  JOIN conversation_participants cp1 ON c.id = cp1.conversation_id AND cp1.user_id = ?
                                  JOIN conversation_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id = ?");
    $check_stmt->bind_param("ii", $_SESSION['user_id'], $recipient_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $existing_conversation = $check_result->fetch_assoc();
        $conversation_id = $existing_conversation['id'];
        $_SESSION['success_msg'] = "Existing conversation found. Redirecting...";
        header("Location: parent_view_messages.php?conversation_id=" . $conversation_id);
        exit();
    }

    // Create a new conversation
    $insert_conversation_stmt = $conn->prepare("INSERT INTO conversations (subject, last_message_at) VALUES (?, NOW())");
    $insert_conversation_stmt->bind_param("s", $subject);
    if ($insert_conversation_stmt->execute()) {
        $conversation_id = $conn->insert_id;
        // Add participants
        $insert_participant_stmt = $conn->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)");
        $insert_participant_stmt->bind_param("iiii", $conversation_id, $_SESSION['user_id'], $conversation_id, $recipient_id);
        $insert_participant_stmt->execute();

        // Send the initial message
        $media_path = null;
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['error'])) {
            foreach ($_FILES['attachments']['error'] as $key => $error) {
                if ($error === UPLOAD_ERR_OK) {
                    $attachment_path = handleFileUpload($_FILES['attachments'], $key);
                    if ($attachment_path !== false) {
                        // Assuming only one initial attachment for simplicity
                        $media_path = $attachment_path;
                        break;
                    }
                }
            }
        }

        $insert_message_stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, content, sent_at, is_read, message_type, media_path) VALUES (?, ?, ?, NOW(), 0, 'regular', ?)");
        $insert_message_stmt->bind_param("iiss", $conversation_id, $_SESSION['user_id'], $message_content, $media_path);
        $insert_message_stmt->execute();

        $_SESSION['success_msg'] = "Conversation started and message sent successfully!";
        header("Location: parent_view_messages.php?conversation_id=" . $conversation_id);
        exit();

    } else {
        $_SESSION['error_msg'] = "Failed to start new conversation.";
        header("Location: parent_messages.php");
        exit();
    }
}

// Handle sending a reply
function handleSendReply() {
    global $conn;

    // Validate conversation access
    $conversation_id = (int)$_POST['conversation_id'];
    if (!verifyConversationAccess($conversation_id, $_SESSION['user_id'])) {
        $_SESSION['error_msg'] = "Invalid conversation access";
        header("Location: parent_messages.php");
        exit();
    }

    // Validate message
    $message = trim($_POST['message']);
    if (empty($message)) {
        $_SESSION['error_msg'] = "Message cannot be empty";
        header("Location: parent_view_messages.php?conversation_id=$conversation_id");
        exit();
    }

    // Sanitize message
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    // Handle file upload
    $media_path = null;
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $media_path = handleFileUpload($_FILES['media']);
        if ($media_path === false) {
            header("Location: parent_view_messages.php?conversation_id=$conversation_id");
            exit();
        }
    }

    // Insert reply using prepared statement
    $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, content, is_read, message_type, media_path)
                            VALUES (?, ?, ?, 0, 'regular', ?)");
    $stmt->bind_param("iiss", $conversation_id, $_SESSION['user_id'], $message, $media_path);

    if ($stmt->execute()) {
        // Update conversation timestamp
        $update = $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?");
        $update->bind_param("i", $conversation_id);
        $update->execute();

        $_SESSION['success_msg'] = "Reply sent successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to send reply";
    }

    header("Location: parent_view_messages.php?conversation_id=$conversation_id");
    exit();
}

// Handle updating a message
function handleUpdateMessage() {
    global $conn;

    $message_id = (int)$_POST['message_id'];
    $conversation_id = (int)$_POST['conversation_id'];

    // Verify user owns the message and has access to conversation
    if (!verifyMessageOwnership($message_id, $_SESSION['user_id']) ||
        !verifyConversationAccess($conversation_id, $_SESSION['user_id'])) {
        $_SESSION['error_msg'] = "Unauthorized to update this message";
        header("Location: parent_view_messages.php?conversation_id=$conversation_id");
        exit();
    }

    $new_content = trim($_POST['new_content']);
    if (empty($new_content)) {
        $_SESSION['error_msg'] = "Message cannot be empty";
        header("Location: parent_view_messages.php?conversation_id=$conversation_id");
        exit();
    }

    $new_content = htmlspecialchars($new_content, ENT_QUOTES, 'UTF-8');

    $stmt = $conn->prepare("UPDATE messages SET content = ?, edited_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_content, $message_id);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Message updated successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to update message";
    }

    header("Location: parent_view_messages.php?conversation_id=$conversation_id");
    exit();
}

// Handle updating media
function handleUpdateMedia() {
    global $conn;

    $message_id = (int)$_POST['message_id'];
    $conversation_id = (int)$_POST['conversation_id'];

    // Verify user owns the message and has access to conversation
    if (!verifyMessageOwnership($message_id, $_SESSION['user_id']) ||
        !verifyConversationAccess($conversation_id, $_SESSION['user_id'])) {
        $_SESSION['error_msg'] = "Unauthorized to update this media";
        header("Location: parent_view_messages.php?conversation_id=$conversation_id");
        exit();
    }

    // Get old media path to delete later
    $stmt = $conn->prepare("SELECT media_path FROM messages WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_media_path = $result->fetch_assoc()['media_path'];

    // Handle new file upload
    $new_media_path = null;
    if (isset($_FILES['new_media']) && $_FILES['new_media']['error'] === UPLOAD_ERR_OK) {
        $new_media_path = handleFileUpload($_FILES['new_media']);
        if ($new_media_path === false) {
            header("Location: parent_view_messages.php?conversation_id=$conversation_id");
            exit();
        }
    } else {
        $_SESSION['error_msg'] = "No file uploaded";
        header("Location: parent_view_messages.php?conversation_id=$conversation_id");
        exit();
    }

    // Update the media path in database
    $stmt = $conn->prepare("UPDATE messages SET media_path = ?, edited_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_media_path, $message_id);

    if ($stmt->execute()) {
        // Delete old media file if it exists
        if ($old_media_path && file_exists($old_media_path)) {
            unlink($old_media_path);
        }

        $_SESSION['success_msg'] = "Media updated successfully!";
    } else {
        // Delete the new file if update failed
        if ($new_media_path && file_exists($new_media_path)) {
            unlink($new_media_path);
        }
        $_SESSION['error_msg'] = "Failed to update media";
    }

    header("Location: parent_view_messages.php?conversation_id=$conversation_id");
    exit();
}

// Handle deleting media
function handleDeleteMedia() {
    global $conn;

    $message_id = (int)$_POST['message_id'];
    $conversation_id = (int)$_POST['conversation_id'];

    // Verify user owns the message and has access to conversation
    if (!verifyMessageOwnership($message_id, $_SESSION['user_id']) ||
        !verifyConversationAccess($conversation_id, $_SESSION['user_id'])) {
        $_SESSION['error_msg'] = "Unauthorized to delete this media";
        header("Location: parent_view_messages.php?conversation_id=$conversation_id");
        exit();
    }

    // Get media path to delete
    $stmt = $conn->prepare("SELECT media_path FROM messages WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $media_path = $result->fetch_assoc()['media_path'];

    // Update database to remove media reference
    $stmt = $conn->prepare("UPDATE messages SET media_path = NULL, edited_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $message_id);

    if ($stmt->execute()) {
        // Delete the media file if it exists
        if ($media_path && file_exists($media_path)) {
            unlink($media_path);
        }

        $_SESSION['success_msg'] = "Media deleted successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to delete media";
    }

    header("Location: parent_view_messages.php?conversation_id=$conversation_id");
    exit();
}

// Verify user has access to conversation
function verifyConversationAccess($conversation_id, $user_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT 1 FROM conversation_participants
                            WHERE conversation_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $conversation_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Verify user owns the message
function verifyMessageOwnership($message_id, $user_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT 1 FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->bind_param("ii", $message_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Secure file upload handler
function handleFileUpload($file, $index = null) {
    global $conn;

    if ($index !== null) {
        if (!isset($file['error'][$index]) || $file['error'][$index] !== UPLOAD_ERR_OK) {
            return false;
        }
        $current_file = [
            'name' => $file['name'][$index],
            'type' => $file['type'][$index],
            'tmp_name' => $file['tmp_name'][$index],
            'error' => $file['error'][$index],
            'size' => $file['size'][$index]
        ];
    } else {
        $current_file = $file;
        if (!$current_file || $current_file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
    }


    $upload_dir = '../uploads/messages/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'audio/mpeg' => 'mp3',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
    ];

    $max_size = 5 * 1024 * 1024; // 5MB

    // Verify file size
    if ($current_file['size'] > $max_size) {
        $_SESSION['error_msg'] = "File size exceeds 5MB limit";
        return false;
    }

    // Verify MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($current_file['tmp_name']);

    if (!array_key_exists($mime, $allowed_types)) {
        $_SESSION['error_msg'] = "Invalid file type";
        return false;
    }

    // Generate safe filename
    $extension = $allowed_types[$mime];
    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $target_path = $upload_dir . $filename;

    if (move_uploaded_file($current_file['tmp_name'], $target_path)) {
        return $target_path;
    } else {
        $_SESSION['error_msg'] = "Failed to upload file";
        return false;
    }
}

// Mark messages as read when viewing conversation
if (isset($_GET['conversation_id'])) {
    $conversation_id = (int)$_GET['conversation_id'];

    // Verify conversation access first
    if (!verifyConversationAccess($conversation_id, $_SESSION['user_id'])) {
        die("Access denied to this conversation");
    }

    // Safe update using prepared statement
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1
                            WHERE conversation_id = ?
                            AND sender_id != ?");
    $stmt->bind_param("ii", $conversation_id, $_SESSION['user_id']);
    $stmt->execute();
}

function getConversationMessages($conversation_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT m.*, u.name as sender_name, u.role as sender_role
                            FROM messages m
                            JOIN users u ON m.sender_id = u.id
                            WHERE m.conversation_id = ?
                            ORDER BY m.sent_at ASC");
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getParticipantInfo($conversation_id, $parent_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT u.*
                            FROM users u
                            JOIN conversation_participants cp ON u.id = cp.user_id
                            WHERE cp.conversation_id = ?
                            AND u.id != ?
                            AND u.role IN ('staff', 'admin')
                            LIMIT 1");
    $stmt->bind_param("ii", $conversation_id, $parent_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$current_conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : null;
$participant_info = null;
if ($current_conversation_id) {
    $participant_info = getParticipantInfo($current_conversation_id, $_SESSION['user_id']);
} else {
    header("Location: parent_messages.php"); // Redirect if no conversation is selected
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation | Daycare Management System</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
 :root {
    --primary-color: #4361ee;
    --secondary-color: #3f37c9;
    --accent-color: #4895ef;
    --danger-color: #f72585;
    --success-color: #4cc9f0;
    --light-color: #f8f9fa;
    --dark-color: #212529;
    --gray-color: #6c757d;
    --light-gray: #e9ecef;
    --transition-speed: 0.3s;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f5f7fa;
    margin: 0;
    padding: 0;
    color: var(--dark-color);
    line-height: 1.6;
}

.main-content {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

.profile-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--light-gray);
}

.profile-header h1 {
    color: var(--primary-color);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.8rem;
}

.profile-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn {
    padding: 8px 15px;
    border-radius: 5px;
    font-weight: 500;
    transition: all var(--transition-speed);
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: var(--secondary-color);
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background-color: #d1145a;
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: var(--gray-color);
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
    transform: translateY(-1px);
}

.btn-success {
    background-color: var(--success-color);
    color: white;
}

.btn-success:hover {
    background-color: #3da8c4;
    transform: translateY(-1px);
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 0.8rem;
}

.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    padding: 15px 20px;
    font-weight: 600;
}

.card-body {
    padding: 20px;
}

.conversation-container {
    background-color: #fff;
    border-radius: 8px;
    overflow-y: auto;
    max-height: 500px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid var(--light-gray);
}

.message-bubble {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: 18px;
    margin-bottom: 15px;
    position: relative;
    word-wrap: break-word;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.admin-message {
    background-color: var(--light-gray);
    margin-right: auto;
    border-bottom-left-radius: 5px;
}

.parent-message {
    background-color: var(--primary-color);
    color: white;
    margin-left: auto;
    border-bottom-right-radius: 5px;
}

.announcement-message {
    background-color: #fff3cd;
    margin: 0 auto;
    text-align: center;
    max-width: 90%;
    border-radius: 8px;
}

.media-preview {
    max-width: 100%;
    max-height: 200px;
    margin-top: 10px;
    border-radius: 5px;
    display: block;
}

.img-thumbnail {
    padding: 0.25rem;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
}

.timestamp {
    font-size: 0.75rem;
    color: var(--gray-color);
    margin-top: 8px;
    text-align: right;
}

.parent-message .timestamp {
    color: rgba(255, 255, 255, 0.8);
}

.reply-form {
    background-color: var(--light-color);
    padding: 20px;
    border-radius: 8px;
    border: 1px solid var(--light-gray);
}

.edit-message-form {
    display: none;
    margin-top: 10px;
    background-color: rgba(255, 255, 255, 0.9);
    padding: 10px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.message-actions {
    position: absolute;
    right: 10px;
    top: 10px;
    opacity: 0;
    transition: opacity var(--transition-speed);
    display: flex;
    gap: 5px;
}

.message-bubble:hover .message-actions {
    opacity: 1;
}

.action-btn {
    background: none;
    border: none;
    color: inherit;
    padding: 0;
    cursor: pointer;
    font-size: 0.8rem;
    transition: transform 0.2s;
}

.action-btn:hover {
    transform: scale(1.1);
}

.parent-message .action-btn {
    color: white;
}

.badge {
    font-weight: 500;
    padding: 5px 8px;
    border-radius: 4px;
}

.alert {
    padding: 10px 15px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.alert-info {
    background-color: #e7f5ff;
    color: #1864ab;
    border: 1px solid #a5d8ff;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    transition: border-color 0.15s;
    margin-bottom: 10px;
}

.form-control:focus {
    border-color: var(--primary-color);
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
}

.form-label {
    font-weight: 500;
    margin-bottom: 5px;
    display: block;
}

.text-muted {
    color: var(--gray-color) !important;
    font-size: 0.8rem;
}

/* Modal styles */
.modal-content {
    border: none;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.modal-header {
    border-bottom: 1px solid var(--light-gray);
    padding: 15px 20px;
}

.modal-title {
    font-weight: 600;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    border-top: 1px solid var(--light-gray);
    padding: 15px 20px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .message-bubble {
        max-width: 85%;
    }
    
    .conversation-container {
        max-height: 400px;
        padding: 15px;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 15px;
    }
    
    .message-bubble {
        max-width: 90%;
        padding: 10px 12px;
    }
    
    .card-body {
        padding: 15px;
    }
    
    .reply-form {
        padding: 15px;
    }
}
    </style>
</head>
<body>
    <div class="main-content">
        <div class="profile-header">
            <h1><i class="fas fa-envelope"></i> Conversation</h1>
            <div class="profile-actions">
                <a href="parent_messages.php" class="btn btn-danger">
                    <i class="fas fa-arrow-left"></i> Back to Messages
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_msg'])): ?>
            <div style="color: green; margin-bottom: 10px;"><?= htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div style="color: red; margin-bottom: 10px;"><?= htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?></div>
        <?php endif; ?>

         <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-comments"></i> Conversation with <?= htmlspecialchars($participant_info['name'] ?? 'Staff') ?>
                </div>
                <div>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($participant_info['email'] ?? '') ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="conversation-container" id="conversationContainer">
                    <?php $messages = getConversationMessages($current_conversation_id); ?>
                    <?php if (mysqli_num_rows($messages) > 0): ?>
                        <?php while ($msg = mysqli_fetch_assoc($messages)): ?>
                            <!-- CHANGED THIS SECTION TO FIX ALIGNMENT -->
                            <div class="d-flex flex-column <?= $msg['sender_id'] != $_SESSION['user_id'] ? 'align-items-start' : 'align-items-end' ?>">
                                <div class="message-bubble
                                    <?= $msg['sender_id'] != $_SESSION['user_id'] ? 'admin-message' : 'parent-message' ?>
                                    <?= $msg['message_type'] === 'announcement' ? 'announcement-message' : '' ?>">

                                    <?php if ($msg['sender_id'] == $_SESSION['user_id']): ?>
                                        <div class="message-actions">
                                            <button class="action-btn edit-message-btn" data-message-id="<?= $msg['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($msg['media_path']): ?>
                                                <button class="action-btn edit-media-btn" data-message-id="<?= $msg['id'] ?>">
                                                    <i class="fas fa-image"></i>
                                                </button>
                                                <button class="action-btn delete-media-btn" data-message-id="<?= $msg['id'] ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($msg['message_type'] === 'announcement'): ?>
                                        <div class="text-center">
                                            <i class="fas fa-bullhorn"></i> <strong>Announcement</strong>
                                        </div>
                                    <?php endif; ?>

                                    <div id="message-content-<?= $msg['id'] ?>">
                                        <p><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                                    </div>
                                    <div id="edit-message-form-<?= $msg['id'] ?>" class="edit-message-form">
                                        <form method="POST" class="mb-3">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="conversation_id" value="<?= $current_conversation_id ?>">
                                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                            <textarea class="form-control mb-2" name="new_content" rows="3"><?= htmlspecialchars($msg['content']) ?></textarea>
                                            <button type="submit" name="update_message" class="btn btn-sm btn-success">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                            <button type="button" class="btn btn-sm btn-secondary cancel-edit-btn">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                    </div>

                                    <div id="edit-media-form-<?= $msg['id'] ?>" class="edit-message-form">
                                        <form method="POST" enctype="multipart/form-data" class="mb-3">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="conversation_id" value="<?= $current_conversation_id ?>">
                                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                            <input type="file" class="form-control mb-2" name="new_media" accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                                            <button type="submit" name="update_media" class="btn btn-sm btn-success">
                                                <i class="fas fa-save"></i> Update Media
                                            </button>
                                            <button type="button" class="btn btn-sm btn-secondary cancel-edit-btn">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                    </div>

                                    <?php if (!empty($msg['media_path'])): ?>
                                        <div id="media-container-<?= $msg['id'] ?>">
                                            <?php
                                            $file_ext = strtolower(pathinfo($msg['media_path'], PATHINFO_EXTENSION));
                                            $image_exts = ['jpg', 'jpeg', 'png', 'gif'];
                                            $video_exts = ['mp4', 'webm'];
                                            $audio_exts = ['mp3', 'wav'];
                                            ?>

                                            <?php if (in_array($file_ext, $image_exts)): ?>
                                                <img src="<?= htmlspecialchars($msg['media_path']) ?>" class="media-preview img-thumbnail">
                                            <?php elseif (in_array($file_ext, $video_exts)): ?>
                                                <video controls class="media-preview">
                                                    <source src="<?= htmlspecialchars($msg['media_path']) ?>" type="video/<?= $file_ext ?>">
                                                </video>
                                            <?php elseif (in_array($file_ext, $audio_exts)): ?>
                                                <audio controls class="media-preview">
                                                    <source src="<?= htmlspecialchars($msg['media_path']) ?>" type="audio/<?= $file_ext ?>">
                                                </audio>
                                            <?php else: ?>
                                                <a href="<?= htmlspecialchars($msg['media_path']) ?>" class="btn btn-sm btn-secondary" download>
                                                    <i class="fas fa-download"></i> Download Attachment
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="timestamp">
                                            <?= date('M j, g:i a', strtotime($msg['sent_at'])) ?>
                                            <?php if (isset($msg['edited_at']) && $msg['edited_at']): ?>
                                                <span class="badge bg-info">Edited</span>
                                            <?php endif; ?>
                                            <?php if ($msg['sender_role'] === 'admin' && !$msg['is_read']): ?>
                                                <span class="badge bg-success">New</span>
                                            <?php endif; ?>
                                        </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No messages in this conversation.</div>
                    <?php endif; ?>
                </div>
                <div class="reply-form">
                    <form method="POST" enctype="multipart/form-data" class="mt-3">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="conversation_id" value="<?= (int)$current_conversation_id ?>">

                        <div class="mb-3">
                            <label for="message" class="form-label">Your Reply</label>
                            <textarea class="form-control" id="message" name="message" rows="3" required placeholder="Type your message here..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="mediaInput" class="form-label">Media Attachment (Optional)</label>
                            <input type="file" class="form-control" name="media" id="mediaInput" accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                            <small class="text-muted">Max size: 5MB. Supported: images, videos, audio, PDF, Word</small>
                            <div id="mediaPreview" class="mt-2"></div>
                        </div>

                        <button type="submit" name="send_reply" class="btn btn-primary">
                            <i class="fas fa-reply"></i> Send Reply
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteMediaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this media? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteMediaForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="conversation_id" id="deleteConversationId">
                        <input type="hidden" name="message_id" id="deleteMessageId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_media" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll conversation to bottom
        const conversationContainer = document.getElementById('conversationContainer');
        if (conversationContainer) {
            conversationContainer.scrollTop = conversationContainer.scrollHeight;
        }

        // Media preview functionality
        document.getElementById('mediaInput')?.addEventListener('change', function(e) {
            const preview = document.getElementById('mediaPreview');
            preview.innerHTML = '';

            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();

            if (file.type.startsWith('image/')) {
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'media-preview img-thumbnail';
                    preview.appendChild(img);
                }
                reader.readAsDataURL(file);
            } else if (file.type.startsWith('video/')) {
                reader.onload = function(e) {
                    const video = document.createElement('video');
                    video.src = e.target.result;
                    video.controls = true;
                    video.className = 'media-preview';
                    preview.appendChild(video);
                }
                reader.readAsDataURL(file);
            } else if (file.type.startsWith('audio/')) {
                reader.onload = function(e) {
                    const audio = document.createElement('audio');
                    audio.src = e.target.result;
                    audio.controls = true;
                    audio.className = 'media-preview';
                    preview.appendChild(audio);
                }
                reader.readAsDataURL(file);
            } else {
                const div = document.createElement('div');
                div.className = 'alert alert-info';
                div.textContent = 'File: ' + file.name;
                preview.appendChild(div);
            }
        });

        // Message editing functionality
        document.querySelectorAll('.edit-message-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const messageId = this.getAttribute('data-message-id');
                document.getElementById(`message-content-${messageId}`).style.display = 'none';
                document.getElementById(`edit-message-form-${messageId}`).style.display = 'block';

                // Hide any other open edit forms
                document.querySelectorAll('.edit-message-form').forEach(form => {
                    if (form.id !== `edit-message-form-${messageId}`) {
                        form.style.display = 'none';
                        const otherMsgId = form.id.split('-')[3];
                        document.getElementById(`message-content-${otherMsgId}`).style.display = 'block';
                    }
                });
            });
        });

        // Media editing functionality
        document.querySelectorAll('.edit-media-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const messageId = this.getAttribute('data-message-id');
                document.getElementById(`media-container-${messageId}`).style.display = 'none';
                document.getElementById(`edit-media-form-${messageId}`).style.display = 'block';

                // Hide any other open edit forms
                document.querySelectorAll('.edit-message-form').forEach(form => {
                    if (form.id !== `edit-media-form-${messageId}`) {
                        form.style.display = 'none';
                        const otherMsgId = form.id.split('-')[3];
                        if (form.id.startsWith('edit-message-form')) {
                            document.getElementById(`message-content-${otherMsgId}`).style.display = 'block';
                        } else {
                            document.getElementById(`media-container-${otherMsgId}`).style.display = 'block';
                        }
                    }
                });
            });
        });

        // Delete media functionality
        document.querySelectorAll('.delete-media-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const messageId = this.getAttribute('data-message-id');
                document.getElementById('deleteMessageId').value = messageId;
                document.getElementById('deleteConversationId').value = <?= $current_conversation_id ?>;

                const modal = new bootstrap.Modal(document.getElementById('deleteMediaModal'));
                modal.show();
            });
        });

        // Cancel edit buttons
        document.querySelectorAll('.cancel-edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('.edit-message-form');
                const messageId = form.id.split('-')[3];

                if (form.id.startsWith('edit-message-form')) {
                    document.getElementById(`message-content-${messageId}`).style.display = 'block';
                } else {
                    document.getElementById(`media-container-${messageId}`).style.display = 'block';
                }

                form.style.display = 'none';
            });
        });
    </script>
</body>
</html>