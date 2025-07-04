<?php
require 'auth.php';
require 'db.php';

if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$invoiceId = (int)$_GET['id'];
$adminId = $_SESSION['user_id'];

// Get invoice details
$invoice = $db->query("
    SELECT i.*, u.name as parent_name, u.email, u.phone,
           c.first_name as child_first_name, c.last_name as child_last_name
    FROM invoices i
    JOIN users u ON i.parent_id = u.id
    JOIN children c ON i.child_id = c.id
    WHERE i.id = $invoiceId
")->fetch();

if (!$invoice) {
    header("Location: admin_payments.php");
    exit;
}

// Check if we're sending the reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reminderType = $_POST['reminder_type'];
    $customMessage = cleanInput($_POST['custom_message']);
    
    // Default message
    $message = "Dear {$invoice['parent_name']},\n\n";
    $message .= "This is a reminder that payment for invoice #{$invoice['invoice_number']} ";
    $message .= "for {$invoice['child_first_name']} {$invoice['child_last_name']} ";
    $message .= "is due on " . date('F j, Y', strtotime($invoice['due_date'])) . ".\n\n";
    $message .= "Amount Due: $" . number_format($invoice['total_amount'], 2) . "\n\n";
    
    if (!empty($customMessage)) {
        $message .= "Note: $customMessage\n\n";
    }
    
    $message .= "You can make payments through our parent portal or contact us for assistance.\n\n";
    $message .= "Thank you,\nDaycare Administration";
    
    // Record the reminder
    $stmt = $db->prepare("
        INSERT INTO payment_reminders 
        (invoice_id, sent_at, sent_by, reminder_type, message)
        VALUES (?, NOW(), ?, ?, ?)
    ");
    $stmt->execute([$invoiceId, $adminId, $reminderType, $message]);
    
    // Send the actual reminder (in a real system)
    if ($reminderType === 'email' && !empty($invoice['email'])) {
        // mail($invoice['email'], "Payment Reminder: Invoice #{$invoice['invoice_number']}", $message);
        // Log this in a real system
    } elseif ($reminderType === 'sms' && !empty($invoice['phone'])) {
        // Use SMS gateway API to send message
        // Log this in a real system
    }
    
    header("Location: admin_payments.php?success=Reminder+sent+successfully");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Payment Reminder</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-height: 100px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #2196F3; color: white; }
        .btn-secondary { background: #9E9E9E; color: white; }
        .preview-box { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Send Payment Reminder</h1>
        <p>Invoice #<?= $invoice['invoice_number'] ?> for <?= htmlspecialchars($invoice['child_first_name'] . ' ' . $invoice['child_last_name']) ?></p>
        <p>Parent: <?= htmlspecialchars($invoice['parent_name']) ?></p>
        <p>Amount Due: $<?= number_format($invoice['total_amount'], 2) ?></p>
        <p>Due Date: <?= date('F j, Y', strtotime($invoice['due_date'])) ?></p>
        
        <form method="POST">
            <div class="form-group">
                <label>Reminder Type</label>
                <div style="display: flex; gap: 15px;">
                    <label>
                        <input type="radio" name="reminder_type" value="email" checked> 
                        Email (<?= htmlspecialchars($invoice['email']) ?>)
                    </label>
                    <?php if (!empty($invoice['phone'])): ?>
                        <label>
                            <input type="radio" name="reminder_type" value="sms"> 
                            SMS (<?= htmlspecialchars($invoice['phone']) ?>)
                        </label>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="custom_message">Custom Message (optional)</label>
                <textarea name="custom_message" id="custom_message" placeholder="Add any additional notes to include in the reminder"></textarea>
            </div>
            
            <div class="form-group">
                <label>Preview</label>
                <div class="preview-box" id="preview">
                    Dear <?= htmlspecialchars($invoice['parent_name']) ?>,

This is a reminder that payment for invoice #<?= $invoice['invoice_number'] ?> 
for <?= htmlspecialchars($invoice['child_first_name'] . ' ' . $invoice['child_last_name']) ?> 
is due on <?= date('F j, Y', strtotime($invoice['due_date'])) ?>.

Amount Due: $<?= number_format($invoice['total_amount'], 2) ?>


You can make payments through our parent portal or contact us for assistance.

Thank you,
Daycare Administration
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between;">
                <a href="admin_payments.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Send Reminder</button>
            </div>
        </form>
    </div>

    <script>
        // Update preview when custom message changes
        document.getElementById('custom_message').addEventListener('input', function() {
            const defaultMessage = `Dear <?= htmlspecialchars($invoice['parent_name']) ?>,

This is a reminder that payment for invoice #<?= $invoice['invoice_number'] ?> 
for <?= htmlspecialchars($invoice['child_first_name'] . ' ' . $invoice['child_last_name']) ?> 
is due on <?= date('F j, Y', strtotime($invoice['due_date'])) ?>.

Amount Due: $<?= number_format($invoice['total_amount'], 2) ?>`;

            const customMessage = this.value.trim();
            let fullMessage = defaultMessage;
            
            if (customMessage) {
                fullMessage += '\n\nNote: ' + customMessage + '\n';
            }
            
            fullMessage += '\nYou can make payments through our parent portal or contact us for assistance.\n\nThank you,\nDaycare Administration';
            
            document.getElementById('preview').textContent = fullMessage;
        });
    </script>
</body>
</html>