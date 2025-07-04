<?php
session_start();
include '../db_connect.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$invoiceId = $_GET['id'] ?? null;
$parentId = $_SESSION['user_id'];

if (!$invoiceId) {
    header("Location: parent_view_invoices.php");
    exit();
}

// Get invoice details with proper error handling
$stmt = $conn->prepare("SELECT i.*, c.first_name as child_first_name, c.last_name as child_last_name,
                       u.name as parent_name
                       FROM invoices i
                       JOIN children c ON i.child_id = c.id
                       JOIN users u ON i.parent_id = u.id
                       WHERE i.id = ? AND i.parent_id = ?");
$stmt->bind_param("ii", $invoiceId, $parentId);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    header("Location: parent_invoices.php");
    exit();
}

// Initialize missing fields with default values
$invoice['line_items'] = $invoice['line_items'] ?? '[]';
$invoice['subtotal'] = $invoice['subtotal'] ?? 0;
$invoice['tax_amount'] = $invoice['tax_amount'] ?? 0;
$invoice['discount_amount'] = $invoice['discount_amount'] ?? 0;
$invoice['total_amount'] = $invoice['total_amount'] ?? 0;
$invoice['paid_amount'] = $invoice['paid_amount'] ?? 0;
$invoice['notes'] = $invoice['notes'] ?? '';
$invoice['issue_date'] = $invoice['issue_date'] ?? date('Y-m-d');
$invoice['due_date'] = $invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
$invoice['billing_period_start'] = $invoice['billing_period_start'] ?? date('Y-m-01');
$invoice['billing_period_end'] = $invoice['billing_period_end'] ?? date('Y-m-t');

// Get payments for this invoice
$stmt = $conn->prepare("SELECT p.*, pm.nickname as payment_method_nickname
                       FROM payments p
                       LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
                       WHERE p.invoice_id = ?
                       ORDER BY p.payment_date DESC");
$stmt->bind_param("i", $invoiceId);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate remaining balance
$remaining = $invoice['total_amount'] - $invoice['paid_amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?> - Daycare Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Your CSS styles remain the same */
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            border: none;
            background-color: var(--primary-color);
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
            margin-right: 10px;
            margin-bottom: 15px;
        }

        .btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn i {
            margin-right: 5px;
        }

        .btn-success {
            background-color: var(--success-color);
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        .btn-secondary {
            background-color: #7f8c8d;
        }

        .btn-secondary:hover {
            background-color: #95a5a6;
        }

        .invoice-details {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            align-items: center;
        }

        .invoice-title {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin: 0;
        }

        .invoice-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
        }

        .invoice-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .invoice-meta p {
            margin: 5px 0;
        }

        .invoice-meta strong {
            color: var(--dark-color);
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .invoice-table th, .invoice-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .invoice-table th {
            background: #f8f9fa;
            font-weight: 500;
            color: var(--dark-color);
        }

        .invoice-table tr:hover td {
            background-color: #f9f9f9;
        }

        .invoice-totals {
            margin-top: 30px;
            text-align: right;
        }

        .invoice-totals table {
            display: inline-table;
            width: auto;
            border-collapse: collapse;
        }

        .invoice-totals td {
            padding: 8px 15px;
            text-align: right;
        }

        .invoice-totals tr:last-child td {
            font-weight: bold;
            font-size: 1.1rem;
            border-top: 1px solid #eee;
        }

        .invoice-notes {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
        }

        .invoice-notes h3 {
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .payment-history {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
        }

        .payment-history h2 {
            margin-top: 0;
            color: var(--dark-color);
            margin-bottom: 20px;
        }

        .no-payments {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .invoice-details, .invoice-details *,
            .payment-history, .payment-history * {
                visibility: visible;
            }
            .invoice-details {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
                box-shadow: none;
                border: none;
            }
            .no-print {
                display: none;
            }
            .btn {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .invoice-meta {
                grid-template-columns: 1fr;
            }
            
            .invoice-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .invoice-status {
                margin-top: 10px;
            }
            
            .invoice-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="no-print">
            <a href="parent_view_invoice.php" class="btn btn-success">
                <i class="fas fa-arrow-left"></i> Back to Invoices
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print Invoice
            </button>
        </div>
        
        <div class="invoice-details">
            <div class="invoice-header">
                <h1 class="invoice-title">Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></h1>
                <span class="invoice-status status-<?= htmlspecialchars($invoice['status']) ?>">
                    <?= ucfirst(htmlspecialchars($invoice['status'])) ?>
                </span>
            </div>
            
            <div class="invoice-meta">
                <div>
                    <p><strong>Issued To:</strong></p>
                    <p><?= htmlspecialchars($invoice['parent_name']) ?></p>
                </div>
                <div>
                    <p><strong>Child:</strong></p>
                    <p><?= htmlspecialchars($invoice['child_first_name'] . ' ' . $invoice['child_last_name']) ?></p>
                </div>
                <div>
                    <p><strong>Issue Date:</strong> <?= date('M j, Y', strtotime($invoice['issue_date'])) ?></p>
                    <p><strong>Due Date:</strong> <?= date('M j, Y', strtotime($invoice['due_date'])) ?></p>
                </div>
                <div>
                    <p><strong>Billing Period:</strong></p>
                    <p><?= date('M j, Y', strtotime($invoice['billing_period_start'])) ?> to <?= date('M j, Y', strtotime($invoice['billing_period_end'])) ?></p>
                </div>
            </div>
            
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Rate</th>
                        <th>Quantity</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $lineItems = json_decode($invoice['line_items'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($lineItems)) {
                        foreach ($lineItems as $item): 
                            if (isset($item['description'], $item['rate'], $item['quantity'], $item['amount'])) {
                    ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['description']) ?></td>
                                    <td>$<?= number_format($item['rate'], 2) ?></td>
                                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                                    <td>$<?= number_format($item['amount'], 2) ?></td>
                                </tr>
                    <?php 
                            }
                        endforeach; 
                    } else {
                        // Fallback if line items are invalid
                    ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No line items available</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            
            <div class="invoice-totals">
                <table>
                    <tr>
                        <td>Subtotal:</td>
                        <td>$<?= number_format($invoice['subtotal'], 2) ?></td>
                    </tr>
                    <?php if ($invoice['tax_amount'] > 0): ?>
                    <tr>
                        <td>Tax:</td>
                        <td>$<?= number_format($invoice['tax_amount'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($invoice['discount_amount'] > 0): ?>
                    <tr>
                        <td>Discount:</td>
                        <td>-$<?= number_format($invoice['discount_amount'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Total Amount:</td>
                        <td>$<?= number_format($invoice['total_amount'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Paid Amount:</td>
                        <td>$<?= number_format($invoice['paid_amount'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Balance Due:</td>
                        <td>$<?= number_format($remaining, 2) ?></td>
                    </tr>
                </table>
            </div>
            
            <?php if (!empty($invoice['notes'])): ?>
                <div class="invoice-notes">
                    <h3>Notes</h3>
                    <p><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="payment-history">
            <h2>Payment History</h2>
            
            <?php if (empty($payments)): ?>
                <div class="no-payments">
                    <p>No payments have been made for this invoice yet.</p>
                </div>
            <?php else: ?>
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($payment['payment_date'])) ?></td>
                                <td>$<?= number_format($payment['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($payment['payment_method_nickname'] ?? $payment['payment_method'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($payment['status']) ?></td>
                                <td><?= htmlspecialchars($payment['transaction_id'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>