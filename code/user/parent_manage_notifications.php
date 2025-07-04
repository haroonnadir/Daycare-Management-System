<?php
session_start();
include '../db_connect.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
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

// Handle marking notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = "Invalid CSRF token";
        header("Location: parent_manage_notifications.php");
        exit();
    }

    if (isset($_POST['mark_as_read'])) {
        $notification_id = (int)$_POST['notification_id'];
        
        // Verify the notification belongs to the parent
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 
                               WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Notification marked as read";
        } else {
            $_SESSION['error_msg'] = "Failed to update notification";
        }
        
        header("Location: parent_manage_notifications.php");
        exit();
    }
    
    if (isset($_POST['mark_all_as_read'])) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 
                               WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "All notifications marked as read";
        } else {
            $_SESSION['error_msg'] = "Failed to update notifications";
        }
        
        header("Location: parent_manage_notifications.php");
        exit();
    }
}

// Get all notifications for the parent
function getParentNotifications($parent_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM notifications 
                               WHERE user_id = ? 
                               ORDER BY created_at DESC");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        return $stmt->get_result();
    } catch (mysqli_sql_exception $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

// Get unread notification count
function getUnreadNotificationCount($parent_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications 
                               WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['count'];
    } catch (mysqli_sql_exception $e) {
        error_log("Database error: " . $e->getMessage());
        return 0;
    }
}

$notifications = getParentNotifications($_SESSION['user_id']);
$unread_count = getUnreadNotificationCount($_SESSION['user_id']);

// If there was a database error
if ($notifications === false) {
    die("Unable to retrieve notifications at this time. Please try again later.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification-card {
            border-left: 4px solid #0d6efd;
            margin-bottom: 15px;
            transition: all 0.2s;
        }
        .notification-card.unread {
            border-left-color: #dc3545;
            background-color: #f8f9fa;
        }
        .notification-card:hover {
            transform: translateX(5px);
        }
        .badge-notification {
            position: absolute;
            top: -5px;
            right: -5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Notifications</h1>
                    <h1 class="h2"> <a href="parent_dashboard.php">go back dashbord </a></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <button type="submit" name="mark_all_as_read" class="btn btn-sm btn-outline-secondary" <?= ($unread_count == 0) ? 'disabled' : '' ?>>
                                    <i class="fas fa-check-double"></i> Mark All as Read
                                </button>
                            </form>
                        </div>
                        <span class="position-relative">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" disabled>
                                <i class="fas fa-bell"></i>
                            </button>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger rounded-pill badge-notification"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <?php if ($notifications->num_rows > 0): ?>
                            <?php while ($notification = $notifications->fetch_assoc()): ?>
                                <div class="card notification-card <?= $notification['is_read'] ? '' : 'unread' ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <h5 class="card-title"><?= htmlspecialchars($notification['title']) ?></h5>
                                            <small class="text-muted"><?= date('M j, Y g:i a', strtotime($notification['created_at'])) ?></small>
                                        </div>
                                        <p class="card-text"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                                        
                                        <?php if (!empty($notification['related_url'])): ?>
                                            <a href="<?= htmlspecialchars($notification['related_url']) ?>" class="btn btn-sm btn-info mt-2">
                                                <i class="fas fa-external-link-alt"></i> View Details
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="POST" class="d-inline mt-2">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                                                <button type="submit" name="mark_as_read" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check"></i> Mark as Read
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No notifications found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>