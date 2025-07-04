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

$paymentId = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? '';

// Validate action
$validActions = ['complete', 'fail', 'refund'];
if (!in_array($action, $validActions)) {
    header('Location: admin_manage_payments.php');
    exit;
}

// Get payment details
$payment = $pdo->query("SELECT * FROM payments WHERE id = $paymentId")->fetch(PDO::FETCH_ASSOC);
if (!$payment) {
    header('Location: admin_manage_payments.php');
    exit;
}

// Process action
try {
    switch ($action) {
        case 'complete':
            $status = 'completed';
            break;
        case 'fail':
            $status = 'failed';
            break;
        case 'refund':
            $status = 'refunded';
            break;
    }
    
    $sql = "UPDATE payments SET status = :status, processed_by = :processed_by WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $status,
        ':processed_by' => $_SESSION['user_id'],
        ':id' => $paymentId
    ]);
    
    // Update invoice status if needed
    if ($action == 'complete' || $action == 'refund') {
        $invoice = $billingSystem->getInvoiceById($payment['invoice_id']);
        $paidAmount = $billingSystem->getTotalPaid($payment['invoice_id']);
        
        $newStatus = ($paidAmount >= $invoice['grand_total']) ? 'paid' : $invoice['status'];
        if ($newStatus != $invoice['status']) {
            $sql = "UPDATE invoices SET status = :status WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':status' => $newStatus, ':id' => $payment['invoice_id']]);
        }
    }
    
    $_SESSION['success_message'] = "Payment marked as " . ucfirst($status);
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error processing payment: " . $e->getMessage();
}

header("Location: admin_manage_payments.php");
exit;
?>