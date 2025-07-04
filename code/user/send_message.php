<?php
session_start();
require_once '../db_connect.php';
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$conversation_id = intval($_POST['conversation_id']);
$message = trim($_POST['message']);

// Verify user is part of this conversation
$verify_sql = "SELECT 1 FROM conversation_participants 
              WHERE conversation_id = ? AND user_id = ?";
$stmt = $conn->prepare($verify_sql);
$stmt->bind_param("ii", $conversation_id, $user_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'message' => 'Not part of this conversation']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Add message
    $message_sql = "INSERT INTO messages (conversation_id, sender_id, content) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($message_sql);
    $stmt->bind_param("iis", $conversation_id, $user_id, $message);
    $stmt->execute();
    $message_id = $conn->insert_id;

    // Handle file uploads
    if (!empty($_FILES['attachments']['name'][0])) {
        $upload_dir = UPLOAD_PATH . 'attachments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $attachment_sql = "INSERT INTO message_attachments 
                          (message_id, file_path, original_name, file_type, file_size) 
                          VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($attachment_sql);

        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $original_name = basename($_FILES['attachments']['name'][$key]);
                $file_type = $_FILES['attachments']['type'][$key];
                $file_size = $_FILES['attachments']['size'][$key];
                $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
                $new_filename = uniqid('attach_', true) . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($tmp_name, $destination)) {
                    $stmt->bind_param("isssi", $message_id, $destination, $original_name, $file_type, $file_size);
                    $stmt->execute();
                }
            }
        }
    }

    // Update conversation last message time
    $update_sql = "UPDATE conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();

    // Mark as unread for all other participants
    $unread_sql = "UPDATE conversation_participants SET has_unread = TRUE 
                  WHERE conversation_id = ? AND user_id != ?";
    $stmt = $conn->prepare($unread_sql);
    $stmt->bind_param("ii", $conversation_id, $user_id);
    $stmt->execute();

    // Get other participants to notify
    $participants_sql = "SELECT user_id FROM conversation_participants 
                        WHERE conversation_id = ? AND user_id != ?";
    $stmt = $conn->prepare($participants_sql);
    $stmt->bind_param("ii", $conversation_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Get conversation subject for notification
    $subject_sql = "SELECT subject FROM conversations WHERE id = ?";
    $stmt = $conn->prepare($subject_sql);
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $subject = $stmt->get_result()->fetch_row()[0];

    // Get sender name for notification
    $sender_sql = "SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?";
    $stmt = $conn->prepare($sender_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $sender_name = $stmt->get_result()->fetch_row()[0];

    // Send notifications
    while ($participant = $result->fetch_assoc()) {
        $title = "New Message: $subject";
        $message = "You have received a new message from $sender_name";
        sendNotification($participant['user_id'], $title, $message);
    }

    mysqli_commit($conn);
    
    header("Content-Type: application/json");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>