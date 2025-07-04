<?php
require '../db_connect.php';
require_once 'billing_functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$billingSystem = new BillingSystem($pdo);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $invoiceId = $_POST['invoice_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $paymentMethod = $_POST['payment_method'] ?? '';
    $transactionId = $_POST['transaction_id'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    try {
        $success = $billingSystem->recordPayment(
            $invoiceId,
            $amount,
            $paymentMethod,
            $transactionId,
            $_SESSION['user_id']
        );
        
        if ($success) {
            $_SESSION['success_message'] = "Payment recorded successfully";
            header("Location: admin_view_invoice.php?id=$invoiceId");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error recording payment: " . $e->getMessage();
        header("Location: admin_view_invoice.php?id=$invoiceId");
        exit;
    }
}

header('Location: admin_manage_invoices.php');
exit;
?>