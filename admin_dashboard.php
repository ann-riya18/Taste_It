<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
  header("Location: admin_login.html");
  exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Stats
$userCount = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$chefCount = $conn->query("SELECT COUNT(DISTINCT user_id) AS chefs FROM recipes WHERE status='approved'")->fetch_assoc()['chefs'];
$approvedRecipes = $conn->query("SELECT COUNT(*) AS total FROM recipes WHERE status='approved'")->fetch_assoc()['total'];
$pendingRecipes = $conn->query("SELECT COUNT(*) AS total FROM recipes WHERE status='pending'")->fetch_assoc()['total'];
$declinedRecipes = $conn->query("SELECT COUNT(*) AS total FROM recipes WHERE status='declined'")->fetch_assoc()['total'];

// Most liked from approved
$mostLiked = $conn->query("SELECT title, likes FROM recipes WHERE status='approved' ORDER BY likes DESC LIMIT 1")->fetch_assoc();
$mostLikedTitle = $mostLiked['title'] ?? "N/A";
$mostLikedLikes = $mostLiked['likes'] ?? 0;

// Recent approved activity
$recent = $conn->query("
  SELECT r.title, u.username, r.created_at 
  FROM recipes r JOIN users u ON r.user_id = u.id 
  WHERE r.status = 'approved'
  ORDER BY r.created_at DESC LIMIT 5
");

// Top contributors from approved
$topUsers = $conn->query("
  SELECT u.username, COUNT(r.id) AS count 
  FROM users u JOIN recipes r ON u.id = r.user_id 
  WHERE r.status = 'approved'
  GROUP BY u.id ORDER BY count DESC LIMIT 3
");
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Poppins', sans-serif;
      display: flex;
      min-height: 100vh;
      background: url('img/bg15.jpg') no-repeat center center/cover;
    }

    .sidebar {
      width: 260px;
      background-color: #B0C364;
      padding: 30px 20px;
      border-right: 2px solid #f4f4f4;
      box-shadow: 2px 0 10px rgba(0,0,0,0.05);
    }
    .sidebar h2 {
      font-size: 22px;
      color: #fff;
      margin-bottom: 30px;
    }
    .sidebar a {
      display: block;
      background-color:rgba(255, 255, 255, 0.90);
      color: #333;
      font-weight: 600;
      padding: 12px 18px;
      margin-bottom: 15px;
      border-radius: 8px;
      text-decoration: none;
      transition: all 0.3s ease;
    }
    .sidebar a:hover {
      background-color: #e3efc9;
      color: #4B5F1F;
      transform: translateX(2px);
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .main-content {
      flex: 1;
      padding: 40px;
      background: rgba(255,255,255,0.4);
      backdrop-filter: blur(5px);
    }

    .header {
      background: rgba(255, 255, 255, 0.90);
      padding: 20px 30px;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      color:#5A6E2D;
    }

    .summary-cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 25px;
      margin-bottom: 40px;
    }

    .card {
      background: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      text-align: center;
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      min-height: 220px;
      transition: all 0.3s ease;
      text-decoration: none;
      color: inherit;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
      background-color: #f9f9f9;
    }

    .card h3 {
      color: #333;
      margin: 10px 0 0 0;
      font-size: 16px;
      font-weight: 600;
    }

    .card p {
      margin-top: 40px;
      font-size: 28px;
      color: #5A6E2D;
      font-weight: 600;
    }

    .section {
      background: rgba(255, 255, 255, 0.90);
      padding: 25px;
      border-radius: 12px;
      margin-bottom: 30px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }

    .section h3 {
      color: #5A6E2D;
      font-size: 18px;
      margin-bottom: 15px;
    }

    .section ul {
      list-style: none;
      padding-left: 0;
    }

    .section li {
      margin-bottom: 10px;
      color: #333;
    }

    .section li em {
      color: #555;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <h2>Admin Panel</h2>
    <a href="recipe_requests.php">Pending Recipes</a>
    <a href="review_comments.php">Moderate Comments</a>
    <a href="graph_insights.php">Graphical Insights</a>
    <a href="admin_logout.php">Logout</a>
  </div>

  <div class="main-content">
    <div class="header">
      <h2>Welcome to Admin Dashboard</h2>
      <p>👤 <?php echo $_SESSION['admin_email']; ?></p>
    </div>

    <div class="summary-cards">
      <div class="card">
        <h3>⭐ Most Liked Recipe</h3>
        <p><?php echo htmlspecialchars($mostLikedTitle) . " ($mostLikedLikes likes)"; ?></p>
      </div>
      <a href="approved_recipes.php" class="card">
        <h3>🍽️ Approved Recipes</h3>
        <p><?php echo $approvedRecipes; ?></p>
      </a>
      <a href="pending_recipes.php" class="card">
        <h3>⏳ Pending Recipes</h3>
        <p><?php echo $pendingRecipes; ?></p>
      </a>
      <a href="declined_recipes.php" class="card">
        <h3>❌ Declined Recipes</h3>
        <p><?php echo $declinedRecipes; ?></p>
      </a>
      <a href="total_users.php" class="card">
        <h3>👥 Total Users</h3>
        <p><?php echo $userCount; ?></p>
      </a>
      <a href="declined_recipes.php" class="card">
        <h3>👨‍🍳 Total Chefs</h3>
        <p><?php echo $chefCount; ?></p>
      </a>
    </div>

    <div class="section">
      <h3>🕒 Recent Activity</h3>
      <ul>
        <?php while ($row = $recent->fetch_assoc()): ?>
          <li><strong><?php echo htmlspecialchars($row['username']); ?></strong> uploaded <em><?php echo htmlspecialchars($row['title']); ?></em> on <?php echo date("M d, Y H:i", strtotime($row['created_at'])); ?></li>
        <?php endwhile; ?>
      </ul>
    </div>

    <div class="section">
      <h3>🏆 Top Contributors</h3>
      <ul>
        <?php while ($row = $topUsers->fetch_assoc()): ?>
          <li><strong><?php echo htmlspecialchars($row['username']); ?></strong> - <?php echo $row['count']; ?> recipes</li>
        <?php endwhile; ?>
      </ul>
    </div>
  </div>
</body>
</html>
