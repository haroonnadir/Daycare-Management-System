<?php
require_once '../db_connect.php';
require_once 'billing_functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Initialize BillingSystem with MySQLi connection
$billingSystem = new BillingSystem($conn);

// Handle invoice deletion only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invoice'])) {
    // Handle invoice deletion
    $invoiceId = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
    if ($invoiceId) {
        try {
            $billingSystem->deleteInvoice($invoiceId);
            $_SESSION['success_message'] = "Invoice deleted successfully!";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error deleting invoice: " . $e->getMessage();
        }
    }
    // Redirect to prevent form resubmission
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Get all invoices with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$invoices = $billingSystem->getAllInvoices($perPage, $offset);
$totalInvoices = $billingSystem->getTotalInvoicesCount();
$totalPages = ceil($totalInvoices / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Invoices - Childcare Billing System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .status-paid {
            background-color: #28a745;
            color: white;
        }
        .status-pending {
            background-color: #ffc107;
            color: black;
        }
        .status-overdue {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Invoices</h1>
                    <h1 class="h2"><a href="admin_dashboard.php" class="btn btn-primary">Back to Dashboard</a></h1>
                </div>

                <!-- Display messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Invoices Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">All Invoices</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Child</th>
                                        <th>Parent</th>
                                        <th>Billing Period</th>
                                        <th class="text-end">Amount</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <?php
                                        $statusClass = '';
                                        if ($invoice['status'] === 'paid') {
                                            $statusClass = 'status-paid';
                                        } elseif (strtotime($invoice['due_date']) < time() && $invoice['status'] !== 'paid') {
                                            $statusClass = 'status-overdue';
                                        } else {
                                            $statusClass = 'status-pending';
                                        }
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                            <td><?= htmlspecialchars($invoice['child_name']) ?></td>
                                            <td><?= htmlspecialchars($invoice['parent_name']) ?></td>
                                            <td>
                                                <?= date('M j, Y', strtotime($invoice['billing_period_start'])) ?> - 
                                                <?= date('M j, Y', strtotime($invoice['billing_period_end'])) ?>
                                            </td>
                                            <td class="text-end">$<?= number_format($invoice['total_amount'], 2) ?></td>
                                            <td><?= date('M j, Y', strtotime($invoice['due_date'])) ?></td>
                                            <td>
                                                <span class="status-badge <?= $statusClass ?>">
                                                    <?= ucfirst($invoice['status']) ?>
                                                    <?php if ($statusClass === 'status-overdue'): ?>
                                                        (Overdue)
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="admin_view_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="admin_edit_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this invoice?');">
                                                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                                    <button type="submit" name="delete_invoice" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($invoices)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No invoices found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>