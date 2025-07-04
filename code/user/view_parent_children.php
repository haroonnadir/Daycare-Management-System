<?php
session_start();
require '../db_connect.php';

// Verify parent access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

// Get parent's children with their details
$children = [];

// Prepare query to fetch children details for the logged-in parent
$stmt = $conn->prepare("
    SELECT 
        c.id, 
        c.first_name, 
        c.last_name, 
        c.date_of_birth,
        c.gender,
        c.allergies,
        c.medical_conditions,
        c.enrollment_date,
        g.name AS group_name,
        g.id AS group_id
    FROM children c
    JOIN parent_child pc ON c.id = pc.child_id
    LEFT JOIN child_group cg ON c.id = cg.child_id
    LEFT JOIN groups g ON cg.group_id = g.id
    WHERE pc.parent_id = ?
    ORDER BY c.first_name
");

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all results as associative array
while ($row = $result->fetch_assoc()) {
    $children[] = $row;
}

$stmt->close();

// Rest of your HTML and display code remains the same...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Children | Daycare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .children-container {
            max-width: 1200px;
            margin: 2rem auto;
        }
        .children-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
        }
        .child-card {
            transition: transform 0.2s;
            border-radius: 8px;
            overflow: hidden;
        }
        .child-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .child-photo {
            height: 200px;
            object-fit: cover;
            object-position: top;
        }
        .badge-group {
            background-color: #0d6efd;
        }
        .info-label {
            font-weight: 500;
            color: #495057;
        }
        .info-value {
            font-weight: 400;
        }
        .action-btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }
        .btn-goback-secondary {
            background-color:rgb(218, 200, 129);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            padding: 10px 18px;
            display: inline-flex;
            align-items: center;
            font-size: 1rem;
            text-decoration: none;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }

        .btn-goback-secondary i {
            margin-right: 6px;
            font-size: 1.1rem;
        }

        .btn-goback-secondary:hover {
            background-color:rgb(37, 224, 0);
        }



    </style>
</head>
<body>

    <div class="container children-container">
        <div class="children-card card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-people-fill"></i> My Children</h4>
                <a href="add_child.php" class="btn btn-light btn-sm">
                    <i class="bi bi-plus-circle"></i> Add Child
                </a>
            </div>
            
            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-circle-fill"></i> <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle-fill"></i> <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (empty($children)): ?>
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle-fill"></i> You don't have any children registered yet.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($children as $child): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="child-card card h-100">
                                    <div class="position-relative">
                                        <img src="<?= !empty($child['photo_path']) ? htmlspecialchars($child['photo_path']) : '../assets/default-child.jpg' ?>" 
                                             class="card-img-top child-photo" 
                                             alt="<?= htmlspecialchars($child['first_name']) ?>">
                                        <?php if (!empty($child['group_name'])): ?>
                                            <span class="badge badge-group position-absolute top-0 end-0 m-2">
                                                <?= htmlspecialchars($child['group_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                                        </h5>
                                        
                                        <div class="mb-2">
                                            <span class="info-label">Age: </span>
                                            <span class="info-value">
                                                <?= date_diff(date_create($child['date_of_birth']), date_create('today'))->y ?> years
                                            </span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <span class="info-label">Gender: </span>
                                            <span class="info-value">
                                                <?= htmlspecialchars($child['gender']) ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <span class="info-label">Enrolled: </span>
                                            <span class="info-value">
                                                <?= date('M j, Y', strtotime($child['enrollment_date'])) ?>
                                            </span>
                                        </div>
                                        
                                        <?php if (!empty($child['allergies'])): ?>
                                            <div class="mb-2">
                                                <span class="info-label">Allergies: </span>
                                                <span class="info-value text-danger">
                                                    <?= htmlspecialchars($child['allergies']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($child['medical_conditions'])): ?>
                                            <div class="mb-3">
                                                <span class="info-label">Medical Conditions: </span>
                                                <span class="info-value text-danger">
                                                    <?= htmlspecialchars($child['medical_conditions']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-footer bg-white">
                                        <div class="d-grid gap-2">
                                            <a href="view_child_details.php?id=<?= $child['id'] ?>" 
                                               class="btn btn-outline-primary action-btn">
                                               <i class="bi bi-eye-fill"></i> View Details
                                            </a>
                                            <a href="edit_info_child.php?id=<?= $child['id'] ?>" 
                                               class="btn btn-outline-secondary action-btn">
                                               <i class="bi bi-pencil-fill"></i> Edit
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div style="text-align: right;">
            <a href="./parent_dashboard.php" class="btn-goback-secondary">
                <i class="bi bi-arrow-left"></i> Go Back
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>