<?php
require 'auth.php';
require 'db.php';

if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$invoiceId = (int)$_GET['id'];

// Get invoice details
$invoice = $db->query("
    SELECT i.*, u.name as parent_name, 
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

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $paymentMethod = $_POST['payment_method'];
    $paymentDate = $_POST['payment_date'];
    $transactionId = cleanInput($_POST['transaction_id']);
    $notes = cleanInput($_POST['notes']);
    
    // Validate amount
    if ($amount <= 0 || $amount > $invoice['total_amount']) {
        $error = "Invalid payment amount";
    } else {
        // Record payment
        $stmt = $db->prepare("
            INSERT INTO payments 
            (invoice_id, amount, payment_method, transaction_id, payment_date, recorded_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoiceId, 
            $amount, 
            $paymentMethod, 
            $transactionId, 
            $paymentDate, 
            $_SESSION['user_id'], 
            $notes
        ]);
        
        // Update invoice status if fully paid
        $paidAmount = $db->query("
            SELECT COALESCE(SUM(amount), 0) 
            FROM payments 
            WHERE invoice_id = $invoiceId
        ")->fetchColumn();
        
        if ($paidAmount >= $invoice['total_amount']) {
            $db->query("UPDATE invoices SET status = 'paid' WHERE id = $invoiceId");
        }
        
        header("Location: admin_payments.php?success=Payment+recorded+successfully");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #2196F3; color: white; }
        .btn-secondary { background: #9E9E9E; color: white; }
        .error { color: #F44336; margin-bottom: 15px; }
        .invoice-info { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Record Payment</h1>
        
        <div class="invoice-info">
            <p><strong>Invoice #<?= $invoice['invoice_number'] ?></strong></p>
            <p>For: <?= htmlspecialchars($invoice['child_first_name'] . ' ' . $invoice['child_last_name']) ?></p>
            <p>Parent: <?= htmlspecialchars($invoice['parent_name']) ?></p>
            <p>Total Due: $<?= number_format($invoice['total_amount'], 2) ?></p>
            <p>Due Date: <?= date('F j, Y', strtotime($invoice['due_date'])) ?></p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="amount">Amount Paid *</label>
                <input type="number" name="amount" id="amount" min="0.01" max="<?= $invoice['total_amount'] ?>" 
                       step="0.01" value="<?= $invoice['total_amount'] ?>" required>
            </div>
            
            <div class="form-group">
                <label for="payment_method">Payment Method *</label>
                <select name="payment_method" id="payment_method" required>
                    <option value="credit_card">Credit Card</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cash">Cash</option>
                    <option value="online">Online Payment</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="payment_date">Payment Date *</label>
                <input type="date" name="payment_date" id="payment_date" 
                       value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="transaction_id">Transaction/Reference ID</label>
                <input type="text" name="transaction_id" id="transaction_id">
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea name="notes" id="notes" rows="3"></textarea>
            </div>
            
            <div style="display: flex; justify-content: space-between;">
                <a href="admin_payments.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Record Payment</button>
            </div>
        </form>
    </div>
</body>
</html>