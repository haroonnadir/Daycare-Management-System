<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

$user_id = $_SESSION['user_id'];
$conversation_id = intval($_POST['conversation_id']);

// Mark all unread messages in this conversation as read
$update_sql = "UPDATE messages m
              JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
              SET m.is_read = TRUE
              WHERE m.conversation_id = ?
              AND cp.user_id = ?
              AND m.sender_id != ?
              AND m.is_read = FALSE";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param("iii", $conversation_id, $user_id, $user_id);
$stmt->execute();
?>