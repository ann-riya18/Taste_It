<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Login required.");
}

$user_id = $_SESSION['user_id'];
$week_number = date("W"); // ISO week number

// Check if already shuffled this week
$check = $conn->prepare("SELECT * FROM shuffle_actions WHERE user_id=? AND week_number=?");
$check->bind_param("ii", $user_id, $week_number);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo "<div class='alert alert-warning text-center m-3'>
            <strong>Note:</strong> You have already shuffled meals for this week.
          </div>";
} else {
    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $courses = ['Breakfast','Lunch','Snacks','Dinner'];

    foreach ($days as $day) {
        foreach ($courses as $course) {
            $query = $conn->query("SELECT id FROM recipes WHERE course='$course' ORDER BY RAND() LIMIT 1");
            if ($query->num_rows > 0) {
                $recipe = $query->fetch_assoc();
                $recipe_id = $recipe['id'];
                // Save into meal plan
                $conn->query("UPDATE meal_plans 
                              SET recipe_id=$recipe_id 
                              WHERE user_id=$user_id AND week_number=$week_number AND day='$day' AND course='$course'");
            }
        }
    }

    // Save shuffle action
    $insert = $conn->prepare("INSERT INTO shuffle_actions (user_id, week_number) VALUES (?, ?)");
    $insert->bind_param("ii", $user_id, $week_number);
    $insert->execute();

    echo "<div class='alert alert-success text-center m-3'>
            Meals successfully shuffled for this week!
          </div>";
}
?>
