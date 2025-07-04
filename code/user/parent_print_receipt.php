<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

$payment_id = $_GET['id'] ?? 0;
$parent_id = $_SESSION['user_id'];

// Get payment details
$sql = "SELECT p.*, i.invoice_number, i.total_amount, 
               c.first_name, c.last_name
        FROM payments p
        JOIN invoices i ON p.invoice_id = i.id
        JOIN parent_child pc ON i.child_id = pc.child_id
        JOIN children c ON i.child_id = c.id
        WHERE p.id = ? AND pc.parent_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $payment_id, $parent_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    header("Location: parent_invoices.php?");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - Daycare Management</title>
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
            }
            .no-print {
                display: none !important;
            }
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
        
        <div class="card">
            <div class="card-header bg-success text-white">
                <h3 class="text-center">Payment Receipt</h3>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Received From:</h5>
                        <p>
                            <?= htmlspecialchars($_SESSION['user_name']) ?><br>
                            For <?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <h5>Receipt #: <?= htmlspecialchars($payment['id']) ?></h5>
                        <p>
                            Date: <?= date('M j, Y', strtotime($payment['payment_date'])) ?><br>
                            Invoice #: <?= htmlspecialchars($payment['invoice_number']) ?>
                        </p>
                    </div>
                </div>
                
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Payment for Invoice <?= htmlspecialchars($payment['invoice_number']) ?></td>
                            <td>$<?= number_format($payment['amount'], 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-end"><strong>Total Paid</strong></td>
                            <td><strong>$<?= number_format($payment['amount'], 2) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="mt-4 p-3 bg-light rounded">
                    <h6>Payment Method:</h6>
                    <p><?= htmlspecialchars($payment['payment_method']) ?></p>
                    
                    <h6 class="mt-3">Status:</h6>
                    <span class="badge bg-success">COMPLETED</span>
                </div>
                
                <div class="mt-4 text-center">
                    <p>Thank you for your payment!</p>
                </div>
            </div>
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