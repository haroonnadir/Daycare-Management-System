<?php
include '../db_connect.php';

session_start();

// Check if admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle Delete Staff
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $sql_delete = "DELETE FROM users WHERE id = $delete_id AND role IN ('staff', 'carer', 'manager')";
    if (mysqli_query($conn, $sql_delete)) {
        header("Location: manage_staff.php");
        exit();
    } else {
        echo "Error deleting staff: " . mysqli_error($conn);
    }
}

// Fetch staff accounts
$sql = "SELECT id, name, email, phone, role FROM users WHERE role IN ('staff', 'carer', 'manager')";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Staff - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --text-color: #333;
            --text-light: #fff;
            --border-radius: 6px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f7fa;
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        h2 {
            margin: 0;
            color: var(--dark-color);
            font-size: 1.8rem;
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--text-light);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-dark {
            background-color: var(--dark-color);
            color: var(--text-light);
        }

        .btn-dark:hover {
            background-color: #1a252f;
            transform: translateY(-2px);
        }

        .btn-success {
            background-color: var(--success-color);
            color: var(--text-light);
        }

        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: var(--accent-color);
            color: var(--text-light);
        }

        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background-color: var(--dark-color);
            color: var(--text-light);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }

        .action-links {
            display: flex;
            gap: 10px;
        }

        .action-link {
            padding: 6px 12px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .edit-link {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
        }

        .edit-link:hover {
            background-color: rgba(52, 152, 219, 0.2);
        }

        .delete-link {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--accent-color);
        }

        .delete-link:hover {
            background-color: rgba(231, 76, 60, 0.2);
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 10px;
            }
            
            .action-links {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h2><i class="fas fa-users-cog"></i> Manage Staff Accounts</h2>
            <a href="admin_dashboard.php" class="btn btn-dark">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <a href="add_staff.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New Staff
        </a>

        <table>
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($row['role'])); ?></td>
                            <td>
                                <div class="action-links">
                                    <a href="edit_staff.php?id=<?php echo $row['id']; ?>" class="action-link edit-link">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="manage_staff.php?delete_id=<?php echo $row['id']; ?>" 
                                       class="action-link delete-link"
                                       onclick="return confirm('Are you sure you want to delete this staff member?')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="no-data">No staff members found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php mysqli_close($conn); ?>