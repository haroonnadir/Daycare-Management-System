<?php
session_start();
include '../db_connect.php';

// Verify staff access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'staff')) {
    header("Location: login.php");
    exit();
}

// Get child ID from URL
$child_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$child_id) {
    header("Location: staff_manage_children.php");
    exit();
}

// Fetch child details
$child_query = "SELECT * FROM children WHERE id = ?";
$stmt = $conn->prepare($child_query);
$stmt->bind_param("i", $child_id);
$stmt->execute();
$child = $stmt->get_result()->fetch_assoc();

if (!$child) {
    header("Location: staff_manage_children.php");
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

// Fetch update history
$history_query = "SELECT cu.*, u.name as updated_by_name 
                 FROM child_updates cu
                 JOIN users u ON cu.updated_by = u.id
                 WHERE child_id = ?
                 ORDER BY updated_at DESC";
$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $child_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Child: <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></title>
    <style>
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .section { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .section h2 { color: #2c3e50; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .info-row { display: flex; margin-bottom: 10px; }
        .info-label { width: 250px; font-weight: bold; color: #34495e; }
        .info-value { flex: 1; color: #2c3e50; }
        .photo-container { text-align: center; margin-bottom: 20px; }
        .child-photo { width: 200px; height: 200px; object-fit: cover; border-radius: 50%; border: 3px solid #3498db; }
        .progress-container { margin: 20px 0; }
        progress { width: 100%; height: 25px; }
        .history-item { background: #f9f9f9; padding: 15px; margin-bottom: 10px; border-radius: 5px; }
        .history-changes { margin-top: 10px; padding-left: 15px; border-left: 3px solid #3498db; }
        .btn { 
            padding: 8px 15px; 
            background: #3498db; 
            color: white; 
            border: none; 
            border-radius: 4px;
            cursor: pointer; 
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-edit { background: #2ecc71; }
        .btn-back { background: #95a5a6; }
        .empty-value { color: #95a5a6; font-style: italic; }
    </style>
</head>
<body>
<div class="container">
    <h1>Child Details: <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></h1>
    
    <div class="progress-container">
        <h3>Profile Completion</h3>
        <progress value="<?= $percent_view ?>" max="100"></progress>
        <p><?= $percent_view ?>% complete</p>
    </div>

    <div class="photo-container">
        <?php if (!empty($child['photo_path'])): ?>
            <img src="<?= htmlspecialchars($child['photo_path']) ?>" class="child-photo" alt="Child Photo">
        <?php else: ?>
            <div class="child-photo" style="background: #ddd; display: flex; align-items: center; justify-content: center;">
                No Photo Available
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Basic Information</h2>
        
        <div class="info-row">
            <div class="info-label">Full Name:</div>
            <div class="info-value"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Date of Birth:</div>
            <div class="info-value"><?= !empty($child['date_of_birth']) ? date('F j, Y', strtotime($child['date_of_birth'])) : '<span class="empty-value">Not provided</span>' ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Age:</div>
            <div class="info-value">
                <?php 
                if (!empty($child['date_of_birth'])) {
                    $dob = new DateTime($child['date_of_birth']);
                    $now = new DateTime();
                    $age = $dob->diff($now);
                    echo $age->y . ' years';
                } else {
                    echo '<span class="empty-value">Not provided</span>';
                }
                ?>
            </div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Gender:</div>
            <div class="info-value"><?= !empty($child['gender']) ? htmlspecialchars($child['gender']) : '<span class="empty-value">Not provided</span>' ?></div>
        </div>
    </div>

    <?php if ($parent): ?>
    <div class="section">
        <h2>Parent Information</h2>
        
        <div class="info-row">
            <div class="info-label">Parent Name:</div>
            <div class="info-value"><?= htmlspecialchars($parent['name']) ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div class="info-value"><?= htmlspecialchars($parent['email']) ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Phone:</div>
            <div class="info-value"><?= htmlspecialchars($parent['phone']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="section">
        <h2>Medical Information</h2>
        
        <div class="info-row">
            <div class="info-label">Allergies:</div>
            <div class="info-value"><?= !empty($child['allergies']) ? nl2br(htmlspecialchars($child['allergies'])) : '<span class="empty-value">None reported</span>' ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Medical Conditions:</div>
            <div class="info-value"><?= !empty($child['medical_conditions']) ? nl2br(htmlspecialchars($child['medical_conditions'])) : '<span class="empty-value">None reported</span>' ?></div>
        </div>
    </div>

    <div class="section">
        <h2>Emergency Information</h2>
        
        <div class="info-row">
            <div class="info-label">Emergency Contact Name:</div>
            <div class="info-value"><?= !empty($child['emergency_contact_name']) ? htmlspecialchars($child['emergency_contact_name']) : '<span class="empty-value">Not provided</span>' ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Emergency Contact Phone:</div>
            <div class="info-value"><?= !empty($child['emergency_contact_phone']) ? htmlspecialchars($child['emergency_contact_phone']) : '<span class="empty-value">Not provided</span>' ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Authorized Pickup Persons:</div>
            <div class="info-value">
                <?php 
                if (!empty($child['authorized_pickup'])) {
                    $pickup_list = preg_split('/[\n,]+/', $child['authorized_pickup']);
                    echo '<ul>';
                    foreach ($pickup_list as $person) {
                        if (trim($person) !== '') {
                            echo '<li>' . htmlspecialchars(trim($person)) . '</li>';
                        }
                    }
                    echo '</ul>';
                } else {
                    echo '<span class="empty-value">None specified</span>';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Additional Notes</h2>
        <div class="info-value"><?= !empty($child['notes']) ? nl2br(htmlspecialchars($child['notes'])) : '<span class="empty-value">No additional notes</span>' ?></div>
    </div>

    <?php if (!empty($history)): ?>
    <div class="section">
        <h2>Update History</h2>
        <?php foreach ($history as $entry): ?>
            <div class="history-item">
                <div><strong><?= htmlspecialchars($entry['updated_by_name']) ?></strong> on <?= date('F j, Y \a\t g:i a', strtotime($entry['updated_at'])) ?></div>
                <?php 
                $changes = json_decode($entry['changed_fields'], true);
                $previous = json_decode($entry['previous_values'], true);
                
                if (!empty($changes)): ?>
                    <div class="history-changes">
                        <?php foreach ($changes as $field => $new_value): ?>
                            <div>
                                <strong><?= ucfirst(str_replace('_', ' ', $field)) ?>:</strong>
                                Changed from 
                                "<span style="color:#e74c3c"><?= !empty($previous[$field]) ? htmlspecialchars($previous[$field]) : 'empty' ?></span>" 
                                to 
                                "<span style="color:#2ecc71"><?= !empty($new_value) ? htmlspecialchars($new_value) : 'empty' ?></span>"
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="margin-top: 30px;">
        <a href="edit_child.php?id=<?= $child['id'] ?>" class="btn btn-edit">Edit Child</a>
        <a href="staff_manage_children.php" class="btn btn-back">Back to Children List</a>
    </div>
</div>
</body>
</html>