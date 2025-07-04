<?php
// parent_process_payment.php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

$parent_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = $_POST['invoice_id'] ?? 0;
    $payment_method_id = $_POST['payment_method_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // 1. Get payment method details
        $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $payment_method_id, $parent_id);
        $stmt->execute();
        $payment_method = $stmt->get_result()->fetch_assoc();
        
        if (!$payment_method) {
            throw new Exception("Invalid payment method selected");
        }
        
        // 2. Verify invoice exists and belongs to parent
        $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ? AND parent_id = ?");
        $stmt->bind_param("ii", $invoice_id, $parent_id);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        
        if (!$invoice) {
            throw new Exception("Invoice not found");
        }
        
        // 3. Process payment (simulated - in real app, connect to payment gateway)
        $transaction_id = 'TXN-' . uniqid();
        $receipt_number = 'RCPT-' . date('Ymd') . '-' . str_pad($invoice_id, 6, '0', STR_PAD_LEFT);
        
        // 4. Record payment
        $stmt = $conn->prepare("INSERT INTO payments (
            invoice_id, 
            parent_id, 
            amount, 
            payment_method, 
            status, 
            transaction_id, 
            receipt_number, 
            payment_method_id
        ) VALUES (?, ?, ?, ?, 'Completed', ?, ?, ?)");
        
        $stmt->bind_param(
            "iidsssi", 
            $invoice_id, 
            $parent_id, 
            $amount, 
            $payment_method['nickname'], 
            $transaction_id, 
            $receipt_number, 
            $payment_method_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to record payment: " . $stmt->error);
        }
        
        // 5. Update invoice status
        $stmt = $conn->prepare("UPDATE invoices SET status = 'Paid' WHERE id = ?");
        $stmt->bind_param("i", $invoice_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Payment processed successfully! Receipt #: $receipt_number";
        header("Location: parent_invoices.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Payment failed: " . $e->getMessage();
        header("Location: parent_select_payment.php?invoice_id=" . $invoice_id);
        exit();
    }
}