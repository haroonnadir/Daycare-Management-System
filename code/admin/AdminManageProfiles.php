<?php
session_start();
include '../db_connect.php';

// Check if user is logged in and is a parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get the logged-in parent's ID
$parent_id = $_SESSION['user_id'];

// Fetch parent data
$sql = "SELECT id, name, cnic, email, phone, age, address, town, region, postcode, country FROM users WHERE id = $parent_id";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) == 1) {
    $parent = mysqli_fetch_assoc($result);
} else {
    echo "Parent not found.";
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
    <title>Manage My Profile | Daycare Management System</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --danger-color: #f72585;
            --success-color: #4cc9f0;
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
            display: flex;
            min-height: 100vh;
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
            padding: 30px;
            transition: all var(--transition-speed);
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .profile-header h1 {
            color: var(--primary-color);
            font-size: 28px;
        }

        .profile-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: 1px solid var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
            border: 1px solid var(--danger-color);
        }

        .btn-danger:hover {
            background-color: #d1144a;
            border-color: #d1144a;
        }

        .btn i {
            margin-right: 8px;
        }

        .profile-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-bottom: 30px;
        }

        .profile-section-title {
            color: var(--primary-color);
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .detail-item {
            margin-bottom: 15px;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            display: block;
        }

        .detail-value {
            font-size: 16px;
            padding: 10px 15px;
            background-color: #f9f9f9;
            border-radius: 6px;
            border-left: 4px solid var(--primary-color);
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .profile-details {
                grid-template-columns: 1fr;
            }
        }

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
                padding: 20px;
            }
            
            .profile-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .profile-actions {
                width: 100%;
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Parent Dashboard</h3>
            <p>Daycare Management System</p>
        </div>
        
        <div class="sidebar-menu">
            <ul>
                <li>
                    <a href="parent_dashboard.php" class="<?= $current_page == 'parent_dashboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="parent_attendance.php" class="<?= $current_page == 'parent_attendance.php' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i> Attendance
                        <span class="badge badge-primary">3</span>
                    </a>
                </li>
                <li>
                    <a href="parent_reports.php" class="<?= $current_page == 'parent_reports.php' ? 'active' : '' ?>">
                        <i class="fas fa-clipboard-list"></i> Daily Reports
                    </a>
                </li>
                <li>
                    <a href="parent_children.php" class="<?= $current_page == 'parent_children.php' ? 'active' : '' ?>">
                        <i class="fas fa-child"></i> My Children
                    </a>
                </li>
                <li>
                    <a href="parent_meals.php" class="<?= $current_page == 'parent_meals.php' ? 'active' : '' ?>">
                        <i class="fas fa-utensils"></i> Meal Plans
                    </a>
                </li>
                <li>
                    <a href="parent_messages.php" class="<?= $current_page == 'parent_messages.php' ? 'active' : '' ?>">
                        <i class="fas fa-envelope"></i> Messages
                        <span class="badge badge-primary">5</span>
                    </a>
                </li>
                <li>
                    <a href="parent_payments.php" class="<?= $current_page == 'parent_payments.php' ? 'active' : '' ?>">
                        <i class="fas fa-credit-card"></i> Payments
                    </a>
                </li>
                <li>
                    <a href="ManageProfiles.php" class="<?= $current_page == 'ManageProfiles.php' ? 'active' : '' ?>">
                        <i class="fas fa-cog"></i> Settings
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="profile-header">
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
            <div class="profile-actions">
                <a href="edit_admin_profile.php?id=<?= $parent['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
                <a href="parent_dashboard.php" class="btn btn-danger">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <div class="profile-card">
            <h2 class="profile-section-title"><i class="fas fa-id-card"></i> Personal Information</h2>
            <div class="profile-details">
                <div class="detail-item">
                    <span class="detail-label">Full Name</span>
                    <div class="detail-value"><?= htmlspecialchars($parent['name']) ?></div>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">CNIC</span>
                    <div class="detail-value"><?= htmlspecialchars($parent['cnic']) ?></div>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Email</span>
                    <div class="detail-value"><?= htmlspecialchars($parent['email']) ?></div>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Phone Number</span>
                    <div class="detail-value"><?= htmlspecialchars($parent['phone']) ?></div>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Age</span>
                    <div class="detail-value"><?= htmlspecialchars($parent['age']) ?></div>
                </div>
            </div>
        </div>
        
        <div class="profile-card">
            <h2 class="profile-section-title"><i class="fas fa-map-marker-alt"></i> Address Information</h2>
            <div class="profile-details">
                <div class="detail-item">
                    <span class="detail-label">Street Address</span>
                    <div class="detail-value"><?= htmlspecialchars($parent['address']) ?></div>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Town/City</span>
                    <div class="detail-value"><?= htmlspecialchars($parent['town']) ?></div>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Region/State</span>
                    <div class="detail-value"><?= htmlspecialchars($parent['region']) ?></div>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Postal Code</span>
                    <div class="detail-value"><?= htmlspecialchars($parent['postcode']) ?></div>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Country</span>
                    <div class="detail-value"><?= htmlspecialchars($parent['country']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // You can add JavaScript functionality here if needed
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Add responsive sidebar toggle
            // const menuToggle = document.createElement('button');
            // menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            // menuToggle.classList.add('menu-toggle');
            // document.querySelector('.profile-header').prepend(menuToggle);
            
            // menuToggle.addEventListener('click', function() {
            //     document.querySelector('.sidebar').classList.toggle('active');
            // });
        });
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>