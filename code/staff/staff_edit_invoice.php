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

// Get invoice ID from URL
$invoiceId = $_GET['id'] ?? null;
if (!$invoiceId || !is_numeric($invoiceId)) {
    $_SESSION['error_message'] = "Invalid invoice ID";
    header('Location: admin_manage_invoices.php');
    exit;
}

// Get invoice details
try {
    $invoice = $billingSystem->getInvoiceById($invoiceId);
    if (!$invoice) {
        $_SESSION['error_message'] = "Invoice not found";
        header('Location: admin_manage_invoices.php');
        exit;
    }
    
    // Get invoice items
    $invoiceItems = $billingSystem->getInvoiceItems($invoiceId);
    
    // Get all parents and children for dropdowns
    $parents = $billingSystem->getAllParents();
    $children = $billingSystem->getChildrenByParent($invoice['parent_id']);
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error loading invoice: " . $e->getMessage();
    header('Location: admin_manage_invoices.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_invoice'])) {
    $childId = $_POST['child_id'];
    $parentId = $_POST['parent_id'];
    $billingPeriodStart = $_POST['billing_period_start'];
    $billingPeriodEnd = $_POST['billing_period_end'];
    $dueDate = $_POST['due_date'];
    $items = json_decode($_POST['invoice_items'], true);
    
    try {
        $billingSystem->updateInvoice(
            $invoiceId,
            $childId,
            $parentId,
            $billingPeriodStart,
            $billingPeriodEnd,
            $dueDate,
            $items
        );
        
        $_SESSION['success_message'] = "Invoice updated successfully!";
        header("Location: admin_view_invoice.php?id=$invoiceId");
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating invoice: " . $e->getMessage();
        header("Location: admin_edit_invoice.php?id=$invoiceId");
        exit;
    }
}

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice - Childcare Billing System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .invoice-item-row {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="staff_dashboard.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Go Back to Dashboard
                        </a>
                    </div>
                </div>
                <!-- Display messages -->
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Edit Invoice Form -->
                <form method="POST" id="invoiceForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="parent_id" class="form-label">Parent</label>
                            <select class="form-select" id="parent_id" name="parent_id" required>
                                <option value="">Select Parent</option>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?= $parent['id'] ?>" <?= $parent['id'] == $invoice['parent_id'] ? 'selected' : '' ?>>
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
                                    <option value="<?= $child['id'] ?>" <?= $child['id'] == $invoice['child_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($child['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="billing_period_start" class="form-label">Billing Period Start</label>
                            <input type="date" class="form-control" id="billing_period_start" name="billing_period_start" 
                                   value="<?= htmlspecialchars($invoice['billing_period_start']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="billing_period_end" class="form-label">Billing Period End</label>
                            <input type="date" class="form-control" id="billing_period_end" name="billing_period_end" 
                                   value="<?= htmlspecialchars($invoice['billing_period_end']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                   value="<?= htmlspecialchars($invoice['due_date']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Invoice Items</label>
                        <div id="invoiceItemsContainer">
                            <!-- Invoice items will be added here -->
                            <?php foreach ($invoiceItems as $item): ?>
                                <div class="invoice-item-row row g-2">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control item-description" 
                                               placeholder="Description" value="<?= htmlspecialchars($item['description']) ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control item-quantity" 
                                               placeholder="Qty" min="1" value="<?= htmlspecialchars($item['quantity']) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" step="0.01" class="form-control item-price" 
                                               placeholder="Price" min="0" value="<?= htmlspecialchars($item['unit_price']) ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger remove-item-btn w-100">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="addItemBtn" class="btn btn-sm btn-secondary mt-2">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                        <input type="hidden" name="invoice_items" id="invoice_items">
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="admin_manage_invoices.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" name="update_invoice" class="btn btn-primary">Update Invoice</button>
                    </div>
                </form>
            </main>
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
            if (!parentId) return;
            
            // AJAX request to get children for selected parent
            fetch(`get_children.php?parent_id=${parentId}`)
                .then(response => response.json())
                .then(children => {
                    const childSelect = document.getElementById('child_id');
                    childSelect.innerHTML = '<option value="">Select Child</option>';
                    
                    children.forEach(child => {
                        const option = document.createElement('option');
                        option.value = child.id;
                        option.textContent = child.name;
                        childSelect.appendChild(option);
                    });
                });
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