<?php
session_start();
require_once '../db_connect.php';

// Authentication and authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

$parent_id = $_SESSION['user_id'];

// Get filter parameters if they exist
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$status_filter = isset($_GET['status']) ? $_GET['status'] : null;

// Base SQL query
$sql = "SELECT i.*, c.first_name, c.last_name 
        FROM invoices i
        JOIN parent_child pc ON i.child_id = pc.child_id
        JOIN children c ON i.child_id = c.id
        WHERE pc.parent_id = ?";

// Add filters to query
$params = array($parent_id);
$types = "i";

if ($start_date) {
    $sql .= " AND i.due_date >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if ($end_date) {
    $sql .= " AND i.due_date <= ?";
    $params[] = $end_date;
    $types .= "s";
}

if ($status_filter) {
    if ($status_filter === 'overdue') {
        $sql .= " AND i.status != 'paid' AND i.due_date < CURDATE()";
    } else {
        $sql .= " AND i.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
}

$sql .= " ORDER BY i.due_date DESC";

$stmt = $conn->prepare($sql);

// Bind parameters dynamically
if (count($params) > 1) {
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param($types, $params[0]);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Invoices - Daycare Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .invoice-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        .invoice-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .invoice-unpaid {
            border-left-color: #dc3545;
        }
        .invoice-paid {
            border-left-color: #28a745;
        }
        .invoice-overdue {
            border-left-color: #ffc107;
        }
        .badge-overdue {
            background-color: #ffc107;
            color: #212529;
        }
        .go-back-btn {
            background-color: #6c757d;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
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
        .filter-active {
            background-color: #e9ecef;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>My Invoices</h2>
            <a href="parent_dashboard.php" class="go-back-btn">
                <i class="fas fa-arrow-left"></i> Go Back to Dashboard
            </a>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5 class="card-title">Invoice Summary</h5>
                        <?php
                        $summary_sql = "SELECT 
                            SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_total,
                            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                            SUM(CASE WHEN status != 'paid' AND due_date >= CURDATE() THEN total_amount ELSE 0 END) as unpaid_total,
                            SUM(CASE WHEN status != 'paid' AND due_date >= CURDATE() THEN 1 ELSE 0 END) as unpaid_count,
                            SUM(CASE WHEN status != 'paid' AND due_date < CURDATE() THEN total_amount ELSE 0 END) as overdue_total,
                            SUM(CASE WHEN status != 'paid' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count
                            FROM invoices i
                            JOIN parent_child pc ON i.child_id = pc.child_id
                            WHERE pc.parent_id = ?";
                        $stmt = $conn->prepare($summary_sql);
                        $stmt->bind_param("i", $parent_id);
                        $stmt->execute();
                        $summary = $stmt->get_result()->fetch_assoc();
                        ?>
                        <div class="row">
                            <div class="col-4">
                                <div class="text-success">
                                    <h6>Paid</h6>
                                    <h4><?= $summary['paid_count'] ?></h4>
                                    <small>$<?= number_format($summary['paid_total'], 2) ?></small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div>
                                    <h6>Unpaid</h6>
                                    <h4><?= $summary['unpaid_count'] ?></h4>
                                    <small>$<?= number_format($summary['unpaid_total'], 2) ?></small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-warning">
                                    <h6>Overdue</h6>
                                    <h4><?= $summary['overdue_count'] ?></h4>
                                    <small>$<?= number_format($summary['overdue_total'], 2) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Invoices</h5>
                <div>
                    <button class="btn btn-sm btn-outline-secondary me-2" id="filterBtn">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <?php if ($start_date || $end_date || $status_filter): ?>
                        <a href="parent_invoices.php" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-times me-1"></i>Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Child</th>
                                    <th>Issue Date</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($invoice = $result->fetch_assoc()): 
                                    $status_class = '';
                                    $status_badge = '';
                                    if ($invoice['status'] == 'paid') {
                                        $status_class = 'invoice-paid';
                                        $status_badge = '<span class="badge bg-success">Paid</span>';
                                    } elseif (strtotime($invoice['due_date']) < time()) {
                                        $status_class = 'invoice-overdue';
                                        $status_badge = '<span class="badge badge-overdue">Overdue</span>';
                                    } else {
                                        $status_class = 'invoice-unpaid';
                                        $status_badge = '<span class="badge bg-secondary">Unpaid</span>';
                                    }
                                ?>
                                <tr class="<?= $status_class ?>">
                                    <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                    <td><?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?></td>
                                    <td><?= date('M j, Y', strtotime($invoice['issue_date'])) ?></td>
                                    <td><?= date('M j, Y', strtotime($invoice['due_date'])) ?></td>
                                    <td>$<?= number_format($invoice['total_amount'], 2) ?></td>
                                    <td><?= $status_badge ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="parent_view_invoice.php?id=<?= $invoice['id'] ?>" 
                                               class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button onclick="window.print()" class="btn btn-outline-secondary" title="Print">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            
                                            <?php if ($invoice['status'] != 'paid'): ?>
                                            <a href="parent_select_payment.php?invoice_id=<?= $invoice['id'] ?>" 
                                                class="btn btn-outline-success" title="Pay">
                                                <i class="fas fa-credit-card"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No invoices found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="GET" action="parent_invoices.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="filterModalLabel">Filter Invoices</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="start_date" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                        </div>
                        <div class="mb-3">
                            <label for="end_date" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="unpaid" <?= $status_filter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                <option value="overdue" <?= $status_filter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date pickers
            flatpickr("#start_date", {
                dateFormat: "Y-m-d",
                defaultDate: "<?= date('Y-m-01') ?>"
            });
            
            flatpickr("#end_date", {
                dateFormat: "Y-m-d",
                defaultDate: "<?= date('Y-m-t') ?>"
            });

            // Filter modal
            const filterBtn = document.getElementById('filterBtn');
            const filterModal = new bootstrap.Modal(document.getElementById('filterModal'));
            
            filterBtn.addEventListener('click', function() {
                filterModal.show();
            });
        });
    </script>
</body>
</html>