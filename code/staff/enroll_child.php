<?php
session_start();
include '../db_connect.php';

// Verify admin/staff access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: ../auth/login.php");
    exit();
}

$error = '';
$success = '';

// Fetch parents to populate dropdown
$parents_result = $conn->query("SELECT id, name FROM users WHERE role = 'parent' ORDER BY name");
$parents = $parents_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $dob = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $parent_id = intval($_POST['parent_id'] ?? 0);

    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender) || $parent_id <= 0) {
        $error = "Please fill in all required fields.";
    } else {
        // Insert child
        $stmt = $conn->prepare("INSERT INTO children (first_name, last_name, date_of_birth, gender) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $first_name, $last_name, $dob, $gender);

        if ($stmt->execute()) {
            $child_id = $stmt->insert_id;

            // Insert into parent_child table
            $stmt2 = $conn->prepare("INSERT INTO parent_child (parent_id, child_id) VALUES (?, ?)");
            $stmt2->bind_param("ii", $parent_id, $child_id);

            if ($stmt2->execute()) {
                $success = "Child enrolled successfully!";
            } else {
                $error = "Error assigning parent to child: " . $stmt2->error;
                // Optional: delete the inserted child if parent assignment fails
                $conn->query("DELETE FROM children WHERE id = $child_id");
            }
        } else {
            $error = "Error inserting child: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Enroll New Child</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8fafc;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        form label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        form input, form select {
            width: 100%;
            padding: 8px 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            margin-top: 25px;
            padding: 10px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #2980b9;
        }
        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        a.back-link {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
        }
        a.back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Enroll New Child</h1>

    <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="first_name">First Name *</label>
        <input type="text" id="first_name" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">

        <label for="last_name">Last Name *</label>
        <input type="text" id="last_name" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">

        <label for="date_of_birth">Date of Birth *</label>
        <input type="date" id="date_of_birth" name="date_of_birth" required value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">

        <label for="gender">Gender *</label>
        <select id="gender" name="gender" required>
            <option value="">Select gender</option>
            <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
            <option value="Other" <?= (($_POST['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
        </select>

        <label for="parent_id">Assign Parent *</label>
        <select id="parent_id" name="parent_id" required>
            <option value="">Select parent</option>
            <?php foreach ($parents as $parent): ?>
                <option value="<?= $parent['id'] ?>" <?= (($_POST['parent_id'] ?? '') == $parent['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($parent['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Enroll Child</button>
    </form>

    <a href="admin_manage_children.php" class="back-link">&larr; Back to Manage Children</a>
</div>
</body>
</html>
