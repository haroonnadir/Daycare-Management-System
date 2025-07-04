<?php
class BillingSystem {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Add the new updateInvoice method
    public function updateInvoice($invoiceId, $childId, $parentId, $billingPeriodStart, $billingPeriodEnd, $dueDate, $items) {
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // 1. Update invoice header information
            $stmt = $this->conn->prepare("
                UPDATE invoices 
                SET child_id = ?, 
                    parent_id = ?, 
                    billing_period_start = ?, 
                    billing_period_end = ?, 
                    due_date = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "iisssi", 
                $childId, 
                $parentId, 
                $billingPeriodStart, 
                $billingPeriodEnd, 
                $dueDate, 
                $invoiceId
            );
            $stmt->execute();
            $stmt->close();
            
            // 2. Delete existing invoice items
            $stmt = $this->conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
            $stmt->bind_param("i", $invoiceId);
            $stmt->execute();
            $stmt->close();
            
            // 3. Insert new invoice items
            $totalAmount = 0;
            $itemStmt = $this->conn->prepare("
                INSERT INTO invoice_items 
                (invoice_id, description, quantity, unit_price, amount) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($items as $item) {
                $amount = $item['quantity'] * $item['unit_price'];
                $totalAmount += $amount;
                
                $itemStmt->bind_param(
                    "isddd", 
                    $invoiceId, 
                    $item['description'], 
                    $item['quantity'], 
                    $item['unit_price'], 
                    $amount
                );
                $itemStmt->execute();
            }
            $itemStmt->close();
            
            // 4. Update the total amount in the invoice
            $stmt = $this->conn->prepare("
                UPDATE invoices 
                SET total_amount = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("di", $totalAmount, $invoiceId);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $this->conn->commit();
            
            return true;
        } catch (Exception $e) {
            // Rollback on error
            $this->conn->rollback();
            throw $e;
        }
    }

    // All your existing methods remain unchanged below
    public function getInvoiceById($invoiceId) {
        $query = "SELECT i.*, 
                         CONCAT(c.first_name, ' ', c.last_name) AS child_name,
                         u.name AS parent_name, u.email AS parent_email,
                         (SELECT SUM(amount) FROM payments WHERE invoice_id = i.id) AS paid_amount
                  FROM invoices i
                  JOIN children c ON i.child_id = c.id
                  JOIN users u ON i.parent_id = u.id
                  WHERE i.id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("i", $invoiceId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error fetching invoice: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function getInvoiceItems($invoiceId) {
        $query = "SELECT * FROM invoice_items WHERE invoice_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("i", $invoiceId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error fetching invoice items: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $items = [];
        
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        return $items;
    }
    
    public function getInvoicePayments($invoiceId) {
        $query = "SELECT p.*, u.name AS processed_by_name 
                  FROM payments p
                  LEFT JOIN users u ON p.processed_by = u.id
                  WHERE p.invoice_id = ?
                  ORDER BY p.payment_date DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("i", $invoiceId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error fetching payments: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $payments = [];
        
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        
        return $payments;
    }
    
    public function getTotalPaid($invoiceId) {
        $query = "SELECT COALESCE(SUM(amount), 0) AS total_paid 
                  FROM payments 
                  WHERE invoice_id = ? AND status = 'completed'";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("i", $invoiceId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error calculating total paid: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['total_paid'];
    }
    
    public function generateFinancialReport($startDate, $endDate) {
        $report = [];
        
        $query = "SELECT 
                    DATE_FORMAT(i.created_at, '%Y-%m') AS month,
                    COUNT(*) AS invoice_count,
                    SUM(i.total_amount) AS total_revenue,
                    COUNT(DISTINCT i.parent_id) AS paying_parents
                  FROM invoices i
                  WHERE i.created_at BETWEEN ? AND ?
                  GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
                  ORDER BY month";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("ss", $startDate, $endDate);
        
        if (!$stmt->execute()) {
            throw new Exception("Error generating financial report: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        
        $stmt->close();
        return $report;
    }
    
    public function getOverdueInvoices() {
        $overdueInvoices = [];
        $currentDate = date('Y-m-d');
        
        $query = "SELECT i.id, i.invoice_number, i.due_date, i.total_amount, 
                         (i.total_amount - IFNULL(SUM(p.amount), 0)) AS balance,
                         CONCAT(c.first_name, ' ', c.last_name) AS child_name,
                         u.name AS parent_name
                  FROM invoices i
                  JOIN children c ON i.child_id = c.id
                  JOIN users u ON i.parent_id = u.id AND u.role = 'parent'
                  LEFT JOIN payments p ON i.id = p.invoice_id
                  WHERE i.due_date < ? 
                  GROUP BY i.id
                  HAVING balance > 0";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("s", $currentDate);
        
        if (!$stmt->execute()) {
            throw new Exception("Error fetching overdue invoices: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $overdueInvoices[] = $row;
        }
        
        $stmt->close();
        return $overdueInvoices;
    }
    
    public function getPendingPaymentsCount() {
        $query = "SELECT COUNT(*) AS count FROM payments WHERE status = 'pending'";
        $result = $this->conn->query($query);
        
        if (!$result) {
            throw new Exception("Error getting pending payments count: " . $this->conn->error);
        }
        
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    
    public function getAllParents() {
        $parents = [];
        $query = "SELECT id, name, email, phone FROM users WHERE role = 'parent'";
        $result = $this->conn->query($query);
        
        if (!$result) {
            throw new Exception("Error getting parents: " . $this->conn->error);
        }
        
        while ($row = $result->fetch_assoc()) {
            $parents[] = $row;
        }
        
        return $parents;
    }
    
    public function getChildrenByParent($parentId) {
        if (!is_numeric($parentId)) {
            throw new InvalidArgumentException("Parent ID must be numeric");
        }
        
        $children = [];
        $query = "SELECT c.id, CONCAT(c.first_name, ' ', c.last_name) AS name, c.date_of_birth 
                  FROM children c
                  JOIN parent_child pc ON c.id = pc.child_id
                  WHERE pc.parent_id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("i", $parentId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error fetching children: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $children[] = $row;
        }
        
        $stmt->close();
        return $children;
    }
    
    public function getAllInvoices($limit = 10, $offset = 0) {
        $query = "SELECT i.*, 
                         CONCAT(c.first_name, ' ', c.last_name) AS child_name,
                         u.name AS parent_name,
                         (SELECT SUM(amount) FROM payments WHERE invoice_id = i.id) AS paid_amount
                  FROM invoices i
                  JOIN children c ON i.child_id = c.id
                  JOIN users u ON i.parent_id = u.id
                  ORDER BY i.created_at DESC
                  LIMIT ? OFFSET ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getTotalInvoicesCount() {
        $result = $this->conn->query("SELECT COUNT(*) AS count FROM invoices");
        return $result->fetch_assoc()['count'];
    }
    
    public function createInvoice($childId, $parentId, $startDate, $endDate, $dueDate, $items) {
        // Generate invoice number
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Calculate total amount
        $totalAmount = array_reduce($items, function($sum, $item) {
            return $sum + ($item['quantity'] * $item['unit_price']);
        }, 0);
        
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Create invoice
            $query = "INSERT INTO invoices (invoice_number, child_id, parent_id, 
                      billing_period_start, billing_period_end, due_date, 
                      total_amount, status, created_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("siisssd", $invoiceNumber, $childId, $parentId, 
                             $startDate, $endDate, $dueDate, $totalAmount);
            $stmt->execute();
            $invoiceId = $this->conn->insert_id;
            
            // Add invoice items
            $itemQuery = "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, amount)
                          VALUES (?, ?, ?, ?, ?)";
            $itemStmt = $this->conn->prepare($itemQuery);
            
            foreach ($items as $item) {
                $amount = $item['quantity'] * $item['unit_price'];
                $itemStmt->bind_param("isddd", $invoiceId, $item['description'], 
                                     $item['quantity'], $item['unit_price'], $amount);
                $itemStmt->execute();
            }
            
            $this->conn->commit();
            return $invoiceId;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    public function deleteInvoice($invoiceId) {
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Delete payments first (if any)
            $this->conn->query("DELETE FROM payments WHERE invoice_id = $invoiceId");
            
            // Delete invoice items
            $this->conn->query("DELETE FROM invoice_items WHERE invoice_id = $invoiceId");
            
            // Delete invoice
            $this->conn->query("DELETE FROM invoices WHERE id = $invoiceId");
            
            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    public function getAllChildren() {
        $query = "SELECT c.id, c.first_name, c.last_name, pc.parent_id 
                  FROM children c
                  JOIN parent_child pc ON c.id = pc.child_id";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
        public function updateLastReminder($invoiceId) {
        $sql = "UPDATE invoices SET last_reminder_sent = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $invoiceId);
        $stmt->execute();
    }
}