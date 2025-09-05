<?php
session_start();
include 'db.php'; 

$user_id = $_SESSION['user_id'] ?? 1; // fallback for demo
$planner_type = $_GET['planner'] ?? 'health'; // 'health' or 'what_to_cook'
$action = $_GET['action'] ?? 'fetch';

$week_start = date('Y-m-d', strtotime('monday this week'));

if ($action === 'shuffle') {
    $day = $_GET['day'];
    $meal_type = $_GET['meal_type'];

    // Pick a random recipe
    $recipe = $conn->query("SELECT id,title,image_path FROM recipes ORDER BY RAND() LIMIT 1")->fetch_assoc();

    // Update meal_plans
    $stmt = $conn->prepare("UPDATE meal_plans 
        SET recipe_id=? 
        WHERE user_id=? AND planner_type=? AND week_start=? AND day_of_week=? AND meal_type=?");
    $stmt->bind_param("iissss", $recipe['id'], $user_id, $planner_type, $week_start, $day, $meal_type);
    $stmt->execute();

    echo json_encode($recipe);
    exit;
}

// Fetch full plan
$sql = "SELECT mp.day_of_week, mp.meal_type, r.id as recipe_id, r.title, r.image_path
        FROM meal_plans mp 
        JOIN recipes r ON mp.recipe_id = r.id
        WHERE mp.user_id=? AND mp.planner_type=? AND mp.week_start=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $planner_type, $week_start);
$stmt->execute();
$result = $stmt->get_result();

$plan = [];
while ($row = $result->fetch_assoc()) {
    $plan[$row['day_of_week']][$row['meal_type']] = $row;
}

// Define days & meal types
$days = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
$meal_types = ["Breakfast","Lunch","Dinner"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Meal Planner</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #f8f8f8;
        padding: 20px;
    }
    h1 {
        text-align: center;
        margin-bottom: 20px;
        color: #333;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin: auto;
        background: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    th, td {
        border: 1px solid #ddd;
        padding: 15px;
        text-align: center;
        vertical-align: middle;
    }
    th {
        background: #B0C364;
        color: white;
    }
    .meal-card {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .meal-card img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 8px;
    }
    .shuffle-btn {
        background: #B0C364;
        border: none;
        padding: 6px 10px;
        color: white;
        font-size: 12px;
        cursor: pointer;
        border-radius: 6px;
    }
    .shuffle-btn:hover {
        background: #8ea54e;
    }
  </style>
</head>
<body>
    <h1><?= ucfirst($planner_type) ?> Meal Planner (Week of <?= date("M d", strtotime($week_start)) ?>)</h1>
    <table>
        <tr>
            <th>Day</th>
            <?php foreach ($meal_types as $meal): ?>
                <th><?= $meal ?></th>
            <?php endforeach; ?>
        </tr>
        <?php foreach ($days as $day): ?>
        <tr>
            <td><b><?= $day ?></b></td>
            <?php foreach ($meal_types as $meal): 
                $item = $plan[$day][$meal] ?? null; ?>
                <td>
                    <?php if ($item): ?>
                        <div class="meal-card" id="<?= $day ?>-<?= $meal ?>">
                            <img src="<?= $item['image_path'] ?>" alt="<?= $item['title'] ?>">
                            <div><?= $item['title'] ?></div>
                            <?php if ($planner_type === "what_to_cook"): ?>
                                <button class="shuffle-btn" onclick="shuffleMeal('<?= $day ?>','<?= $meal ?>')">Shuffle</button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <em>No meal planned</em>
                    <?php endif; ?>
                </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </table>

<script>
function shuffleMeal(day, meal_type) {
    fetch(`meal_planner.php?action=shuffle&planner=<?= $planner_type ?>&day=${day}&meal_type=${meal_type}`)
        .then(res => res.json())
        .then(data => {
            const cell = document.getElementById(`${day}-${meal_type}`);
            if(cell){
                cell.innerHTML = `
                    <img src="${data.image_path}" alt="${data.title}">
                    <div>${data.title}</div>
                    <button class="shuffle-btn" onclick="shuffleMeal('${day}','${meal_type}')">Shuffle</button>
                `;
            }
        });
}
</script>
</body>
</html>
