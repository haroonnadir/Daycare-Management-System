<?php
session_start();
require '../db_connect.php';

// Check if parent is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

$parent_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get parent's children
$children = [];
$stmt = $conn->prepare("
    SELECT c.id, c.first_name, c.last_name 
    FROM children c
    JOIN parent_child pc ON c.id = pc.child_id
    WHERE pc.parent_id = ?
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $children[] = $row;
}

// Handle delete request
if (isset($_GET['delete'])) {
    $request_id = $_GET['delete'];
    
    // Verify the request belongs to this parent before deleting
    $stmt = $conn->prepare("
        DELETE FROM meal_change_requests 
        WHERE id = ? AND parent_id = ? AND status = 'Pending'
    ");
    $stmt->bind_param("ii", $request_id, $parent_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success = "Request deleted successfully!";
        } else {
            $error = "Request not found or cannot be deleted (may already be processed).";
        }
    } else {
        $error = "Error deleting request: " . $conn->error;
    }
}

// Handle form submission for new requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $child_id = $_POST['child_id'];
    $current_meal = $_POST['current_meal'];
    $requested_meal = $_POST['requested_meal'];
    $reason = $_POST['reason'];
    $change_date = $_POST['change_date'];
    
    // Validate the child belongs to this parent
    $valid_child = false;
    foreach ($children as $child) {
        if ($child['id'] == $child_id) {
            $valid_child = true;
            break;
        }
    }
    
    if ($valid_child) {
        $stmt = $conn->prepare("
            INSERT INTO meal_change_requests 
            (child_id, parent_id, current_meal, requested_meal, reason, change_date, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->bind_param("iissss", $child_id, $parent_id, $current_meal, $requested_meal, $reason, $change_date);
        
        if ($stmt->execute()) {
            $success = "Meal change request submitted successfully!";
        } else {
            $error = "Error submitting request: " . $conn->error;
        }
    } else {
        $error = "Invalid child selected";
    }
}

// Create meal_change_requests table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS meal_change_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        child_id INT NOT NULL,
        parent_id INT NOT NULL,
        current_meal VARCHAR(255) NOT NULL,
        requested_meal VARCHAR(255) NOT NULL,
        reason TEXT NOT NULL,
        change_date DATE NOT NULL,
        status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (child_id) REFERENCES children(id),
        FOREIGN KEY (parent_id) REFERENCES users(id)
    )
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Meal Change</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container { 
            max-width: 900px; 
            margin-top: 30px;
            padding-bottom: 30px;
        }
        .card { 
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .card-header { 
            background-color: #4e73df; 
            color: white; 
            border-radius: 10px 10px 0 0 !important;
            padding: 1.25rem 1.5rem;
        }
        .btn-primary { 
            background-color: #4e73df; 
            border-color: #4e73df;
        }
        .badge-pending { 
            background-color: #fff3cd; 
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .badge-approved { 
            background-color: #d4edda; 
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .badge-rejected { 
            background-color: #f8d7da; 
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .meal-change-details {
            line-height: 1.4;
        }
        .action-btn {
            min-width: 80px;
        }
        .form-control, .form-select {
            border-radius: 6px;
        }
        .alert {
            border-radius: 8px;
        }
        .meal-request-card {
            margin-bottom: 25px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="text-center">Request Meal Change</h3>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="child_id" class="form-label">Child</label>
                        <select class="form-select" id="child_id" name="child_id" required>
                            <option value="">Select your child</option>
                            <?php foreach ($children as $child): ?>
                                <option value="<?= $child['id'] ?>">
                                    <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="change_date" class="form-label">Date for Change</label>
                        <input type="date" class="form-control" id="change_date" name="change_date" 
                               min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_meal" class="form-label">Current Scheduled Meal</label>
                        <input type="text" class="form-control" id="current_meal" name="current_meal" required>
                        <small class="text-muted">What is your child currently scheduled to eat?</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="requested_meal" class="form-label">Requested Meal</label>
                        <input type="text" class="form-control" id="requested_meal" name="requested_meal" required>
                        <small class="text-muted">What would you like your child to eat instead?</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Change</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        <small class="text-muted">Please explain why you're requesting this change (allergies, preferences, etc.)</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                        <a href="parent_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Display existing requests -->
        <?php if (!empty($children)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h4>Your Recent Meal Change Requests</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Child</th>
                                    <th>Date</th>
                                    <th>Request</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("
                                    SELECT r.*, c.first_name, c.last_name 
                                    FROM meal_change_requests r
                                    JOIN children c ON r.child_id = c.id
                                    WHERE r.parent_id = ?
                                    ORDER BY r.change_date DESC
                                    LIMIT 5
                                ");
                                $stmt->bind_param("i", $parent_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                while ($row = $result->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                        <td><?= date('M j, Y', strtotime($row['change_date'])) ?></td>
                                        <td>
                                            <strong>Change from:</strong> <?= htmlspecialchars($row['current_meal']) ?><br>
                                            <strong>To:</strong> <?= htmlspecialchars($row['requested_meal']) ?>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill badge-<?= strtolower($row['status']) ?>">
                                                <?= $row['status'] ?>
                                            </span>
                                            <?php if ($row['admin_notes']): ?>
                                                <br><small>Note: <?= htmlspecialchars($row['admin_notes']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] === 'Pending'): ?>
                                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this request?')">
                                                    Delete
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($result->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No meal change requests found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum date to today
        document.getElementById('change_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>