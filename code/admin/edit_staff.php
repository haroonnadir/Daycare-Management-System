<?php
session_start();
include '../db_connect.php';

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get staff ID from URL
$staff_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$staff_id) {
    header("Location: manage_staff.php");
    exit();
}

// Fetch staff details
$query = "SELECT u.*, s.position, s.hire_date, s.salary, s.qualifications, 
                 s.emergency_contact_name, s.emergency_contact_phone
          FROM users u
          JOIN staff_details s ON u.id = s.user_id
          WHERE u.id = ? AND u.role = 'staff'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();

if (!$staff) {
    header("Location: manage_staff.php");
    exit();
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $cnic = trim($_POST['cnic']);
    $position = trim($_POST['position']);
    $hire_date = $_POST['hire_date'];
    $salary = trim($_POST['salary']);
    $qualifications = trim($_POST['qualifications']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
    $password = $_POST['password'];
    
    // Basic validation
    if (empty($name) || empty($email) || empty($phone) || empty($cnic)) {
        $error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if email or CNIC already exists for another user
        $check_query = "SELECT id FROM users WHERE (email = ? OR cnic = ?) AND id != ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ssi", $email, $cnic, $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "A user with this email or CNIC already exists";
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Update users table
                $user_query = "UPDATE users SET 
                                name = ?, 
                                email = ?, 
                                phone = ?, 
                                cnic = ?";
                
                // Add password update if provided
                $params = [$name, $email, $phone, $cnic];
                $types = "ssss";
                
                if (!empty($password)) {
                    $user_query .= ", password = ?";
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $params[] = $hashed_password;
                    $types .= "s";
                }
                
                $user_query .= " WHERE id = ?";
                $params[] = $staff_id;
                $types .= "i";
                
                $stmt = $conn->prepare($user_query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                
                // Update staff_details table
                $staff_query = "UPDATE staff_details SET 
                                position = ?, 
                                hire_date = ?, 
                                salary = ?, 
                                qualifications = ?, 
                                emergency_contact_name = ?, 
                                emergency_contact_phone = ? 
                              WHERE user_id = ?";
                $stmt = $conn->prepare($staff_query);
                $stmt->bind_param("ssdsssi", 
                    $position, 
                    $hire_date, 
                    $salary, 
                    $qualifications,
                    $emergency_contact_name,
                    $emergency_contact_phone,
                    $staff_id
                );
                $stmt->execute();
                
                $conn->commit();
                $success = "Staff member updated successfully!";
                
                // Refresh staff data
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $staff_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $staff = $result->fetch_assoc();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error updating staff member: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff Member | Daycare Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .required:after {
            content: " *";
            color: #e74c3c;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        input[type="date"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border 0.3s;
        }
        input:focus,
        textarea:focus,
        select:focus {
            border-color: #3498db;
            outline: none;
        }
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-secondary {
            background: #95a5a6;
        }
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .error {
            color: #e74c3c;
            background: #fadbd8;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5b7b1;
        }
        .success {
            color: #27ae60;
            background: #d5f5e3;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #a3e4d7;
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .password-note {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-top: 5px;
        }
        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-user-edit"></i> Edit Staff Member</h1>
        
        <a href="manage_staff.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Staff Management
        </a>
        
        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="edit_staff.php?id=<?= $staff_id ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="name" class="required">Full Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($staff['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email" class="required">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($staff['email']) ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone" class="required">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($staff['phone']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="cnic" class="required">CNIC</label>
                    <input type="text" id="cnic" name="cnic" value="<?= htmlspecialchars($staff['cnic']) ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password">
                <p class="password-note">Leave blank to keep current password</p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="position" class="required">Position</label>
                    <select id="position" name="position" required>
                        <option value="">Select Position</option>
                        <option value="Teacher" <?= $staff['position'] === 'Teacher' ? 'selected' : '' ?>>Teacher</option>
                        <option value="Assistant" <?= $staff['position'] === 'Assistant' ? 'selected' : '' ?>>Assistant</option>
                        <option value="Nurse" <?= $staff['position'] === 'Nurse' ? 'selected' : '' ?>>Nurse</option>
                        <option value="Administrator" <?= $staff['position'] === 'Administrator' ? 'selected' : '' ?>>Administrator</option>
                        <option value="Cook" <?= $staff['position'] === 'Cook' ? 'selected' : '' ?>>Cook</option>
                        <option value="Janitor" <?= $staff['position'] === 'Janitor' ? 'selected' : '' ?>>Janitor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="hire_date" class="required">Hire Date</label>
                    <input type="date" id="hire_date" name="hire_date" value="<?= htmlspecialchars($staff['hire_date']) ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="salary">Salary</label>
                <input type="number" id="salary" name="salary" step="0.01" min="0" value="<?= htmlspecialchars($staff['salary']) ?>">
            </div>
            
            <div class="form-group">
                <label for="qualifications">Qualifications</label>
                <textarea id="qualifications" name="qualifications"><?= htmlspecialchars($staff['qualifications']) ?></textarea>
            </div>
            
            <h2><i class="fas fa-phone-alt"></i> Emergency Contact Information</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="emergency_contact_name">Contact Name</label>
                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" 
                           value="<?= htmlspecialchars($staff['emergency_contact_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="emergency_contact_phone">Contact Phone</label>
                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" 
                           value="<?= htmlspecialchars($staff['emergency_contact_phone']) ?>">
                </div>
            </div>
            
            <div class="form-group" style="display: flex; gap: 10px;">
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Update Staff Member
                </button>
                <a href="manage_staff.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <a href="delete_staff.php?id=<?= $staff_id ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this staff member?');">
                    <i class="fas fa-trash-alt"></i> Delete
                </a>
            </div>
        </form>
    </div>
</body>
</html>