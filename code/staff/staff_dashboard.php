<?php
session_start();

// Redirect if not logged in or not an staff
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'staff') {
    header("Location: index.php");
    exit();
}

// Highlight active link
$current_page = basename($_SERVER['PHP_SELF']);

// Database connection
include '../db_connect.php';

// Get statistics for dashboard
$stats = [
    'total_parents' => 0,
    'total_children' => 0,
    'pending_payments' => 0,
    'unread_messages' => 0,
    'pending_tasks' => 0 // Assuming you have a tasks table, you'll need to adjust this
];

// Fetch total parents
$query = "SELECT COUNT(*) FROM users WHERE role = 'parent'";
$result = mysqli_query($conn, $query);
if ($result) {
    $row = mysqli_fetch_row($result);
    $stats['total_parents'] = $row[0];
    mysqli_free_result($result);
}

// Fetch total children
$query = "SELECT COUNT(*) FROM children";
$result = mysqli_query($conn, $query);
if ($result) {
    $row = mysqli_fetch_row($result);
    $stats['total_children'] = $row[0];
    mysqli_free_result($result);
}

// Fetch pending payments (assuming 'Pending' status in invoices)
$query = "SELECT COUNT(DISTINCT i.invoice_number)
          FROM invoices i
          LEFT JOIN payments p ON i.id = p.invoice_id
          WHERE i.status = 'sent' AND p.invoice_id IS NULL;";
$result = mysqli_query($conn, $query);
if ($result) {
    $row = mysqli_fetch_row($result);
    $stats['pending_payments'] = $row[0];
    mysqli_free_result($result);
}

// Fetch unread messages count (assuming 'is_read' = 0)
$query = "SELECT COUNT(DISTINCT cp.conversation_id)
          FROM conversation_participants cp
          WHERE cp.user_id = ? AND cp.has_unread = 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    $row = mysqli_fetch_row($result);
    $stats['unread_messages'] = $row[0];
    mysqli_free_result($result);
}
mysqli_stmt_close($stmt);


// Get recent activities (example - adjust queries based on your definition of recent activity)
$activities = [];

// Recent Child Registrations
$query = "SELECT CONCAT(first_name, ' ', last_name) as full_name, enrollment_date FROM children ORDER BY enrollment_date DESC LIMIT 5";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $time_ago = humanTiming(strtotime($row['enrollment_date']));
        $activities[] = ['time' => $time_ago . ' ago', 'activity' => 'New child registration - ' . htmlspecialchars($row['full_name'])];
    }
    mysqli_free_result($result);
}

// Recent Payments
$query = "SELECT u.name as parent_name, p.payment_date FROM payments p JOIN users u ON p.parent_id = u.id ORDER BY p.payment_date DESC LIMIT 5";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $time_ago = humanTiming(strtotime($row['payment_date']));
        $activities[] = ['time' => $time_ago . ' ago', 'activity' => 'Payment received from ' . htmlspecialchars($row['parent_name'])];
    }
    mysqli_free_result($result);
}

// Recent Staff Accounts Created
$query = "SELECT name, created_at FROM users WHERE role = 'staff' ORDER BY created_at DESC LIMIT 5";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $time_ago = humanTiming(strtotime($row['created_at']));
        $activities[] = ['time' => $time_ago . ' ago', 'activity' => 'New staff account created - ' . htmlspecialchars($row['name'])];
    }
    mysqli_free_result($result);
}

// Function to calculate time ago
function humanTiming($timestamp) {
    $time = time() - $timestamp;
    $tokens = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'min',
        1 => 'sec'
    ];
    foreach ($tokens as $unit => $text) {
        if ($time < $unit) continue;
        $numberOfUnits = floor($time / $unit);
        return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '');
    }
    return 'just now';
}

// Dummy data for weekly attendance chart - replace with actual data fetching
$weeklyAttendanceChildren = [42, 45, 40, 38, 44, 20];
$weeklyAttendanceStaff = [10, 12, 11, 9, 12, 5];

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | Daycare Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --info-color: #43aa8b;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --sidebar-width: 280px;
            --transition-speed: 0.3s;
            --border-radius: 8px; /* Consistent border radius */
            --box-shadow-light: 0 2px 10px rgba(0, 0, 0, 0.05); /* Light box shadow */
            --box-shadow-medium: 0 4px 20px rgba(0, 0, 0, 0.08); /* Medium box shadow */
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            display: flex;
            min-height: 100vh;
            line-height: 1.6; /* Improved text readability */
        }

        /* Navbar Styles */
        .navbar {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 20px; /* Add horizontal padding */
            text-align: left; /* Align title to the left */
            font-size: 20px;
            font-weight: bold;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-left {
            display: flex;
            align-items: center;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            margin-right: 15px;
            cursor: pointer;
            display: none;
        }

        .notification-bell {
            position: relative;
            margin-right: 20px;
            cursor: pointer;
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 60px;
            overflow-y: auto;
            transition: all var(--transition-speed);
            z-index: 999;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); /* Add a subtle right shadow */
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h3 {
            color: white;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu ul {
            list-style: none;
        }

        .sidebar-menu li {
            position: relative;
            margin-bottom: 5px;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all var(--transition-speed);
            border-left: 3px solid transparent;
        }

        .sidebar-menu li a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 3px solid white;
        }

        .sidebar-menu li a.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-left: 3px solid white;
            font-weight: 600;
        }

        .sidebar-menu li a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .badge {
            display: inline-block;
            padding: 3px 7px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            border-radius: 10px; /* Slightly less rounded for badges */
            margin-left: auto;
        }

        .badge-primary {
            background-color: white;
            color: var(--primary-color);
        }

        .badge-danger {
            background-color: white;
            color: var(--danger-color);
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all var(--transition-speed);
            text-decoration: none;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .logout-btn i {
            margin-right: 8px;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px;
            margin-top: 60px; /* Account for fixed navbar */
            transition: all var(--transition-speed);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            color: var(--primary-color);
            font-size: 28px;
            margin-bottom: 0; /* Remove default margin for better alignment */
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .welcome-message {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-light);
            margin-bottom: 20px;
        }

        .welcome-message h2 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .dashboard-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow-medium);
            transition: transform 0.3s;
            cursor: pointer;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .card-icon.users {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }

        .card-icon.children {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success-color);
        }

        .card-icon.staff {
            background-color: rgba(248, 150, 30, 0.1);
            color: var(--warning-color);
        }

        .card-icon.payments {
            background-color: rgba(120, 111, 166, 0.1);
            color: #786fa6;
        }

        .card-icon.reports {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger-color);
        }

        .card-icon.messages {
            background-color: rgba(67, 170, 139, 0.1);
            color: var(--info-color);
        }

        .card-value {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--secondary-color); /* Slightly darker text for value */
        }

        .card-title {
            color: #666;
            font-size: 14px;
        }

        /* Section Cards */
        .section-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow-medium);
            margin-bottom: 20px;
        }

        .section-title {
            color: var(--primary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            font-size: 20px; /* Slightly larger section titles */
        }

        .section-title i {
            margin-right: 10px;
        }

        /* Activity List */
        .activity-list {
            list-style: none;
            padding: 0; /* Remove default padding for lists */
        }

        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 14px;
        }

        .activity-content {
            flex: 1;
        }

        .activity-time {
            font-size: 12px;
            color: #999;
            margin-top: 3px; /* Add a little spacing */
        }

        /* Chart Container */
        .chart-container {
            height: 300px;
            margin-top: 20px;
            border-radius: var(--border-radius); /* Optional: round chart container */
            overflow: hidden; /* Hide any overflow from the chart */
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); /* Adjust minmax for smaller screens */
            gap: 15px;
            margin-top: 20px;
        }

        .quick-action {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: var(--box-shadow-light);
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none; /* Make the whole card clickable */
            color: inherit; /* Inherit text color */
        }

        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .quick-action i {
            font-size: 24px;
            color: var(--accent-color); /* Use accent color for icons */
            margin-bottom: 10px;
        }

        .quick-action span {
            display: block;
            font-size: 14px;
            color: #555;
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .dashboard-cards {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .navbar {
                padding: 15px; /* Adjust navbar padding for smaller screens */
            }
        }

        @media (max-width: 768px) {
            .dashboard-cards {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: start;
            }

            .dashboard-header h1 {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-left">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <span>Daycare Management System</span>
    </div>
    <div class="user-info">
        <div class="notification-bell">
            <i class="fas fa-bell"></i>
            <span class="notification-count">
                <?= $stats['unread_messages'] ?>
            </span>
        </div>
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['name'] ?? 'staff') ?>&background=random" alt="User">
        <span><?= htmlspecialchars($_SESSION['name'] ?? 'staff') ?></span>
    </div>
</div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3>Staff Dashboard</h3>
        <p>Daycare Management System</p>
    </div>

    <div class="sidebar-menu">
        <ul>
            <li>
                <a href="staff_dashboard.php" class="<?= $current_page == 'staff_dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="ManageProfiles.php" class="<?= $current_page == 'ManageProfiles.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> All Accounts
                </a>
            </li>
            <li>
                <a href="staff_manage_children.php" class="<?= $current_page == 'staff_manage_children.php' ? 'active' : '' ?>">
                    <i class="fas fa-child"></i> Children Management
                    <span class="badge badge-primary"><?= $stats['total_children'] ?></span>
                </a>
            </li>
            <li>
                <a href="checkin_checkout.php" class="<?= $current_page == 'checkin_checkout.php' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> Attendance
                </a>
            </li>
            <li>
                <a href="staff_manage_activities.php" class="<?= $current_page == 'staff_manage_activities.php' ? 'active' : '' ?>">
                    <i class="fas fa-clipboard-list"></i> Daily Activities
                </a>
            </li>
            <li>
                <a href="staff_meal_schedule.php" class="<?= $current_page == 'staff_meal_schedule.php' ? 'active' : '' ?>">
                    <i class="fas fa-utensils"></i> Meal Schedule
                </a>
            </li>
            <li>
                <a href="staff_manage_messages.php" class="<?= $current_page == 'staff_manage_messages.php' ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i> Messages
                    <span class="badge badge-primary"><?= $stats['unread_messages'] ?></span>
                </a>
            </li>
            <li>
                <a href="staff_manage_notifications.php" class="<?= $current_page == 'staff_manage_notifications.php' ? 'active' : '' ?>">
                    <i class="fas fa-bell"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="staff_manage_invoices.php">
                    <i class="fas fa-file-invoice"></i> Manage Invoices
                </a>
            </li>
            <li>
                <a href="staff_generate_reports.php" class="<?= $current_page == 'staff_generate_reports.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> Invoices & Analytics
                </a>
            </li>
            <li>
                <a href="staff_manage_profiles.php" class="<?= $current_page == 'staff_manage_profiles.php' ? 'active' : '' ?>">
                    <i class="bi bi-person"></i> Profile
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
    <div class="main-content">
        <div class="dashboard-header">
            <h1>Staff Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Staff') ?></span>
            </div>
        </div>

        <div class="welcome-message">
            <h2><i class="fas fa-home"></i> Overview</h2>
            <p>Manage all aspects of your daycare center from this dashboard. Quickly access important information and perform common tasks.</p>
        </div>

        <div class="dashboard-cards">
            <a href="staff_manage_parents.php" class="dashboard-card">
                <div class="card-header">
                    <div>
                        <div class="card-value"><?= $stats['total_parents'] ?></div>
                        <div class="card-title">Parent Accounts</div>
                    </div>
                    <div class="card-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </a>

            <a href="staff_manage_children.php" class="dashboard-card">
                <div class="card-header">
                    <div>
                        <div class="card-value"><?= $stats['total_children'] ?></div>
                        <div class="card-title">Enrolled Children</div>
                    </div>
                    <div class="card-icon children">
                        <i class="fas fa-child"></i>
                    </div>
                </div>
            </a>

            <a href="staff_manage_payments.php" class="dashboard-card">
                <div class="card-header">
                    <div>
                        <div class="card-value">$<?= number_format($stats['pending_payments'], 2) ?></div>
                        <div class="card-title">Pending Payments</div>
                    </div>
                    <div class="card-icon payments">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </a>

            <a href="staff_manage_messages.php" class="dashboard-card">
                <div class="card-header">
                    <div>
                        <div class="card-value"><?= $stats['unread_messages'] ?></div>
                        <div class="card-title">Unread Messages</div>
                    </div>
                    <div class="card-icon messages">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>
            </a>

            <a href="staff_manage_tasks.php" class="dashboard-card">
                <div class="card-header">
                    <div>
                        <div class="card-value"><?= $stats['pending_tasks'] ?></div>
                        <div class="card-title">Pending Tasks</div>
                    </div>
                    <div class="card-icon reports">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
            </a>
        </div>

        <div class="section-card">
            <h2 class="section-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="quick-actions">
                <a href="enroll_child.php" class="quick-action">
                    <i class="fas fa-plus-circle"></i>
                    <span>Enroll New Child</span>
                </a>
                <a href="staff_manage_notifications.php" class="quick-action">
                    <i class="fas fa-bullhorn"></i>
                    <span>Send Notification</span>
                </a>
                <a href="staff_manage_invoices.php" class="quick-action">
                    <i class="fas fa-file-invoice"></i>
                    <span>Generate Invoice</span>
                </a>
                <a href="checkin_checkout.php" class="quick-action">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Check-In Child</span>
                </a>
                <a href="checkin_checkout.php" class="quick-action">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Check-Out Child</span>
                </a>
            </div>
        </div>

        <div class="section-card">
            <h2 class="section-title"><i class="fas fa-history"></i> Recent Activity</h2>
            <ul class="activity-list">
                <?php foreach($activities as $activity): ?>
                <li class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="activity-content">
                        <?= htmlspecialchars($activity['activity']) ?>
                        <div class="activity-time"><?= $activity['time'] ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="section-card">
            <h2 class="section-title"><i class="fas fa-chart-line"></i> Weekly Attendance</h2>
            <div class="chart-container">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar on mobile
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Attendance Chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                datasets: [{
                    label: 'Children Present',
                    data: <?= json_encode($weeklyAttendanceChildren) ?>,
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 1
                }, {
                    label: 'Staff Present',
                    data: <?= json_encode($weeklyAttendanceStaff) ?>,
                    backgroundColor: 'rgba(248, 150, 30, 0.7)',
                    borderColor: 'rgba(248, 150, 30, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Sample notification dropdown (would be implemented fully in production)
        document.querySelector('.notification-bell').addEventListener('click', function() {
            alert('Notification center would open here showing all notifications');
        });
    </script>
</body>
</html>