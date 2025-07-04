<?php
require_once '../db_connect.php';
require_once 'billing_functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Initialize BillingSystem with MySQLi connection
$billingSystem = new BillingSystem($conn);

// Set default date range (current month)
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Generate financial report
$financialReport = $billingSystem->generateFinancialReport($startDate, $endDate);

// Get overdue invoices
$overdueInvoices = $billingSystem->getOverdueInvoices();

// Get pending payments count for badge
$pendingPayments = $billingSystem->getPendingPaymentsCount();

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Childcare Billing System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <style>
        /* Remove default link styling inside buttons */
        .btn a {
            color: inherit;
            text-decoration: none;
        }

        /* Better hover effect for the back button */
        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: #6c757d;
        }

        /* If using Bootstrap Icons (recommended) */
        .bi {
            vertical-align: middle;
        }
        .card-counter {
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
            margin: 5px;
            padding: 20px 10px;
            background-color: #fff;
            height: 100%;
            border-radius: 5px;
            transition: .3s linear all;
        }
        .card-counter:hover {
            box-shadow: 4px 4px 20px rgba(0, 0, 0, 0.2);
            transition: .3s linear all;
        }
        .card-counter.primary {
            background-color: #007bff;
            color: #FFF;
        }
        .card-counter.success {
            background-color: #28a745;
            color: #FFF;
        }
        .card-counter.warning {
            background-color: #ffc107;
            color: #FFF;
        }
        .card-counter.danger {
            background-color: #dc3545;
            color: #FFF;
        }
        .card-counter i {
            font-size: 3em;
            opacity: 0.3;
        }
        .card-counter .count-numbers {
            font-size: 2em;
            display: block;
        }
        .card-counter .count-name {
            font-style: italic;
            text-transform: capitalize;
            opacity: 0.8;
            display: block;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Reports & Analytics</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="admin_dashboard.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Go Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Date range selector -->
                <form method="get" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?= htmlspecialchars($startDate) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?= htmlspecialchars($endDate) ?>" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                        </div>
                    </div>
                </form>

                <!-- Summary cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card-counter primary">
                            <i class="fas fa-file-invoice"></i>
                            <span class="count-numbers"><?= count($financialReport) ?></span>
                            <span class="count-name">Reporting Periods</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-counter success">
                            <i class="fas fa-dollar-sign"></i>
                            <span class="count-numbers">
                                $<?= !empty($financialReport) ? number_format(array_sum(array_column($financialReport, 'total_revenue')), 2) : '0.00' ?>
                            </span>
                            <span class="count-name">Total Revenue</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-counter warning">
                            <i class="fas fa-exclamation-circle"></i>
                            <span class="count-numbers"><?= count($overdueInvoices) ?></span>
                            <span class="count-name">Overdue Invoices</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-counter danger">
                            <i class="fas fa-users"></i>
                            <span class="count-numbers">
                                <?= !empty($financialReport) ? max(array_column($financialReport, 'paying_parents')) : 0 ?>
                            </span>
                            <span class="count-name">Active Paying Parents</span>
                        </div>
                    </div>
                </div>

                <!-- Revenue chart -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Monthly Revenue</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" height="300"></canvas>
                    </div>
                </div>

                <!-- Financial report table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Financial Report</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th class="text-end">Invoices</th>
                                        <th class="text-end">Revenue</th>
                                        <th class="text-end">Paying Parents</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($financialReport as $row): ?>
                                        <tr>
                                            <td><?= date('F Y', strtotime($row['month'] . '-01')) ?></td>
                                            <td class="text-end"><?= $row['invoice_count'] ?></td>
                                            <td class="text-end">$<?= number_format($row['total_revenue'], 2) ?></td>
                                            <td class="text-end"><?= $row['paying_parents'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($financialReport)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No data available for the selected period</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Overdue invoices -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Overdue Invoices</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($overdueInvoices)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Child</th>
                                            <th>Parent</th>
                                            <th class="text-end">Due Date</th>
                                            <th class="text-end">Amount</th>
                                            <th class="text-end">Balance</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($overdueInvoices as $invoice): ?>
                                            <?php
                                            // Safely get values with null coalescing
                                            $grandTotal = $invoice['grand_total'] ?? 0;
                                            $balance = $invoice['balance'] ?? $grandTotal;
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($invoice['invoice_number'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($invoice['child_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($invoice['parent_name'] ?? 'N/A') ?></td>
                                                <td class="text-end"><?= isset($invoice['due_date']) ? date('M j, Y', strtotime($invoice['due_date'])) : 'N/A' ?></td>
                                                <td class="text-end">$<?= number_format($grandTotal, 2) ?></td>
                                                <td class="text-end">$<?= number_format($balance, 2) ?></td>
                                                <td>
                                                    <?php if (isset($invoice['id'])): ?>
                                                        <a href="admin_view_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                        <a href="admin_view_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-envelope"></i> Remind
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success mb-0">
                                No overdue invoices found!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        // Revenue chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [
                    <?php if (!empty($financialReport)): ?>
                        <?= implode(',', array_map(function($row) { 
                            return "'" . date('M Y', strtotime($row['month'] . '-01')) . "'"; 
                        }, $financialReport)) ?>
                    <?php else: ?>
                        ''
                    <?php endif; ?>
                ],
                datasets: [{
                    label: 'Revenue ($)',
                    data: [
                        <?php if (!empty($financialReport)): ?>
                            <?= implode(',', array_column($financialReport, 'total_revenue')) ?>
                        <?php else: ?>
                            0
                        <?php endif; ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.raw.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>