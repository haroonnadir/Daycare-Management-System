<?php
session_start();
require '../db_connect.php';

// Verify parent access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

// Check if child ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid child ID";
    header("Location: view_parent_children.php");
    exit();
}

$child_id = (int)$_GET['id'];

// Verify the child belongs to the logged-in parent
$stmt = $conn->prepare("
    SELECT c.* FROM children c
    JOIN parent_child pc ON c.id = pc.child_id
    WHERE pc.parent_id = ? AND c.id = ?
");
$stmt->bind_param("ii", $_SESSION['user_id'], $child_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Child not found or access denied";
    header("Location: view_parent_children.php");
    exit();
}

$child = $result->fetch_assoc();
$stmt->close();

// Get groups for dropdown
$groups = [];
$current_group = null;
$group_query = "SELECT id, name FROM groups ORDER BY name";
$group_result = $conn->query($group_query);
if ($group_result) {
    while ($row = $group_result->fetch_assoc()) {
        $groups[] = $row;
    }
}

// Get current group assignment
$stmt = $conn->prepare("
    SELECT g.id, g.name FROM groups g
    JOIN child_group cg ON g.id = cg.group_id
    WHERE cg.child_id = ?
");
$stmt->bind_param("i", $child_id);
$stmt->execute();
$group_result = $stmt->get_result();
if ($group_result->num_rows > 0) {
    $current_group = $group_result->fetch_assoc();
}
$stmt->close();

$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $allergies = trim($_POST['allergies'] ?? '');
    $medical_conditions = trim($_POST['medical_conditions'] ?? '');
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
    $authorized_pickup = trim($_POST['authorized_pickup'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $group_id = $_POST['group_id'] ?? null;

    // Validation
    if (empty($first_name)) {
        $errors['first_name'] = "First name is required";
    }

    if (empty($last_name)) {
        $errors['last_name'] = "Last name is required";
    }

    if (empty($date_of_birth) || !DateTime::createFromFormat('Y-m-d', $date_of_birth)) {
        $errors['date_of_birth'] = "Valid date of birth is required";
    } elseif (strtotime($date_of_birth) > strtotime('today')) {
        $errors['date_of_birth'] = "Date of birth cannot be in the future";
    }

    if (!in_array($gender, ['Male', 'Female', 'Other'])) {
        $errors['gender'] = "Please select a valid gender";
    }

    if (empty($emergency_contact_name)) {
        $errors['emergency_contact_name'] = "Emergency contact name is required";
    }

    if (empty($emergency_contact_phone)) {
        $errors['emergency_contact_phone'] = "Emergency contact phone is required";
    }

    // Process photo upload
    $photo_path = $child['photo_path'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/children/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed_ext)) {
            // Delete old photo if it exists and isn't the default
            if ($photo_path && $photo_path !== '../assets/default-child.jpg' && file_exists($photo_path)) {
                unlink($photo_path);
            }

            $new_filename = uniqid('child_', true) . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $photo_path = $destination;
            } else {
                $errors['photo'] = "Failed to upload photo";
            }
        } else {
            $errors['photo'] = "Invalid file type. Only JPG, JPEG, PNG, GIF are allowed";
        }
    }

    // Update database if no errors
    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            // Update child info
            $stmt = $conn->prepare("
                UPDATE children SET
                    first_name = ?,
                    last_name = ?,
                    date_of_birth = ?,
                    gender = ?,
                    allergies = ?,
                    medical_conditions = ?,
                    emergency_contact_name = ?,
                    emergency_contact_phone = ?,
                    authorized_pickup = ?,
                    notes = ?,
                    photo_path = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "sssssssssssi",
                $first_name, $last_name, $date_of_birth, $gender, $allergies,
                $medical_conditions, $emergency_contact_name, $emergency_contact_phone,
                $authorized_pickup, $notes, $photo_path, $child_id
            );
            $stmt->execute();
            $stmt->close();

            // Handle group assignment
            if (!empty($group_id)) {
                // Check if group assignment already exists
                $stmt = $conn->prepare("SELECT 1 FROM child_group WHERE child_id = ?");
                $stmt->bind_param("i", $child_id);
                $stmt->execute();
                $group_exists = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if ($group_exists) {
                    // Update existing group assignment
                    $stmt = $conn->prepare("
                        UPDATE child_group SET
                            group_id = ?,
                            assigned_by = ?,
                            assigned_at = NOW()
                        WHERE child_id = ?
                    ");
                    $assigned_by = $_SESSION['user_id'];
                    $stmt->bind_param("iii", $group_id, $assigned_by, $child_id);
                } else {
                    // Create new group assignment
                    $stmt = $conn->prepare("
                        INSERT INTO child_group (child_id, group_id, assigned_by)
                        VALUES (?, ?, ?)
                    ");
                    $assigned_by = $_SESSION['user_id'];
                    $stmt->bind_param("iii", $child_id, $group_id, $assigned_by);
                }
                $stmt->execute();
                $stmt->close();
            } elseif ($current_group) {
                // Remove existing group assignment if group was cleared
                $stmt = $conn->prepare("DELETE FROM child_group WHERE child_id = ?");
                $stmt->bind_param("i", $child_id);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            $_SESSION['success'] = "Child information updated successfully!";
            header("Location: view_child_details.php?id=" . $child_id);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors['database'] = "Error updating child: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?= htmlspecialchars($child['first_name']) ?>'s Information | Daycare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .edit-container {
            max-width: 800px;
            margin: 2rem auto;
        }
        .edit-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
        }
        .form-icon {
            color: #0d6efd;
            margin-right: 8px;
        }
        .btn-submit {
            padding: 0.5rem 1.5rem;
            font-weight: 500;
        }
        .is-invalid {
            border-color: #dc3545;
        }
        .invalid-feedback {
            color: #dc3545;
            font-size: 0.875em;
        }
        .photo-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
        }
        .current-photo {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

    <div class="container edit-container">
        <div class="edit-card card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="bi bi-pencil-fill"></i> 
                    Edit <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>'s Information
                </h4>
            </div>
            
            <div class="card-body">
                <?php if (!empty($errors['database'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-circle-fill"></i> <?= $errors['database'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <div class="mb-3">
                                <img src="<?= htmlspecialchars($child['photo_path'] ?? '../assets/default-child.jpg') ?>" 
                                     class="current-photo" 
                                     alt="Current photo">
                            </div>
                            <div class="mb-3">
                                <label for="photo" class="form-label fw-bold">
                                    <i class="bi bi-camera"></i> Update Photo
                                </label>
                                <input type="file" class="form-control <?= isset($errors['photo']) ? 'is-invalid' : '' ?>" 
                                       id="photo" name="photo" accept="image/*">
                                <img id="photoPreview" src="#" alt="Photo preview" class="photo-preview img-thumbnail d-none">
                                <?php if (isset($errors['photo'])): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors['photo'] ?>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted">Leave blank to keep current photo</small>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label fw-bold">
                                        <i class="bi bi-person form-icon"></i>First Name
                                    </label>
                                    <input type="text" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" 
                                           id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($_POST['first_name'] ?? $child['first_name']) ?>" required>
                                    <?php if (isset($errors['first_name'])): ?>
                                        <div class="invalid-feedback">
                                            <?= $errors['first_name'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label fw-bold">
                                        <i class="bi bi-person form-icon"></i>Last Name
                                    </label>
                                    <input type="text" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" 
                                           id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($_POST['last_name'] ?? $child['last_name']) ?>" required>
                                    <?php if (isset($errors['last_name'])): ?>
                                        <div class="invalid-feedback">
                                            <?= $errors['last_name'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="date_of_birth" class="form-label fw-bold">
                                        <i class="bi bi-calendar-date form-icon"></i>Date of Birth
                                    </label>
                                    <input type="date" class="form-control <?= isset($errors['date_of_birth']) ? 'is-invalid' : '' ?>" 
                                           id="date_of_birth" name="date_of_birth" 
                                           value="<?= htmlspecialchars($_POST['date_of_birth'] ?? $child['date_of_birth']) ?>" 
                                           max="<?= date('Y-m-d') ?>" required>
                                    <?php if (isset($errors['date_of_birth'])): ?>
                                        <div class="invalid-feedback">
                                            <?= $errors['date_of_birth'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="gender" class="form-label fw-bold">
                                        <i class="bi bi-gender-ambiguous form-icon"></i>Gender
                                    </label>
                                    <select class="form-select <?= isset($errors['gender']) ? 'is-invalid' : '' ?>" 
                                            id="gender" name="gender" required>
                                        <option value="">Select gender</option>
                                        <option value="Male" <?= ($_POST['gender'] ?? $child['gender']) === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($_POST['gender'] ?? $child['gender']) === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($_POST['gender'] ?? $child['gender']) === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                    <?php if (isset($errors['gender'])): ?>
                                        <div class="invalid-feedback">
                                            <?= $errors['gender'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="emergency_contact_name" class="form-label fw-bold">
                                        <i class="bi bi-person-badge form-icon"></i>Emergency Contact Name
                                    </label>
                                    <input type="text" class="form-control <?= isset($errors['emergency_contact_name']) ? 'is-invalid' : '' ?>" 
                                           id="emergency_contact_name" name="emergency_contact_name" 
                                           value="<?= htmlspecialchars($_POST['emergency_contact_name'] ?? $child['emergency_contact_name']) ?>" required>
                                    <?php if (isset($errors['emergency_contact_name'])): ?>
                                        <div class="invalid-feedback">
                                            <?= $errors['emergency_contact_name'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="emergency_contact_phone" class="form-label fw-bold">
                                        <i class="bi bi-telephone form-icon"></i>Emergency Contact Phone
                                    </label>
                                    <input type="tel" class="form-control <?= isset($errors['emergency_contact_phone']) ? 'is-invalid' : '' ?>" 
                                           id="emergency_contact_phone" name="emergency_contact_phone" 
                                           value="<?= htmlspecialchars($_POST['emergency_contact_phone'] ?? $child['emergency_contact_phone']) ?>" required>
                                    <?php if (isset($errors['emergency_contact_phone'])): ?>
                                        <div class="invalid-feedback">
                                            <?= $errors['emergency_contact_phone'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="group_id" class="form-label fw-bold">
                                    <i class="bi bi-people form-icon"></i>Group
                                </label>
                                <select class="form-select" id="group_id" name="group_id">
                                    <option value="">Select group (optional)</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?= $group['id'] ?>" 
                                            <?= ($_POST['group_id'] ?? $current_group['id'] ?? '') == $group['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($group['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="allergies" class="form-label fw-bold">
                            <i class="bi bi-exclamation-triangle form-icon"></i>Allergies (if any)
                        </label>
                        <textarea class="form-control" id="allergies" name="allergies" rows="2"><?= htmlspecialchars($_POST['allergies'] ?? $child['allergies']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="medical_conditions" class="form-label fw-bold">
                            <i class="bi bi-heart-pulse form-icon"></i>Medical Conditions (if any)
                        </label>
                        <textarea class="form-control" id="medical_conditions" name="medical_conditions" rows="2"><?= htmlspecialchars($_POST['medical_conditions'] ?? $child['medical_conditions']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="authorized_pickup" class="form-label fw-bold">
                            <i class="bi bi-person-check form-icon"></i>Authorized Pickup Persons
                        </label>
                        <textarea class="form-control" id="authorized_pickup" name="authorized_pickup" rows="2"><?= htmlspecialchars($_POST['authorized_pickup'] ?? $child['authorized_pickup']) ?></textarea>
                        <small class="text-muted">List all persons authorized to pick up the child, separated by commas</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label fw-bold">
                            <i class="bi bi-journal-text form-icon"></i>Additional Notes
                        </label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($_POST['notes'] ?? $child['notes']) ?></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="view_child_details.php?id=<?= $child['id'] ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary btn-submit">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Photo preview functionality
        document.getElementById('photo').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('photoPreview');
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                }
                reader.readAsDataURL(file);
            }
        });

        // Form validation
        (function() {
            'use strict';
            
            const form = document.querySelector('.needs-validation');
            
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        })();
    </script>
</body>
</html>