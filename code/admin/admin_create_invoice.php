<?php
session_start();
require '../db_connect.php';
require_once 'billing_functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$billingSystem = new BillingSystem($pdo);

// Get children and parents for dropdowns
$children = $pdo->query("SELECT c.id, CONCAT(c.first_name, ' ', c.last_name) AS name, 
                         CONCAT(p.first_name, ' ', p.last_name) AS parent_name, p.id AS parent_id
                         FROM children c
                         JOIN users p ON c.parent_id = p.id
                         ORDER BY c.last_name, c.first_name")->fetchAll(PDO::FETCH_ASSOC);

$billingTypes = ['hourly', 'daily', 'weekly', 'monthly'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $data = [
            'child_id' => $_POST['child_id'],
            'parent_id' => $_POST['parent_id'],
            'issue_date' => $_POST['issue_date'],
            'due_date' => $_POST['due_date'],
            'billing_period_start' => $_POST['billing_period_start'],
            'billing_period_end' => $_POST['billing_period_end'],
            'billing_type' => $_POST['billing_type'],
            'total_amount' => $_POST['total_amount'],
            'tax_amount' => $_POST['tax_amount'] ?? 0,
            'discount_amount' => $_POST['discount_amount'] ?? 0,
            'grand_total' => $_POST['grand_total'],
            'status' => $_POST['status'],
            'notes' => $_POST['notes'] ?? null,
            'created_by' => $_SESSION['user_id'],
            'items' => []
        ];
        
        // Add items
        foreach ($_POST['item_description'] as $index => $description) {
            if (!empty($description)) {
                $data['items'][] = [
                    'description' => $description,
                    'quantity' => $_POST['item_quantity'][$index],
                    'unit_price' => $_POST['item_unit_price'][$index],
                    'amount' => $_POST['item_amount'][$index]
                ];
            }
        }
        
        $invoiceId = $billingSystem->createInvoice($data);
        
        // Redirect to view invoice
        header("Location: admin_view_invoice.php?id=$invoiceId");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get pending payments count for badge
$pendingPayments = $billingSystem->getPendingPaymentsCount();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice - Childcare Billing System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .item-row { margin-bottom: 15px; }
        .item-actions { padding-top: 30px; }
        .total-section { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
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
                            <a class="nav-link text-white" href="admin_manage_payments.php">
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
                    <h1 class="h2">Create New Invoice</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="admin_manage_invoices.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Invoices
                        </a>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" id="invoiceForm">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Invoice Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="child_id" class="form-label">Child</label>
                                        <select class="form-select" id="child_id" name="child_id" required>
                                            <option value="">Select Child</option>
                                            <?php foreach ($children as $child): ?>
                                                <option value="<?= $child['id'] ?>" data-parent="<?= $child['parent_id'] ?>">
                                                    <?= htmlspecialchars($child['name']) ?> (Parent: <?= htmlspecialchars($child['parent_name']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <input type="hidden" id="parent_id" name="parent_id">
                                    
                                    <div class="mb-3">
                                        <label for="billing_type" class="form-label">Billing Type</label>
                                        <select class="form-select" id="billing_type" name="billing_type" required>
                                            <option value="">Select Type</option>
                                            <?php foreach ($billingTypes as $type): ?>
                                                <option value="<?= $type ?>"><?= ucfirst($type) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="issue_date" class="form-label">Issue Date</label>
                                            <input type="date" class="form-control" id="issue_date" name="issue_date" 
                                                   value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="due_date" class="form-label">Due Date</label>
                                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                                   value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="billing_period_start" class="form-label">Billing Period Start</label>
                                            <input type="date" class="form-control" id="billing_period_start" 
                                                   name="billing_period_start" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="billing_period_end" class="form-label">Billing Period End</label>
                                            <input type="date" class="form-control" id="billing_period_end" 
                                                   name="billing_period_end" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="draft" selected>Draft</option>
                                            <option value="sent">Sent</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Invoice Items</h5>
                                    <button type="button" id="addItem" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Add Item
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div id="itemsContainer">
                                        <!-- Items will be added here dynamically -->
                                        <div class="row item-row">
                                            <div class="col-md-5">
                                                <input type="text" class="form-control" name="item_description[]" placeholder="Description" required>
                                            </div>
                                            <div class="col-md-2">
                                                <input type="number" class="form-control item-quantity" name="item_quantity[]" placeholder="Qty" min="0" step="0.01" required>
                                            </div>
                                            <div class="col-md-2">
                                                <input type="number" class="form-control item-unit-price" name="item_unit_price[]" placeholder="Unit Price" min="0" step="0.01" required>
                                            </div>
                                            <div class="col-md-2">
                                                <input type="number" class="form-control item-amount" name="item_amount[]" placeholder="Amount" min="0" step="0.01" readonly required>
                                            </div>
                                            <div class="col-md-1 item-actions">
                                                <button type="button" class="btn btn-sm btn-danger remove-item" disabled>
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="total-section">
                                        <div class="row mb-2">
                                            <div class="col-md-8 text-end">
                                                <strong>Subtotal:</strong>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="number" class="form-control" id="total_amount" name="total_amount" value="0" readonly>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-2">
                                            <div class="col-md-8 text-end">
                                                <strong>Tax:</strong>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="number" class="form-control" id="tax_amount" name="tax_amount" value="0" min="0" step="0.01">
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-2">
                                            <div class="col-md-8 text-end">
                                                <strong>Discount:</strong>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="number" class="form-control" id="discount_amount" name="discount_amount" value="0" min="0" step="0.01">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-8 text-end">
                                                <strong>Grand Total:</strong>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="number" class="form-control" id="grand_total" name="grand_total" value="0" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Save Invoice
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set parent ID when child is selected
        document.getElementById('child_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            document.getElementById('parent_id').value = selectedOption.dataset.parent;
        });
        
        // Add item row
        document.getElementById('addItem').addEventListener('click', function() {
            const newRow = document.createElement('div');
            newRow.className = 'row item-row';
            newRow.innerHTML = `
                <div class="col-md-5">
                    <input type="text" class="form-control" name="item_description[]" placeholder="Description" required>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control item-quantity" name="item_quantity[]" placeholder="Qty" min="0" step="0.01" required>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control item-unit-price" name="item_unit_price[]" placeholder="Unit Price" min="0" step="0.01" required>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control item-amount" name="item_amount[]" placeholder="Amount" min="0" step="0.01" readonly required>
                </div>
                <div class="col-md-1 item-actions">
                    <button type="button" class="btn btn-sm btn-danger remove-item">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.getElementById('itemsContainer').appendChild(newRow);
            
            // Enable remove buttons when there are multiple items
            if (document.querySelectorAll('.item-row').length > 1) {
                document.querySelectorAll('.remove-item').forEach(btn => btn.disabled = false);
            }
        });
        
        // Remove item row
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item')) {
                const itemRow = e.target.closest('.item-row');
                itemRow.remove();
                
                // Disable remove button if only one item left
                if (document.querySelectorAll('.item-row').length <= 1) {
                    document.querySelector('.remove-item').disabled = true;
                }
                
                calculateTotals();
            }
        });
        
        // Calculate item amount when quantity or price changes
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('item-quantity') || e.target.classList.contains('item-unit-price')) {
                const row = e.target.closest('.item-row');
                const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
                const unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
                const amount = quantity * unitPrice;
                row.querySelector('.item-amount').value = amount.toFixed(2);
                calculateTotals();
            }
            
            // Calculate grand total when tax or discount changes
            if (e.target.id === 'tax_amount' || e.target.id === 'discount_amount') {
                calculateTotals();
            }
        });
        
        // Calculate all totals
        function calculateTotals() {
            let subtotal = 0;
            document.querySelectorAll('.item-amount').forEach(input => {
                subtotal += parseFloat(input.value) || 0;
            });
            
            document.getElementById('total_amount').value = subtotal.toFixed(2);
            
            const tax = parseFloat(document.getElementById('tax_amount').value) || 0;
            const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
            const grandTotal = subtotal + tax - discount;
            
            document.getElementById('grand_total').value = grandTotal.toFixed(2);
        }
        
        // Set default billing period to current month
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        document.getElementById('billing_period_start').valueAsDate = firstDay;
        document.getElementById('billing_period_end').valueAsDate = lastDay;
        
        // Initialize calculations
        calculateTotals();
    </script>
</body>
</html>