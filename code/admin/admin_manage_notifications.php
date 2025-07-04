<?php
session_start();
include '../db_connect.php';

// Authentication check - allow both admin and staff
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if notifications table exists
$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($table_check->num_rows == 0) {
    die("Notifications system is not properly configured. Please contact administrator.");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = "Invalid CSRF token";
        header("Location: admin_manage_notifications.php");
        exit();
    }

    if (isset($_POST['send_notification'])) {
        $notification_type = $_POST['notification_type'];
        $title = trim($_POST['title']);
        $message = trim($_POST['message']);
        $is_urgent = ($notification_type === 'emergency') ? 1 : 0;
        $send_to_all = isset($_POST['send_to_all']) ? 1 : 0;
        $event_date = ($notification_type === 'event' || $notification_type === 'holiday') ? $_POST['event_date'] : null;
        
        // Validate inputs
        if (empty($title) || empty($message)) {
            $_SESSION['error_msg'] = "Title and message are required";
            header("Location: admin_manage_notifications.php");
            exit();
        }

        if (($notification_type === 'event' || $notification_type === 'holiday') && empty($event_date)) {
            $_SESSION['error_msg'] = "Date is required for events and holidays";
            header("Location: admin_manage_notifications.php");
            exit();
        }

        // Sanitize inputs
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        
        // Determine recipients
        $recipients = [];
        if ($send_to_all) {
            $result = $conn->query("SELECT id FROM users WHERE role IN ('parent', 'staff')");
            while ($row = $result->fetch_assoc()) {
                $recipients[] = $row['id'];
            }
        } else {
            // For staff, they can only send to parents
            if ($_SESSION['role'] === 'staff') {
                $result = $conn->query("SELECT id FROM users WHERE role = 'parent'");
                while ($row = $result->fetch_assoc()) {
                    $recipients[] = $row['id'];
                }
            } else {
                // Admin can select specific recipients
                if (!empty($_POST['recipients'])) {
                    foreach ($_POST['recipients'] as $recipient_id) {
                        $recipients[] = (int)$recipient_id;
                    }
                }
            }
        }

        if (empty($recipients)) {
            $_SESSION['error_msg'] = "No recipients selected";
            header("Location: staff_manage_notifications.php");
            exit();
        }

        // Insert notifications
        $success_count = 0;
        $stmt = $conn->prepare("INSERT INTO notifications 
                              (user_id, title, message, notification_type, is_urgent, event_date) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($recipients as $user_id) {
            $stmt->bind_param("isssis", $user_id, $title, $message, $notification_type, $is_urgent, $event_date);
            if ($stmt->execute()) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['success_msg'] = "Notification sent to $success_count recipients!";
            
            // If this is an emergency, also send emails
            if ($notification_type === 'emergency') {
                sendEmergencyEmails($recipients, $title, $message);
            }
        } else {
            $_SESSION['error_msg'] = "Failed to send notification to any recipients";
        }
        
        header("Location: staff_manage_notifications.php");
        exit();
    }
}

// Function to send emergency emails (simplified example)
function sendEmergencyEmails($recipient_ids, $subject, $message) {
    global $conn;
    
    // Get recipient emails
    $ids = implode(',', array_map('intval', $recipient_ids));
    $result = $conn->query("SELECT email FROM users WHERE id IN ($ids)");
    
    while ($row = $result->fetch_assoc()) {
        // In a real application, you would implement actual email sending here
        // This is just a placeholder
        $to = $row['email'];
        // mail($to, $subject, $message);
        error_log("Would send email to: $to with subject: $subject");
    }
}

// Get all users for dropdown (only for admin)
function getRecipientOptions() {
    global $conn;
    return $conn->query("SELECT id, name, role FROM users WHERE role IN ('parent', 'staff') ORDER BY role, name");
}

// Get recent notifications
function getRecentNotifications() {
    global $conn;
    return $conn->query("SELECT n.*, u.name as sender_name 
                        FROM notifications n
                        JOIN users u ON n.user_id = u.id
                        WHERE n.notification_type IN ('event', 'holiday', 'emergency')
                        ORDER BY n.created_at DESC LIMIT 10");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Style for the "go back dashboard" link */
    .h2 a {
        display: inline-block;
        font-size: 16px;
        margin-left: 15px;
        padding: 5px 10px;
        background-color: #f8f9fa;
        color: #0d6efd;
        text-decoration: none;
        border-radius: 4px;
        border: 1px solid #dee2e6;
        transition: all 0.3s ease;
    }

    .h2 a:hover {
        background-color: #0d6efd;
        color: white;
        text-decoration: none;
        border-color: #0d6efd;
    }

    /* Adjust the header layout */
    .d-flex.justify-content-between.flex-wrap.flex-md-nowrap.align-items-center.pt-3.pb-2.mb-3.border-bottom {
        display: flex;
        align-items: center;
        gap: 15px;
    }
            .notification-card {
            border-left: 4px solid #0d6efd;
            margin-bottom: 15px;
        }
        .notification-card.event {
            border-left-color: #198754;
        }
        .notification-card.holiday {
            border-left-color: #6f42c1;
        }
        .notification-card.emergency {
            border-left-color: #dc3545;
        }
        .recipient-checkboxes {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-bullhorn"></i> Send Notifications
                        <h1 class="h2"> <a href="admin_dashboard.php">GO BACK DASHBOARD </a></h1>
                    </h1>
                </div>

                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-paper-plane"></i> Create New Notification
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Notification Type</label>
                                        <select class="form-select" name="notification_type" id="notificationType" required>
                                            <option value="event">Event</option>
                                            <option value="holiday">Holiday</option>
                                            <option value="emergency">Emergency/Closure</option>
                                            <option value="general">General Announcement</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3" id="eventDateField" style="display: none;">
                                        <label class="form-label">Date</label>
                                        <input type="date" class="form-control" name="event_date" id="eventDate">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Title</label>
                                        <input type="text" class="form-control" name="title" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Message</label>
                                        <textarea class="form-control" name="message" rows="5" required></textarea>
                                    </div>
                                    
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="sendToAll" name="send_to_all">
                                                <label class="form-check-label" for="sendToAll">
                                                    Send to all parents and staff
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3" id="recipientSelection">
                                            <label class="form-label">Select Recipients</label>
                                            <div class="recipient-checkboxes">
                                                <?php $users = getRecipientOptions(); ?>
                                                <?php while ($user = $users->fetch_assoc()): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input recipient-checkbox" type="checkbox" 
                                                               name="recipients[]" value="<?= (int)$user['id'] ?>" 
                                                               id="recipient_<?= (int)$user['id'] ?>">
                                                        <label class="form-check-label" for="recipient_<?= (int)$user['id'] ?>">
                                                            <?= htmlspecialchars($user['name']) ?> (<?= ucfirst($user['role']) ?>)
                                                        </label>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <input type="hidden" name="send_to_all" value="1">
                                        <div class="alert alert-info">
                                            As staff, your notification will be sent to all parents.
                                        </div>
                                    <?php endif; ?>
                                    
                                    <button type="submit" name="send_notification" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Send Notification
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-history"></i> Recent Notifications
                            </div>
                            <div class="card-body">
                                <?php $notifications = getRecentNotifications(); ?>
                                <?php if ($notifications->num_rows > 0): ?>
                                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                                        <div class="card notification-card <?= htmlspecialchars($notification['notification_type']) ?> mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <h5 class="card-title"><?= htmlspecialchars($notification['title']) ?></h5>
                                                        <h6 class="card-subtitle mb-2 text-muted">
                                                            <?= ucfirst($notification['notification_type']) ?> 
                                                            <?php if ($notification['event_date']): ?>
                                                                - <?= date('M j, Y', strtotime($notification['event_date'])) ?>
                                                            <?php endif; ?>
                                                        </h6>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= date('M j, g:i a', strtotime($notification['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <p class="card-text"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                                                <small class="text-muted">
                                                    Sent by: <?= htmlspecialchars($notification['sender_name']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">No recent notifications found.</div>
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
        // Show/hide date field based on notification type
        document.getElementById('notificationType').addEventListener('change', function() {
            const type = this.value;
            const dateField = document.getElementById('eventDateField');
            
            if (type === 'event' || type === 'holiday') {
                dateField.style.display = 'block';
                document.getElementById('eventDate').required = true;
            } else {
                dateField.style.display = 'none';
                document.getElementById('eventDate').required = false;
            }
        });
        
        // Toggle recipient checkboxes when "send to all" is checked
        <?php if ($_SESSION['role'] === 'admin'): ?>
            document.getElementById('sendToAll').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.recipient-checkbox');
                const recipientDiv = document.getElementById('recipientSelection');
                
                if (this.checked) {
                    recipientDiv.style.opacity = '0.5';
                    checkboxes.forEach(cb => {
                        cb.checked = false;
                        cb.disabled = true;
                    });
                } else {
                    recipientDiv.style.opacity = '1';
                    checkboxes.forEach(cb => {
                        cb.disabled = false;
                    });
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>