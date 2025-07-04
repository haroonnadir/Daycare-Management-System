<?php
require '../db_connect.php';
require_once 'billing_functions.php';
require_once 'reminder_notification_functions.php';

session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit;
}

// Validate invoice ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: staff_invoices.php?error=Invalid invoice ID");
    exit();
}

$invoiceId = (int)$_GET['id'];
$billingSystem = new BillingSystem($conn);
$notificationSystem = new NotificationSystem($conn);

try {
    $invoice = $billingSystem->getInvoiceById($invoiceId);
    if (!$invoice) {
        header("Location: staff_invoices.php?error=Invoice not found");
        exit();
    }
    
    $invoiceItems = $billingSystem->getInvoiceItems($invoiceId);
    $paidAmount = $invoice['paid_amount'] ?? 0;
    $balance = $invoice['total_amount'] - $paidAmount;
    
    // Determine status
    if ($balance <= 0) {
        $statusBadge = 'bg-success';
        $statusText = 'Paid';
    } elseif ($invoice['due_date'] < date('Y-m-d')) {
        $statusBadge = 'bg-danger';
        $statusText = 'Overdue';
    } else {
        $statusBadge = 'bg-warning';
        $statusText = ucfirst($invoice['status']);
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Handle reminder submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
    $message = cleanInput($_POST['message'] ?? '');
    $sendCopy = isset($_POST['send_copy']);
    
    // Send reminder notification
    $notificationSystem->createNotification(
        $invoice['parent_id'],
        'Payment Reminder: Invoice #' . $invoice['invoice_number'],
        "Reminder: Invoice #{$invoice['invoice_number']} is due on " . 
        date('M j, Y', strtotime($invoice['due_date'])) . 
        ". Amount due: $" . number_format($balance, 2) . 
        ($message ? "\n\n" . $message : ''),
        "parent_view_invoice.php?id={$invoiceId}"
    );
    
    // Send copy to staff if requested
    if ($sendCopy) {
        $notificationSystem->createNotification(
            $_SESSION['user_id'],
            'Reminder Sent for Invoice #' . $invoice['invoice_number'],
            "You sent a reminder for Invoice #{$invoice['invoice_number']} to " .
            $invoice['parent_name'],
            "staff_view_invoice.php?id={$invoiceId}"
        );
    }
    
    // Update last reminder timestamp
    $billingSystem->updateLastReminder($invoiceId);
    
    $_SESSION['success_message'] = "Reminder sent successfully!";
    header("Location: staff_view_invoice.php?id={$invoiceId}");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .invoice-header { background-color: #f8f9fa; padding: 20px; border-radius: 5px; }
        .totals-table { width: 300px; margin-left: auto; }
        .last-reminder { font-size: 0.9em; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container py-5">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></h1>
            <a href="staff_manage_invoices.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Invoices
            </a>
        </div>

        <div class="invoice-header mb-4">
            <div class="row">
                <div class="col-md-6">
                    <h4>Billed To:</h4>
                    <p>
                        <strong><?= htmlspecialchars($invoice['parent_name']) ?></strong><br>
                        Child: <?= htmlspecialchars($invoice['child_name']) ?><br>
                        Email: <?= htmlspecialchars($invoice['parent_email']) ?>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>
                        <strong>Invoice Date:</strong> <?= date('M j, Y', strtotime($invoice['created_at'])) ?><br>
                        <strong>Due Date:</strong> <?= date('M j, Y', strtotime($invoice['due_date'])) ?><br>
                        <strong>Status:</strong> 
                        <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                    </p>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <h4>Billing Period:</h4>
            <p>
                <?= date('M j, Y', strtotime($invoice['billing_period_start'])) ?> 
                to 
                <?= date('M j, Y', strtotime($invoice['billing_period_end'])) ?>
            </p>
        </div>

        <div class="table-responsive mb-5">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoiceItems as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($item['description']) ?></td>
                        <td class="text-end"><?= number_format($item['quantity'], 2) ?></td>
                        <td class="text-end">$<?= number_format($item['unit_price'], 2) ?></td>
                        <td class="text-end">$<?= number_format($item['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="totals-table mb-5">
            <table class="table">
                <tr>
                    <th>Subtotal:</th>
                    <td class="text-end">$<?= number_format($invoice['total_amount'], 2) ?></td>
                </tr>
                <tr>
                    <th>Amount Paid:</th>
                    <td class="text-end">$<?= number_format($paidAmount, 2) ?></td>
                </tr>
                <tr class="table-active">
                    <th>Balance Due:</th>
                    <td class="text-end">$<?= number_format($balance, 2) ?></td>
                </tr>
            </table>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <a href="staff_edit_invoice.php?id=<?= $invoiceId ?>" class="btn btn-primary me-2">
                    <i class="fas fa-edit"></i> Edit Invoice
                </a>
                <!-- <?php if ($balance > 0): ?>
                <a href="staff_record_payment.php?invoice_id=<?= $invoiceId ?>" class="btn btn-success">
                    <i class="fas fa-money-bill-wave"></i> Record Payment
                </a> -->
                <?php endif; ?>
            </div>
        </div>

        <!-- Reminder Section -->
        <div class="card mt-5">
            <div class="card-header bg-warning">
                <h5 class="mb-0">
                    <i class="fas fa-bell"></i> Payment Reminder
                    <?php if ($invoice['last_reminder_sent']): ?>
                        <span class="last-reminder">
                            (Last sent: <?= date('M j, Y g:i a', strtotime($invoice['last_reminder_sent'])) ?>)
                        </span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($balance > 0 && $invoice['status'] != 'cancelled'): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="message" class="form-label">Additional Message (optional)</label>
                            <textarea class="form-control" id="message" name="message" rows="3"
                                      placeholder="Add any additional message for the parent..."></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="send_copy" name="send_copy" checked>
                            <label class="form-check-label" for="send_copy">Send copy to my notifications</label>
                        </div>
                        
                        <button type="submit" name="send_reminder" class="btn btn-warning">
                            <i class="fas fa-paper-plane"></i> Send Reminder
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        No reminder needed - invoice is fully paid or cancelled.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>