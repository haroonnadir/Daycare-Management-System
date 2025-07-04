<?php
session_start();
include '../db_connect.php';

// Verify parent access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

// Get parent's children
$parent_id = $_SESSION['user_id'];
$children_query = "SELECT c.id, c.first_name, c.last_name 
                  FROM children c
                  JOIN parent_child pc ON c.id = pc.child_id
                  WHERE pc.parent_id = ?";
$stmt = $conn->prepare($children_query);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get selected child (default to first child if none selected)
$selected_child_id = $_GET['child_id'] ?? ($children[0]['id'] ?? null);

// Get attendance records for selected child
$attendance_records = [];
if ($selected_child_id) {
    $attendance_query = "SELECT date, check_in, check_out, status, temperature, notes
                        FROM attendance
                        WHERE child_id = ?
                        ORDER BY date DESC
                        LIMIT 30"; // Last 30 records
    $stmt = $conn->prepare($attendance_query);
    $stmt->bind_param("i", $selected_child_id);
    $stmt->execute();
    $attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get attendance summary for selected child
$summary = [];
if ($selected_child_id) {
    $summary_query = "SELECT 
                        COUNT(*) as total_days,
                        SUM(status = 'Present') as present_days,
                        SUM(status = 'Late') as late_days,
                        SUM(status = 'Half-day') as half_days,
                        SUM(status = 'Absent') as absent_days
                      FROM attendance
                      WHERE child_id = ?";
    $stmt = $conn->prepare($summary_query);
    $stmt->bind_param("i", $selected_child_id);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Attendance | Daycare Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --info-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --white: #ffffff;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1, h2, h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        h1 {
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .child-selector {
            background-color: var(--white);
            padding: 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .child-selector label {
            font-weight: 500;
            margin-right: 10px;
        }
        
        .child-selector select {
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
            font-family: inherit;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            text-align: center;
            transition: var(--transition);
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
        }
        
        .summary-card.total {
            border-top: 4px solid var(--primary-color);
        }
        
        .summary-card.present {
            border-top: 4px solid var(--success-color);
        }
        
        .summary-card.late {
            border-top: 4px solid var(--warning-color);
        }
        
        .summary-card.half-day {
            border-top: 4px solid var(--info-color);
        }
        
        .summary-card.absent {
            border-top: 4px solid var(--danger-color);
        }
        
        .card-title {
            font-size: 0.9rem;
            color: var(--gray-color);
            margin-bottom: 5px;
        }
        
        .card-value {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .attendance-table th, 
        .attendance-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .attendance-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .attendance-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-present { background-color: #e6f7ee; color: #00a854; }
        .status-late { background-color: #fff7e6; color: #fa8c16; }
        .status-half-day { background-color: #f0f5ff; color: #597ef7; }
        .status-absent { background-color: #fff1f0; color: #f5222d; }
        
        .no-records {
            text-align: center;
            padding: 40px;
            color: var(--gray-color);
            font-style: italic;
        }
        
        .btn-back {
            display: inline-block;
            padding: 10px 15px;
            background-color: var(--gray-color);
            color: var(--white);
            border-radius: var(--border-radius);
            text-decoration: none;
            margin-top: 20px;
            transition: var(--transition);
        }
        
        .btn-back:hover {
            background-color: #5a6268;
        }
        
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .attendance-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        @media (max-width: 480px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-calendar-alt"></i> Child Attendance</h1>
        
        <?php if (empty($children)): ?>
            <div class="no-records">
                <p>No children registered under your account.</p>
                <a href="parent_dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="child-selector">
                <form method="get" action="parent_attendance.php">
                    <label for="child_id">Select Child:</label>
                    <select name="child_id" id="child_id" onchange="this.form.submit()">
                        <?php foreach ($children as $child): ?>
                            <option value="<?= $child['id'] ?>" <?= $child['id'] == $selected_child_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <?php if ($selected_child_id): ?>
                <div class="summary-cards">
                    <div class="summary-card total">
                        <p class="card-title">Total Days</p>
                        <p class="card-value"><?= $summary['total_days'] ?? 0 ?></p>
                    </div>
                    <div class="summary-card present">
                        <p class="card-title">Present Days</p>
                        <p class="card-value"><?= $summary['present_days'] ?? 0 ?></p>
                    </div>
                    <!-- <div class="summary-card late">
                        <p class="card-title">Late Arrivals</p>
                        <p class="card-value"><?= $summary['late_days'] ?? 0 ?></p>
                    </div>
                    <div class="summary-card half-day">
                        <p class="card-title">Half Days</p>
                        <p class="card-value"><?= $summary['half_days'] ?? 0 ?></p>
                    </div>
                    <div class="summary-card absent">
                        <p class="card-title">Absent Days</p>
                        <p class="card-value"><?= $summary['absent_days'] ?? 0 ?></p>
                    </div> -->
                </div>
                
                <?php if (!empty($attendance_records)): ?>
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Temperature</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($record['status']) ?>">
                                            <?= $record['status'] ?>
                                        </span>
                                    </td>
                                    <td><?= $record['check_in'] ? date('g:i a', strtotime($record['check_in'])) : '-' ?></td>
                                    <td><?= $record['check_out'] ? date('g:i a', strtotime($record['check_out'])) : '-' ?></td>
                                    <td><?= $record['temperature'] ? $record['temperature'] . '°C' : '-' ?></td>
                                    <td><?= $record['notes'] ? nl2br(htmlspecialchars($record['notes'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-records">
                        <p>No attendance records found for this child.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <a href="parent_dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        <?php endif; ?>
    </div>
</body>
</html>