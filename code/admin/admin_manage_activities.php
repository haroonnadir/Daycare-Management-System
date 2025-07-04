<?php
session_start();
require '../db_connect.php';

// Set timezone
date_default_timezone_set('America/New_York');

// Verify admin/staff access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: login.php");
    exit();
}

// Set default date to today if not specified
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$date = DateTime::createFromFormat('Y-m-d', $selected_date);
if (!$date) {
    $selected_date = date('Y-m-d');
    $date = new DateTime();
}

// Handle date navigation
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'prev':
            $date->modify('-1 day');
            break;
        case 'next':
            $date->modify('+1 day');
            break;
        case 'today':
            $date = new DateTime();
            break;
    }
    $selected_date = $date->format('Y-m-d');
    header("Location: admin_manage_activities.php?date=" . $selected_date);
    exit();
}

// Get activity categories
$categories_query = "SELECT * FROM activity_categories ORDER BY name";
$categories = $conn->query($categories_query)->fetch_all(MYSQLI_ASSOC);

// Get all children with their group information
$children_query = "SELECT c.id, c.first_name, c.last_name, g.name AS group_name 
                  FROM children c
                  LEFT JOIN child_group cg ON c.id = cg.child_id
                  LEFT JOIN groups g ON cg.group_id = g.id
                  ORDER BY c.first_name";
$children = $conn->query($children_query)->fetch_all(MYSQLI_ASSOC);

// Handle activity submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $child_id = filter_input(INPUT_POST, 'child_id', FILTER_VALIDATE_INT);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $description = trim($_POST['description'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $mood = $_POST['mood'] ?? 'happy';
    $staff_id = $_SESSION['user_id'];
    $time_part = $_POST['start_time'] ?: date('H:i:s');
    $start_time = $selected_date . ' ' . $time_part; // Combine selected date with time
    
    // Validate inputs with specific error messages
    $errors = [];
    
    if (!$child_id) {
        $errors[] = "Please select a child";
    }
    
    if (!$category_id) {
        $errors[] = "Please select an activity type";
    }
    
    if (empty($description)) {
        $errors[] = "Please enter an activity description";
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: admin_manage_activities.php?date=" . $selected_date);
        exit();
    }

    try {
        $conn->begin_transaction();
        
        // Insert activity record
        $query = "INSERT INTO child_activities 
                 (child_id, category_id, staff_id, start_time, description, notes, mood)
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iisssss", 
            $child_id, 
            $category_id, 
            $staff_id,
            $start_time,
            $description,
            $notes,
            $mood
        );
        $stmt->execute();
        $activity_id = $conn->insert_id;
        
        // Debug logging
        error_log("Inserted activity ID: $activity_id for date: $selected_date");
        
        // Handle file uploads
        if (!empty($_FILES['activity_photos']['name'][0])) {
            $upload_dir = '../uploads/activity_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['activity_photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['activity_photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = basename($_FILES['activity_photos']['name'][$key]);
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $new_filename = 'activity_' . $activity_id . '_' . time() . '_' . $key . '.' . $file_ext;
                    $target_file = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $media_query = "INSERT INTO activity_media 
                                      (activity_id, file_path, file_type)
                                      VALUES (?, ?, 'image')";
                        $stmt = $conn->prepare($media_query);
                        $stmt->bind_param("is", $activity_id, $target_file);
                        $stmt->execute();
                    }
                }
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "Activity recorded successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error recording activity: " . $e->getMessage();
        error_log("Activity save error: " . $e->getMessage());
    }
    
    // Force output buffer flush before redirect
    ob_end_flush();
    header("Location: admin_manage_activities.php?date=" . $selected_date);
    exit();
}

// Get activities for selected date with improved query
$activities_query = "SELECT a.*, c.first_name, c.last_name, cat.name as category_name, cat.color,
                    g.name as group_name, u.name as staff_name
                    FROM child_activities a
                    JOIN children c ON a.child_id = c.id
                    JOIN activity_categories cat ON a.category_id = cat.id
                    JOIN users u ON a.staff_id = u.id
                    LEFT JOIN child_group cg ON c.id = cg.child_id
                    LEFT JOIN groups g ON cg.group_id = g.id
                    WHERE DATE(a.start_time) = ?
                    ORDER BY a.start_time DESC";
$stmt = $conn->prepare($activities_query);
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Debug logging for activities query
error_log("Activities query for $selected_date returned " . count($activities) . " results");

// Get media for activities
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Activities | Daycare Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <style>
        /* ===== Base Styles ===== */
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --info-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --white: #ffffff;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        h1, h2, h3, h4, h5, h6 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        h1 {
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ===== Header & Navigation ===== */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-back {
            background-color: var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            border: none;
            font-size: 0.9rem;
            box-shadow: var(--box-shadow);
        }

        .btn-back:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .btn-back i {
            font-size: 0.9em;
        }

        /* ===== Date Navigation ===== */
        .date-navigation .card-body {
            padding: 1rem;
        }

        .date-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ===== Badges & Indicators ===== */
        .group-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 8px;
        }

        .staff-badge {
            background-color: #e8f5e9;
            color: #388e3c;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }

        .badge {
            font-weight: 500;
        }

        /* ===== Mood Indicators ===== */
        .mood-happy { color: #2ecc71; }
        .mood-calm { color: #3498db; }
        .mood-fussy { color: #f39c12; }
        .mood-crying { color: #e74c3c; }
        .mood-tired { color: #9b59b6; }

        /* ===== Form Elements ===== */
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .required:after {
            content: " *";
            color: red;
        }

        .form-control, .form-select {
            border-radius: var(--border-radius);
            padding: 0.5rem 0.75rem;
            border: 1px solid #ced4da;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* ===== Activity Cards ===== */
        .activity-card {
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border: none;
        }

        .activity-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .activity-card .card-body {
            padding: 1.25rem;
        }

        .activity-media {
            margin-top: 1rem;
        }

        .activity-media img {
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .activity-media img:hover {
            transform: scale(1.05);
        }

        /* ===== Alerts & Messages ===== */
        .alert {
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .alert-light {
            background-color: #f8f9fa;
            border-left: 4px solid #e9ecef;
        }

        /* ===== Buttons ===== */
        .btn {
            border-radius: var(--border-radius);
            padding: 8px 15px;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn i {
            font-size: 0.9em;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        /* ===== Responsive Adjustments ===== */
        @media (max-width: 992px) {
            .date-controls {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .btn-back {
                width: 100%;
                justify-content: center;
            }

            .date-controls .btn {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 15px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .date-navigation .card-body {
                flex-direction: column;
                gap: 10px;
            }
            
            .date-controls {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-clipboard-list"></i> Manage Daily Activities</h1>
            <a href="admin_dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <div class="date-navigation card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div class="date-controls">
                    <a href="admin_manage_activities.php?action=prev&date=<?= $selected_date ?>" class="btn btn-primary">
                        <i class="fas fa-chevron-left"></i> Previous Day
                    </a>
                    <a href="admin_manage_activities.php?action=today" class="btn btn-secondary mx-2">
                        Today
                    </a>
                    <a href="admin_manage_activities.php?action=next&date=<?= $selected_date ?>" class="btn btn-primary">
                        Next Day <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <h2 class="mb-0"><?= $date->format('l, F j, Y') ?></h2>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-plus-circle"></i> Record New Activity</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="activityForm">
                            <div class="mb-3">
                                <label for="child_id" class="form-label required">Child</label>
                                <select id="child_id" name="child_id" class="form-select" required>
                                    <option value="">Select Child</option>
                                    <?php foreach ($children as $child): ?>
                                        <option value="<?= $child['id'] ?>">
                                            <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                                            <?php if ($child['group_name']): ?>
                                                <span class="group-badge"><?= htmlspecialchars($child['group_name']) ?></span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category_id" class="form-label required">Activity Type</label>
                                <select id="category_id" name="category_id" class="form-select" required>
                                    <option value="">Select Activity Type</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>">
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_time" class="form-label">Start Time</label>
                                    <input type="time" id="start_time" name="start_time" class="form-control" value="<?= date('H:i') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="mood" class="form-label">Mood</label>
                                    <select id="mood" name="mood" class="form-select">
                                        <option value="happy">Happy</option>
                                        <option value="calm">Calm</option>
                                        <option value="fussy">Fussy</option>
                                        <option value="crying">Crying</option>
                                        <option value="tired">Tired</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label required">Activity Description</label>
                                <textarea id="description" name="description" class="form-control" rows="3" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Additional Notes</label>
                                <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="activity_photos" class="form-label">Activity Photos</label>
                                <input type="file" name="activity_photos[]" multiple accept="image/*" class="form-control">
                            </div>
                            
                            <input type="hidden" name="add_activity" value="1">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Activity
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-history"></i> Today's Activities</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activities)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No activities recorded for <?= $date->format('l, F j, Y') ?>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($activities as $activity): ?>
                                    <div class="activity-card card mb-3">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <span class="badge" style="background-color: <?= $activity['color'] ?>">
                                                        <?= htmlspecialchars($activity['category_name']) ?>
                                                    </span>
                                                    <h5 class="d-inline-block ms-2">
                                                        <?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?>
                                                        <?php if ($activity['group_name']): ?>
                                                            <span class="group-badge"><?= htmlspecialchars($activity['group_name']) ?></span>
                                                        <?php endif; ?>
                                                    </h5>
                                                </div>
                                                <small class="text-muted">
                                                    <?= date('g:i a', strtotime($activity['start_time'])) ?>
                                                </small>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <span class="mood-<?= $activity['mood'] ?>">
                                                    <i class="fas fa-smile"></i> <?= ucfirst($activity['mood']) ?>
                                                </span>
                                                <span class="staff-badge ms-2">
                                                    <i class="fas fa-user"></i> <?= htmlspecialchars($activity['staff_name']) ?>
                                                </span>
                                            </div>
                                            
                                            <p><?= nl2br(htmlspecialchars($activity['description'])) ?></p>
                                            
                                            <?php if (!empty($activity['notes'])): ?>
                                                <div class="alert alert-light">
                                                    <strong>Notes:</strong> <?= nl2br(htmlspecialchars($activity['notes'])) ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($activity_media[$activity['id']])): ?>
                                                <div class="activity-media d-flex flex-wrap gap-2 mt-2">
                                                    <?php foreach ($activity_media[$activity['id']] as $media): ?>
                                                        <a href="<?= htmlspecialchars($media['file_path']) ?>" data-lightbox="activity-<?= $activity['id'] ?>">
                                                            <img src="<?= htmlspecialchars($media['file_path']) ?>" class="img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-3 text-end">
                                                <a href="delete_activity.php?id=<?= $activity['id'] ?>&date=<?= $selected_date ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this activity?');">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

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

        // Client-side form validation
        document.getElementById('activityForm').addEventListener('submit', function(e) {
            const childId = document.getElementById('child_id').value;
            const categoryId = document.getElementById('category_id').value;
            const description = document.getElementById('description').value.trim();
            
            if (!childId || !categoryId || !description) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
        });
    </script>
</body>
</html>