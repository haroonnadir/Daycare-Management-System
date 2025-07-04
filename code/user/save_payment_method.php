<?php
session_start();
require_once '../db_connect.php';

// Validate user is logged in and is a parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied. You must be a parent to perform this action.");
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error_message'] = "Invalid form submission";
    header("Location: parent_payments.php");
    exit();
}

// Get parent ID
$parent_id = $_SESSION['user_id'];

// Validate and sanitize input data
$payment_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$nickname = filter_input(INPUT_POST, 'nickname', FILTER_SANITIZE_STRING);
$is_default = isset($_POST['set_as_default']) ? 1 : 0;

try {
    // Start transaction
    $conn->begin_transaction();

    // If setting as default, first unset any existing default
    if ($is_default) {
        $stmt = $conn->prepare("
            UPDATE payment_methods 
            SET is_default = 0 
            WHERE user_id = ? AND is_default = 1
        ");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
    }

    // Handle different payment types
    switch ($payment_type) {
        case 'card':
            // Validate card data
            $card_number = str_replace(' ', '', filter_input(INPUT_POST, 'card_number', FILTER_SANITIZE_STRING));
            $expiry_month = filter_input(INPUT_POST, 'expiry_month', FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 12]
            ]);
            $expiry_year = filter_input(INPUT_POST, 'expiry_year', FILTER_VALIDATE_INT, [
                'options' => ['min_range' => date('Y'), 'max_range' => date('Y') + 20]
            ]);
            $cvv = filter_input(INPUT_POST, 'cvv', FILTER_SANITIZE_STRING);
            $card_holder_name = filter_input(INPUT_POST, 'card_holder_name', FILTER_SANITIZE_STRING);
            $billing_address = filter_input(INPUT_POST, 'billing_address', FILTER_SANITIZE_STRING);

            if (!$card_number || !$expiry_month || !$expiry_year || !$cvv || !$card_holder_name) {
                throw new Exception("Invalid card data provided");
            }

            // In a real application, you would tokenize the card with a payment processor
            // Here we're just storing a masked version for demo purposes
            $last_four = substr($card_number, -4);
            $account_display = "**** **** **** " . $last_four;

            $stmt = $conn->prepare("
                INSERT INTO payment_methods 
                (user_id, type, nickname, account_number, account_display, 
                 expiry_month, expiry_year, card_holder_name, billing_address, 
                 is_default, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->bind_param(
                "issssisssi", 
                $parent_id, 
                $payment_type, 
                $nickname, 
                $last_four,  // In real app, store token here
                $account_display,
                $expiry_month,
                $expiry_year,
                $card_holder_name,
                $billing_address,
                $is_default
            );
            break;

        case 'bank':
            // Validate bank data
            $account_holder_name = filter_input(INPUT_POST, 'account_holder_name', FILTER_SANITIZE_STRING);
            $routing_number = filter_input(INPUT_POST, 'routing_number', FILTER_SANITIZE_STRING);
            $account_number = filter_input(INPUT_POST, 'account_number', FILTER_SANITIZE_STRING);
            $account_type = filter_input(INPUT_POST, 'account_type', FILTER_SANITIZE_STRING);

            if (!$account_holder_name || !$routing_number || !$account_number) {
                throw new Exception("Invalid bank account data provided");
            }

            // Mask account number for display
            $last_four = substr($account_number, -4);
            $account_display = "****" . $last_four;

            $stmt = $conn->prepare("
                INSERT INTO payment_methods 
                (user_id, type, nickname, account_number, account_display, 
                 routing_number, account_type, account_holder_name, 
                 is_default, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->bind_param(
                "issssssi", 
                $parent_id, 
                $payment_type, 
                $nickname, 
                $last_four,  // In real app, store token here
                $account_display,
                $routing_number,
                $account_type,
                $account_holder_name,
                $is_default
            );
            break;

        case 'paypal':
            // Validate PayPal data
            $paypal_email = filter_input(INPUT_POST, 'paypal_email', FILTER_VALIDATE_EMAIL);
            if (!$paypal_email) {
                throw new Exception("Invalid PayPal email address");
            }

            $account_display = $paypal_email;

            $stmt = $conn->prepare("
                INSERT INTO payment_methods 
                (user_id, type, nickname, account_number, account_display, 
                 is_default, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->bind_param(
                "isssi", 
                $parent_id, 
                $payment_type, 
                $nickname, 
                $paypal_email,
                $account_display,
                $is_default
            );
            break;

        default:
            throw new Exception("Invalid payment method type");
    }

    // Execute the prepared statement
    if (!$stmt->execute()) {
        throw new Exception("Failed to save payment method: " . $stmt->error);
    }

    // Commit transaction
    $conn->commit();

    $_SESSION['success_message'] = "Payment method added successfully!";
    header("Location: parent_payments.php");
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }

    error_log("Payment method save error: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to save payment method: " . $e->getMessage();
    header("Location: parent_payments.php");
    exit();
}