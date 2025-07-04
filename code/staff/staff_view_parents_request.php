<?php
session_start();
include '../db_connect.php'; // Correct path because ManageProfiles.php is inside /staff folder

// Check if user is logged in and is an staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit();
}


// Process form submission for status changes
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    // First check if request exists
    $check_stmt = $conn->prepare("SELECT status FROM meal_change_requests WHERE id = ?");
    $check_stmt->bind_param("i", $request_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $_SESSION['error'] = "Request not found";
        header("Location: staff_view_parents_request.php");
        exit();
    }
    
    $request = $check_result->fetch_assoc();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        if ($action === 'approve') {
            // Approve the request (whether it's Pending or Rejected)
            $new_status = 'Approved';
            $staff_notes = 'Approved by staff';
            $success_msg = "Request has been successfully approved";
        } elseif ($action === 'reject') {
            // Reject the request (whether it's Pending or Approved)
            $new_status = 'Rejected';
            $staff_notes = 'Rejected by staff';
            $success_msg = "Request has been successfully rejected";
        } else {
            throw new Exception("Invalid action");
        }
        
        // Update the request status
        $update_stmt = $conn->prepare("UPDATE meal_change_requests SET status = ?, staff_notes = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $new_status, $staff_notes, $request_id);
        $update_stmt->execute();
        
        $conn->commit();
        $_SESSION['success'] = $success_msg;
        header("Location: staff_view_parents_request.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error processing request: " . $e->getMessage();
        header("Location: staff_view_parents_request.php");
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

// Process form submission for reject with reason
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
    $request_id = (int)$_POST['request_id'];
    $rejection_reason = trim($_POST['rejection_reason']);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update the request status and add staff notes
        $update_stmt = $conn->prepare("UPDATE meal_change_requests SET status = 'Rejected', staff_notes = ? WHERE id = ?");
        $update_stmt->bind_param("si", $rejection_reason, $request_id);
        $update_stmt->execute();
        
        $conn->commit();
        $_SESSION['success'] = "Request has been successfully rejected";
        header("Location: staff_view_parents_request.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error rejecting request: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Staff - View Parent Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container { 
            max-width: 1400px; 
            margin-top: 30px;
            padding-bottom: 30px;
        }
        .filter-card { 
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
            display: inline-block;
            min-width: 80px;
            text-align: center;
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
        .badge-secondary { 
            background-color: #e2e3e5; 
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        .table-responsive { 
            overflow-x: auto;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            background-color: white;
        }
        .action-btns { 
            white-space: nowrap;
            min-width: 200px;
        }
        .rejection-modal textarea { 
            min-height: 120px;
            border-radius: 6px;
        }
        .table {
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        .table th {
            white-space: nowrap;
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 10;
            padding: 12px 16px;
            font-weight: 600;
            color: #495057;
        }
        .table td {
            vertical-align: middle;
            padding: 12px 16px;
            color: #212529;
        }
        .table tr:hover td {
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            border-radius: 8px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.1);
            padding: 16px 20px;
            font-weight: 600;
            border-radius: 8px 8px 0 0 !important;
        }
        .no-requests {
            padding: 3rem;
            text-align: center;
            color: #6c757d;
            background-color: white;
            border-radius: 8px;
        }
        .no-requests i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #adb5bd;
        }
        .processed-text {
            color: #6c757d;
            font-style: italic;
            font-size: 0.85rem;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 4px;
        }
        .alert {
            border-radius: 8px;
        }
        .form-control, .form-select {
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
        }
        .modal-content {
            border-radius: 10px;
        }
        .modal-header {
            border-radius: 10px 10px 0 0;
        }
        .filter-btn {
            border-radius: 6px;
        }
        .status-change-btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <h2 class="mb-4"><i class="bi bi-people-fill me-2"></i> Parent Meal Change Requests</h2>
        
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
                        <button type="submit" class="btn btn-primary me-2 filter-btn"><i class="bi bi-funnel me-1"></i> Filter</button>
                        <a href="staff_view_parents_request.php" class="btn btn-outline-secondary filter-btn"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</a>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <a href="staff_meal_schedule.php" class="btn btn-secondary filter-btn" style="white-space: nowrap;">
                            <i class="bi bi-arrow-left me-1"></i> Go Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Requests Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Meal Change Requests</h5>
                <span class="badge bg-primary rounded-pill">Total: <?= $result->num_rows ?></span>
            </div>
            <div class="card-body p-0">
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
                                <th>Staff Notes</th>
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
                                    <td colspan="9" class="no-requests">
                                        <i class="bi bi-exclamation-circle"></i>
                                        <p class="mt-2 mb-0">No meal change requests found for the selected criteria.</p>
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
                                    <td>
                                        <?= htmlspecialchars($row['parent_name']) ?>
                                        <div class="text-muted small"><?= htmlspecialchars($row['parent_email']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($row['current_meal']) ?></td>
                                    <td><?= htmlspecialchars($row['requested_meal']) ?></td>
                                    <td><?= htmlspecialchars($row['reason']) ?></td>
                                    <td>
                                        <span class="status-badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $row['staff_notes'] ? htmlspecialchars($row['staff_notes']) : '-' ?></td>
                                    <td class="action-btns">
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <a href="staff_view_parents_request.php?id=<?= $row['id'] ?>&action=approve" 
                                               class="btn btn-sm btn-success status-change-btn"
                                               onclick="return confirm('Are you sure you want to approve this request?');">
                                               <i class="bi bi-check-circle me-1"></i> Approve
                                            </a>
                                            <button class="btn btn-sm btn-danger reject-btn status-change-btn" 
                                                    data-id="<?= $row['id'] ?>"
                                                    data-child="<?= htmlspecialchars($row['child_first_name'] . ' ' . $row['child_last_name']) ?>"
                                                    data-date="<?= $request_date ?>">
                                               <i class="bi bi-x-circle me-1"></i> Reject
                                            </button>
                                        <?php elseif ($row['status'] === 'Approved'): ?>
                                            <button class="btn btn-sm btn-danger reject-btn status-change-btn" 
                                                    data-id="<?= $row['id'] ?>"
                                                    data-child="<?= htmlspecialchars($row['child_first_name'] . ' ' . $row['child_last_name']) ?>"
                                                    data-date="<?= $request_date ?>">
                                               <i class="bi bi-x-circle me-1"></i> Change to Rejected
                                            </button>
                                            <span class="badge bg-success">Approved</span>
                                        <?php elseif ($row['status'] === 'Rejected'): ?>
                                            <a href="staff_view_parents_request.php?id=<?= $row['id'] ?>&action=approve" 
                                               class="btn btn-sm btn-success status-change-btn"
                                               onclick="return confirm('Are you sure you want to approve this previously rejected request?');">
                                               <i class="bi bi-check-circle me-1"></i> Change to Approved
                                            </a>
                                            <span class="badge bg-danger">Rejected</span>
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