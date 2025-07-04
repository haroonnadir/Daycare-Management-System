<?php
session_start();
include '../db_connect.php';

// Verify admin/staff access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: login.php");
    exit();
}


$adminId = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = cleanInput($_POST['title']);
    $message = cleanInput($_POST['message']);
    $notificationType = $_POST['notification_type'];
    $priority = $_POST['priority'];
    $startDate = $_POST['start_date'] ?: null;
    $endDate = $_POST['end_date'] ?: null;
    $publish = isset($_POST['publish']);
    
    // Insert notification
    $stmt = $db->prepare("INSERT INTO notifications 
        (title, message, notification_type, start_date, end_date, priority, created_by, is_published, published_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $publishedAt = $publish ? date('Y-m-d H:i:s') : null;
    $stmt->execute([
        $title, 
        $message, 
        $notificationType, 
        $startDate, 
        $endDate, 
        $priority, 
        $adminId,
        $publish ? 1 : 0,
        $publishedAt
    ]);
    
    $notificationId = $db->lastInsertId();
    
    // Send to recipients if published
    if ($publish) {
        sendNotificationToRecipients($db, $notificationId);
    }
    
    $success = $publish ? "Notification published successfully!" : "Draft saved successfully!";
}

// Get all notifications
$notifications = $db->query("
    SELECT n.*, u.name as author_name,
           (SELECT COUNT(*) FROM notification_recipients WHERE notification_id = n.id) as recipient_count
    FROM notifications n
    JOIN users u ON n.created_by = u.id
    ORDER BY n.created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Management</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        .notification-card { border-left: 4px solid; margin-bottom: 15px; }
        .notification-event { border-left-color: #4CAF50; }
        .notification-holiday { border-left-color: #2196F3; }
        .notification-emergency { border-left-color: #F44336; }
        .notification-reminder { border-left-color: #FFC107; }
        .notification-general { border-left-color: #9C27B0; }
        .priority-high { border-left-width: 8px; }
        .priority-critical { border-left-width: 12px; animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 0.8em; }
        .badge-draft { background: #9E9E9E; color: white; }
        .badge-published { background: #4CAF50; color: white; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        textarea { min-height: 150px; }
        .date-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #2196F3; color: white; }
        .btn-secondary { background: #4CAF50; color: white; }
        .btn-danger { background: #F44336; color: white; }
        .type-buttons { display: flex; gap: 10px; margin-bottom: 15px; }
        .type-btn { flex: 1; text-align: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; }
        .type-btn.active { border-color: #2196F3; background: #E3F2FD; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Notification Management</h1>
            <button onclick="document.getElementById('createModal').style.display='block'" class="btn btn-primary">
                Create New Notification
            </button>
        </div>

        <?php if (isset($success)): ?>
            <div class="card" style="background: #E8F5E9;">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Recent Notifications</h2>
            
            <?php if (empty($notifications)): ?>
                <p>No notifications have been created yet.</p>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="card notification-card notification-<?= $notification['notification_type'] ?> 
                         priority-<?= $notification['priority'] === 'critical' ? 'critical' : ($notification['priority'] === 'high' ? 'high' : '') ?>">
                        <div style="display: flex; justify-content: space-between;">
                            <h3><?= htmlspecialchars($notification['title']) ?></h3>
                            <span class="badge badge-<?= $notification['is_published'] ? 'published' : 'draft' ?>">
                                <?= $notification['is_published'] ? 'Published' : 'Draft' ?>
                            </span>
                        </div>
                        
                        <p><strong>Type:</strong> <?= ucfirst($notification['notification_type']) ?> 
                        | <strong>Priority:</strong> <?= ucfirst($notification['priority']) ?></p>
                        
                        <?php if ($notification['start_date']): ?>
                            <p><strong>Date:</strong> 
                                <?= date('M j, Y g:i A', strtotime($notification['start_date'])) ?>
                                <?php if ($notification['end_date']): ?>
                                    to <?= date('M j, Y g:i A', strtotime($notification['end_date'])) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        
                        <p><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                        
                        <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                            <small>Created by <?= htmlspecialchars($notification['author_name']) ?> on 
                                <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?></small>
                            <small>Recipients: <?= $notification['recipient_count'] ?></small>
                        </div>
                        
                        <div style="margin-top: 10px;">
                            <?php if (!$notification['is_published']): ?>
                                <form method="POST" action="publish_notification.php" style="display: inline;">
                                    <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8em;">
                                        Publish Now
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="edit_notification.php?id=<?= $notification['id'] ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8em;">
                                <?= $notification['is_published'] ? 'View' : 'Edit' ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Notification Modal -->
    <div id="createModal" style="display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: white; margin: 5% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Create New Notification</h2>
                <span onclick="document.getElementById('createModal').style.display='none'" style="cursor: pointer; font-size: 24px;">&times;</span>
            </div>
            
            <form method="POST">
                <div class="type-buttons">
                    <div class="type-btn active" data-type="general" onclick="setNotificationType('general')">
                        General
                    </div>
                    <div class="type-btn" data-type="event" onclick="setNotificationType('event')">
                        Event
                    </div>
                    <div class="type-btn" data-type="holiday" onclick="setNotificationType('holiday')">
                        Holiday
                    </div>
                    <div class="type-btn" data-type="emergency" onclick="setNotificationType('emergency')">
                        Emergency
                    </div>
                    <div class="type-btn" data-type="reminder" onclick="setNotificationType('reminder')">
                        Reminder
                    </div>
                </div>
                <input type="hidden" name="notification_type" id="notification_type" value="general">
                
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" name="title" id="title" required>
                </div>
                
                <div class="form-group">
                    <label for="message">Message *</label>
                    <textarea name="message" id="message" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select name="priority" id="priority">
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                
                <div id="dateFields" class="hidden">
                    <div class="date-grid">
                        <div class="form-group">
                            <label for="start_date">Start Date/Time</label>
                            <input type="datetime-local" name="start_date" id="start_date">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date/Time (optional)</label>
                            <input type="datetime-local" name="end_date" id="end_date">
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; display: flex; justify-content: space-between;">
                    <div>
                        <input type="checkbox" name="publish" id="publish" checked>
                        <label for="publish" style="display: inline;">Publish immediately</label>
                    </div>
                    <div>
                        <button type="button" onclick="document.getElementById('createModal').style.display='none'" 
                                style="padding: 8px 16px; background: #f5f5f5; border: none; border-radius: 4px; margin-right: 10px;">
                            Cancel
                        </button>
                        <button type="submit" style="padding: 8px 16px; background: #4CAF50; color: white; border: none; border-radius: 4px;">
                            Save Notification
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Set notification type and show/hide date fields
        function setNotificationType(type) {
            document.getElementById('notification_type').value = type;
            
            // Update active button
            document.querySelectorAll('.type-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.type === type) {
                    btn.classList.add('active');
                }
            });
            
            // Show date fields for event/holiday types
            if (type === 'event' || type === 'holiday') {
                document.getElementById('dateFields').classList.remove('hidden');
            } else {
                document.getElementById('dateFields').classList.add('hidden');
            }
            
            // Set appropriate priority for emergency
            if (type === 'emergency') {
                document.getElementById('priority').value = 'critical';
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('createModal')) {
                document.getElementById('createModal').style.display = 'none';
            }
        }
    </script>
</body>
</html>