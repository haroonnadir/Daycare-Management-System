<?php
session_start();
include '../db_connect.php';

// Verify admin/staff access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: login.php");
    exit();
}


// Filter parameters
$status = $_GET['status'] ?? 'pending';
$month = $_GET['month'] ?? date('Y-m');
$childName = $_GET['child'] ?? '';

// Build query
$query = "
    SELECT 
        i.id, i.invoice_number, i.total_amount, i.status, i.due_date,
        p.amount as paid_amount, p.payment_date, p.payment_method,
        u.name as parent_name, u.email as parent_email, u.phone as parent_phone,
        c.first_name as child_first_name, c.last_name as child_last_name,
        (SELECT COUNT(*) FROM payment_reminders WHERE invoice_id = i.id) as reminder_count
    FROM invoices i
    JOIN children c ON i.child_id = c.id
    JOIN users u ON i.parent_id = u.id
    LEFT JOIN payments p ON i.id = p.invoice_id
    WHERE DATE_FORMAT(i.issue_date, '%Y-%m') = ?
";

$params = [$month];

if ($status === 'pending') {
    $query .= " AND i.status IN ('sent', 'overdue')";
} elseif ($status === 'paid') {
    $query .= " AND i.status = 'paid'";
} elseif ($status === 'overdue') {
    $query .= " AND i.status = 'overdue'";
}

if (!empty($childName)) {
    $query .= " AND CONCAT(c.first_name, ' ', c.last_name) LIKE ?";
    $params[] = "%$childName%";
}

$query .= " ORDER BY i.due_date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Get available months
$months = $db->query("
    SELECT DISTINCT DATE_FORMAT(issue_date, '%Y-%m') as month 
    FROM invoices 
    ORDER BY month DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Tracking</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .filters { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .payment-card { background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 15px; margin-bottom: 15px; }
        .payment-header { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; }
        .payment-status { padding: 3px 8px; border-radius: 12px; font-size: 0.8em; }
        .status-pending { background: #FFC107; color: black; }
        .status-paid { background: #4CAF50; color: white; }
        .status-overdue { background: #F44336; color: white; }
        .payment-details { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .reminder-badge { background: #9C27B0; color: white; border-radius: 50%; padding: 0 6px; font-size: 0.8em; margin-left: 5px; }
        .actions { display: flex; gap: 10px; margin-top: 15px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.9em; }
        .btn-primary { background: #2196F3; color: white; }
        .btn-success { background: #4CAF50; color: white; }
        .btn-warning { background: #FFC107; color: black; }
        .empty-state { text-align: center; padding: 40px 20px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; }
        .due-soon { background-color: #FFF9C4; }
        .overdue { background-color: #FFEBEE; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Payment Tracking</h1>
        
        <div class="filters">
            <div>
                <label for="month">Month</label>
                <select id="month" onchange="updateFilters()">
                    <?php foreach ($months as $m): ?>
                        <option value="<?= $m['month'] ?>" <?= $m['month'] === $month ? 'selected' : '' ?>>
                            <?= date('F Y', strtotime($m['month'] . '-01')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" onchange="updateFilters()">
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>
            <div>
                <label for="child">Child Name</label>
                <input type="text" id="child" placeholder="Filter by child" 
                       value="<?= htmlspecialchars($childName) ?>" onchange="updateFilters()">
            </div>
        </div>
        
        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <h2>No payments found</h2>
                <p>No payments match your current filters.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Child</th>
                        <th>Parent</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Reminders</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): 
                        $dueClass = '';
                        if ($payment['status'] === 'overdue') {
                            $dueClass = 'overdue';
                        } elseif (strtotime($payment['due_date']) - time() < 86400 * 3) { // Due in 3 days
                            $dueClass = 'due-soon';
                        }
                    ?>
                        <tr class="<?= $dueClass ?>">
                            <td><?= $payment['invoice_number'] ?></td>
                            <td><?= htmlspecialchars($payment['child_first_name'] . ' ' . $payment['child_last_name']) ?></td>
                            <td>
                                <?= htmlspecialchars($payment['parent_name']) ?>
                                <div style="font-size: 0.8em; color: #666;">
                                    <?= htmlspecialchars($payment['parent_email']) ?>
                                </div>
                            </td>
                            <td>$<?= number_format($payment['total_amount'], 2) ?></td>
                            <td>
                                <?= date('M j, Y', strtotime($payment['due_date'])) ?>
                                <?php if ($payment['status'] === 'overdue'): ?>
                                    <div style="color: #F44336; font-size: 0.8em;">
                                        <?= floor((time() - strtotime($payment['due_date'])) / 86400) ?> days overdue
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="payment-status status-<?= $payment['status'] ?>">
                                    <?= ucfirst($payment['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($payment['reminder_count'] > 0): ?>
                                    <span class="reminder-badge"><?= $payment['reminder_count'] ?></span>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="view_invoice.php?id=<?= $payment['id'] ?>" class="btn btn-primary" title="View Invoice">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                    <?php if ($payment['status'] !== 'paid'): ?>
                                        <a href="send_reminder.php?id=<?= $payment['id'] ?>" class="btn btn-warning" title="Send Reminder">
                                            <i class="fas fa-bell"></i>
                                        </a>
                                        <a href="record_payment.php?id=<?= $payment['id'] ?>" class="btn btn-success" title="Record Payment">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        function updateFilters() {
            const month = document.getElementById('month').value;
            const status = document.getElementById('status').value;
            const child = document.getElementById('child').value;
            window.location.href = `admin_payments.php?month=${month}&status=${status}&child=${encodeURIComponent(child)}`;
        }
    </script>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</body>
</html>