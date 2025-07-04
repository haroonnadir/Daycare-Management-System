<?php
include '../db_connect.php';

// Admin authentication
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// Get filter parameters
$month = $_GET['month'] ?? date('Y-m');
$status = $_GET['status'] ?? 'all';

// Build query
$query = "
    SELECT i.*, 
           u.name as parent_name, u.email as parent_email,
           c.first_name as child_first_name, c.last_name as child_last_name
    FROM invoices i
    JOIN users u ON i.parent_id = u.id
    JOIN children c ON i.child_id = c.id
    WHERE DATE_FORMAT(i.billing_period_start, '%Y-%m') = ?
";

if ($status !== 'all') {
    $query .= " AND i.status = ?";
    $params = [$month, $status];
} else {
    $params = [$month];
}

$query .= " ORDER BY i.issue_date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Get available months for filter
$months = $db->query("
    SELECT DISTINCT DATE_FORMAT(billing_period_start, '%Y-%m') as month 
    FROM invoices 
    ORDER BY month DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .filters { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 20px; }
        .invoice-card { background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 15px; }
        .invoice-header { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        .invoice-status { padding: 3px 8px; border-radius: 12px; font-size: 0.8em; }
        .status-draft { background: #9E9E9E; color: white; }
        .status-sent { background: #2196F3; color: white; }
        .status-paid { background: #4CAF50; color: white; }
        .status-overdue { background: #F44336; color: white; }
        .status-cancelled { background: #607D8B; color: white; }
        .invoice-details { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .invoice-items { margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; }
        .totals { text-align: right; margin-top: 15px; }
        .actions { display: flex; gap: 10px; margin-top: 15px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #2196F3; color: white; }
        .btn-success { background: #4CAF50; color: white; }
        .btn-danger { background: #F44336; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Billing Dashboard</h1>
            <a href="generate_invoices.php" class="btn btn-primary">Generate Monthly Invoices</a>
        </div>
        
        <div class="filters">
            <div>
                <label for="month">Month:</label>
                <select id="month" onchange="updateFilters()">
                    <?php foreach ($months as $m): ?>
                        <option value="<?= $m['month'] ?>" <?= $m['month'] === $month ? 'selected' : '' ?>>
                            <?= date('F Y', strtotime($m['month'] . '-01')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="status">Status:</label>
                <select id="status" onchange="updateFilters()">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
        </div>
        
        <?php if (empty($invoices)): ?>
            <div class="invoice-card">
                <p>No invoices found for the selected filters.</p>
            </div>
        <?php else: ?>
            <?php foreach ($invoices as $invoice): ?>
                <div class="invoice-card">
                    <div class="invoice-header">
                        <h2>Invoice #<?= $invoice['invoice_number'] ?></h2>
                        <span class="invoice-status status-<?= $invoice['status'] ?>">
                            <?= ucfirst($invoice['status']) ?>
                        </span>
                    </div>
                    
                    <div class="invoice-details">
                        <div>
                            <p><strong>Parent:</strong> <?= htmlspecialchars($invoice['parent_name']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($invoice['parent_email']) ?></p>
                        </div>
                        <div>
                            <p><strong>Child:</strong> <?= htmlspecialchars($invoice['child_first_name'] . ' ' . $invoice['child_last_name']) ?></p>
                            <p><strong>Period:</strong> <?= date('M j, Y', strtotime($invoice['billing_period_start'])) ?> to <?= date('M j, Y', strtotime($invoice['billing_period_end'])) ?></p>
                        </div>
                    </div>
                    
                    <div class="invoice-items">
                        <table>
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $items = $db->query("
                                    SELECT * FROM invoice_items 
                                    WHERE invoice_id = {$invoice['id']}
                                ")->fetchAll();
                                ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['description']) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>$<?= number_format($item['unit_price'], 2) ?></td>
                                        <td>$<?= number_format($item['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="totals">
                            <p><strong>Subtotal: $<?= number_format($invoice['subtotal'], 2) ?></strong></p>
                            <?php if ($invoice['discount_amount'] > 0): ?>
                                <p>Discount: -$<?= number_format($invoice['discount_amount'], 2) ?></p>
                            <?php endif; ?>
                            <?php if ($invoice['tax_amount'] > 0): ?>
                                <p>Tax: $<?= number_format($invoice['tax_amount'], 2) ?></p>
                            <?php endif; ?>
                            <p><strong>Total: $<?= number_format($invoice['total_amount'], 2) ?></strong></p>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <a href="view_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-primary">View Details</a>
                        <?php if ($invoice['status'] === 'draft'): ?>
                            <a href="send_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-success">Send to Parent</a>
                        <?php endif; ?>
                        <?php if ($invoice['status'] === 'sent' || $invoice['status'] === 'overdue'): ?>
                            <a href="record_payment.php?id=<?= $invoice['id'] ?>" class="btn btn-success">Record Payment</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function updateFilters() {
            const month = document.getElementById('month').value;
            const status = document.getElementById('status').value;
            window.location.href = `billing_dashboard.php?month=${month}&status=${status}`;
        }
    </script>
</body>
</html>