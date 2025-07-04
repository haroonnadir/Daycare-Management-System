<?php
session_start();
require '../db_connect.php';

// Verify admin/staff access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['date'])) {
    $_SESSION['error'] = "Invalid request";
    header("Location: admin_manage_activities.php");
    exit();
}

$activity_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$redirect_date = $_GET['date'];

try {
    $conn->begin_transaction();
    
    // First delete associated media files and records
    $media_query = "SELECT file_path FROM activity_media WHERE activity_id = ?";
    $stmt = $conn->prepare($media_query);
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    $media_files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($media_files as $file) {
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
    }
    
    // Delete media records
    $delete_media = "DELETE FROM activity_media WHERE activity_id = ?";
    $stmt = $conn->prepare($delete_media);
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    
    // Delete the activity
    $delete_activity = "DELETE FROM child_activities WHERE id = ?";
    $stmt = $conn->prepare($delete_activity);
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    
    $conn->commit();
    $_SESSION['success'] = "Activity deleted successfully";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error deleting activity: " . $e->getMessage();
}

header("Location: admin_manage_activities.php?date=" . $redirect_date);
exit();
?>