<?php
session_start();
include '../db_connect.php';

// Verify admin/staff access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: login.php");
    exit();
}

// Get child ID from URL
$child_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$child_id) {
    header("Location: admin_manage_children.php");
    exit();
}

// Fetch child details
$child_query = "SELECT * FROM children WHERE id = ?";
$stmt = $conn->prepare($child_query);
$stmt->bind_param("i", $child_id);
$stmt->execute();
$child = $stmt->get_result()->fetch_assoc();

if (!$child) {
    header("Location: admin_manage_children.php");
    exit();
}

// Calculate profile completeness percentage
$fields_to_check = [
    'first_name', 'last_name', 'date_of_birth', 'gender',
    'allergies', 'medical_conditions',
    'emergency_contact_name', 'emergency_contact_phone',
    'authorized_pickup', 'notes', 'photo_path'
];
$total_fields = count($fields_to_check);
$filled_fields = 0;

foreach ($fields_to_check as $field) {
    if (!empty($child[$field])) {
        $filled_fields++;
    }
}

$percent_view = round(($filled_fields / $total_fields) * 100);

// Fetch parent info if available
$parent = null;
if (!empty($child['parent_id'])) {
    $parent_query = "SELECT name, email, phone FROM users WHERE id = ?";
    $stmt = $conn->prepare($parent_query);
    $stmt->bind_param("i", $child['parent_id']);
    $stmt->execute();
    $parent = $stmt->get_result()->fetch_assoc();
}

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $changes = [];
    $previous_values = [];

    $fields = [
        'first_name', 'last_name', 'date_of_birth', 'gender',
        'allergies', 'medical_conditions',
        'emergency_contact_name', 'emergency_contact_phone', 'authorized_pickup', 'notes'
    ];

    foreach ($fields as $field) {
        $new_value = $_POST[$field] ?? '';
        $current_value = $child[$field] ?? '';

        if ($field === 'date_of_birth') {
            $new_value = date('Y-m-d', strtotime($new_value));
        }

        if ($new_value != $current_value) {
            $changes[$field] = $new_value;
            $previous_values[$field] = $current_value;
        }
    }

    // Photo upload
    if (!empty($_FILES['photo']['name'])) {
        $upload_dir = 'uploads/child_photos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $new_filename = 'child_' . $child_id . '_' . time() . '.' . $file_ext;
        $target_file = $upload_dir . $new_filename;

        $check = getimagesize($_FILES['photo']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                if (!empty($child['photo_path']) && file_exists($child['photo_path'])) {
                    unlink($child['photo_path']);
                }
                $changes['photo_path'] = $target_file;
                $previous_values['photo_path'] = $child['photo_path'] ?? '';
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        } else {
            $error = "File is not an image.";
        }
    }

    if (empty($error)) {
        if (!empty($changes)) {
            $query = "UPDATE children SET ";
            $types = '';
            $params = [];

            foreach ($changes as $field => $value) {
                $query .= "$field = ?, ";
                $types .= is_int($value) ? 'i' : 's';
                $params[] = $value;
            }

            $query = rtrim($query, ', ') . " WHERE id = ?";
            $types .= 'i';
            $params[] = $child_id;

            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                // Record in history
                $changes_json = json_encode($changes);
                $previous_values_json = json_encode($previous_values);
                
                $history_query = "INSERT INTO child_updates (child_id, updated_by, changed_fields, previous_values) 
                                VALUES (?, ?, ?, ?)";
                $history_stmt = $conn->prepare($history_query);
                $history_stmt->bind_param("iiss",
                    $child_id,
                    $_SESSION['user_id'],
                    $changes_json,
                    $previous_values_json
                );
                $history_stmt->execute();

                $success = "Child information updated successfully!";

                // Refresh child data and percent view
                $stmt = $conn->prepare($child_query);
                $stmt->bind_param("i", $child_id);
                $stmt->execute();
                $child = $stmt->get_result()->fetch_assoc();

                // Recalculate percent view
                $filled_fields = 0;
                foreach ($fields_to_check as $field) {
                    if (!empty($child[$field])) {
                        $filled_fields++;
                    }
                }
                $percent_view = round(($filled_fields / $total_fields) * 100);
            } else {
                $error = "Error updating child information: " . $conn->error;
            }
        } else {
            $success = "No changes were made.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Child: <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></title>
    <style>
        .form-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-section { margin-bottom: 30px; }
        .form-section h2 { border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .form-row { display: flex; margin-bottom: 15px; }
        .form-label { width: 200px; font-weight: bold; padding-right: 15px; }
        .form-input { flex: 1; }
        input[type="text"], input[type="date"], input[type="tel"], textarea, select { width: 100%; padding: 8px; }
        textarea { height: 100px; }
        .photo-preview { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; }
        .error { color: red; }
        .success { color: green; }
        progress { width: 100%; height: 20px; }
        button, a.button { 
            padding: 8px 15px; 
            background: #4CAF50; 
            color: white; 
            border: none; 
            cursor: pointer; 
            text-decoration: none;
            display: inline-block;
        }
        a.button.cancel { background: #f44336; margin-left: 10px; }
    </style>
</head>
<body>
<div class="form-container">
    <h1>Edit Child Information</h1>
    <a href="view_child.php?id=<?= $child['id'] ?>" class="button">Back to Child Details</a>

    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-section">
            <h2>Basic Information</h2>
            <div class="form-row">
                <div class="form-label">Profile Completion:</div>
                <div class="form-input">
                    <progress value="<?= $percent_view ?>" max="100"></progress>
                    <?= $percent_view ?>%
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="first_name">First Name:</label>
                <div class="form-input">
                    <input type="text" name="first_name" id="first_name"
                           value="<?= htmlspecialchars($child['first_name']) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="last_name">Last Name:</label>
                <div class="form-input">
                    <input type="text" name="last_name" id="last_name"
                           value="<?= htmlspecialchars($child['last_name']) ?>">
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="date_of_birth">Date of Birth:</label>
                <div class="form-input">
                    <input type="date" name="date_of_birth" id="date_of_birth"
                           value="<?= $child['date_of_birth'] ?>" required>
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="gender">Gender:</label>
                <div class="form-input">
                    <select name="gender" id="gender" required>
                        <option value="Male" <?= $child['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $child['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= $child['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <label class="form-label">Photo:</label>
                <div class="form-input">
                    <?php if (!empty($child['photo_path'])): ?>
                        <img src="<?= htmlspecialchars($child['photo_path']) ?>" class="photo-preview" id="photo-preview">
                    <?php else: ?>
                        <div class="photo-preview" style="background: #ddd; display: flex; align-items: center; justify-content: center;">
                            No Photo
                        </div>
                    <?php endif; ?>
                    <input type="file" name="photo" id="photo" accept="image/*" onchange="previewPhoto(event)">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2>Medical Information</h2>

            <div class="form-row">
                <label class="form-label" for="allergies">Allergies:</label>
                <div class="form-input">
                    <textarea name="allergies" id="allergies"><?= htmlspecialchars($child['allergies']) ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="medical_conditions">Medical Conditions:</label>
                <div class="form-input">
                    <textarea name="medical_conditions" id="medical_conditions"><?= htmlspecialchars($child['medical_conditions']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2>Emergency Information</h2>

            <div class="form-row">
                <label class="form-label" for="emergency_contact_name">Emergency Contact Name:</label>
                <div class="form-input">
                    <input type="text" name="emergency_contact_name" id="emergency_contact_name"
                           value="<?= htmlspecialchars($child['emergency_contact_name']) ?>">
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="emergency_contact_phone">Emergency Contact Phone:</label>
                <div class="form-input">
                    <input type="tel" name="emergency_contact_phone" id="emergency_contact_phone"
                           value="<?= htmlspecialchars($child['emergency_contact_phone']) ?>">
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="authorized_pickup">Authorized Pickup Persons:</label>
                <div class="form-input">
                    <textarea name="authorized_pickup" id="authorized_pickup"><?= htmlspecialchars($child['authorized_pickup']) ?></textarea>
                    <small>(One per line or comma separated)</small>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2>Additional Notes</h2>
            <textarea name="notes" style="width: 100%; height: 100px;"><?= htmlspecialchars($child['notes']) ?></textarea>
        </div>

        <div class="form-row">
            <button type="submit">Save Changes</button>
             <a href="view_child.php?id=<?= $child['id'] ?>" class="button cancel">Cancel</a>
        </div>
    </form>
    <div>

</div>
</div>


<script>
    function previewPhoto(event) {
        const preview = document.getElementById('photo-preview');
        const file = event.target.files[0];
        const reader = new FileReader();

        reader.onload = function(e) {
            if (!preview) {
                const container = event.target.parentNode;
                const newPreview = document.createElement('img');
                newPreview.id = 'photo-preview';
                newPreview.className = 'photo-preview';
                newPreview.src = e.target.result;
                container.insertBefore(newPreview, event.target);
            } else {
                preview.src = e.target.result;
            }
        }

        if (file) {
            reader.readAsDataURL(file);
        }
    }
</script>
</body>
</html>