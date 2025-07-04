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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_invoice'])) {
        // Handle invoice creation
        $childId = $_POST['child_id'];
        $parentId = $_POST['parent_id'];
        $billingPeriodStart = $_POST['billing_period_start'];
        $billingPeriodEnd = $_POST['billing_period_end'];
        $dueDate = $_POST['due_date'];
        $items = json_decode($_POST['invoice_items'], true);
        
        try {
            $invoiceId = $billingSystem->createInvoice($childId, $parentId, $billingPeriodStart, 
                                                     $billingPeriodEnd, $dueDate, $items);
            $_SESSION['success_message'] = "Invoice created successfully!";
            header("Location: admin_view_invoice.php?id=$invoiceId");
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error creating invoice: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_invoice'])) {
        // Handle invoice deletion
        $invoiceId = $_POST['invoice_id'];
        try {
            $billingSystem->deleteInvoice($invoiceId);
            $_SESSION['success_message'] = "Invoice deleted successfully!";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error deleting invoice: " . $e->getMessage();
        }
    }
}

// Get all invoices with pagination
$page = $_GET['page'] ?? 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$invoices = $billingSystem->getAllInvoices($perPage, $offset);
$totalInvoices = $billingSystem->getTotalInvoicesCount();
$totalPages = ceil($totalInvoices / $perPage);


// Get all parents and children for dropdowns
$parents = $billingSystem->getAllParents();
$children = $billingSystem->getAllChildren();

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Invoices - Childcare Billing System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
                
        /* Style for the "GO BACK DASHBOARD" link */
        .h2 a {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            text-align: center;
            text-decoration: none;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 0.25rem;
            color: #fff;
            background-color: #6c757d; /* Bootstrap's secondary color */
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, 
                        border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .h2 a:hover {
            color: #fff;
            background-color: #5a6268; /* Darker shade for hover */
            text-decoration: none;
        }

        /* Optional: Match the style to your primary button */
        .h2 a.btn-primary {
            background-color: #0d6efd; /* Bootstrap primary blue */
            border-color: #0d6efd;
        }

        .h2 a.btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .invoice-item-row {
            margin-bottom: 10px;
        }
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
                    <h1 class="h2"><a href="admin_dashboard.php">GO BACK DASHBOARD</a></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                            <i class="fas fa-plus"></i> Create Invoice
                        </button>
                    </div>
                </div>

                <!-- Display messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error_message'] ?>
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

    <!-- Create Invoice Modal -->
    <div class="modal fade" id="createInvoiceModal" tabindex="-1" aria-labelledby="createInvoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="invoiceForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createInvoiceModalLabel">Create New Invoice</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="parent_id" class="form-label">Parent</label>
                                <select class="form-select" id="parent_id" name="parent_id" required>
                                    <option value="">Select Parent</option>
                                    <?php foreach ($parents as $parent): ?>
                                        <option value="<?= $parent['id'] ?>">
                                            <?= htmlspecialchars($parent['name']) ?> (<?= htmlspecialchars($parent['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="child_id" class="form-label">Child</label>
                                <select class="form-select" id="child_id" name="child_id" required>
                                    <option value="">Select Child</option>
                                    <?php foreach ($children as $child): ?>
                                        <option value="<?= $child['id'] ?>" data-parent="<?= $child['parent_id'] ?>">
                                            <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="billing_period_start" class="form-label">Billing Period Start</label>
                                <input type="date" class="form-control" id="billing_period_start" name="billing_period_start" required>
                            </div>
                            <div class="col-md-6">
                                <label for="billing_period_end" class="form-label">Billing Period End</label>
                                <input type="date" class="form-control" id="billing_period_end" name="billing_period_end" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Invoice Items</label>
                            <div id="invoiceItemsContainer">
                                <!-- Invoice items will be added here -->
                                <div class="invoice-item-row row g-2">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control item-description" placeholder="Description" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control item-quantity" placeholder="Qty" min="1" value="1" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" step="0.01" class="form-control item-price" placeholder="Price" min="0" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger remove-item-btn w-100">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="addItemBtn" class="btn btn-sm btn-secondary mt-2">
                                <i class="fas fa-plus"></i> Add Item
                            </button>
                            <input type="hidden" name="invoice_items" id="invoice_items">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_invoice" class="btn btn-primary">Create Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date pickers
        flatpickr("#billing_period_start", { dateFormat: "Y-m-d" });
        flatpickr("#billing_period_end", { dateFormat: "Y-m-d" });
        flatpickr("#due_date", { dateFormat: "Y-m-d" });

        // Filter children dropdown based on selected parent
        document.getElementById('parent_id').addEventListener('change', function() {
            const parentId = this.value;
            const childSelect = document.getElementById('child_id');
            
            // Enable all options first
            Array.from(childSelect.options).forEach(option => {
                option.disabled = false;
            });
            
            if (parentId) {
                // Disable options that don't belong to selected parent
                Array.from(childSelect.options).forEach(option => {
                    if (option.value && option.dataset.parent !== parentId) {
                        option.disabled = true;
                    }
                });
                
                // Reset selection if current selection is not valid
                if (childSelect.value && childSelect.options[childSelect.selectedIndex].disabled) {
                    childSelect.value = '';
                }
            }
        });

        // Invoice items management
        document.getElementById('addItemBtn').addEventListener('click', function() {
            const container = document.getElementById('invoiceItemsContainer');
            const newItem = document.createElement('div');
            newItem.className = 'invoice-item-row row g-2 mt-2';
            newItem.innerHTML = `
                <div class="col-md-5">
                    <input type="text" class="form-control item-description" placeholder="Description" required>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control item-quantity" placeholder="Qty" min="1" value="1" required>
                </div>
                <div class="col-md-3">
                    <input type="number" step="0.01" class="form-control item-price" placeholder="Price" min="0" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger remove-item-btn w-100">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newItem);
        });

        // Remove item
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item-btn') || 
                e.target.closest('.remove-item-btn')) {
                const btn = e.target.classList.contains('remove-item-btn') ? 
                    e.target : e.target.closest('.remove-item-btn');
                const itemRow = btn.closest('.invoice-item-row');
                
                // Don't allow removing the last item
                if (document.querySelectorAll('.invoice-item-row').length > 1) {
                    itemRow.remove();
                }
            }
        });

        // Form submission - prepare items data
        document.getElementById('invoiceForm').addEventListener('submit', function(e) {
            const items = [];
            let isValid = true;
            
            document.querySelectorAll('.invoice-item-row').forEach(row => {
                const description = row.querySelector('.item-description').value;
                const quantity = row.querySelector('.item-quantity').value;
                const price = row.querySelector('.item-price').value;
                
                // Validate item
                if (!description || !quantity || !price) {
                    isValid = false;
                    return;
                }
                
                items.push({
                    description: description,
                    quantity: parseFloat(quantity),
                    unit_price: parseFloat(price)
                });
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all item fields');
                return;
            }
            
            if (items.length === 0) {
                e.preventDefault();
                alert('Please add at least one item');
                return;
            }
            
            document.getElementById('invoice_items').value = JSON.stringify(items);
        });
    </script>
</body>
</html>