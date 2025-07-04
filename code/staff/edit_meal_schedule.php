<?php
include '../db_connect.php'; // Corrected path

if (!isStaff() && !isAdmin()) {
    header("Location: login.php");
    exit;
}
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isStaff() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'staff';
}

$scheduleId = (int)$_GET['id'];
$staffId = $_SESSION['user_id'];

// Get schedule info
$schedule = $db->query("
    SELECT * FROM meal_schedules 
    WHERE id = $scheduleId
")->fetch();

if (!$schedule) {
    header("Location: meal_schedules.php");
    exit;
}

// Check if published (read-only if published)
$isPublished = (bool)$schedule['is_published'];

// Get all meals for this schedule
$meals = $db->query("
    SELECT * FROM daily_meals
    WHERE schedule_id = $scheduleId
    ORDER BY 
        FIELD(day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
        FIELD(meal_type, 'Breakfast','Morning Snack','Lunch','Afternoon Snack','Dinner')
")->fetchAll();

// Get favorite meals
$favorites = $db->query("
    SELECT * FROM meal_favorites
    WHERE created_by = $staffId
    ORDER BY name
")->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isPublished) {
    if (isset($_POST['save_meal'])) {
        $day = $_POST['day'];
        $mealType = $_POST['meal_type'];
        $menuItem = cleanInput($_POST['menu_item']);
        $description = cleanInput($_POST['description']);
        $allergens = cleanInput($_POST['allergens']);
        $nutrition = cleanInput($_POST['nutrition_info']);
        
        // Check if meal already exists for this day/type
        $existing = $db->query("
            SELECT id FROM daily_meals 
            WHERE schedule_id = $scheduleId 
            AND day = '$day' 
            AND meal_type = '$mealType'
        ")->fetch();
        
        if ($existing) {
            // Update existing
            $stmt = $db->prepare("
                UPDATE daily_meals SET 
                menu_item = ?, 
                description = ?, 
                allergens = ?, 
                nutrition_info = ?
                WHERE id = ?
            ");
            $stmt->execute([$menuItem, $description, $allergens, $nutrition, $existing['id']]);
        } else {
            // Insert new
            $stmt = $db->prepare("
                INSERT INTO daily_meals 
                (schedule_id, day, meal_type, menu_item, description, allergens, nutrition_info)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$scheduleId, $day, $mealType, $menuItem, $description, $allergens, $nutrition]);
        }
        
        $success = "Meal saved successfully!";
    }
    
    if (isset($_POST['save_favorite'])) {
        $name = cleanInput($_POST['favorite_name']);
        $menuItem = cleanInput($_POST['favorite_item']);
        $description = cleanInput($_POST['favorite_description']);
        $allergens = cleanInput($_POST['favorite_allergens']);
        $nutrition = cleanInput($_POST['favorite_nutrition']);
        
        $stmt = $db->prepare("
            INSERT INTO meal_favorites 
            (name, description, allergens, nutrition_info, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $menuItem, $description, $allergens, $nutrition, $staffId]);
        
        $favoriteSuccess = "Meal added to favorites!";
        header("Location: edit_meal_schedule.php?id=$scheduleId#favorites");
        exit;
    }
}

// Generate days of the week for the current schedule
$daysOfWeek = [];
$currentDate = new DateTime($schedule['week_start_date']);
for ($i = 0; $i < 7; $i++) {
    $daysOfWeek[] = [
        'name' => $currentDate->format('l'),
        'date' => $currentDate->format('Y-m-d')
    ];
    $currentDate->modify('+1 day');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($schedule['title']) ?> - Edit Meal Schedule</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .meal-plan { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .day-card { background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .day-header { border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        .meal-item { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #eee; }
        .meal-item:last-child { border-bottom: none; }
        .meal-type { font-weight: 500; color: #2196F3; margin-bottom: 5px; }
        .menu-item { font-weight: bold; }
        .meal-description { color: #666; font-size: 0.9em; margin: 5px 0; }
        .allergens { font-size: 0.8em; color: #F44336; background: #FFEBEE; padding: 2px 5px; border-radius: 3px; display: inline-block; }
        .nutrition { font-size: 0.8em; color: #4CAF50; }
        .edit-form { background: #E3F2FD; padding: 15px; border-radius: 5px; margin-top: 10px; }
        .form-group { margin-bottom: 10px; }
        label { display: block; font-size: 0.9em; margin-bottom: 3px; color: #555; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        textarea { min-height: 60px; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #2196F3; color: white; }
        .btn-secondary { background: #4CAF50; color: white; }
        .btn-danger { background: #F44336; color: white; }
        .favorites-section { background: white; padding: 20px; border-radius: 8px; margin-top: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .favorites-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-top: 15px; }
        .favorite-card { background: #FFF9C4; padding: 10px; border-radius: 5px; }
        .readonly { background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= htmlspecialchars($schedule['title']) ?></h1>
            <p>Week of <?= date('F j, Y', strtotime($schedule['week_start_date'])) ?> to <?= date('F j, Y', strtotime($schedule['week_end_date'])) ?></p>
            <?php if ($isPublished): ?>
                <p style="color: #4CAF50;">This schedule has been published and is now read-only.</p>
            <?php else: ?>
                <p style="color: #FF9800;">This schedule is in draft mode.</p>
            <?php endif; ?>
            <a href="meal_schedules.php" style="color: #2196F3; text-decoration: none;">&larr; Back to all schedules</a>
        </div>
        
        <?php if (isset($success)): ?>
            <div style="background: #E8F5E9; color: #4CAF50; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
                <?= $success ?>
            </div>
        <?php endif; ?>
        
        <div class="meal-plan">
            <?php foreach ($daysOfWeek as $day): ?>
                <div class="day-card">
                    <div class="day-header">
                        <h2><?= $day['name'] ?></h2>
                        <p><?= date('M j', strtotime($day['date'])) ?></p>
                    </div>
                    
                    <?php 
                    $mealTypes = ['Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner'];
                    foreach ($mealTypes as $mealType): 
                        // Find existing meal for this day/type
                        $meal = null;
                        foreach ($meals as $m) {
                            if ($m['day'] === $day['name'] && $m['meal_type'] === $mealType) {
                                $meal = $m;
                                break;
                            }
                        }
                    ?>
                        <div class="meal-item">
                            <div class="meal-type"><?= $mealType ?></div>
                            
                            <?php if ($meal): ?>
                                <div class="menu-item"><?= htmlspecialchars($meal['menu_item']) ?></div>
                                <?php if (!empty($meal['description'])): ?>
                                    <div class="meal-description"><?= htmlspecialchars($meal['description']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($meal['allergens'])): ?>
                                    <div class="allergens">Allergens: <?= htmlspecialchars($meal['allergens']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($meal['nutrition_info'])): ?>
                                    <div class="nutrition">Nutrition: <?= htmlspecialchars($meal['nutrition_info']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="color: #999; font-style: italic;">No meal scheduled</div>
                            <?php endif; ?>
                            
                            <?php if (!$isPublished): ?>
                                <button onclick="openMealForm('<?= $day['name'] ?>', '<?= $mealType ?>')" 
                                        style="margin-top: 10px; background: #2196F3; color: white; border: none; padding: 5px 10px; border-radius: 3px; font-size: 0.8em;">
                                    <?= $meal ? 'Edit' : 'Add' ?> Meal
                                </button>
                                
                                <div id="form-<?= md5($day['name'].$mealType) ?>" class="edit-form" style="display: none;">
                                    <form method="POST">
                                        <input type="hidden" name="day" value="<?= $day['name'] ?>">
                                        <input type="hidden" name="meal_type" value="<?= $mealType ?>">
                                        
                                        <div class="form-group">
                                            <label for="menu_item">Menu Item</label>
                                            <input type="text" name="menu_item" id="menu_item" 
                                                   value="<?= $meal ? htmlspecialchars($meal['menu_item']) : '' ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="description">Description</label>
                                            <textarea name="description" id="description"><?= $meal ? htmlspecialchars($meal['description']) : '' ?></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="allergens">Allergens</label>
                                            <input type="text" name="allergens" id="allergens" 
                                                   value="<?= $meal ? htmlspecialchars($meal['allergens']) : '' ?>"
                                                   placeholder="e.g., Contains nuts, dairy">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="nutrition_info">Nutrition Info</label>
                                            <input type="text" name="nutrition_info" id="nutrition_info" 
                                                   value="<?= $meal ? htmlspecialchars($meal['nutrition_info']) : '' ?>"
                                                   placeholder="e.g., High protein, vegetarian">
                                        </div>
                                        
                                        <button type="submit" name="save_meal" class="btn btn-primary">Save</button>
                                        <button type="button" onclick="closeMealForm('<?= $day['name'] ?>', '<?= $mealType ?>')" 
                                                class="btn btn-secondary">Cancel</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="favorites-section" id="favorites">
            <h2>My Favorite Meals</h2>
            <p>Save frequently used meals to quickly add them to schedules</p>
            
            <?php if (isset($favoriteSuccess)): ?>
                <div style="background: #E8F5E9; color: #4CAF50; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                    <?= $favoriteSuccess ?>
                </div>
            <?php endif; ?>
            
            <div class="favorites-grid">
                <?php foreach ($favorites as $favorite): ?>
                    <div class="favorite-card">
                        <h3><?= htmlspecialchars($favorite['name']) ?></h3>
                        <p><strong><?= htmlspecialchars($favorite['description']) ?></strong></p>
                        <?php if (!empty($favorite['allergens'])): ?>
                            <p class="allergens">Allergens: <?= htmlspecialchars($favorite['allergens']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($favorite['nutrition_info'])): ?>
                            <p class="nutrition">Nutrition: <?= htmlspecialchars($favorite['nutrition_info']) ?></p>
                        <?php endif; ?>
                        
                        <?php if (!$isPublished): ?>
                            <button onclick="useFavorite(
                                '<?= htmlspecialchars($favorite['name'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($favorite['description'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($favorite['allergens'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($favorite['nutrition_info'], ENT_QUOTES) ?>'
                            )" style="margin-top: 10px; background: #4CAF50; color: white; border: none; padding: 5px 10px; border-radius: 3px; font-size: 0.8em;">
                                Use This Meal
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!$isPublished): ?>
                <div style="margin-top: 30px; background: #E3F2FD; padding: 15px; border-radius: 5px;">
                    <h3>Add New Favorite Meal</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="favorite_name">Favorite Name</label>
                            <input type="text" name="favorite_name" id="favorite_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="favorite_item">Menu Item</label>
                            <input type="text" name="favorite_item" id="favorite_item" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="favorite_description">Description</label>
                            <textarea name="favorite_description" id="favorite_description"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="favorite_allergens">Allergens</label>
                            <input type="text" name="favorite_allergens" id="favorite_allergens">
                        </div>
                        
                        <div class="form-group">
                            <label for="favorite_nutrition">Nutrition Info</label>
                            <input type="text" name="favorite_nutrition" id="favorite_nutrition">
                        </div>
                        
                        <button type="submit" name="save_favorite" class="btn btn-primary">Save Favorite</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function openMealForm(day, mealType) {
            const formId = `form-${md5(day+mealType)}`;
            document.getElementById(formId).style.display = 'block';
        }
        
        function closeMealForm(day, mealType) {
            const formId = `form-${md5(day+mealType)}`;
            document.getElementById(formId).style.display = 'none';
        }
        
        function useFavorite(name, description, allergens, nutrition) {
            // Find the first visible form
            const form = document.querySelector('.edit-form[style="display: block;"]');
            if (form) {
                form.querySelector('[name="menu_item"]').value = name;
                form.querySelector('[name="description"]').value = description;
                form.querySelector('[name="allergens"]').value = allergens;
                form.querySelector('[name="nutrition_info"]').value = nutrition;
            } else {
                alert('Please open a meal form first to add this favorite.');
            }
        }
        
        // Simple MD5 hash for unique IDs
        function md5(string) {
            return string.split('').reduce((acc, char) => {
                return acc + char.charCodeAt(0);
            }, 0);
        }
    </script>
</body>
</html>