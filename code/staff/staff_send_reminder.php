<?php
session_start();
require '../db_connect.php';
require_once 'billing_functions.php';

// Redirect if not logged in or not an Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}


// Only allow admin access
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$billingSystem = new BillingSystem($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoiceId = $_POST['invoice_id'] ?? 0;
    $message = $_POST['message'] ?? '';
    $sendCopy = isset($_POST['send_copy']);
    
    try {
        // Get invoice details
        $invoice = $billingSystem->getInvoiceById($invoiceId);
        if (!$invoice) {
            throw new Exception("Invoice not found");
        }

        // Get parent email
        $parentEmail = $invoice['parent_email'];
        
        // Send email reminder
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.example.com'; // Your SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'your_email@example.com';
            $mail->Password   = 'your_password';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            // Recipients
            $mail->setFrom('no-reply@childcarecenter.com', 'Childcare Center');
            $mail->addAddress($parentEmail, $invoice['parent_name']);
            
            if ($sendCopy) {
                $mail->addCC('admin@childcarecenter.com');
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Payment Reminder for Invoice #' . $invoice['invoice_number'];
            
            $emailBody = "<h2>Payment Reminder</h2>
                        <p>Dear " . htmlspecialchars($invoice['parent_name']) . ",</p>
                        <p>This is a friendly reminder that payment for the following invoice is overdue:</p>
                        <ul>
                            <li>Invoice #: " . htmlspecialchars($invoice['invoice_number']) . "</li>
                            <li>Child: " . htmlspecialchars($invoice['child_name']) . "</li>
                            <li>Due Date: " . date('F j, Y', strtotime($invoice['due_date'])) . "</li>
                            <li>Amount Due: $" . number_format($invoice['total_amount'], 2) . "</li>
                        </ul>";
            
            if (!empty($message)) {
                $emailBody .= "<p><strong>Additional Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>";
            }
            
            $emailBody .= "<p>Please make your payment at your earliest convenience.</p>
                          <p>Thank you,<br>Childcare Center</p>";
            
            $mail->Body = $emailBody;
            $mail->AltBody = strip_tags($emailBody);
            
            $mail->send();
            
            // Log the reminder in database
            $stmt = $conn->prepare("INSERT INTO payment_reminders (invoice_id, sent_at, message) VALUES (?, NOW(), ?)");
            $stmt->bind_param("is", $invoiceId, $message);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Reminder sent successfully to " . htmlspecialchars($invoice['parent_name']);
        } catch (Exception $e) {
            throw new Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
        
        header("Location: admin_view_invoice.php?id=" . $invoiceId);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error sending reminder: " . $e->getMessage();
        header("Location: admin_view_invoice.php?id=" . $invoiceId);
        exit();
    }
}

// If not POST request, redirect
header("Location: admin_manage_invoices.php");
exit();