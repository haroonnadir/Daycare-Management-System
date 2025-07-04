<?php
session_start();
require '../db_connect.php';

// Verify parent access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
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
    header("Location: parent_meal_schedule.php?date=" . $week_start->format('Y-m-d'));
    exit();
}

// Get meal types and groups
$meal_types = $conn->query("SELECT * FROM meal_types ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$groups = $conn->query("SELECT * FROM groups ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch weekly schedule
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Meal Schedule | Daycare System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container { max-width: 1400px; }
        .week-navigation { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 15px; margin-bottom: 20px; }
        .week-title { font-size: 1.5rem; font-weight: 600; color: #2c3e50; }
        .meal-schedule { background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .schedule-header { background-color: #3498db; color: white; font-weight: 600; }
        .day-header { background-color: #2980b9; color: white; font-weight: 600; text-align: center; }
        .meal-time-header { background-color: #e3f2fd; font-weight: 600; writing-mode: vertical-rl; transform: rotate(180deg); text-align: center; white-space: nowrap; width: 30px; }
        .group-header { background-color: #f8f9fa; font-weight: 600; text-align: center; }
        .today { background-color: #e3f2fd; }
        .form-control-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        textarea.form-control-sm { height: 60px; }
        .btn-outline-primary { color: #3498db; border-color: #3498db; }
        .btn-outline-primary:hover { background-color: #3498db; color: white; }
        .badge-date { font-size: 0.8em; background-color: #e3f2fd; color: #2c3e50; }
    </style>
</head>
<body>

<div class="container py-4">

    <div class="week-navigation d-flex justify-content-between align-items-center mb-4">
        <div>
            <button class="btn btn-outline-primary" onclick="navigateWeek('prev')">
                <i class="fas fa-chevron-left me-1"></i> Previous Week
            </button>
        </div>

        <div class="text-center">
            <h2 class="week-title m-0">
                Meal Schedule: 
                <span class="badge bg-primary">
                    <?= $week_start->format('M j') ?> - <?= $week_end->format('M j, Y') ?>
                </span>
            </h2>
            <button class="btn btn-sm btn-outline-secondary mt-1" onclick="navigateWeek('today')">
                <i class="fas fa-calendar-day me-1"></i> This Week
            </button>
        </div>

        <div>
            <button class="btn btn-outline-primary" onclick="navigateWeek('next')">
                Next Week <i class="fas fa-chevron-right ms-1"></i>
            </button>
        </div>
    </div>

    <div class="meal-schedule">
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
                                <table class="table table-sm table-borderless m-0">
                                    <?php foreach ($groups as $group): ?>
                                        <tr>
                                            <td class="group-header p-1"><?= $group['name'] ?></td>
                                        </tr>
                                        <tr>
                                            <td class="p-1">
                                                <input type="text" class="form-control form-control-sm" 
                                                       value="<?= isset($schedule[$date_str][$meal['id']][$group['id']]) ? 
                                                           htmlspecialchars($schedule[$date_str][$meal['id']][$group['id']]['menu_item']) : '' ?>" 
                                                       readonly>
                                                <textarea class="form-control form-control-sm mt-1" readonly><?= isset($schedule[$date_str][$meal['id']][$group['id']]) ? 
                                                    htmlspecialchars($schedule[$date_str][$meal['id']][$group['id']]['notes']) : '' ?></textarea>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
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

    <div class="d-flex justify-content-between mt-3">
        <a href="parent_dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
        <a href="request_meal_change.php" class="btn btn-warning">
            <i class="fas fa-utensils me-1"></i> Request Meal Change
        </a>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
<script>
    function navigateWeek(action) {
        window.location.href = 'parent_meal_schedule.php?action=' + action + '&date=<?= $week_start->format('Y-m-d') ?>';
    }
</script>

</body>
</html>
