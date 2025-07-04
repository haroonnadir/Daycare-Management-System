<?php
session_start();
include '../db_connect.php'; // Corrected path to the database connection

// Check if user is logged in and is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    echo "No user ID provided!";
    exit();
}

$id = intval($_GET['id']); // Prevent SQL injection

// Fetch user details
$sql = "SELECT * FROM users WHERE id = $id";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "User not found!";
    exit();
}

$user = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    $update_sql = "UPDATE users SET 
                    name = '$name',
                    email = '$email',
                    phone = '$phone',
                    role = '$role'
                   WHERE id = $id";

    if (mysqli_query($conn, $update_sql)) {
        header("Location: ManageProfiles.php");
        exit();
    } else {
        echo "Error updating profile: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile - Daycare Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .form-container {
            width: 50%;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        h2 {
            text-align: center;
            color: #1abc9c;
        }
        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            margin-top: 15px;
            width: 100%;
            padding: 10px;
            background-color: #1abc9c;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
        }
        a {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #3498db;
            text-decoration: none;
        }
        .back-btn {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 10px;
            background-color: #0078ff;
            text-align: center;
            border-radius: 5px;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Edit User Profile</h2>

    <form method="post" action="">
        <label>Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

        <label>Phone</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>

        <label>Role</label>
        <select name="role" required>
            <option value="staff" <?= $user['role'] == 'staff' ? 'selected' : '' ?>>Staff</option>
            <option value="parent" <?= $user['role'] == 'parent' ? 'selected' : '' ?>>Parent</option>

        </select>

        <button type="submit">Update Profile</button>
    </form>

    <a href="ManageProfiles.php" class="back-btn">← Back to Manage Profiles</a>
</div>

</body>
</html>

<?php
mysqli_close($conn);
?>
