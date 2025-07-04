<?php
session_start();
require '../db_connect.php';

// Verify parent access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

// Check if child ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid child ID";
    header("Location: view_parent_children.php");
    exit();
}

$child_id = (int)$_GET['id'];

// Verify the child belongs to the logged-in parent
$stmt = $conn->prepare("
    SELECT c.* FROM children c
    JOIN parent_child pc ON c.id = pc.child_id
    WHERE pc.parent_id = ? AND c.id = ?
");
$stmt->bind_param("ii", $_SESSION['user_id'], $child_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Child not found or access denied";
    header("Location: view_parent_children.php");
    exit();
}

$child = $result->fetch_assoc();
$stmt->close();

// Get child's group information
$group = null;
$stmt = $conn->prepare("
    SELECT g.id, g.name FROM groups g
    JOIN child_group cg ON g.id = cg.group_id
    WHERE cg.child_id = ?
");
$stmt->bind_param("i", $child_id);
$stmt->execute();
$group_result = $stmt->get_result();
if ($group_result->num_rows > 0) {
    $group = $group_result->fetch_assoc();
}
$stmt->close();

// Get attendance records (last 30 days)
$attendance = [];
$stmt = $conn->prepare("
    SELECT date, status, check_in, check_out, notes 
    FROM attendance 
    WHERE child_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY date DESC
");
$stmt->bind_param("i", $child_id);
$stmt->execute();
$attendance_result = $stmt->get_result();
while ($row = $attendance_result->fetch_assoc()) {
    $attendance[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($child['first_name']) ?>'s Details | Daycare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .details-container {
            max-width: 1200px;
            margin: 2rem auto;
        }
        .details-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
        }
        .child-photo {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            border: 3px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-label {
            font-weight: 500;
            color: #495057;
        }
        .info-value {
            font-weight: 400;
        }
        .badge-present {
            background-color: #28a745;
        }
        .badge-absent {
            background-color: #dc3545;
        }
        .badge-late {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-halfday {
            background-color: #17a2b8;
        }
        .meal-status-pending {
            color: #6c757d;
        }
        .meal-status-approved {
            color: #28a745;
        }
        .meal-status-rejected {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container details-container">
        <div class="details-card card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="bi bi-person-video3"></i> 
                    <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>'s Details
                </h4>
            </div>
            
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-4 text-center">
                        <img src="<?= !empty($child['photo_path']) ? htmlspecialchars($child['photo_path']) : '../assets/default-child.jpg' ?>" 
                             class="child-photo mb-3" 
                             alt="<?= htmlspecialchars($child['first_name']) ?>">
                        <?php if ($group): ?>
                            <div class="mb-2">
                                <span class="badge bg-primary">
                                    <?= htmlspecialchars($group['name']) ?> Group
                                </span>
                            </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-muted">
                                Age: <?= date_diff(date_create($child['date_of_birth']), date_create('today'))->y ?> years
                            </span>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <span class="info-label">First Name:</span>
                                <span class="info-value"><?= htmlspecialchars($child['first_name']) ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Last Name:</span>
                                <span class="info-value"><?= htmlspecialchars($child['last_name']) ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Date of Birth:</span>
                                <span class="info-value"><?= date('F j, Y', strtotime($child['date_of_birth'])) ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Gender:</span>
                                <span class="info-value"><?= htmlspecialchars($child['gender']) ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Enrollment Date:</span>
                                <span class="info-value"><?= date('F j, Y', strtotime($child['enrollment_date'])) ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Emergency Contact:</span>
                                <span class="info-value">
                                    <?= htmlspecialchars($child['emergency_contact_name']) ?><br>
                                    <?= htmlspecialchars($child['emergency_contact_phone']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($child['allergies']) || !empty($child['medical_conditions'])): ?>
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-exclamation-triangle-fill"></i> Health Information</h5>
                        <?php if (!empty($child['allergies'])): ?>
                            <div class="mb-2">
                                <strong>Allergies:</strong>
                                <span class="text-danger"><?= htmlspecialchars($child['allergies']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($child['medical_conditions'])): ?>
                            <div>
                                <strong>Medical Conditions:</strong>
                                <span class="text-danger"><?= htmlspecialchars($child['medical_conditions']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($child['authorized_pickup'])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-person-check"></i> Authorized Pickup Persons</h5>
                        </div>
                        <div class="card-body">
                            <p><?= nl2br(htmlspecialchars($child['authorized_pickup'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            
                <?php if (!empty($child['notes'])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-journal-text"></i> Additional Notes</h5>
                        </div>
                        <div class="card-body">
                            <p><?= nl2br(htmlspecialchars($child['notes'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between">
                    <a href="view_parent_children.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Children List
                    </a>
                    <div>
                        <a href="edit_info_child.php?id=<?= $child['id'] ?>" class="btn btn-primary">
                            <i class="bi bi-pencil-fill"></i> Edit Information
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>