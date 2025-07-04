<?php
session_start();
require_once '../db_connect.php';

// Authentication and authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

// End of authentication and authorization
$invoice_id = $_GET['id'] ?? 0;
$parent_id = $_SESSION['user_id'];

// Verify this invoice belongs to the parent
$sql = "SELECT i.*, c.first_name, c.last_name 
        FROM invoices i
        JOIN parent_child pc ON i.child_id = pc.child_id
        JOIN children c ON i.child_id = c.id
        WHERE pc.parent_id = ? AND i.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $parent_id, $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    header("Location: parent_invoices.php");
    exit();
}

// Get invoice items
$items_sql = "SELECT * FROM invoice_items WHERE invoice_id = ?";
$stmt = $conn->prepare($items_sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$items = $stmt->get_result();

// Get payments if any
$payments_sql = "SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC";
$stmt = $conn->prepare($payments_sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$payments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $invoice['invoice_number'] ?> - Daycare Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $invoice['invoice_number'] ?> - Daycare Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .go-back-btn {
            background-color: #6c757d;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            border: none;
            font-size: 0.9rem;
        }
        .go-back-btn:hover {
            background-color: #5a6268;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }
        .go-back-btn i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="parent_invoices.php" class="go-back-btn me-3">
                    <i class="fas fa-arrow-left"></i> Back to Invoices
                </a>
                <h2 class="d-inline-block mb-0">
                    <i class="fas fa-file-invoice-dollar me-2"></i>
                    Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?>
                </h2>
            </div>
            <div>
                <a href="parent_print_invoice.php?id=<?= $invoice['id'] ?>" 
                   class="btn btn-outline-secondary me-2" target="_blank">
                    <i class="fas fa-print me-1"></i>Print
                </a>
                <?php if ($invoice['status'] != 'paid'): ?>
                <a href="parent_pay_invoice.php?id=<?= $invoice['id'] ?>" 
                   class="btn btn-success">
                    <i class="fas fa-credit-card me-1"></i>Pay Now
                </a>
                <?php endif; ?>
            </div>
        </div>


        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Invoice Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-5 text-muted">Child:</div>
                            <div class="col-7"><?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 text-muted">Issue Date:</div>
                            <div class="col-7"><?= date('M j, Y', strtotime($invoice['issue_date'])) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 text-muted">Due Date:</div>
                            <div class="col-7"><?= date('M j, Y', strtotime($invoice['due_date'])) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 text-muted">Billing Period:</div>
                            <div class="col-7">
                                <?= date('M j', strtotime($invoice['billing_period_start'])) ?> - 
                                <?= date('M j, Y', strtotime($invoice['billing_period_end'])) ?>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 text-muted">Status:</div>
                            <div class="col-7">
                                <?php if ($invoice['status'] == 'paid'): ?>
                                    <span class="badge bg-success">Paid</span>
                                <?php elseif (strtotime($invoice['due_date']) < time()): ?>
                                    <span class="badge bg-warning text-dark">Overdue</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Unpaid</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Amount Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-6 text-muted">Subtotal:</div>
                            <div class="col-6 text-end">$<?= number_format($invoice['total_amount'] - $invoice['tax_amount'] - $invoice['discount_amount'], 2) ?></div>
                        </div>
                        <?php if ($invoice['discount_amount'] > 0): ?>
                        <div class="row mb-2">
                            <div class="col-6 text-muted">Discount:</div>
                            <div class="col-6 text-end text-success">-$<?= number_format($invoice['discount_amount'], 2) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($invoice['tax_amount'] > 0): ?>
                        <div class="row mb-2">
                            <div class="col-6 text-muted">Tax:</div>
                            <div class="col-6 text-end">$<?= number_format($invoice['tax_amount'], 2) ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="row mb-2">
                            <div class="col-6"><strong>Total Amount:</strong></div>
                            <div class="col-6 text-end"><strong>$<?= number_format($invoice['grand_total'], 2) ?></strong></div>
                        </div>
                        <?php if ($invoice['status'] == 'paid'): ?>
                        <div class="row mb-2">
                            <div class="col-6 text-muted">Amount Paid:</div>
                            <div class="col-6 text-end">$<?= number_format($invoice['paid_amount'], 2) ?></div>
                        </div>
                        <div class="row">
                            <div class="col-6 text-muted">Payment Date:</div>
                            <div class="col-6 text-end"><?= date('M j, Y', strtotime($invoice['last_payment_date'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Invoice Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $items->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td class="text-end"><?= number_format($item['quantity'], 2) ?></td>
                                <td class="text-end">$<?= number_format($item['unit_price'], 2) ?></td>
                                <td class="text-end">$<?= number_format($item['amount'], 2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($payments->num_rows > 0): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Payment History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Method</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $payments->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('M j, Y g:i a', strtotime($payment['payment_date'])) ?></td>
                                <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                                <td class="text-end">$<?= number_format($payment['amount'], 2) ?></td>
                                <td>
                                    <?php if ($payment['status'] == 'Completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php elseif ($payment['status'] == 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?= htmlspecialchars($payment['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="parent_print_receipt.php?id=<?= $payment['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fas fa-receipt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>