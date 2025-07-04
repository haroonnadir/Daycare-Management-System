<?php
require 'auth.php';
require 'db.php';

if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationId = (int)$_POST['notification_id'];
    
    // Update notification as published
    $stmt = $db->prepare("UPDATE notifications 
        SET is_published = TRUE, published_at = NOW() 
        WHERE id = ?");
    $stmt->execute([$notificationId]);
    
    // Send to recipients
    sendNotificationToRecipients($db, $notificationId);
    
    header("Location: admin_notifications.php?success=Notification+published+successfully");
    exit;
}

header("Location: admin_notifications.php");
exit;

/**
 * Sends notification to all appropriate recipients
 */
function sendNotificationToRecipients($db, $notificationId) {
    // Get all active users (parents and staff)
    $users = $db->query("
        SELECT id FROM users 
        WHERE active = 1 AND (role = 'parent' OR role = 'staff')
    ")->fetchAll();
    
    // Insert into recipients table
    $stmt = $db->prepare("
        INSERT INTO notification_recipients (notification_id, user_id)
        VALUES (?, ?)
    ");
    
    foreach ($users as $user) {
        $stmt->execute([$notificationId, $user['id']]);
        
        // In a real implementation, you would also:
        // 1. Check user's notification preferences
        // 2. Send email/SMS/push notifications accordingly
        // 3. Log the delivery attempts
    }
}
?>