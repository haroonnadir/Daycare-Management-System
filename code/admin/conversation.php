<?php
session_start();
include '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if conversation ID is provided
if (!isset($_GET['cid'])) {
    header("Location: messages.php");
    exit();
}

$conversation_id = intval($_GET['cid']);
$user_id = $_SESSION['user_id'];

// Verify user is part of this conversation
$stmt = $conn->prepare("SELECT 1 FROM conversation_participants 
                       WHERE conversation_id = ? AND user_id = ?");
$stmt->bind_param("ii", $conversation_id, $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    header("Location: messages.php");
    exit();
}

// File upload directory
$uploadDir = '../uploads/messages/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $message = trim($_POST['message']);
    $media_path = null;
    
    // Validate message
    if (empty($message)) {
        $_SESSION['error_msg'] = "Message cannot be empty";
        header("Location: conversation.php?cid=$conversation_id");
        exit();
    }
    
    // Handle file upload
    if (!empty($_FILES['media']['name'])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'audio/mpeg', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if ($_FILES['media']['size'] > $maxSize) {
            $_SESSION['error_msg'] = "File size exceeds 5MB limit";
            header("Location: conversation.php?cid=$conversation_id");
            exit();
        }
        
        if (!in_array($_FILES['media']['type'], $allowedTypes)) {
            $_SESSION['error_msg'] = "Invalid file type. Only images, videos, audio, and PDFs are allowed";
            header("Location: conversation.php?cid=$conversation_id");
            exit();
        }
        
        $fileName = time() . '_' . basename($_FILES['media']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['media']['tmp_name'], $targetPath)) {
            $media_path = $targetPath;
        } else {
            $_SESSION['error_msg'] = "Failed to upload media file";
            header("Location: conversation.php?cid=$conversation_id");
            exit();
        }
    }
    
    // Insert message into database
    $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, content, is_read, message_type, media_path) 
                           VALUES (?, ?, ?, 0, 'regular', ?)");
    $stmt->bind_param("iiss", $conversation_id, $user_id, $message, $media_path);
    
    if ($stmt->execute()) {
        // Update conversation last message time
        $conn->query("UPDATE conversations SET last_message_at = NOW() WHERE conversation_id = $conversation_id");
        
        // Mark as unread for other participant(s)
        $conn->query("UPDATE messages m
                     JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
                     SET m.is_read = 0
                     WHERE m.conversation_id = $conversation_id 
                     AND cp.user_id != $user_id");
        
        $_SESSION['success_msg'] = "Message sent successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to send message";
    }
    
    header("Location: conversation.php?cid=$conversation_id");
    exit();
}

// Get conversation details
$conversation = $conn->query("SELECT * FROM conversations WHERE conversation_id = $conversation_id")->fetch_assoc();

// Get other participant's details
$other_participant = $conn->query("SELECT u.id, u.name, u.role 
                                  FROM conversation_participants cp
                                  JOIN users u ON cp.user_id = u.id
                                  WHERE cp.conversation_id = $conversation_id 
                                  AND cp.user_id != $user_id")->fetch_assoc();

// Get all messages in this conversation
$messages = $conn->query("SELECT m.*, u.name as sender_name, u.role as sender_role
                          FROM messages m
                          JOIN users u ON m.sender_id = u.id
                          WHERE m.conversation_id = $conversation_id
                          ORDER BY m.sent_at ASC");

// Mark all messages as read for current user
$conn->query("UPDATE messages SET is_read = 1 
              WHERE conversation_id = $conversation_id 
              AND sender_id != $user_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .message-card {
            border-radius: 10px;
            margin-bottom: 15px;
            padding: 15px;
            position: relative;
            max-width: 80%;
        }
        .sent {
            background-color: #e3f2fd;
            margin-left: auto;
            border-bottom-right-radius: 0;
        }
        .received {
            background-color: #f8f9fa;
            margin-right: auto;
            border-bottom-left-radius: 0;
        }
        .message-time {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .message-sender {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .admin-badge {
            background-color: #0d6efd;
            color: white;
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
            margin-left: 5px;
        }
        .parent-badge {
            background-color: #6c757d;
            color: white;
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
            margin-left: 5px;
        }
        .announcement-badge {
            background-color: #ffc107;
            color: black;
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
            margin-left: 5px;
        }
        .media-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 5px;
        }
        .conversation-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        #message-container {
            height: 60vh;
            overflow-y: auto;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Conversation</h1>
                    <a href="messages.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Messages
                    </a>
                </div>

                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?></div>
                <?php endif; ?>

                <div class="conversation-header">
                    <h4>
                        Conversation with <?= htmlspecialchars($other_participant['name']) ?>
                        <span class="<?= $other_participant['role'] === 'admin' ? 'admin-badge' : 'parent-badge' ?>">
                            <?= ucfirst($other_participant['role']) ?>
                        </span>
                    </h4>
                    <p class="text-muted">Started <?= date('M j, Y', strtotime($conversation['created_at'])) ?></p>
                </div>

                <div id="message-container">
                    <?php while ($msg = $messages->fetch_assoc()): ?>
                        <div class="message-card <?= $msg['sender_id'] === $user_id ? 'sent' : 'received' ?>">
                            <div class="message-sender">
                                <?= htmlspecialchars($msg['sender_name']) ?>
                                <?php if ($msg['sender_role'] === 'admin'): ?>
                                    <span class="admin-badge">Admin</span>
                                <?php else: ?>
                                    <span class="parent-badge">Parent</span>
                                <?php endif; ?>
                                <?php if ($msg['message_type'] === 'announcement'): ?>
                                    <span class="announcement-badge">Announcement</span>
                                <?php endif; ?>
                            </div>
                            <p><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                            
                            <?php if (!empty($msg['media_path'])): 
                                $fileExt = strtolower(pathinfo($msg['media_path'], PATHINFO_EXTENSION));
                                if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <img src="<?= $msg['media_path'] ?>" class="media-preview" alt="Attached image">
                                <?php elseif (in_array($fileExt, ['mp4', 'webm', 'ogg'])): ?>
                                    <video controls class="media-preview">
                                        <source src="<?= $msg['media_path'] ?>" type="video/<?= $fileExt ?>">
                                    </video>
                                <?php elseif (in_array($fileExt, ['mp3', 'wav'])): ?>
                                    <audio controls class="media-preview">
                                        <source src="<?= $msg['media_path'] ?>" type="audio/<?= $fileExt ?>">
                                    </audio>
                                <?php else: ?>
                                    <a href="<?= $msg['media_path'] ?>" class="btn btn-sm btn-secondary" download>
                                        <i class="fas fa-download"></i> Download Attachment
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="message-time">
                                <?= date('M j, Y g:i a', strtotime($msg['sent_at'])) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="message" class="form-label">Your Message</label>
                        <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="media" class="form-label">Attachment (Optional)</label>
                        <input type="file" class="form-control" id="media" name="media" accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                        <small class="text-muted">Max size: 5MB. Supported: images, videos, audio, PDF, Word</small>
                    </div>
                    <button type="submit" name="send_reply" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Reply
                    </button>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to bottom of message container
        const messageContainer = document.getElementById('message-container');
        messageContainer.scrollTop = messageContainer.scrollHeight;
        
        // Preview image before upload
        document.querySelector('input[name="media"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const previewContainer = document.createElement('div');
                previewContainer.className = 'mt-2';
                
                const reader = new FileReader();
                reader.onload = function(event) {
                    const fileExt = file.name.split('.').pop().toLowerCase();
                    
                    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
                        const preview = document.createElement('img');
                        preview.src = event.target.result;
                        preview.className = 'media-preview';
                        previewContainer.appendChild(preview);
                    } else if (['mp4', 'webm', 'ogg'].includes(fileExt)) {
                        const preview = document.createElement('video');
                        preview.src = event.target.result;
                        preview.controls = true;
                        preview.className = 'media-preview';
                        previewContainer.appendChild(preview);
                    } else if (['mp3', 'wav'].includes(fileExt)) {
                        const preview = document.createElement('audio');
                        preview.src = event.target.result;
                        preview.controls = true;
                        preview.className = 'media-preview';
                        previewContainer.appendChild(preview);
                    } else {
                        const preview = document.createElement('div');
                        preview.className = 'alert alert-info';
                        preview.textContent = 'File ready for upload: ' + file.name;
                        previewContainer.appendChild(preview);
                    }
                    
                    const existingPreview = document.querySelector('.preview-container');
                    if (existingPreview) {
                        existingPreview.replaceWith(previewContainer);
                    } else {
                        e.target.parentNode.appendChild(previewContainer);
                    }
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>