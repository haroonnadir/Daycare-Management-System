<?php
session_start();
require_once '../db_connect.php';

// Authentication and authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

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
    <title>Print Invoice #<?= $invoice['invoice_number'] ?> - Daycare Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .print-section, .print-section * {
                visibility: visible;
            }
            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
            .card {
                border: none;
                box-shadow: none;
            }
            table {
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
        body {
            background-color: #fff;
        }
        .print-header {
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
            padding-bottom: 20px;
        }
        .print-footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container print-section py-4">
        <div class="text-end no-print mb-3">
            <button onclick="window.print()" class="btn btn-primary me-2">
                <i class="fas fa-print me-1"></i> Print
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fas fa-times me-1"></i> Close
            </button>
        </div>

        <div class="print-header text-center mb-4">
            <h2>Daycare Center</h2>
            <p class="mb-0">123 Daycare Street, City, State ZIP</p>
            <p class="mb-0">Phone: (123) 456-7890 | Email: info@daycare.com</p>
        </div>

        <div class="d-flex justify-content-between mb-4">
            <div>
                <h4>Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></h4>
                <p class="mb-1"><strong>Issued:</strong> <?= date('M j, Y', strtotime($invoice['issue_date'])) ?></p>
                <p class="mb-1"><strong>Due:</strong> <?= date('M j, Y', strtotime($invoice['due_date'])) ?></p>
                <?php if ($invoice['status'] == 'paid' && isset($invoice['last_payment_date'])): ?>
                    <p class="mb-1"><strong>Paid:</strong> <?= date('M j, Y', strtotime($invoice['last_payment_date'])) ?></p>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <h4>Invoice To:</h4>
                <p class="mb-1"><?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?></p>
                <p class="mb-1">Child of <?= htmlspecialchars($_SESSION['user_name']) ?></p>
                <p class="mb-1">Billing Period: <?= date('M j', strtotime($invoice['billing_period_start'])) ?> - <?= date('M j, Y', strtotime($invoice['billing_period_end'])) ?></p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr class="table-active">
                            <th>Description</th>
                            <th class="text-end">Qty</th>
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

        <div class="row justify-content-end">
            <div class="col-md-5">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td><strong>Subtotal</strong></td>
                            <td class="text-end">$<?= number_format($invoice['total_amount'] - $invoice['tax_amount'] - $invoice['discount_amount'], 2) ?></td>
                        </tr>
                        <?php if ($invoice['discount_amount'] > 0): ?>
                        <tr>
                            <td><strong>Discount</strong></td>
                            <td class="text-end text-success">-$<?= number_format($invoice['discount_amount'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($invoice['tax_amount'] > 0): ?>
                        <tr>
                            <td><strong>Tax</strong></td>
                            <td class="text-end">$<?= number_format($invoice['tax_amount'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="table-active">
                            <td><strong>Total Amount</strong></td>
                            <td class="text-end"><strong>$<?= number_format($invoice['grand_total'], 2) ?></strong></td>
                        </tr>
                        <?php if ($invoice['status'] == 'paid'): ?>
                        <tr>
                            <td><strong>Amount Paid</strong></td>
                            <td class="text-end">$<?= number_format($invoice['paid_amount'], 2) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Balance</strong></td>
                            <td class="text-end">$<?= number_format($invoice['grand_total'] - $invoice['paid_amount'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            <div class="d-flex justify-content-between">
                <div class="w-50 pe-4">
                    <h5>Payment Status:</h5>
                    <?php if ($invoice['status'] == 'paid'): ?>
                        <span class="badge bg-success">PAID</span>
                        <p class="mt-2">Thank you for your payment!</p>
                    <?php elseif (strtotime($invoice['due_date']) < time()): ?>
                        <span class="badge bg-warning text-dark">OVERDUE</span>
                        <p class="mt-2">Please make payment immediately.</p>
                    <?php else: ?>
                        <span class="badge bg-secondary">UNPAID</span>
                        <p class="mt-2">Payment due by <?= date('M j, Y', strtotime($invoice['due_date'])) ?>.</p>
                    <?php endif; ?>
                </div>
                <div class="w-50 ps-4">
                    <h5>Payment Methods:</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check-circle text-success me-2"></i> Credit/Debit Card</li>
                        <li><i class="fas fa-check-circle text-success me-2"></i> Bank Transfer</li>
                        <li><i class="fas fa-check-circle text-success me-2"></i> Cash</li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if ($payments->num_rows > 0): ?>
        <div class="mt-4">
            <h5>Payment History</h5>
            <table class="table table-bordered">
                <thead>
                    <tr class="table-active">
                        <th>Date</th>
                        <th>Method</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($payment = $payments->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($payment['payment_date'])) ?></td>
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
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="print-footer">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Daycare Center</strong></p>
                    <p>123 Daycare Street<br>City, State ZIP</p>
                </div>
                <div class="col-md-6 text-end">
                    <p>Thank you for your business!</p>
                    <p>Questions? Contact us at:<br>info@daycare.com or (123) 456-7890</p>
                </div>
            </div>
            <p class="text-center mt-3">This is a computer generated invoice. No signature required.</p>
        </div>
    </div>

    <script>
        // Automatically trigger print dialog when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>