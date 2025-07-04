<?php
require 'auth.php';
require 'db.php';

// Admin authentication
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// Configuration
$currentMonth = date('Y-m');
$billingPeriodStart = date('Y-m-01');
$billingPeriodEnd = date('Y-m-t');
$issueDate = date('Y-m-d');
$dueDate = date('Y-m-d', strtotime('+15 days'));

// Get all active children with billing rates
$children = $db->query("
    SELECT c.id as child_id, c.first_name, c.last_name, 
           u.id as parent_id, u.name as parent_name, u.email as parent_email,
           br.id as rate_id, br.rate_name, br.daily_rate, br.monthly_rate
    FROM children c
    JOIN users u ON c.parent_id = u.id
    JOIN child_billing cb ON c.id = cb.child_id
    JOIN billing_rates br ON cb.rate_id = br.id
    WHERE c.active = 1
    AND (cb.end_date IS NULL OR cb.end_date >= '$billingPeriodStart')
    AND (cb.start_date <= '$billingPeriodEnd')
")->fetchAll();

// Process each child
foreach ($children as $child) {
    // Calculate attendance days for the month
    $attendanceDays = $db->query("
        SELECT COUNT(*) as days
        FROM attendance 
        WHERE child_id = {$child['child_id']}
        AND date BETWEEN '$billingPeriodStart' AND '$billingPeriodEnd'
        AND status = 'Present'
    ")->fetchColumn();
    
    // Calculate amount based on rate type
    if ($child['monthly_rate'] !== null) {
        $subtotal = $child['monthly_rate'];
        $rateType = 'Monthly';
    } else {
        $subtotal = $attendanceDays * $child['daily_rate'];
        $rateType = 'Daily';
    }
    
    // Generate invoice number
    $invoiceNumber = 'INV-' . date('Ym') . '-' . str_pad($child['child_id'], 4, '0', STR_PAD_LEFT);
    
    // Check if invoice already exists
    $existingInvoice = $db->query("
        SELECT id FROM invoices 
        WHERE invoice_number = '$invoiceNumber'
    ")->fetch();
    
    if (!$existingInvoice) {
        // Create invoice
        $stmt = $db->prepare("
            INSERT INTO invoices (
                invoice_number, parent_id, child_id, billing_period_start, 
                billing_period_end, issue_date, due_date, subtotal, 
                total_amount, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $invoiceNumber,
            $child['parent_id'],
            $child['child_id'],
            $billingPeriodStart,
            $billingPeriodEnd,
            $issueDate,
            $dueDate,
            $subtotal,
            $subtotal, // Assuming no tax/discount for now
            'draft',
            $_SESSION['user_id']
        ]);
        
        $invoiceId = $db->lastInsertId();
        
        // Add invoice items
        if ($rateType === 'Daily') {
            $stmt = $db->prepare("
                INSERT INTO invoice_items (
                    invoice_id, description, date, quantity, 
                    unit_price, amount
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $invoiceId,
                'Daily rate for ' . date('F Y', strtotime($billingPeriodStart)),
                null,
                $attendanceDays,
                $child['daily_rate'],
                $subtotal
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO invoice_items (
                    invoice_id, description, date, quantity, 
                    unit_price, amount
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $invoiceId,
                'Monthly rate for ' . date('F Y', strtotime($billingPeriodStart)),
                null,
                1,
                $child['monthly_rate'],
                $subtotal
            ]);
        }
        
        // In a real system, you would also:
        // 1. Apply any discounts
        // 2. Calculate taxes
        // 3. Send invoice to parent
        // 4. Log the generation
    }
}

echo "Invoice generation completed for " . date('F Y') . ". " . count($children) . " invoices processed.";
?>