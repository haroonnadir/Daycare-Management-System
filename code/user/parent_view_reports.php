<?php
session_start();
require '../db_connect.php';

// Verify parent access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

// Get parent's children
$parent_id = $_SESSION['user_id'];
$children_query = "SELECT c.id, c.first_name, c.last_name 
                  FROM children c
                  JOIN parent_child pc ON c.id = pc.child_id
                  WHERE pc.parent_id = ?
                  ORDER BY c.first_name";
$stmt = $conn->prepare($children_query);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($children)) {
    $_SESSION['error'] = "No children associated with your account";
    header("Location: parent_view_reports.php");
    exit();
}

// Set default child (first child if not specified)
$selected_child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : $children[0]['id'];

// Verify the selected child belongs to the parent
$valid_child = false;
foreach ($children as $child) {
    if ($child['id'] == $selected_child_id) {
        $valid_child = true;
        break;
    }
}

if (!$valid_child) {
    $selected_child_id = $children[0]['id'];
}

// Set date range (default: last 7 days)
$date_range = isset($_GET['range']) ? $_GET['range'] : '7days';
$start_date = new DateTime();

switch ($date_range) {
    case 'today':
        $start_date = new DateTime();
        break;
    case '7days':
        $start_date->modify('-7 days');
        break;
    case '30days':
        $start_date->modify('-30 days');
        break;
    case 'month':
        $start_date = new DateTime('first day of this month');
        break;
    default:
        $start_date->modify('-7 days');
}

$end_date = new DateTime();
$end_date->modify('+1 day'); // Include today

// Get child's activities
$activities_query = "SELECT a.*, cat.name as category_name, cat.color, 
                    u.name as staff_name, DATE(a.start_time) as activity_date
                    FROM child_activities a
                    JOIN activity_categories cat ON a.category_id = cat.id
                    JOIN users u ON a.staff_id = u.id
                    WHERE a.child_id = ? 
                    AND a.start_time BETWEEN ? AND ?
                    ORDER BY a.start_time DESC";
$stmt = $conn->prepare($activities_query);
$start_date_str = $start_date->format('Y-m-d');
$end_date_str = $end_date->format('Y-m-d');
$stmt->bind_param("iss", $selected_child_id, $start_date_str, $end_date_str);
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get activity media
$activity_media = [];
if (!empty($activities)) {
    $activity_ids = array_column($activities, 'id');
    $placeholders = implode(',', array_fill(0, count($activity_ids), '?'));
    $media_query = "SELECT * FROM activity_media WHERE activity_id IN ($placeholders)";
    $stmt = $conn->prepare($media_query);
    $stmt->bind_param(str_repeat('i', count($activity_ids)), ...$activity_ids);
    $stmt->execute();
    $media_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($media_results as $media) {
        $activity_media[$media['activity_id']][] = $media;
    }
}

// Get child's name for display
$child_name = '';
foreach ($children as $child) {
    if ($child['id'] == $selected_child_id) {
        $child_name = $child['first_name'] . ' ' . $child['last_name'];
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Child Reports | Daycare Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .activity-card {
            border-left: 4px solid;
        }
        .mood-happy { color: #2ecc71; }
        .mood-calm { color: #3498db; }
        .mood-fussy { color: #f39c12; }
        .mood-crying { color: #e74c3c; }
        .mood-tired { color: #9b59b6; }
        .media-thumbnail {
            width: 100px;
            height: 100px;
            object-fit: cover;
            margin-right: 5px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="fas fa-child"></i> Child Activity Reports</h1>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h3>Viewing Reports for: <strong><?= htmlspecialchars($child_name) ?></strong></h3>
                        <div class="btn-group mt-2">
                            <?php foreach ($children as $child): ?>
                                <a href="parent_view_reports.php?child_id=<?= $child['id'] ?>&range=<?= $date_range ?>" 
                                   class="btn btn-sm <?= $child['id'] == $selected_child_id ? 'btn-primary' : 'btn-outline-primary' ?>">
                                    <?= htmlspecialchars($child['first_name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="float-md-end">
                            <h5>Date Range</h5>
                            <div class="btn-group">
                                <a href="parent_view_reports.php?child_id=<?= $selected_child_id ?>&range=today" 
                                   class="btn btn-sm <?= $date_range == 'today' ? 'btn-info' : 'btn-outline-info' ?>">Today</a>
                                <a href="parent_view_reports.php?child_id=<?= $selected_child_id ?>&range=7days" 
                                   class="btn btn-sm <?= $date_range == '7days' ? 'btn-info' : 'btn-outline-info' ?>">Last 7 Days</a>
                                <a href="parent_view_reports.php?child_id=<?= $selected_child_id ?>&range=30days" 
                                   class="btn btn-sm <?= $date_range == '30days' ? 'btn-info' : 'btn-outline-info' ?>">Last 30 Days</a>
                                <a href="parent_view_reports.php?child_id=<?= $selected_child_id ?>&range=month" 
                                   class="btn btn-sm <?= $date_range == 'month' ? 'btn-info' : 'btn-outline-info' ?>">This Month</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (empty($activities)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No activities recorded for <?= htmlspecialchars($child_name) ?> in the selected date range.
            </div>
        <?php else: ?>
            <div class="activity-list">
                <?php 
                $current_date = null;
                foreach ($activities as $activity): 
                    $activity_date = date('l, F j, Y', strtotime($activity['activity_date']));
                    if ($activity_date !== $current_date): 
                        $current_date = $activity_date;
                ?>
                        <h4 class="mt-4 mb-3 border-bottom pb-2"><?= $current_date ?></h4>
                    <?php endif; ?>
                    
                    <div class="card mb-3 activity-card" style="border-left-color: <?= $activity['color'] ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge" style="background-color: <?= $activity['color'] ?>">
                                        <?= htmlspecialchars($activity['category_name']) ?>
                                    </span>
                                    <span class="ms-2 text-muted">
                                        <?= date('g:i a', strtotime($activity['start_time'])) ?>
                                    </span>
                                </div>
                                <span class="mood-<?= $activity['mood'] ?>">
                                    <i class="fas fa-smile"></i> <?= ucfirst($activity['mood']) ?>
                                </span>
                            </div>
                            
                            <p><?= nl2br(htmlspecialchars($activity['description'])) ?></p>
                            
                            <?php if (!empty($activity['notes'])): ?>
                                <div class="alert alert-light">
                                    <strong>Notes from staff:</strong> <?= nl2br(htmlspecialchars($activity['notes'])) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($activity_media[$activity['id']])): ?>
                                <div class="mt-3">
                                    <h6><i class="fas fa-images"></i> Activity Photos:</h6>
                                    <div class="d-flex flex-wrap">
                                        <?php foreach ($activity_media[$activity['id']] as $media): ?>
                                            <a href="<?= htmlspecialchars($media['file_path']) ?>" data-lightbox="activity-<?= $activity['id'] ?>">
                                                <img src="<?= htmlspecialchars($media['file_path']) ?>" class="media-thumbnail img-thumbnail">
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3 text-muted small">
                                <i class="fas fa-user"></i> Recorded by: <?= htmlspecialchars($activity['staff_name']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>    
        <button>
            <a href="parent_dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>    
        </button>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    <script>
        // Initialize lightbox
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': true,
            'albumLabel': "Image %1 of %2"
        });
    </script>
</body>
</html>