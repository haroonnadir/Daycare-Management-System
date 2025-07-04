<?php
session_start();
require '../db_connect.php';

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Process approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reject_request'])) {
        $request_id = (int)$_POST['request_id'];
        $rejection_reason = trim($_POST['rejection_reason']);
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update the request status and add admin notes
            $update_stmt = $conn->prepare("UPDATE meal_change_requests SET status = 'Rejected', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("sii", $rejection_reason, $_SESSION['user_id'], $request_id);
            $update_stmt->execute();
            
            $conn->commit();
            $_SESSION['success'] = "Request has been successfully rejected";
            header("Location: admin_view_parents_request.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error rejecting request: " . $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // First get the request details
        $stmt = $conn->prepare("SELECT * FROM meal_change_requests WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        
        if (!$request) {
            throw new Exception("Request not found");
        }
        
        // Update the meal plan for the child on the specified date
        $update_meal = $conn->prepare("INSERT INTO meal_plans (child_id, meal_date, meal) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE meal = ?");
        $update_meal->bind_param("isss", $request['child_id'], $request['change_date'], $request['requested_meal'], $request['requested_meal']);
        $update_meal->execute();
        
        // Update the request status to Approved
        $update_status = $conn->prepare("UPDATE meal_change_requests SET status = 'Approved', admin_notes = 'Approved by admin', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $update_status->bind_param("ii", $_SESSION['user_id'], $request_id);
        $update_status->execute();
        
        $conn->commit();
        $_SESSION['success'] = "Request has been successfully approved and meal plan updated";
        header("Location: admin_view_parents_request.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error approving request: " . $e->getMessage();
        header("Location: admin_view_parents_request.php");
        exit();
    }
}

// Get filter parameters
$status = $_GET['status'] ?? 'All';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$parent_name = $_GET['parent_name'] ?? '';

// Prepare base query
$query = "
    SELECT mcr.*, 
           c.first_name AS child_first_name, 
           c.last_name AS child_last_name,
           u.name AS parent_name,
           u.email AS parent_email
    FROM meal_change_requests mcr
    JOIN children c ON mcr.child_id = c.id
    JOIN users u ON mcr.parent_id = u.id
    WHERE (? = 'All' OR mcr.status = ?)
    AND mcr.change_date BETWEEN ? AND ?
    AND (u.name LIKE CONCAT('%', ?, '%') OR ? = '')
    ORDER BY mcr.change_date DESC, mcr.status
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

$empty_param = '';
$stmt->bind_param("ssssss", $status, $status, $start_date, $end_date, $parent_name, $empty_param);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - View Parent Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 1400px; margin-top: 30px; }
        .filter-card { margin-bottom: 20px; }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-approved { background-color: #28a745; color: #fff; }
        .badge-rejected { background-color: #dc3545; color: #fff; }
        .badge-secondary { background-color: #6c757d; color: #fff; }
        .table-responsive { overflow-x: auto; }
        .action-btns { white-space: nowrap; }
        .rejection-modal textarea { min-height: 100px; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <h2 class="mb-4"><i class="bi bi-people-fill"></i> Parent Meal Change Requests</h2>
        
        <!-- Filter Form -->
        <div class="card filter-card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="All" <?= $status === 'All' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Approved" <?= $status === 'Approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="Rejected" <?= $status === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="start_date" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="admin_view_parents_request.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Requests Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Meal Change Requests</h5>
                <span class="badge bg-primary">Total: <?= $result->num_rows ?></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Request Date</th>
                                <th>Child</th>
                                <th>Parent</th>
                                <th>Current Meal</th>
                                <th>Requested Meal</th>
                                <th>Parent's Reason</th>
                                <th>Status</th>
                                <th>Admin Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $status_class = [
                                'Pending' => 'badge-pending',
                                'Approved' => 'badge-approved',
                                'Rejected' => 'badge-rejected'
                            ];

                            if ($result->num_rows === 0): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <i class="bi bi-exclamation-circle fs-4"></i>
                                        <p class="mt-2">No meal change requests found for the selected criteria.</p>
                                    </td>
                                </tr>
                            <?php else:
                                while ($row = $result->fetch_assoc()):
                                    $badgeClass = $status_class[$row['status']] ?? 'badge-secondary';
                                    $request_date = date('M j, Y', strtotime($row['change_date']));
                            ?>
                                <tr>
                                    <td><?= $request_date ?></td>
                                    <td><?= htmlspecialchars($row['child_first_name'] . ' ' . $row['child_last_name']) ?></td>
                                    <td><?= htmlspecialchars($row['parent_name']) ?></td>
                                    <td><?= htmlspecialchars($row['current_meal']) ?></td>
                                    <td><?= htmlspecialchars($row['requested_meal']) ?></td>
                                    <td><?= htmlspecialchars($row['reason']) ?></td>
                                    <td>
                                        <span class="status-badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $row['admin_notes'] ? htmlspecialchars($row['admin_notes']) : '-' ?></td>
                                    <td class="action-btns">
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <a href="?id=<?= $row['id'] ?>&action=approve" 
                                               class="btn btn-sm btn-success me-1"
                                               onclick="return confirm('Approve this request? This will update the meal plan.');">
                                               <i class="bi bi-check-circle"></i> Approve
                                            </a>
                                            <button class="btn btn-sm btn-danger reject-btn" 
                                                    data-id="<?= $row['id'] ?>"
                                                    data-child="<?= htmlspecialchars($row['child_first_name'] . ' ' . $row['child_last_name']) ?>"
                                                    data-date="<?= $request_date ?>">
                                               <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">Processed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal fade" id="rejectionModal" tabindex="-1" aria-labelledby="rejectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content rejection-modal">
                <form method="POST" action="">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="rejectionModalLabel">Reject Meal Change Request</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are rejecting the meal change request for <strong id="childName"></strong> on <strong id="requestDate"></strong>.</p>
                        <p>Please provide a reason for rejection (this will be visible to the parent):</p>
                        <input type="hidden" name="request_id" id="modalRequestId">
                        <div class="mb-3">
                            <textarea class="form-control" name="rejection_reason" required placeholder="Enter rejection reason..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reject_request" class="btn btn-danger">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle rejection modal
        document.addEventListener('DOMContentLoaded', function() {
            const rejectButtons = document.querySelectorAll('.reject-btn');
            const modal = new bootstrap.Modal(document.getElementById('rejectionModal'));
            
            rejectButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('modalRequestId').value = this.dataset.id;
                    document.getElementById('childName').textContent = this.dataset.child;
                    document.getElementById('requestDate').textContent = this.dataset.date;
                    modal.show();
                });
            });
            
            // Auto-close alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    new bootstrap.Alert(alert).close();
                });
            }, 5000);
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>