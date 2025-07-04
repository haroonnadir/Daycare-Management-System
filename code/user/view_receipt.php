<?php
session_start();
include '../db_connect.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$parentId = $_SESSION['user_id'];

// Check if invoice_id is provided
if (!isset($_GET['invoice_id'])) {
    header("Location: parent_view_invoice.php");
    exit();
}

$invoiceId = intval($_GET['invoice_id']);

// Get invoice and payment details
$stmt = $conn->prepare("
    SELECT 
        i.*, 
        c.first_name as child_first_name, 
        c.last_name as child_last_name,
        p.payment_date,
        p.payment_method,
        p.amount as payment_amount,
        p.receipt_number,
        p.transaction_id,
        pm.nickname as payment_method_nickname,
        pm.type as payment_method_type,
        pm.card_holder_name,
        pm.account_holder_name
    FROM invoices i
    JOIN children c ON i.child_id = c.id
    LEFT JOIN payments p ON p.invoice_id = i.id
    LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
    WHERE i.id = ? AND i.parent_id = ?
    ORDER BY p.payment_date DESC
    LIMIT 1
");
$stmt->bind_param("ii", $invoiceId, $parentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: parent_view_invoice.php");
    exit();
}

$invoice = $result->fetch_assoc();

// If no payment found but invoice exists, redirect back
if (empty($invoice['payment_date'])) {
    header("Location: parent_view_invoice.php");
    exit();
}

// Format payment method for display
if ($invoice['payment_method_type'] === 'card') {
    $paymentMethod = $invoice['payment_method_nickname'] . ' (Card)';
} elseif ($invoice['payment_method_type'] === 'bank') {
    $paymentMethod = $invoice['payment_method_nickname'] . ' (Bank Transfer)';
} elseif ($invoice['payment_method_type'] === 'paypal') {
    $paymentMethod = 'PayPal (' . $invoice['payment_method_nickname'] . ')';
} else {
    $paymentMethod = $invoice['payment_method'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Daycare Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #6c757d;
            --success-color: #4CAF50;
            --danger-color: #F44336;
            --warning-color: #FFC107;
            --info-color: #2196F3;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .receipt-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .receipt-header h1 {
            color: var(--primary-color);
            margin: 0 0 10px;
        }

        .receipt-number {
            font-size: 1.1rem;
            color: var(--secondary-color);
        }

        .receipt-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .receipt-section {
            margin-bottom: 25px;
        }

        .receipt-section h2 {
            font-size: 1.2rem;
            color: var(--dark-color);
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .receipt-row strong {
            color: var(--dark-color);
        }

        .receipt-total {
            font-size: 1.2rem;
            font-weight: 600;
            text-align: right;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #eee;
        }

        .receipt-footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: var(--secondary-color);
            font-size: 0.9rem;
        }

        .btn-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #3a56d4;
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .paid-badge {
            background: var(--success-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
            margin-left: 10px;
        }

        @media (max-width: 768px) {
            .receipt-details {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-card, .receipt-card * {
                visibility: visible;
            }
            .receipt-card {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .btn-group {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="receipt-card">
            <div class="receipt-header">
                <h1>Payment Receipt</h1>
                <div class="receipt-number">Receipt #<?= htmlspecialchars($invoice['receipt_number'] ?? 'N/A') ?></div>
                <span class="paid-badge">PAID</span>
            </div>

            <div class="receipt-details">
                <div>
                    <div class="receipt-section">
                        <h2>Invoice Details</h2>
                        <div class="receipt-row">
                            <span>Invoice Number:</span>
                            <strong>#<?= htmlspecialchars($invoice['invoice_number']) ?></strong>
                        </div>
                        <div class="receipt-row">
                            <span>Child:</span>
                            <strong><?= htmlspecialchars($invoice['child_first_name'] . ' ' . $invoice['child_last_name']) ?></strong>
                        </div>
                        <div class="receipt-row">
                            <span>Billing Period:</span>
                            <strong>
                                <?= date('M j, Y', strtotime($invoice['billing_period_start'])) ?> - 
                                <?= date('M j, Y', strtotime($invoice['billing_period_end'])) ?>
                            </strong>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="receipt-section">
                        <h2>Payment Details</h2>
                        <div class="receipt-row">
                            <span>Payment Date:</span>
                            <strong><?= date('M j, Y', strtotime($invoice['payment_date'])) ?></strong>
                        </div>
                        <div class="receipt-row">
                            <span>Payment Method:</span>
                            <strong><?= htmlspecialchars($paymentMethod) ?></strong>
                        </div>
                        <?php if (!empty($invoice['transaction_id'])): ?>
                        <div class="receipt-row">
                            <span>Transaction ID:</span>
                            <strong><?= htmlspecialchars($invoice['transaction_id']) ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="receipt-section">
                <h2>Payment Summary</h2>
                <div class="receipt-row">
                    <span>Invoice Amount:</span>
                    <strong>$<?= number_format($invoice['total_amount'], 2) ?></strong>
                </div>
                <div class="receipt-row">
                    <span>Amount Paid:</span>
                    <strong>$<?= number_format($invoice['payment_amount'], 2) ?></strong>
                </div>
                <?php if ($invoice['total_amount'] != $invoice['payment_amount']): ?>
                <div class="receipt-row">
                    <span>Balance:</span>
                    <strong>$<?= number_format($invoice['total_amount'] - $invoice['payment_amount'], 2) ?></strong>
                </div>
                <?php endif; ?>
            </div>

            <div class="receipt-total">
                Thank you for your payment!
            </div>

            <div class="receipt-footer">
                <p>Daycare Name<br>
                123 Daycare Street, City, State 12345<br>
                Phone: (123) 456-7890 | Email: info@daycare.com</p>
            </div>
        </div>

        <div class="btn-group">
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <a href="parent_view_invoice.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Invoices
            </a>
        </div>
    </div>

    <script>
        // Automatically trigger print dialog if print parameter exists
        if (window.location.search.includes('print=true')) {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        }
    </script>
</body>
</html>