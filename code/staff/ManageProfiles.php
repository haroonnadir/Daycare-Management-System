<?php
session_start();
include '../db_connect.php'; // Correct path because ManageProfiles.php is inside /admin folder

// Check if user is logged in and is a staff member
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit();
}

// Fetch only parent users
$sql = "SELECT id, name, email, phone, role FROM users WHERE role = 'parent'";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Parent Profiles - Daycare Management System</title>
<style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2980b9;
        --accent-color: #e74c3c;
        --light-color: #f8f9fa;
        --dark-color: #343a40;
        --success-color: #2ecc71;
        --warning-color: #f39c12;
        --text-color: #333;
        --text-light: #fff;
        --border-radius: 8px;
        --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s ease;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f5f7fa;
        margin: 0;
        padding: 20px;
        color: var(--text-color);
        line-height: 1.6;
    }

    .header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }

    h2 {
        margin: 0;
        color: var(--dark-color);
        font-weight: 600;
        position: relative;
        padding-bottom: 15px;
        flex-grow: 1;
    }

    h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100px;
        height: 3px;
        background: var(--primary-color);
        border-radius: 3px;
    }

    table {
        width: 95%;
        margin: 30px auto;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
        box-shadow: var(--box-shadow);
        border-radius: var(--border-radius);
        overflow: hidden;
    }

    th, td {
        padding: 15px;
        text-align: center;
        border-bottom: 1px solid #e0e0e0;
    }

    th {
        background-color: var(--primary-color);
        color: var(--text-light);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.9em;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
    }

    tr:not(:first-child):hover {
        background-color: rgba(52, 152, 219, 0.1);
        transform: scale(1.01);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: var(--transition);
    }

    .btn {
        padding: 8px 15px;
        border: none;
        border-radius: var(--border-radius);
        text-decoration: none;
        color: white;
        cursor: pointer;
        font-weight: 500;
        font-size: 0.9em;
        transition: var(--transition);
        display: inline-block;
        margin: 0 5px;
    }

    .edit-btn {
        background-color: var(--primary-color);
    }

    .edit-btn:hover {
        background-color: var(--secondary-color);
        transform: translateY(-2px);
    }

    .delete-btn {
        background-color: var(--accent-color);
    }

    .delete-btn:hover {
        background-color: #c0392b;
        transform: translateY(-2px);
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px;
        background-color: var(--dark-color);
        text-align: center;
        border-radius: var(--border-radius);
        color: var(--text-light);
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
        box-shadow: var(--box-shadow);
        white-space: nowrap;
        margin-left: 20px;
    }

    .back-btn:hover {
        background-color: #495057;
        transform: translateY(-2px);
    }

    .back-btn::before {
        content: '←';
        margin-right: 8px;
    }

    .no-data {
        text-align: center;
        padding: 20px;
        color: #666;
        font-style: italic;
    }

    @media (max-width: 768px) {
        .header-container {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .back-btn {
            margin-left: 0;
            margin-top: 15px;
            width: 100%;
        }
        
        table {
            width: 100%;
            display: block;
            overflow-x: auto;
        }
        
        .btn {
            padding: 6px 10px;
            margin: 2px;
            font-size: 0.8em;
        }
    }
</style>
</head>
<body>

<div class="header-container">
    <h2>Manage Parent Profiles</h2>
    <a href="staff_dashboard.php" class="back-btn">Back to Dashboard</a>
</div>

<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Role</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['phone']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($row['role'])) ?></td>
                    <td>
                        <a class="btn edit-btn" href="edit_profile.php?id=<?= $row['id'] ?>">Edit</a>
                        <a class="btn delete-btn" href="delete_profile.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this parent?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">No parent users found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>