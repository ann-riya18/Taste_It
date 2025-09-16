<?php
session_start();
include 'db.php';

$day = $_GET['day'] ?? '';
$course = $_GET['course'] ?? '';

if(!$day || !$course){
    echo json_encode(['error'=>'Invalid parameters']);
    exit;
}

// Get used recipes in this week
$used_recipes = [];
if(isset($_SESSION['meal_plan'])){
    foreach($_SESSION['meal_plan'] as $d => $courses){
        foreach($courses as $c => $recipe){
            $used_recipes[] = $recipe['id'];
        }
    }
}

// Fetch a random new recipe for this course not used this week
$sql = "SELECT id, title, description, image_path 
        FROM recipes 
        WHERE course='$course' AND status='approved' 
        ".(!empty($used_recipes) ? "AND id NOT IN (".implode(',',$used_recipes).")" : "")."
        ORDER BY RAND() LIMIT 1";

$result = $conn->query($sql);
if($result->num_rows > 0){
    $new_meal = $result->fetch_assoc();
    // Update session to include new recipe
    $_SESSION['meal_plan'][$day][$course] = $new_meal;
    echo json_encode($new_meal);
}else{
    echo json_encode(['error'=>'No more unique recipes available']);
}
?>
