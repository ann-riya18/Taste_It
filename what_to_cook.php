<?php
// what_to_cook.php

include 'db.php';
session_start();

// Days & Courses
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$courses = ['Breakfast','Lunch','Snacks','Dinner'];

// Fetch weekly meal plan
$meal_plan = []; 
foreach ($days as $day) {
    foreach ($courses as $course) {
        $query = "SELECT id, title, image_path FROM recipes WHERE course='$course' AND status='approved' ORDER BY RAND() LIMIT 1";
        $result = $conn->query($query);
        if ($result->num_rows > 0) {
            $meal_plan[$day][$course] = $result->fetch_assoc();
        } else {
            $meal_plan[$day][$course] = ['id'=>0, 'title'=>'No Recipe', 'image_path'=>'images/no-image.png'];
        }
    }
}

// Week info
$currentMonth = date("F");
$weekOfMonth = ceil(date("j") / 7); // week number inside current month
$dayOfWeek = date("l");
$nextWeekUnlocked = ($dayOfWeek === "Sunday");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>What To Cook - Meal Planner</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f8f9fa;
    }
    .sidebar {
      width: 200px;
      height: 100vh;
      background: #B0C364;
      color: #fff;
      position: fixed;
      top: 0; left: 0;
      padding: 20px 0;
    }
    .sidebar a {
      display: block;
      color: #fff;
      padding: 12px 20px;
      text-decoration: none;
    }
    .sidebar a:hover {
      background: rgba(255,255,255,0.2);
    }
    .content {
      margin-left: 220px;
      padding: 20px;
    }
    .meal-row {
      display: grid;
      grid-template-columns: 100px repeat(4, 1fr);
      gap: 15px;
      align-items: stretch;
      padding: 15px 0;
      border-bottom: 1px solid #ddd;
    }
    .day-label {
      font-weight: bold;
      display: flex;
      align-items: center;
    }
    .meal-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      padding: 10px;
      text-align: center;
      transition: transform 0.2s;
      cursor: pointer;
    }
    .meal-card:hover {
      transform: translateY(-3px);
    }
    .meal-card img {
      max-width: 100%;
      max-height: 120px;
      object-fit: contain;
      border-radius: 8px;
      margin-bottom: 6px;
      background: #f9f9f9;
      padding: 4px;
    }
    .meal-course {
      font-size: 13px;
      font-weight: 600;
      color: #B0C364;
      margin-bottom: 4px;
    }
    .meal-title {
      font-size: 14px;
      font-weight: 500;
      color: #333;
    }
    .blurred {
      filter: blur(5px);
      pointer-events: none;
      opacity: 0.7;
    }
    .grocery-checklist input {
      margin-right: 10px;
    }
  </style>
</head>
<body>

<div class="sidebar">
  <h4 class="text-center mb-4">Meal Planner</h4>
  <a href="what_to_cook.php">Home</a>
  <a href="#" id="groceryListBtn">Grocery List</a>
  <a href="#" id="shuffleBtn">Shuffle</a>
  <a href="logout.php">Logout</a>
</div>

<div class="content">
  <h2 class="mb-4"><?php echo $currentMonth; ?> - Week <?php echo $weekOfMonth; ?> Meal Plan</h2>

  <?php foreach ($days as $day): ?>
    <div class="meal-row">
      <div class="day-label"><?php echo $day; ?></div>
      <?php foreach ($courses as $course): 
        $recipe = $meal_plan[$day][$course]; ?>
        <div class="meal-card" onclick="window.location.href='view_recipe.php?id=<?php echo $recipe['id']; ?>'">
          <div class="meal-course"><?php echo $course; ?></div>
          <img src="<?php echo $recipe['image_path']; ?>" alt="Food">
          <div class="meal-title"><?php echo $recipe['title']; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <!-- Shuffle Modal -->
  <div class="modal fade" id="shuffleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content p-3">
        <h5>Select a Recipe to Swap</h5>
        <ul>
          <?php foreach ($days as $day): ?>
            <?php foreach ($courses as $course): 
              $recipe = $meal_plan[$day][$course]; ?>
              <li>
                <a href="swap_recipe.php?day=<?php echo $day; ?>&course=<?php echo $course; ?>&id=<?php echo $recipe['id']; ?>">
                  <?php echo "$day - $course : " . $recipe['title']; ?>
                </a>
              </li>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Grocery Modal -->
  <div class="modal fade" id="groceryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content p-3">
        <h5>Grocery List (Week <?php echo $weekOfMonth + 1; ?>)</h5>
        <?php if ($nextWeekUnlocked): ?>
          <form class="grocery-checklist">
            <div><input type="checkbox"> Rice</div>
            <div><input type="checkbox"> Vegetables</div>
            <div><input type="checkbox"> Milk</div>
            <div><input type="checkbox"> Fruits</div>
          </form>
        <?php else: ?>
          <p class="text-muted">Next week’s grocery list will be available on Sunday.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('shuffleBtn').addEventListener('click', function(){
    var modal = new bootstrap.Modal(document.getElementById('shuffleModal'));
    modal.show();
  });
  document.getElementById('groceryListBtn').addEventListener('click', function(){
    var modal = new bootstrap.Modal(document.getElementById('groceryModal'));
    modal.show();
  });
</script>

</body>
</html>
