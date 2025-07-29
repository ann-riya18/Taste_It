<?php
session_start();
if (!isset($_SESSION['user_email'])) {
  header("Location: user_login.html");
  exit();
}

$host = "localhost";
$user = "root";
$pass = "";
$db = "tasteit";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$email = $_SESSION['user_email'];
$result = $conn->query("SELECT username FROM users WHERE email='$email'");
$username = $result ? $result->fetch_assoc()['username'] : "User";
?>

<!-- Now continue with full HTML below -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #fff8f0;
      margin: 0;
    }
    .navbar {
      background-color: #B0C364;
      color: white;
      padding: 20px;
      font-weight: 600;
    }
    .container {
      padding: 40px;
    }
    h2 {
      color: #D7263D;
    }
    .dashboard-options a {
      display: inline-block;
      margin: 10px;
      padding: 15px 25px;
      background-color: #ffc107;
      border-radius: 10px;
      color: #000;
      font-weight: 600;
      text-decoration: none;
    }
    .dashboard-options a:hover {
      background-color: #e0a800;
    }
  </style>
</head>
<body>

  <div class="navbar">
    Welcome, <?php echo htmlspecialchars($username); ?> | <a href="logout.php" style="color:white;">Logout</a>
  </div>

  <div class="container">
    <h2>User Dashboard</h2>
    <div class="dashboard-options">
      <a href="upload_recipe.php">📤 Upload Recipe</a>
      <a href="my_recipes.php">📚 My Recipes</a>
      <a href="saved_recipes.php">❤️ Saved Recipes</a>
      <a href="my_comments.php">💬 My Comments</a>
    </div>
  </div>

</body>
</html>
