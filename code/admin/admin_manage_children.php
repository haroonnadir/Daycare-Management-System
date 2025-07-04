<?php
session_start();
include '../db_connect.php';

// Function to calculate age
function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

// Verify admin/staff access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: login.php");
    exit();
}

// Handle search and filters
$search = $_GET['search'] ?? '';
$group_filter = $_GET['group'] ?? '';

// Get all groups for dropdown
$groups = $conn->query("SELECT * FROM groups ORDER BY min_age")->fetch_all(MYSQLI_ASSOC);

// Build base query with correct joins
$query = "SELECT 
            c.id, c.first_name, c.last_name, c.date_of_birth, c.gender,
            u.id as parent_id, u.name as parent_name,
            GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as groups,
            GROUP_CONCAT(DISTINCT g.id SEPARATOR ',') as group_ids
          FROM children c
          LEFT JOIN parent_child pc ON c.id = pc.child_id
          LEFT JOIN users u ON pc.parent_id = u.id AND u.role = 'parent'
          LEFT JOIN child_group cg ON c.id = cg.child_id
          LEFT JOIN groups g ON cg.group_id = g.id
          WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR u.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = &$search_param;
    $params[] = &$search_param;
    $params[] = &$search_param;
    $types .= 'sss';
}

if (!empty($group_filter)) {
    $query .= " AND g.id = ?";
    $params[] = &$group_filter;
    $types .= 'i';
}

$query .= " GROUP BY c.id ORDER BY c.first_name, c.last_name";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);
}

$stmt->execute();
$result = $stmt->get_result();
$children = $result->fetch_all(MYSQLI_ASSOC);

// Handle group assignment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_groups'])) {
    $child_id = filter_input(INPUT_POST, 'child_id', FILTER_VALIDATE_INT);
    $new_groups = $_POST['groups'] ?? [];
    $assigned_by = $_SESSION['user_id'];

    $child_age_query = $conn->prepare("SELECT date_of_birth FROM children WHERE id = ?");
    $child_age_query->bind_param("i", $child_id);
    $child_age_query->execute();
    $child_dob = $child_age_query->get_result()->fetch_assoc()['date_of_birth'];
    $child_age = calculateAge($child_dob);

    $conn->begin_transaction();

    try {
        $delete_stmt = $conn->prepare("DELETE FROM child_group WHERE child_id = ?");
        $delete_stmt->bind_param("i", $child_id);
        $delete_stmt->execute();

        $insert_stmt = $conn->prepare("INSERT INTO child_group (child_id, group_id, assigned_by) VALUES (?, ?, ?)");

        foreach ($new_groups as $group_id) {
            $group_id = filter_var($group_id, FILTER_VALIDATE_INT);
            if ($group_id) {
                $group_query = $conn->prepare("SELECT min_age, max_age FROM groups WHERE id = ?");
                $group_query->bind_param("i", $group_id);
                $group_query->execute();
                $group = $group_query->get_result()->fetch_assoc();

                if ($child_age >= $group['min_age'] && $child_age <= $group['max_age']) {
                    $insert_stmt->bind_param("iii", $child_id, $group_id, $assigned_by);
                    $insert_stmt->execute();
                } else {
                    throw new Exception("Group age restriction mismatch");
                }
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Group assignments updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error updating groups: " . $e->getMessage();
    }

    header("Location: admin_manage_children.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Children</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f7fa;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        h1 {
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .back-btn {
            padding: 8px 15px;
            background-color: #2c3e50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s;
        }
        .back-btn:hover {
            background-color: #1a252f;
        }
        .group-badge {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .group-selector {
            display: none;
            margin-top: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #2c3e50;
            color: white;
            position: sticky;
            top: 0;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .search-form {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .search-form input, .search-form select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .search-form button {
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .search-form button:hover {
            background-color: #2980b9;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
        }
        .action-btn {
            padding: 5px 10px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9em;
        }
        .action-btn:hover {
            background-color: #2980b9;
        }
        .enroll-btn {
            background-color: #28a745;
            padding: 8px 15px;
            margin-bottom: 15px;
            display: inline-block;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .enroll-btn:hover {
            background-color: #218838;
        }
        .group-option {
            display: block;
            margin-bottom: 8px;
        }
        .update-btn {
            background-color: #17a2b8;
            margin-top: 10px;
        }
        .update-btn:hover {
            background-color: #138496;
        }
        .age-warning {
            color: #dc3545;
            font-size: 0.8em;
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .back-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1><i class="fas fa-child"></i> Manage Children</h1>
            <a href="admin_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Go back to Dashboard
            </a>
        </div>
        
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Search and Filter Form -->
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search by name..." value="<?= htmlspecialchars($search) ?>">
            <select name="group">
                <option value="">All Groups</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['id'] ?>" <?= $group_filter == $group['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($group['name']) ?> (<?= $group['min_age'] ?>-<?= $group['max_age'] ?>yrs)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit"><i class="fas fa-filter"></i> Filter</button>
            <a href="admin_manage_children.php" class="action-btn"><i class="fas fa-sync-alt"></i> Reset</a>
        </form>

        <!-- Enroll New Child Button -->
        <a href="enroll_child.php" class="enroll-btn"><i class="fas fa-plus"></i> Enroll New Child</a>

        <!-- Children Table -->
        <table>
            <thead>
                <tr>
                    <th>Child Name</th>
                    <th>Age</th>
                    <th>Parent</th>
                    <th>Groups</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($children as $child): 
                $age = calculateAge($child['date_of_birth']);
                $current_group_ids = !empty($child['group_ids']) ? explode(',', $child['group_ids']) : [];
            ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></strong>
                    </td>
                    <td><?= $age ?> years</td>
                    <td><?= htmlspecialchars($child['parent_name'] ?? 'Not assigned') ?></td>
                    <td>
                        <?php if (!empty($child['groups'])): ?>
                            <?php foreach (explode(', ', $child['groups']) as $group): ?>
                                <span class="group-badge"><?= htmlspecialchars($group) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="color: #6c757d;">Not assigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <a href="view_child.php?id=<?= $child['id'] ?>" class="action-btn" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit_child.php?id=<?= $child['id'] ?>" class="action-btn" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button onclick="toggleGroupSelector(<?= $child['id'] ?>)" class="action-btn" title="Assign Groups">
                                <i class="fas fa-users"></i>
                            </button>
                        </div>
                        
                        <div id="group-selector-<?= $child['id'] ?>" class="group-selector">
                            <form method="POST">
                                <input type="hidden" name="child_id" value="<?= $child['id'] ?>">
                                <h4>Assign to Groups</h4>
                                <?php foreach ($groups as $group): 
                                    $is_age_appropriate = ($age >= $group['min_age'] && $age <= $group['max_age']);
                                    $is_assigned = in_array($group['id'], $current_group_ids);
                                ?>
                                    <label class="group-option">
                                        <input type="checkbox" name="groups[]" value="<?= $group['id'] ?>"
                                            <?= $is_assigned ? 'checked' : '' ?>
                                            <?= !$is_age_appropriate ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($group['name']) ?>
                                        (<?= $group['min_age'] ?>-<?= $group['max_age'] ?>yrs)
                                        <?php if (!$is_age_appropriate): ?>
                                            <span class="age-warning">(Not age-appropriate)</span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                                <button type="submit" name="update_groups" class="action-btn update-btn">
                                    <i class="fas fa-save"></i> Update Groups
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>


    </div>

    <script>
        function toggleGroupSelector(childId) {
            const selector = document.getElementById(`group-selector-${childId}`);
            // Hide all other selectors first
            document.querySelectorAll('.group-selector').forEach(el => {
                if (el.id !== `group-selector-${childId}`) {
                    el.style.display = 'none';
                }
            });
            // Toggle current selector
            selector.style.display = selector.style.display === 'block' ? 'none' : 'block';
        }
        
        // Close group selectors when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.group-selector') && !event.target.closest('button[onclick*="toggleGroupSelector"]')) {
                document.querySelectorAll('.group-selector').forEach(el => {
                    el.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>