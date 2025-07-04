<?php
session_start();
require '../db_connect.php';

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Set default week start date (Monday of current week)
$current_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$week_start = new DateTime($current_date);
$week_start->modify('Monday this week');
$week_end = clone $week_start;
$week_end->modify('Sunday this week');

// Handle week navigation
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'prev':
            $week_start->modify('-1 week');
            $week_end->modify('-1 week');
            break;
        case 'next':
            $week_start->modify('+1 week');
            $week_end->modify('+1 week');
            break;
        case 'today':
        default:
            $week_start = new DateTime();
            $week_start->modify('Monday this week');
            $week_end = new DateTime();
            $week_end->modify('Sunday this week');
            break;
    }
    header("Location: admin_meal_schedule.php?date=" . $week_start->format('Y-m-d'));
    exit();
}

// Get all meal types
$meal_types = $conn->query("SELECT * FROM meal_types ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

// Get all groups
$groups = $conn->query("SELECT * FROM groups ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get current meal schedule for the week
$schedule = [];
$schedule_query = $conn->prepare("
    SELECT ms.*, mt.name as meal_name, mt.meal_time, g.name as group_name
    FROM meal_schedule ms
    JOIN meal_types mt ON ms.meal_type_id = mt.id
    JOIN groups g ON ms.group_id = g.id
    WHERE ms.schedule_date BETWEEN ? AND ?
    ORDER BY ms.schedule_date, mt.sort_order, g.name
");

$start_date = $week_start->format('Y-m-d');
$end_date = $week_end->format('Y-m-d');
$schedule_query->bind_param("ss", $start_date, $end_date);

$schedule_query->execute();
$result = $schedule_query->get_result();

while ($row = $result->fetch_assoc()) {
    $schedule[$row['schedule_date']][$row['meal_type_id']][$row['group_id']] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_schedule'])) {
        $conn->begin_transaction();
        
        try {
            // First delete existing schedule for this week
            $delete_stmt = $conn->prepare("
                DELETE FROM meal_schedule 
                WHERE schedule_date BETWEEN ? AND ?
            ");
            $delete_stmt->bind_param("ss", $week_start->format('Y-m-d'), $week_end->format('Y-m-d'));
            $delete_stmt->execute();
            
            // Insert new schedule items
            $insert_stmt = $conn->prepare("
                INSERT INTO meal_schedule 
                (schedule_date, meal_type_id, group_id, menu_item, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['menu'] as $date => $meals) {
                foreach ($meals as $meal_type_id => $groups) {
                    foreach ($groups as $group_id => $data) {
                        if (!empty($data['menu_item'])) {
                            $insert_stmt->bind_param(
                                "siiss",
                                $date,
                                $meal_type_id,
                                $group_id,
                                $data['menu_item'],
                                $data['notes']
                            );
                            $insert_stmt->execute();
                        }
                    }
                }
            }
            
            $conn->commit();
            $_SESSION['success'] = "Meal schedule saved successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error saving meal schedule: " . $e->getMessage();
        }
        
        header("Location: admin_meal_schedule.php?date=" . $week_start->format('Y-m-d'));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Schedule Management | Daycare System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    /* ===== Base Styles ===== */
    :root {
        --primary-color: #3498db;
        --secondary-color: #2980b9;
        --success-color: #2ecc71;
        --danger-color: #e74c3c;
        --warning-color: #f39c12;
        --light-color: #f8f9fa;
        --dark-color: #2c3e50;
        --gray-color: #95a5a6;
        --white: #ffffff;
        --border-radius: 8px;
        --box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        --transition: all 0.3s ease;
    }

    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: var(--dark-color);
        line-height: 1.6;
    }

    .container {
        max-width: 1400px;
        padding: 20px;
    }

    h1, h2, h3 {
        color: var(--dark-color);
        margin-bottom: 1rem;
    }

    h1 {
        font-size: 2rem;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    /* ===== Header & Navigation ===== */
    .header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 15px;
    }

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

    .btn-outline-primary {
        color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-outline-primary:hover {
        background-color: var(--primary-color);
        color: white;
    }

    .btn-outline-secondary {
        color: var(--gray-color);
        border-color: var(--gray-color);
    }

    .btn-outline-secondary:hover {
        background-color: var(--gray-color);
        color: white;
    }

    /* ===== Week Navigation ===== */
    .week-navigation {
        background-color: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        padding: 15px;
        margin-bottom: 20px;
    }

    .week-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--dark-color);
    }

    .badge-date {
        font-size: 0.8em;
        background-color: #e3f2fd;
        color: var(--dark-color);
    }

    /* ===== Meal Schedule Table ===== */
    .meal-schedule {
        background-color: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        overflow-x: auto;
    }

    .table {
        margin-bottom: 0;
    }

    .schedule-header {
        background-color: var(--primary-color);
        color: white;
        font-weight: 600;
    }

    .day-header {
        background-color: var(--secondary-color);
        color: white;
        font-weight: 600;
        text-align: center;
        min-width: 150px;
    }

    .meal-time-header {
        background-color: #e3f2fd;
        font-weight: 600;
        white-space: nowrap;
        width: 150px;
    }

    .group-header {
        background-color: var(--light-color);
        font-weight: 600;
        text-align: center;
        padding: 5px;
    }

    .today {
        background-color: #e3f2fd;
    }

    /* ===== Form Elements ===== */
    .form-control, .form-select {
        border-radius: var(--border-radius);
        padding: 0.5rem 0.75rem;
        border: 1px solid #ced4da;
        transition: var(--transition);
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
    }

    .form-control-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    textarea.form-control-sm {
        height: 60px;
        resize: vertical;
    }

    /* ===== Alerts ===== */
    .alert {
        border-radius: var(--border-radius);
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .alert-dismissible .btn-close {
        padding: 0.5rem;
    }

    /* ===== Utility Classes ===== */
    .text-decoration-none {
        text-decoration: none !important;
    }

    .gap-2 {
        gap: 0.5rem;
    }

    .m-0 {
        margin: 0 !important;
    }

    .mb-2 {
        margin-bottom: 0.5rem !important;
    }

    .mb-4 {
        margin-bottom: 1.5rem !important;
    }

    .mt-1 {
        margin-top: 0.25rem !important;
    }

    .mt-3 {
        margin-top: 1rem !important;
    }

    .me-1 {
        margin-right: 0.25rem !important;
    }

    .me-2 {
        margin-right: 0.5rem !important;
    }

    .ms-1 {
        margin-left: 0.25rem !important;
    }

    .p-1 {
        padding: 0.25rem !important;
    }

    .align-middle {
        vertical-align: middle !important;
    }

    .text-center {
        text-align: center !important;
    }

    .rounded-pill {
        border-radius: 50rem !important;
    }

    /* ===== Responsive Adjustments ===== */
    @media (max-width: 992px) {
        .week-navigation {
            flex-direction: column;
            gap: 15px;
        }
        
        .week-title {
            font-size: 1.25rem;
        }
    }

    @media (max-width: 768px) {
        .header-container {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .table-responsive {
            border: 0;
        }
        
        .table thead {
            display: none;
        }
        
        .table tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
            border-radius: var(--border-radius);
        }
        
        .table td {
            display: block;
            text-align: right;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table td::before {
            content: attr(data-label);
            float: left;
            font-weight: bold;
        }
    }

    @media (max-width: 576px) {
        .container {
            padding: 15px;
        }
        
        h1 {
            font-size: 1.5rem;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
        }
        
        .d-flex.justify-content-between {
            flex-direction: column;
            gap: 10px;
        }
    }
</style>
</head>
<body>
<!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="m-0">
            <i class="fas fa-utensils me-2"></i>Meal Schedule
        </h1>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary">
                <a href="admin_view_parents_request.php" class="text-decoration-none">
                    <i class="fas fa-users me-1"></i> View Parent Requests
                </a>
            </button>
            <a href="admin_dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <!-- Rest of your content remains the same -->
    <div class="week-navigation d-flex justify-content-between align-items-center mb-4">
        <div>
            <button class="btn btn-outline-primary" onclick="navigateWeek('prev')">
                <i class="fas fa-chevron-left me-1"></i> Previous Week
            </button>
        </div>
        
        <div class="text-center">
            <h2 class="week-title m-0">
                Week of: 
                <span class="badge bg-primary">
                    <?= $week_start->format('M j') ?> - <?= $week_end->format('M j, Y') ?>
                </span>
            </h2>
            <div>
                <button class="btn btn-sm btn-outline-secondary" onclick="navigateWeek('today')">
                    <i class="fas fa-calendar-day me-1"></i> This Week
                </button>
            </div>
        </div>
        
        <div>
            <button class="btn btn-outline-primary" onclick="navigateWeek('next')">
                Next Week <i class="fas fa-chevron-right ms-1"></i>
            </button>
        </div>
    </div>
        
        <form method="POST" action="admin_meal_schedule.php">
            <input type="hidden" name="save_schedule" value="1">
            <input type="hidden" name="date" value="<?= $week_start->format('Y-m-d') ?>">
            
            <div class="meal-schedule">
                <div class="table-responsive">
                    <table class="table table-bordered m-0">
                        <thead>
                            <tr class="schedule-header">
                                <th class="meal-time-header">Meal Times</th>
                                <?php 
                                $current_day = clone $week_start;
                                while ($current_day <= $week_end): 
                                    $is_today = $current_day->format('Y-m-d') == date('Y-m-d');
                                ?>
                                    <th class="day-header <?= $is_today ? 'today' : '' ?>">
                                        <?= $current_day->format('D') ?><br>
                                        <span class="badge badge-date rounded-pill">
                                            <?= $current_day->format('M j') ?>
                                        </span>
                                    </th>
                                <?php 
                                    $current_day->modify('+1 day');
                                endwhile; 
                                ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meal_types as $meal): ?>
                                <tr>
                                    <td class="meal-time-header align-middle">
                                        <?= $meal['name'] ?><br>
                                        <small><?= date('g:i a', strtotime($meal['meal_time'])) ?></small>
                                    </td>
                                    
                                    <?php 
                                    $current_day = clone $week_start;
                                    while ($current_day <= $week_end): 
                                        $date_str = $current_day->format('Y-m-d');
                                        $is_today = $date_str == date('Y-m-d');
                                    ?>
                                        <td class="<?= $is_today ? 'today' : '' ?>">
                                            <?php foreach ($groups as $group): ?>
                                                <div class="mb-2">
                                                    <div class="group-header p-1 text-center">
                                                        <?= $group['name'] ?>
                                                    </div>
                                                    <input type="text" 
                                                           name="menu[<?= $date_str ?>][<?= $meal['id'] ?>][<?= $group['id'] ?>][menu_item]"
                                                           class="form-control form-control-sm" 
                                                           value="<?= isset($schedule[$date_str][$meal['id']][$group['id']]) ? 
                                                               htmlspecialchars($schedule[$date_str][$meal['id']][$group['id']]['menu_item']) : '' ?>"
                                                           placeholder="Menu item">
                                                    <textarea 
                                                           name="menu[<?= $date_str ?>][<?= $meal['id'] ?>][<?= $group['id'] ?>][notes]"
                                                           class="form-control form-control-sm mt-1" 
                                                           placeholder="Notes"><?= isset($schedule[$date_str][$meal['id']][$group['id']]) ? 
                                                               htmlspecialchars($schedule[$date_str][$meal['id']][$group['id']]['notes']) : '' ?></textarea>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                    <?php 
                                        $current_day->modify('+1 day');
                                    endwhile; 
                                    ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-3">
                <div>
                    <a href="admin_view_parents_request.php" class="btn btn-outline-secondary">
                        <i class="fas fa-users me-1"></i> View Parent Requests
                    </a>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Schedule
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function navigateWeek(action) {
            const url = new URL(window.location.href);
            url.searchParams.set('action', action);
            window.location.href = url.toString();
        }
        
        // Auto-expand textareas as user types
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });
    </script>
</body>
</html>