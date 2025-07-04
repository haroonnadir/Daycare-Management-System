<?php
session_start();
include '../db_connect.php';

// Verify admin/staff access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: login.php");
    exit();
}

// Set default date to today
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $child_id = filter_input(INPUT_POST, 'child_id', FILTER_VALIDATE_INT);
    $action = $_POST['action']; // 'check_in' or 'check_out'
    $staff_id = $_SESSION['user_id'];
    
    if ($child_id && in_array($action, ['check_in', 'check_out'])) {
        try {
            $conn->begin_transaction();
            
            if ($action === 'check_in') {
                // Get additional check-in data
                $temperature = filter_input(INPUT_POST, 'temperature', FILTER_VALIDATE_FLOAT);
                $health_notes = filter_input(INPUT_POST, 'health_notes', FILTER_SANITIZE_STRING);
                $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
                $status = 'Present'; // Default status
                
                // Validate temperature
                if ($temperature === false || $temperature < 35 || $temperature > 41) {
                    throw new Exception("Please enter a valid temperature between 35°C and 41°C");
                }
                
                // Check if already checked in today
                $check_query = "SELECT id FROM attendance WHERE child_id = ? AND date = ?";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param("is", $child_id, $current_date);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("Child already has attendance record for today");
                }
                
                // Record check-in with all data
                $query = "INSERT INTO attendance (
                            child_id, date, check_in, checked_in_by, 
                            temperature, health_notes, notes, status
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param(
                    "isssdsss", 
                    $child_id, $current_date, $current_time, $staff_id,
                    $temperature, $health_notes, $notes, $status
                );
                $stmt->execute();
                
                $_SESSION['success'] = "Check-in recorded successfully!";
                
            } elseif ($action === 'check_out') {
                // Get check-out notes
                $checkout_notes = filter_input(INPUT_POST, 'checkout_notes', FILTER_SANITIZE_STRING);
                $pickup_person = filter_input(INPUT_POST, 'pickup_person', FILTER_SANITIZE_STRING);
                
                // Verify child was checked in today and not checked out
                $check_query = "SELECT id, check_in FROM attendance 
                               WHERE child_id = ? AND date = ? 
                               AND check_in IS NOT NULL AND check_out IS NULL";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param("is", $child_id, $current_date);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    throw new Exception("Child not checked in or already checked out");
                }
                
                // Update check-out information
                $query = "UPDATE attendance SET 
                            check_out = ?, 
                            checked_out_by = ?,
                            pickup_person = ?,
                            notes = CONCAT(IFNULL(notes, ''), IFNULL(CONCAT('\nCheck-out: ', ?), '')),
                            status = CASE 
                                WHEN TIMESTAMPDIFF(MINUTE, check_in, ?) < 30 THEN 'Late'
                                WHEN TIMESTAMPDIFF(HOUR, check_in, ?) < 4 THEN 'Half-day'
                                ELSE 'Present'
                            END
                          WHERE child_id = ? AND date = ?";
                $stmt = $conn->prepare($query);
                // $stmt->bind_param(
                //     "sisssis", 
                //     $current_time, $staff_id, $pickup_person, $checkout_notes,
                //     $current_time, $current_time, $child_id, $current_date
                // );
                $stmt->bind_param(
                    "sissssis", 
                    $current_time, $staff_id, $pickup_person, $checkout_notes,
                    $current_time, $current_time, $child_id, $current_date
                );

                $stmt->execute();
                
                $_SESSION['success'] = "Check-out recorded successfully!";
            }
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
    }
    header("Location: checkin_checkout.php");
    exit();
}

// Get children who are currently checked in (for checkout list)
$checked_in_query = "SELECT c.id, c.first_name, c.last_name, a.check_in, 
                     a.temperature, a.health_notes, a.status
                     FROM children c
                     JOIN attendance a ON c.id = a.child_id
                     WHERE a.date = ? AND a.check_out IS NULL
                     ORDER BY a.check_in";
$stmt = $conn->prepare($checked_in_query);
$stmt->bind_param("s", $current_date);
$stmt->execute();
$checked_in_children = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all children (for checkin list) - REMOVED THE STATUS FILTER
$all_children_query = "SELECT c.id, c.first_name, c.last_name 
                       FROM children c
                       ORDER BY c.first_name, c.last_name";
$all_children = $conn->query($all_children_query)->fetch_all(MYSQLI_ASSOC);

// Get today's attendance summary
$summary_query = "SELECT 
                    (SELECT COUNT(*) FROM children) as total_children,
                    SUM(check_in IS NOT NULL) as checked_in,
                    SUM(check_out IS NOT NULL) as checked_out,
                    SUM(status = 'Present') as present,
                    SUM(status = 'Late') as late,
                    SUM(status = 'Half-day') as half_day
                  FROM attendance
                  WHERE date = ?";
$stmt = $conn->prepare($summary_query);
$stmt->bind_param("s", $current_date);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Calculate absent children (children not checked in today)
$absent_query = "SELECT COUNT(*) as absent_count 
                 FROM children c
                 WHERE c.id NOT IN (
                     SELECT child_id FROM attendance WHERE date = ? AND check_in IS NOT NULL
                 )";
$stmt = $conn->prepare($absent_query);
$stmt->bind_param("s", $current_date);
$stmt->execute();
$absent_count = $stmt->get_result()->fetch_assoc()['absent_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Check-In/Out | Daycare Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #2c3e50;
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
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-back {
            background-color: var(--primary-color);
            color: var(--white);
            text-decoration: none;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            box-shadow: var(--box-shadow);
        }

        .btn-back:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            opacity: 0.9;
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
        
        .date-display {
            background-color: var(--white);
            padding: 10px 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .error, .success {
            padding: 12px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error {
            background-color: #fde8e8;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }
        
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
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
        
        .summary-card.checked-out {
            border-top: 4px solid var(--warning-color);
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
        
        .section {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }
        
        .section-title {
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .child-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .child-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid var(--primary-color);
            transition: var(--transition);
        }
        
        .child-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .child-info {
            flex: 1;
        }
        
        .child-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .check-in-time {
            font-size: 0.85rem;
            color: var(--gray-color);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn-checkin {
            background-color: var(--success-color);
            color: var(--white);
        }
        
        .btn-checkout {
            background-color: var(--warning-color);
            color: var(--white);
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-present { background-color: #e6f7ee; color: #00a854; }
        .status-absent { background-color: #fff1f0; color: #f5222d; }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
            padding: 20px;
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 50px auto;
            padding: 25px;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal h2 {
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-group input, 
        .form-group textarea, 
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            transition: var(--transition);
        }
        
        .form-group input:focus, 
        .form-group textarea:focus, 
        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
        }
        
        .no-children {
            text-align: center;
            padding: 20px;
            color: var(--gray-color);
            font-style: italic;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .child-list {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: 20px auto;
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1><i class="fas fa-calendar-check"></i> Child Check-In/Out</h1>
            <a href="admin_dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        <div class="date-display">
            <i class="fas fa-calendar-day"></i> <?= date('l, F j, Y') ?>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="summary-cards">
            <div class="summary-card total">
                <p class="card-title">Total Children</p>
                <p class="card-value"><?= $summary['total_children'] ?? 0 ?></p>
            </div>
            <div class="summary-card present">
                <p class="card-title">Present Today</p>
                <p class="card-value"><?= $summary['present'] ?? 0 ?></p>
            </div>
            <div class="summary-card checked-out">
                <p class="card-title">Checked Out</p>
                <p class="card-value"><?= $summary['checked_out'] ?? 0 ?></p>
            </div>
            <div class="summary-card absent">
                <p class="card-title">Absent</p>
                <p class="card-value"><?= $absent_count ?? 0 ?></p>
            </div>
            <!-- <div class="summary-card" style="border-top: 4px solid #f8961e;">
                <p class="card-title">Late Arrivals</p>
                <p class="card-value"><?= $summary['late'] ?? 0 ?></p>
            </div>
            <div class="summary-card" style="border-top: 4px solid #4895ef;">
                <p class="card-title">Half-day</p>
                <p class="card-value"><?= $summary['half_day'] ?? 0 ?></p>
            </div> -->
        </div>
        
        <div class="section">
            <h2 class="section-title"><i class="fas fa-sign-in-alt"></i> Check In Children</h2>
            
            <?php if (empty($all_children)): ?>
                <p class="no-children">No active children enrolled in the system</p>
            <?php else: ?>
                <div class="child-list">
                    <?php foreach ($all_children as $child): 
                        // Check if child is already checked in today
                        $is_checked_in = false;
                        $status = '';
                        $check_in_time = '';
                        $temperature = '';
                        
                        foreach ($checked_in_children as $checked_in) {
                            if ($checked_in['id'] == $child['id']) {
                                $is_checked_in = true;
                                $status = $checked_in['status'];
                                $check_in_time = date('g:i a', strtotime($checked_in['check_in']));
                                $temperature = $checked_in['temperature'];
                                break;
                            }
                        }
                    ?>
                        <div class="child-card">
                            <div class="child-info">
                                <div class="child-name"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></div>
                                <?php if ($is_checked_in): ?>
                                    <div class="check-in-time">
                                        <span class="status-badge status-<?= strtolower($status) ?>">
                                            <?= $status ?>
                                        </span>
                                        <?php if ($temperature): ?>
                                            <span><i class="fas fa-temperature-low"></i> <?= $temperature ?>°C</span>
                                        <?php endif; ?>
                                        <i class="fas fa-clock"></i> Checked in at <?= $check_in_time ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!$is_checked_in): ?>
                                <button type="button" class="btn btn-checkin" onclick="openCheckInModal(<?= $child['id'] ?>, '<?= htmlspecialchars(addslashes($child['first_name'] . ' ' . $child['last_name'])) ?>')">
                                    <i class="fas fa-sign-in-alt"></i> Check In
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2 class="section-title"><i class="fas fa-sign-out-alt"></i> Check Out Children</h2>
            
            <?php if (empty($checked_in_children)): ?>
                <p class="no-children">No children currently checked in</p>
            <?php else: ?>
                <div class="child-list">
                    <?php foreach ($checked_in_children as $child): ?>
                        <div class="child-card">
                            <div class="child-info">
                                <div class="child-name"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></div>
                                <div class="check-in-time">
                                    <span class="status-badge status-<?= strtolower($child['status']) ?>">
                                        <?= $child['status'] ?>
                                    </span>
                                    <?php if ($child['temperature']): ?>
                                        <span><i class="fas fa-temperature-low"></i> <?= $child['temperature'] ?>°C</span>
                                    <?php endif; ?>
                                    <?php if ($child['health_notes']): ?>
                                        <span><i class="fas fa-notes-medical"></i> Health notes</span>
                                    <?php endif; ?>
                                    <i class="fas fa-clock"></i> Checked in at <?= date('g:i a', strtotime($child['check_in'])) ?>
                                </div>
                            </div>
                            <button type="button" class="btn btn-checkout" onclick="openCheckOutModal(<?= $child['id'] ?>, '<?= htmlspecialchars(addslashes($child['first_name'] . ' ' . $child['last_name'])) ?>')">
                                <i class="fas fa-sign-out-alt"></i> Check Out
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        

    </div>
    
    <!-- Check-In Modal -->
    <div id="checkInModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-sign-in-alt"></i> Check In Child</h2>
            <form id="checkInForm" method="POST">
                <input type="hidden" name="child_id" id="modal_child_id">
                <input type="hidden" name="action" value="check_in">
                
                <div class="form-group">
                    <label for="child_name">Child Name</label>
                    <input type="text" id="child_name" readonly>
                </div>
                
                <div class="form-group">
                    <label for="temperature">Temperature (°C) <span class="required">*</span></label>
                    <input type="number" name="temperature" id="temperature" step="0.1" min="35" max="41" required>
                    <small class="form-text">Normal range: 36.5°C - 37.5°C</small>
                </div>
                
                <div class="form-group">
                    <label for="health_notes">Health Notes</label>
                    <textarea name="health_notes" id="health_notes" rows="3" placeholder="Any health concerns, medications, etc."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea name="notes" id="notes" rows="3" placeholder="Any special instructions or observations"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-back" onclick="closeModal('checkInModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-checkin">
                        <i class="fas fa-sign-in-alt"></i> Check In
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Check-Out Modal -->
    <div id="checkOutModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-sign-out-alt"></i> Check Out Child</h2>
            <form id="checkOutForm" method="POST">
                <input type="hidden" name="child_id" id="checkout_child_id">
                <input type="hidden" name="action" value="check_out">
                
                <div class="form-group">
                    <label for="checkout_child_name">Child Name</label>
                    <input type="text" id="checkout_child_name" readonly>
                </div>
                
                <div class="form-group">
                    <label for="pickup_person">Pickup Person <span class="required">*</span></label>
                    <input type="text" name="pickup_person" id="pickup_person" required 
                        placeholder="Name of person picking up the child"
                        minlength="2" maxlength="50"
                        pattern="[A-Za-z ]+" title="Only letters and spaces allowed">
                    <datalist id="regular_pickup_persons">
                        <!-- This will be populated from common pickup persons -->
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label for="checkout_notes">Check-out Notes</label>
                    <textarea name="checkout_notes" id="checkout_notes" rows="3" placeholder="Any observations or notes for parents"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-back" onclick="closeModal('checkOutModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-checkout">
                        <i class="fas fa-sign-out-alt"></i> Check Out
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Open modals
        function openCheckInModal(childId, childName) {
            document.getElementById('modal_child_id').value = childId;
            document.getElementById('child_name').value = childName;
            document.getElementById('checkInModal').style.display = 'block';
            document.getElementById('temperature').focus();
        }
        
        function openCheckOutModal(childId, childName) {
            document.getElementById('checkout_child_id').value = childId;
            document.getElementById('checkout_child_name').value = childName;
            document.getElementById('checkOutModal').style.display = 'block';
            document.getElementById('pickup_person').focus();
        }
        
        // Close modals
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const openModals = document.querySelectorAll('.modal[style="display: block;"]');
                openModals.forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
        
        // Form validation
        document.getElementById('checkInForm').addEventListener('submit', function(e) {
            const tempInput = document.getElementById('temperature');
            const tempValue = parseFloat(tempInput.value);
            
            if (isNaN(tempValue) || tempValue < 35 || tempValue > 41) {
                e.preventDefault();
                alert('Please enter a valid temperature between 35°C and 41°C');
                tempInput.focus();
            }
        });
    </script>
</body>
</html>