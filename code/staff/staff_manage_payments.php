<?php
session_start();
include '../db_connect.php';

require_once 'billing_functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$billingSystem = new BillingSystem($pdo);

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get all payments
$payments = [];
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT p.*, 
        i.invoice_number,
        CONCAT(c.first_name, ' ', c.last_name) AS child_name,
        CONCAT(u.first_name, ' ', u.last_name) AS parent_name,
        CONCAT(staff.first_name, ' ', staff.last_name) AS processed_by_name
        FROM payments p
        JOIN invoices i ON p.invoice_id = i.id
        JOIN children c ON i.child_id = c.id
        JOIN users u ON i.parent_id = u.id
        LEFT JOIN users staff ON p.processed_by = staff.id";

$params = [];
$where = [];

if (!empty($search)) {
    $where[] = "(c.first_name LIKE :search OR c.last_name LIKE :search OR 
                u.first_name LIKE :search OR u.last_name LIKE :search OR
                i.invoice_number LIKE :search OR p.transaction_id LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $where[] = "p.status = :status";
    $params[':status'] = $status;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY p.payment_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending payments count for badge
$pendingPayments = $billingSystem->getPendingPaymentsCount();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Childcare Billing System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .badge {
            font-size: 0.8em;
        }
        .status-pending { color: #ffc107; }
        .status-completed { color: #198754; }
        .status-failed { color: #dc3545; }
        .status-refunded { color: #6c757d; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="admin_dashboard.php">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="admin_manage_invoices.php">
                                <i class="fas fa-file-invoice"></i> Manage Invoices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="admin_generate_reports.php">
                                <i class="fas fa-chart-bar"></i> Reports & Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="admin_manage_payments.php">
                                <i class="fas fa-file-invoice-dollar"></i> Billing & Payments
                                <span class="badge bg-danger"><?= $pendingPayments ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Payments</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                        </div>
                    </div>
                </div>

                <!-- Search and filter form -->
                <form method="get" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="search" placeholder="Search by child, parent, invoice or transaction ID..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="completed" <?= $status == 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="failed" <?= $status == 'failed' ? 'selected' : '' ?>>Failed</option>
                                <option value="refunded" <?= $status == 'refunded' ? 'selected' : '' ?>>Refunded</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Payments table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice</th>
                                <th>Child</th>
                                <th>Parent</th>
                                <th class="text-end">Amount</th>
                                <th>Method</th>
                                <th>Transaction ID</th>
                                <th>Processed By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= date('M j, Y h:i A', strtotime($payment['payment_date'])) ?></td>
                                    <td><?= htmlspecialchars($payment['invoice_number']) ?></td>
                                    <td><?= htmlspecialchars($payment['child_name']) ?></td>
                                    <td><?= htmlspecialchars($payment['parent_name']) ?></td>
                                    <td class="text-end">$<?= number_format($payment['amount'], 2) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?></td>
                                    <td><?= $payment['transaction_id'] ? htmlspecialchars($payment['transaction_id']) : 'N/A' ?></td>
                                    <td><?= $payment['processed_by_name'] ?? 'System' ?></td>
                                    <td>
                                        <span class="status-<?= $payment['status'] ?>">
                                            <?= ucfirst($payment['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($payment['status'] == 'pending'): ?>
                                                <a href="admin_process_payment.php?id=<?= $payment['id'] ?>&action=complete" class="btn btn-success" title="Mark as Completed">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="admin_process_payment.php?id=<?= $payment['id'] ?>&action=fail" class="btn btn-danger" title="Mark as Failed">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php elseif ($payment['status'] == 'completed'): ?>
                                                <a href="admin_process_payment.php?id=<?= $payment['id'] ?>&action=refund" class="btn btn-warning" title="Refund">
                                                    <i class="fas fa-undo"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="admin_view_payment.php?id=<?= $payment['id'] ?>" class="btn btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No payments found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>