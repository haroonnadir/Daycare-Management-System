<?php
class NotificationSystem {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function createNotification($user_id, $title, $message, $related_url = null) {
        $stmt = $this->conn->prepare("INSERT INTO notifications 
                                    (user_id, title, message, related_url, created_at) 
                                    VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("isss", $user_id, $title, $message, $related_url);
        return $stmt->execute();
    }
    
    public function getUserNotifications($user_id, $limit = null, $unread_only = false) {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        if ($unread_only) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC";
        if ($limit) {
            $sql .= " LIMIT ?";
        }
        
        $stmt = $this->conn->prepare($sql);
        if ($limit) {
            $stmt->bind_param("ii", $user_id, $limit);
        } else {
            $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
    
    public function getUnreadCount($user_id) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM notifications 
                                    WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['count'];
    }
    
    public function markAsRead($notification_id, $user_id) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 
                                    WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
        return $stmt->execute();
    }
    
    public function markAllAsRead($user_id) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 
                                    WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        return $stmt->execute();
    }
    
    public function sendEmailNotification($user_email, $subject, $message) {
        // Implement email sending logic using PHPMailer or mail() function
        // This is a placeholder implementation
        $headers = "From: no-reply@childcare.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($user_email, $subject, $message, $headers);
    }
}

// Utility functions
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function cleanInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function sendReminderEmail($invoice) {
    $to = $invoice['parent_email'];
    $subject = "Payment Reminder: Invoice #" . $invoice['invoice_number'];
    
    $message = "
    <html>
    <head>
        <title>Payment Reminder</title>
    </head>
    <body>
        <h2>Payment Reminder</h2>
        <p>Dear " . htmlspecialchars($invoice['parent_name']) . ",</p>
        
        <p>This is a reminder that Invoice #" . $invoice['invoice_number'] . " for " . 
        htmlspecialchars($invoice['child_name']) . " is due on " . 
        date('F j, Y', strtotime($invoice['due_date'])) . ".</p>
        
        <h3>Invoice Summary</h3>
        <ul>
            <li>Invoice Number: #" . $invoice['invoice_number'] . "</li>
            <li>Child: " . htmlspecialchars($invoice['child_name']) . "</li>
            <li>Due Date: " . date('F j, Y', strtotime($invoice['due_date'])) . "</li>
            <li>Amount Due: $" . number_format($invoice['total_amount'] - ($invoice['paid_amount'] ?? 0), 2) . "</li>
        </ul>
        
        <p>Please log in to your account to view and pay the invoice:</p>
        <p><a href='https://yourdomain.com/parent_view_invoice.php?id=" . $invoice['id'] . "'>
            View Invoice
        </a></p>
        
        <p>Thank you,<br>
        Childcare Billing Team</p>
    </body>
    </html>
    ";
    
    $headers = "From: billing@childcare.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}
?>