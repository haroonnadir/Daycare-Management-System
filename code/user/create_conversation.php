<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$parent_id = $_SESSION['user_id'];
$recipient_id = intval($_POST['recipient']);
$subject = trim($_POST['subject']);
$message_content = trim($_POST['message']);

// Validate inputs
if (empty($subject) || empty($message_content)) {
    echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Create conversation
    $conversation_sql = "INSERT INTO conversations (subject, last_message_at) VALUES (?, NOW())";
    $stmt = $conn->prepare($conversation_sql);
    $stmt->bind_param("s", $subject);
    $stmt->execute();
    $conversation_id = $conn->insert_id;
    
    // Add participants
    $participant_sql = "INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)";
    $stmt = $conn->prepare($participant_sql);
    
    // Add parent
    $stmt->bind_param("ii", $conversation_id, $parent_id);
    $stmt->execute();
    
    // Add recipient
    $stmt->bind_param("ii", $conversation_id, $recipient_id);
    $stmt->execute();
    
    // Create message
    $message_sql = "INSERT INTO messages (conversation_id, sender_id, content, sent_at) 
                   VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($message_sql);
    $stmt->bind_param("iis", $conversation_id, $parent_id, $message_content);
    $stmt->execute();
    $message_id = $conn->insert_id;
    
    // Handle file uploads
    if (!empty($_FILES['attachments']['name'][0])) {
        $upload_dir = '../uploads/message_attachments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $attachment_sql = "INSERT INTO message_attachments 
                          (message_id, original_name, file_path, file_type, file_size)
                          VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($attachment_sql);
        
        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
            $original_name = basename($_FILES['attachments']['name'][$key]);
            $file_type = $_FILES['attachments']['type'][$key];
            $file_size = $_FILES['attachments']['size'][$key];
            
            // Generate unique filename
            $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($tmp_name, $upload_path)) {
                $stmt->bind_param("isssi", $message_id, $original_name, $upload_path, $file_type, $file_size);
                $stmt->execute();
            }
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'conversation_id' => $conversation_id]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>