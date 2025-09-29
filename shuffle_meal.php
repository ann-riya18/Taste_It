<?php
// shuffle_meal.php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.html");
    exit();
}

// Connect to database (Assumes db.php is included or connection details are here)
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------- REQUIRED HELPER FUNCTION (Copied from what_to_cook.php) ----------
$current_timestamp = time();

/**
 * Determines the start date of the target week's Monday.
 * @param int $offset 0 for current, 1 for next.
 * @return string The Y-m-d date of the target Monday.
 */
function get_start_monday($offset = 0) {
    global $current_timestamp;

    // strtotime('monday this week') consistently gives the most recent past or current Monday (Y-m-d).
    $base_monday_timestamp = strtotime('monday this week', $current_timestamp);
    
    if ($offset === 0) {
        return date('Y-m-d', $base_monday_timestamp);
    }

    // The "Next Week" plan starts exactly 7 days after the base Monday.
    return date('Y-m-d', strtotime('+1 week', $base_monday_timestamp));
}
// ---------- END HELPER FUNCTION ----------


// 1. GET SELECTED WEEK OFFSET & CALCULATE SESSION KEY
$selected_week_offset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
if ($selected_week_offset < 0 || $selected_week_offset > 1) {
    $selected_week_offset = 0;
}

$start_monday_date = get_start_monday($selected_week_offset);
$session_key = "meal_plan_week_{$start_monday_date}";
$weekTitle = ($selected_week_offset === 0) ? 'Current Week Plan' : 'Next Week Plan';


// 2. LOCKING LOGIC: Next Week (offset 1) is always locked
$is_locked_view = ($selected_week_offset === 1);

if ($is_locked_view) {
    // If the week is locked, show an error and redirect to the meal plan.
    $_SESSION['error_message'] = "Cannot shuffle meals for the Next Week plan. Modifications are only allowed for the Current Week.";
    header("Location: what_to_cook.php?week=$selected_week_offset");
    exit();
}


// 3. CHECK MEAL PLAN EXISTENCE
if (!isset($_SESSION[$session_key]) || empty($_SESSION[$session_key])) {
    // Redirect to ensure the plan is generated first
    header("Location: what_to_cook.php?week=$selected_week_offset");
    exit();
}

// 4. HANDLE SWAP ACTION
if (isset($_GET['swap_day']) && isset($_GET['swap_course'])) {
    $swap_day = $_GET['swap_day'];
    $swap_course = $_GET['swap_course'];
    
    $meal_plan = $_SESSION[$session_key];
    $current_recipe_id = $meal_plan[$swap_day][$swap_course]['id'] ?? 0;
    
    // Get all recipe IDs in this week's plan to exclude them
    $existing_recipe_ids = [];
    foreach ($meal_plan as $day => $meals) {
        foreach ($meals as $course => $recipe) {
            if (isset($recipe['id']) && $recipe['id'] > 0) {
                $existing_recipe_ids[] = $recipe['id'];
            }
        }
    }
    
    // Create placeholders for prepared statement
    $placeholders = implode(',', array_fill(0, count($existing_recipe_ids), '?'));
    
    // Find a random approved recipe of the same course type, excluding existing ones
    $stmt = $conn->prepare("SELECT id, title, image_path, description, ingredients, diet 
                             FROM recipes 
                             WHERE course LIKE ? AND status = 'approved' 
                             AND id NOT IN ($placeholders)
                             ORDER BY RAND() LIMIT 1");
    
    // Bind parameters
    $types = 's' . str_repeat('i', count($existing_recipe_ids));
    $like_course = $swap_course . '%';
    $params = array_merge([$like_course], $existing_recipe_ids);
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $new_recipe = $result->fetch_assoc();
        
        // Update the session with the new recipe
        $_SESSION[$session_key][$swap_day][$swap_course] = $new_recipe;
        
        // Set success message
        $_SESSION['swap_message'] = "Swapped $swap_course with {$new_recipe['title']}";
    } else {
         $_SESSION['swap_message'] = "Could not find a unique recipe to swap for $swap_course.";
    }
    
    $stmt->close();
    
    // Redirect to remove URL parameters, maintaining the week offset
    header("Location: shuffle_meal.php?week=$selected_week_offset");
    exit();
}

// 5. GET MEAL PLAN FOR DISPLAY
$meal_plan = $_SESSION[$session_key];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shuffle Meal Plan | Taste It</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS maintained exactly as provided */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: url('https://images.unsplash.com/photo-1547592180-85f173990554?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.85);
            z-index: -1;
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h2 {
            color: #B0C364;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .meal-list {
            display: grid;
            gap: 15px;
        }
        
        .day-group {
            margin-bottom: 20px;
        }
        
        .day-label {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        .meal-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .meal-image {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 15px;
        }
        
        .meal-info {
            flex: 1;
        }
        
        .meal-type {
            display: inline-block;
            background: #B0C364;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .meal-name {
            font-weight: 500;
            color: #333;
        }
        
        .swap-btn {
            padding: 8px 15px;
            background: white;
            color: #B0C364;
            border: 2px solid #B0C364;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .swap-btn:hover {
            background: #B0C364;
            color: white;
        }
        
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: white;
            color: #B0C364;
            border: 2px solid #B0C364;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: #B0C364;
            color: white;
        }
    </style>
</head>
<body>
    <div class="overlay"></div>
    
    <div class="container">
        <a href="what_to_cook.php?week=<?php echo $selected_week_offset; ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Meal Plan
        </a>
        
        <div class="header">
            <h2><i class="fas fa-random"></i> Shuffle Meal Plan</h2>
            <p>Click swap to replace a meal in your <?php echo strtolower($weekTitle); ?>.</p>
        </div>
        
        <?php 
        // Display swap success/fail message
        if (isset($_SESSION['swap_message'])): ?>
            <div class="message">
                <?php echo $_SESSION['swap_message']; unset($_SESSION['swap_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="meal-list">
            <?php foreach ($meal_plan as $day => $meals): ?>
                <div class="day-group">
                    <div class="day-label"><?php echo date('l, F j', strtotime($day)); ?></div>
                    
                    <?php foreach ($meals as $course => $meal): ?>
                        <?php if (isset($meal['id']) && $meal['id'] > 0): ?>
                            <div class="meal-item">
                                <img src="<?php echo htmlspecialchars($meal['image_path'] ?: 'assets/no-recipe.png'); ?>" alt="" class="meal-image">
                                <div class="meal-info">
                                    <div class="meal-type"><?php echo $course; ?></div>
                                    <div class="meal-name"><?php echo htmlspecialchars($meal['title']); ?></div>
                                </div>
                                <a href="?week=<?php echo $selected_week_offset; ?>&swap_day=<?php echo $day; ?>&swap_course=<?php echo $course; ?>" class="swap-btn">
                                    <i class="fas fa-exchange-alt"></i> Swap
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>