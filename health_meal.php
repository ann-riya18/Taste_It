<?php
session_start();
include 'db.php'; // Your database connection

// Define weeks, days, courses
$weeks = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$courses = ['Breakfast','Lunch','Snacks','Dinner'];

// Diet options
$all_diets = ['Gluten-Free','Lactose-Free','Sugar-Free','High-Protein','Low-Fat','Low-Carb'];

// Function to fetch a recipe for a given course + diet conditions
function get_health_recipe($conn, $course, $selected_diets) {
    $diet_condition_sql = [];
    foreach ($selected_diets as $diet) {
        $diet_condition_sql[] = "FIND_IN_SET('$diet', diet)";
    }
    $diet_sql = implode(' AND ', $diet_condition_sql);

    $sql = "SELECT * FROM recipes 
            WHERE course='$course' AND $diet_sql
            ORDER BY RAND() 
            LIMIT 1";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

// Example: Select any 2 diet types for health meals
$selected_diets = ['Gluten-Free','Low-Carb']; // You can change this dynamically

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Health Meal Planner</title>
<link rel="stylesheet" href="style.css"> <!-- your main CSS -->
<style>
    body { font-family: 'Poppins', sans-serif; background-color: #f5f5f5; margin: 0; }
    .side-panel { width: 220px; background: #B0C364; height: 100vh; position: fixed; }
    .side-panel a { display: block; color: #fff; padding: 15px; text-decoration: none; }
    .side-panel a:hover { background: #a3b256; }
    .top-panel { height: 60px; background: #B0C364; padding-left: 240px; display: flex; align-items: center; color: #fff; font-size: 20px; }
    .main { margin-left: 220px; padding: 20px; }
    .week-tabs { display: flex; gap: 10px; margin-bottom: 20px; }
    .week-tabs button { padding: 10px 20px; border: none; background: #B0C364; color: #fff; cursor: pointer; border-radius: 5px; }
    .week-tabs button.active { background: #8fae44; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: top; }
    .recipe-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 0 8px rgba(0,0,0,0.1); margin: 5px 0; }
    .recipe-card img { width: 100%; height: 120px; object-fit: cover; }
    .recipe-card .title { padding: 5px; font-weight: bold; }
</style>
</head>
<body>

<div class="side-panel">
    <a href="dashboard.php">Dashboard</a>
    <a href="what_to_cook.php">What to Cook</a>
    <a href="health_meal.php">Health Meal Planner</a>
    <a href="grocery_list.php">Grocery List</a>
</div>

<div class="top-panel">
    Health Meal Planner
</div>

<div class="main">
    <div class="week-tabs">
        <?php foreach($weeks as $index => $week): ?>
            <button class="<?= $index===0?'active':'' ?>" onclick="showWeek(<?= $index ?>)"><?= $week ?></button>
        <?php endforeach; ?>
    </div>

    <?php foreach($weeks as $w_index => $week): ?>
    <div class="week-table" id="week-<?= $w_index ?>" style="display: <?= $w_index===0?'block':'none' ?>;">
        <table>
            <tr>
                <th>Day / Course</th>
                <?php foreach($courses as $course): ?>
                    <th><?= $course ?></th>
                <?php endforeach; ?>
            </tr>
            <?php foreach($days as $day): ?>
                <tr>
                    <td><?= $day ?></td>
                    <?php foreach($courses as $course): ?>
                        <?php $recipe = get_health_recipe($conn, $course, $selected_diets); ?>
                        <td>
                            <?php if($recipe): ?>
                                <div class="recipe-card">
                                    <img src="<?= $recipe['image_path'] ?>" alt="<?= $recipe['title'] ?>">
                                    <div class="title"><?= $recipe['title'] ?></div>
                                </div>
                            <?php else: ?>
                                <div>No recipe</div>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>
</div>

<script>
function showWeek(index) {
    document.querySelectorAll('.week-table').forEach((el, i) => el.style.display = i===index ? 'block' : 'none');
    document.querySelectorAll('.week-tabs button').forEach((btn, i) => btn.classList.toggle('active', i===index));
}
</script>

</body>
</html>
