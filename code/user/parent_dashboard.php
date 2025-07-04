<?php
session_start();

// Redirect if not logged in or not a parent
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'parent') {
    header("Location: index.php");
    exit();
}

// Highlight active link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard | Daycare Management System</title>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --danger-color: #f72585;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --sidebar-width: 280px;
            --transition-speed: 0.3s;
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
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            transition: all var(--transition-speed);
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
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
            padding: 0;
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
            border-radius: 50px;
            margin-left: auto;
        }

        .badge-primary {
            background-color: white;
            color: var(--primary-color);
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
            padding: 20px;
            transition: all var(--transition-speed);
        }

        .header {
            background-color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.5rem;
            color: var(--dark-color);
            margin: 0;
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

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-info {
            background-color: #e7f5ff;
            color: #1864ab;
            border: 1px solid #a5d8ff;
        }

        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border: none;
        }

        .card-body {
            padding: 20px;
        }

        .card-title {
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
        }

        .btn-success {
            background-color: #2b8a3e;
            color: white;
        }

        .btn-success:hover {
            background-color: #2f9e44;
        }

        .btn-warning {
            background-color: #e67700;
            color: white;
        }

        .btn-warning:hover {
            background-color: #f08c00;
        }

        .btn-outline-primary {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-outline-secondary {
            background-color: transparent;
            border: 1px solid #868e96;
            color: #868e96;
        }

        .btn-outline-secondary:hover {
            background-color: #f1f3f5;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.875rem;
        }

        .rounded-circle {
            border-radius: 50% !important;
        }

        .img-thumbnail {
            padding: 0.25rem;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            max-width: 100%;
            height: auto;
        }

        .text-muted {
            color: #868e96 !important;
        }

        .text-center {
            text-align: center !important;
        }

        .d-flex {
            display: flex !important;
        }

        .d-grid {
            display: grid !important;
        }

        .gap-2 {
            gap: 0.5rem !important;
        }

        .justify-content-center {
            justify-content: center !important;
        }

        .align-items-center {
            align-items: center !important;
        }

        .flex-shrink-0 {
            flex-shrink: 0 !important;
        }

        .me-2 {
            margin-right: 0.5rem !important;
        }

        .me-3 {
            margin-right: 1rem !important;
        }

        .mb-0 {
            margin-bottom: 0 !important;
        }

        .mb-2 {
            margin-bottom: 0.5rem !important;
        }

        .mb-3 {
            margin-bottom: 1rem !important;
        }

        .mb-4 {
            margin-bottom: 1.5rem !important;
        }

        .mt-2 {
            margin-top: 0.5rem !important;
        }

        .mt-3 {
            margin-top: 1rem !important;
        }

        .py-4 {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }

        .col-12 {
            flex: 0 0 100%;
            max-width: 100%;
            padding-right: 15px;
            padding-left: 15px;
        }

        .col-md-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
            padding-right: 15px;
            padding-left: 15px;
        }

        .col-md-8 {
            flex: 0 0 66.666667%;
            max-width: 66.666667%;
            padding-right: 15px;
            padding-left: 15px;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            display: none;
            float: left;
            min-width: 10rem;
            padding: 0.5rem 0;
            margin: 0.125rem 0 0;
            font-size: 1rem;
            color: #212529;
            text-align: left;
            list-style: none;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0, 0, 0, 0.15);
            border-radius: 0.25rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175);
        }

        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.25rem 1.5rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            text-decoration: none;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .dropdown-divider {
            height: 0;
            margin: 0.5rem 0;
            overflow: hidden;
            border-top: 1px solid #e9ecef;
        }

        .dropdown-toggle::after {
            display: inline-block;
            margin-left: 0.255em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
        }

        .show > .dropdown-menu {
            display: block;
        }

        .text-decoration-none {
            text-decoration: none !important;
        }

        .child-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }

        .meal-plan {
            margin-bottom: 1rem;
        }

        .meal-time {
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 80%;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .col-md-4, .col-md-8 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header text-center py-4">
                <h3>Little Stars Daycare</h3>
                <small>Parent Dashboard</small>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li class="active"><a href="parent_dashboard.php"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a></li>
                    <li><a href="view_parent_children.php"><i class="bi bi-people-fill"></i> <span>My Children</span></a></li>
                    <li><a href="parent_attendance.php"><i class="bi bi-calendar-check"></i> <span>Attendance</span></a></li>
                    <li><a href="parent_view_reports.php"><i class="bi bi-journal-text"></i> <span>Daily Reports</span></a></li>
                    <li><a href="parent_messages.php"><i class="bi bi-chat-left-text"></i> <span>Messages</span>
                        <span class="badge badge-primary">3</span>
                    </a></li>
                    <li><a href="parent_meal_schedule.php"><i class="bi bi-egg-fried"></i> <span>Meal Plans</span></a></li>
                    <li><a href="parent_manage_notifications.php" class="<?= $current_page == 'parent_manage_notifications.php' ? 'active' : '' ?>">
                    <i class="fas fa-bell"></i> Notifications </a>
                    <li><a href="parent_view_invoice.php"><i class="bi bi-receipt"></i> <span>Invoices</span></a></li>
                    <li>
                        <a href="parent_add_payment_method.php" class="<?= $current_page == 'parent_payments.php' ? 'active' : '' ?>">
                            <i class="fas fa-file-invoice-dollar"></i> Billing & Payments
                        </a>
                    </li>
            </li>

                    <li><a href="ManageProfiles.php"><i class="bi bi-person"></i> <span>Profile</span></a></li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="header">
                <h1>Welcome Back!</h1>
                <div class="user-info">
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userDropdown">
                            <img src="https://ui-avatars.com/api/?name=Parent+User&background=random" alt="Parent" class="rounded-circle me-2">
                            <span>Parent User</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="container-fluid">
                <!-- Welcome Message -->
                <div class="alert alert-info">
                    <h5>Welcome back!</h5>
                </div>
                
        
                <!-- Today's Meal Plan -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="card-title mb-4">Today's Meal Plan</h4>
                                
                            <div class="meal-plan">
                                <div class="meal-time">Breakfast (8:00 AM)</div>
                                <p>Whole grain toast with peanut butter, banana slices, and milk</p>
                            </div>
                                
                            <div class="meal-plan">
                                <div class="meal-time">Morning Snack (10:30 AM)</div>
                                <p>Apple slices with cheese cubes and water</p>
                            </div>
                                
                            <div class="meal-plan">
                                <div class="meal-time">Lunch (12:00 PM)</div>
                                <p>Grilled chicken, steamed carrots, mashed potatoes, and apple juice</p>
                            </div>
                                
                                <div class="meal-plan">
                                    <div class="meal-time">Afternoon Snack (3:00 PM)</div>
                                    <p>Yogurt with granola and water</p>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <a href="meal_plans.php" class="btn btn-sm btn-outline-primary">View Weekly Plan</a>
                                    <a href="messages.php" class="btn btn-sm btn-primary">Special Request</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            const dropdownMenu = document.querySelector('.dropdown-menu');
            
            dropdownToggle.addEventListener('click', function(e) {
                e.preventDefault();
                dropdownMenu.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdownToggle.contains(e.target) {
                    dropdownMenu.classList.remove('show');
                }
            });
            
            // Mobile sidebar toggle functionality
            const sidebar = document.querySelector('.sidebar');
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('menu-toggle')) {
                    sidebar.classList.toggle('active');
                }
            });
        });
    </script>
</body>
</html>