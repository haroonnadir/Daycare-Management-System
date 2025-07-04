<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Get messages from session
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$invoice_id = $_GET['invoice_id'] ?? 0;
$parent_id = $_SESSION['user_id'];

// Verify the invoice belongs to this parent
$sql = "SELECT i.* FROM invoices i
        JOIN parent_child pc ON i.child_id = pc.child_id
        WHERE i.id = ? AND pc.parent_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $invoice_id, $parent_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    header("Location: parent_invoices.php");
    exit();
}

// Get user's saved payment methods
$sql = "SELECT * FROM payment_methods 
        WHERE user_id = ? AND is_active = 1
        ORDER BY is_default DESC, created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$payment_methods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Payment Method - Daycare Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>Select Payment Method</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <h5>Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></h5>
                            <p>Amount Due: $<?= number_format($invoice['total_amount'], 2) ?></p>
                        </div>
                        
                        <form action="process_payment.php" method="POST">
                            <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Select Payment Method:</label>
                                <div class="list-group">
                                    <?php if (empty($payment_methods)): ?>
                                        <div class="alert alert-warning">No payment methods found. Please add one.</div>
                                    <?php else: ?>
                                        <?php foreach ($payment_methods as $method): ?>
                                            <label class="list-group-item">
                                                <input class="form-check-input me-2" type="radio" 
                                                       name="payment_method_id" value="<?= $method['id'] ?>" 
                                                       <?= $method['is_default'] ? 'checked' : '' ?> required>
                                                <div>
                                                    <strong><?= htmlspecialchars($method['nickname']) ?></strong>
                                                    <div class="text-muted">
                                                        <?php if ($method['type'] === 'card'): ?>
                                                            Card: **** **** **** <?= substr($method['account_number'], -4) ?>
                                                            (Exp: <?= $method['expiry_month'] ?>/<?= $method['expiry_year'] ?>)
                                                        <?php elseif ($method['type'] === 'bank'): ?>
                                                            Bank: ****<?= substr($method['account_number'], -4) ?>
                                                            (Routing: ****<?= substr($method['routing_number'], -4) ?>)
                                                        <?php else: ?>
                                                            PayPal: <?= htmlspecialchars($method['account_number']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" <?= empty($payment_methods) ? 'disabled' : '' ?>>
                                    <i class="fas fa-credit-card me-1"></i> Pay Now
                                </button>
                                <a href="parent_add_payment_method.php?invoice_id=<?= $invoice_id ?>" 
                                   class="btn btn-outline-secondary">
                                    <i class="fas fa-plus me-1"></i> Add New Payment Method
                                </a>
                                <a href="parent_invoices.php" class="btn btn-link">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>