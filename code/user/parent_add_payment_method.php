<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

$parent_id = $_SESSION['user_id'];
$invoice_id = $_GET['invoice_id'] ?? 0; // Optional: if coming from invoice payment

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $nickname = trim($_POST['nickname'] ?? '');
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    try {
        $conn->begin_transaction();
        
        // If setting as default, first unset any existing defaults
        if ($is_default) {
            $stmt = $conn->prepare("UPDATE payment_methods SET is_default = 0 WHERE user_id = ?");
            $stmt->bind_param("i", $parent_id);
            $stmt->execute();
        }
        
        // Handle different payment types
        switch ($type) {
            case 'card':
                $card_number = str_replace(' ', '', $_POST['card_number']);
                $exp_month = $_POST['exp_month'];
                $exp_year = $_POST['exp_year'];
                $cvv = $_POST['cvv'];
                $card_holder = trim($_POST['card_holder']);
                
                // Validate card (basic validation - in production use a payment processor's validation)
                if (!preg_match('/^\d{13,19}$/', $card_number)) {
                    throw new Exception("Invalid card number");
                }
                
                $stmt = $conn->prepare("INSERT INTO payment_methods 
                    (user_id, type, nickname, account_number, expiry_month, expiry_year, 
                    security_code, card_holder_name, is_default, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("isssssssi", 
                    $parent_id, $type, $nickname, $card_number, 
                    $exp_month, $exp_year, $cvv, $card_holder, $is_default);
                break;
                
            case 'bank':
                $account_number = $_POST['account_number'];
                $routing_number = $_POST['routing_number'];
                $account_type = $_POST['account_type'];
                $account_holder = trim($_POST['account_holder']);
                
                // Basic validation
                if (!preg_match('/^\d{8,17}$/', $account_number)) {
                    throw new Exception("Invalid account number");
                }
                
                $stmt = $conn->prepare("INSERT INTO payment_methods 
                    (user_id, type, nickname, account_number, routing_number, 
                    account_type, account_holder_name, is_default, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("issssssi", 
                    $parent_id, $type, $nickname, $account_number, 
                    $routing_number, $account_type, $account_holder, $is_default);
                break;
                
            case 'paypal':
                $email = filter_var($_POST['paypal_email'], FILTER_SANITIZE_EMAIL);
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid PayPal email");
                }
                
                $stmt = $conn->prepare("INSERT INTO payment_methods 
                    (user_id, type, nickname, account_number, is_default, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("isssi", $parent_id, $type, $nickname, $email, $is_default);
                break;
                
            default:
                throw new Exception("Invalid payment type");
        }
        
        $stmt->execute();
        $conn->commit();
        
        $_SESSION['success'] = "Payment method added successfully!";
        
        // Redirect back to payment selection if coming from invoice
        if ($invoice_id) {
            header("Location: parent_select_payment.php?invoice_id=".$invoice_id);
        } else {
            header("Location: parent_select_payment.php");
        }
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error adding payment method: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payment Method - Daycare Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payment-type-tab {
            cursor: pointer;
        }
        .payment-form {
            display: none;
        }
        .payment-form.active {
            display: block;
        }
        .card-icons {
            font-size: 1.5rem;
            color: #6c757d;
        }
    </style>
</head>
<body> 
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add Payment Method</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <h5>Select Payment Type</h5>
                            <div class="row text-center">
                                <div class="col-md-4 payment-type-tab" data-type="card">
                                    <div class="card p-3">
                                        <i class="far fa-credit-card card-icons"></i>
                                        <h6>Credit/Debit Card</h6>
                                    </div>
                                </div>
                                <div class="col-md-4 payment-type-tab" data-type="bank">
                                    <div class="card p-3">
                                        <i class="fas fa-university card-icons"></i>
                                        <h6>Bank Account</h6>
                                    </div>
                                </div>
                                <div class="col-md-4 payment-type-tab" data-type="paypal">
                                    <div class="card p-3">
                                        <i class="fab fa-cc-paypal card-icons"></i>
                                        <h6>PayPal</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form id="paymentForm" method="POST" action="parent_add_payment_method.php<?= $invoice_id ? '?invoice_id='.$invoice_id : '' ?>">
                            <input type="hidden" name="type" id="paymentType" value="">
                            
                            <div class="mb-3">
                                <label for="nickname" class="form-label">Nickname for this payment method</label>
                                <input type="text" class="form-control" id="nickname" name="nickname" required>
                                <div class="form-text">e.g., "My Visa Card", "Primary Checking"</div>
                            </div>
                            
                            <!-- Card Payment Form -->
                            <div id="cardForm" class="payment-form">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="card_number" class="form-label">Card Number</label>
                                        <input type="text" class="form-control" id="card_number" name="card_number" 
                                               placeholder="1234 5678 9012 3456" data-payment="card">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="card_holder" class="form-label">Cardholder Name</label>
                                        <input type="text" class="form-control" id="card_holder" name="card_holder" data-payment="card">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="exp_month" class="form-label">Expiration Month</label>
                                        <select class="form-select" id="exp_month" name="exp_month" data-payment="card">
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>">
                                                    <?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="exp_year" class="form-label">Expiration Year</label>
                                        <select class="form-select" id="exp_year" name="exp_year" data-payment="card">
                                            <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                                <option value="<?= $i ?>"><?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="cvv" class="form-label">Security Code</label>
                                        <input type="text" class="form-control" id="cvv" name="cvv" 
                                               placeholder="123" maxlength="4" data-payment="card">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Bank Payment Form -->
                            <div id="bankForm" class="payment-form">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="account_number" class="form-label">Account Number</label>
                                        <input type="text" class="form-control" id="account_number" name="account_number" data-payment="bank">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="routing_number" class="form-label">Routing Number</label>
                                        <input type="text" class="form-control" id="routing_number" name="routing_number" data-payment="bank">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="account_holder" class="form-label">Account Holder Name</label>
                                        <input type="text" class="form-control" id="account_holder" name="account_holder" data-payment="bank">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="account_type" class="form-label">Account Type</label>
                                        <select class="form-select" id="account_type" name="account_type" data-payment="bank">
                                            <option value="checking">Checking</option>
                                            <option value="savings">Savings</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- PayPal Payment Form -->
                            <div id="paypalForm" class="payment-form">
                                <div class="mb-3">
                                    <label for="paypal_email" class="form-label">PayPal Email</label>
                                    <input type="email" class="form-control" id="paypal_email" name="paypal_email" data-payment="paypal">
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_default" name="is_default">
                                <label class="form-check-label" for="is_default">Set as default payment method</label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Payment Method
                                </button>
                                <?php if ($invoice_id): ?>
                                    <a href="parent_select_payment.php?invoice_id=<?= $invoice_id ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-1"></i> Back to Payment Selection
                                    </a>
                                <?php else: ?>
                                    <a href="parent_payment_methods.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-1"></i> Back to Payment Methods
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle payment type selection
            const typeTabs = document.querySelectorAll('.payment-type-tab');
            const paymentTypeInput = document.getElementById('paymentType');
            const paymentForms = document.querySelectorAll('.payment-form');
            
            typeTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    
                    // Update active tab styling
                    typeTabs.forEach(t => t.querySelector('.card').classList.remove('border-primary'));
                    this.querySelector('.card').classList.add('border-primary');
                    
                    // Set the payment type
                    paymentTypeInput.value = type;
                    
                    // Show the correct form
                    paymentForms.forEach(form => form.classList.remove('active'));
                    document.getElementById(`${type}Form`).classList.add('active');
                    
                    // Set required fields
                    document.querySelectorAll('[data-payment]').forEach(field => {
                        field.required = (field.getAttribute('data-payment') === type);
                    });
                });
            });
            
            // Auto-format card number
            const cardNumberInput = document.getElementById('card_number');
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\s+/g, '');
                    if (value.length > 0) {
                        value = value.match(new RegExp('.{1,4}', 'g')).join(' ');
                    }
                    e.target.value = value;
                });
            }
        });
    </script>
</body>
</html>