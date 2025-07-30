<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
  header("Location: admin_login.html");
  exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$userCount = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$chefCount = $conn->query("SELECT COUNT(DISTINCT user_id) AS chefs FROM recipes")->fetch_assoc()['chefs'];
$totalRecipes = $conn->query("SELECT COUNT(*) AS total FROM recipes")->fetch_assoc()['total'];
$activeContributors = $conn->query("SELECT COUNT(DISTINCT user_id) AS contributors FROM recipes")->fetch_assoc()['contributors'];

$mostLiked = $conn->query("SELECT title, MAX(likes) AS likes FROM recipes")->fetch_assoc();
$mostLikedTitle = $mostLiked['title'] ?? "N/A";
$mostLikedLikes = $mostLiked['likes'] ?? 0;

$recent = $conn->query("
  SELECT r.title, u.username, r.created_at 
  FROM recipes r JOIN users u ON r.user_id = u.id 
  ORDER BY r.created_at DESC LIMIT 5
");

$topUsers = $conn->query("
  SELECT u.username, COUNT(r.id) AS count 
  FROM users u JOIN recipes r ON u.id = r.user_id 
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

    /* Sidebar */
    .sidebar {
      width: 260px; /* widened from 240px */
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
      background-color: #f9f9f9; /* off-white background */
      color: #000;
      font-weight: 600;
      padding: 12px 18px;
      margin-bottom: 15px;
      border-radius: 8px;
      text-decoration: none;
      transition: all 0.3s ease;
      white-space: nowrap; /* ensures single line */
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .sidebar a:hover {
      background-color: #ffffff;
      transform: translateX(4px);
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    /* Main Content */
    .main-content {
      flex: 1;
      padding: 40px;
      background: rgba(255,255,255,0.4);
      backdrop-filter: blur(5px);
    }

    .header {
      background: rgba(255, 255, 255, 0.95);
      padding: 20px 30px;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }

    .summary-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 25px;
      margin-bottom: 40px;
    }

    .card {
      background: #fff;
      padding: 25px;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      text-align: center;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    .card h3 {
      font-size: 16px;
      color: #888;
      margin-bottom: 10px;
    }

    .card p {
      font-size: 20px;
      color: #D7263D;
      font-weight: 600;
    }

    .section {
      background: rgba(255, 255, 255, 0.95);
      padding: 25px;
      border-radius: 12px;
      margin-bottom: 30px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }

    .section h3 {
      color: #D7263D;
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

  <!-- Sidebar -->
  <div class="sidebar">
    <h2>Admin Panel</h2>
    <a href="recipe_requests.php">Pending Recipes</a>
    <a href="review_comments.php"> Moderate Comments</a>
    <a href="graph_insights.php">Graphical Insights</a>
    <a href="admin_logout.php"> Logout</a>
  </div>

  <!-- Main -->
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
      <div class="card">
        <h3>👥 Total Users</h3>
        <p><?php echo $userCount; ?></p>
      </div>
      <div class="card">
        <h3>👨‍🍳 Total Chefs</h3>
        <p><?php echo $chefCount; ?></p>
      </div>
      <div class="card">
        <h3>🍽️ Total Recipes</h3>
        <p><?php echo $totalRecipes; ?></p>
      </div>
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
