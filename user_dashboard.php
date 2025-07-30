<?php
session_start();
if (!isset($_SESSION['user_email'])) {
  header("Location: user_login.html");
  exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$email = $_SESSION['user_email'];
$result = $conn->query("SELECT username, profile_pic FROM users WHERE email = '$email'");
$row = $result->fetch_assoc();
$username = $row['username'];
$profilePic = $row['profile_pic'] ?: 'uploads/default.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Dashboard | Taste It</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      margin: 0;
      background: #fff8f0;
    }

    .navbar {
      background-color: #B0C364;
      color: white;
      padding: 15px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .navbar .profile {
      display: flex;
      align-items: center;
    }

    .navbar img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 10px;
    }

    .navbar a {
      color: white;
      margin-left: 20px;
      text-decoration: none;
      font-weight: 600;
    }

    .container {
      padding: 40px;
      text-align: center;
    }

    .dashboard-options {
      margin-top: 30px;
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 20px;
    }

    .dashboard-options a {
      background-color: #ffc107;
      padding: 15px 25px;
      border-radius: 10px;
      text-decoration: none;
      color: #000;
      font-weight: 600;
      transition: 0.3s;
    }

    .dashboard-options a:hover {
      background-color: #e0a800;
    }

    h1 {
      color: #D7263D;
      margin-bottom: 10px;
    }

    h3 {
      color: #555;
    }
  </style>
</head>
<body>

  <div class="navbar">
    <div class="profile">
      <img src="<?php echo $profilePic; ?>" alt="Profile Picture">
      <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
    </div>
    <div>
      <a href="index.html">Home</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <div class="container">
    <h1>User Dashboard</h1>
    <h3>What would you like to do today?</h3>

    <div class="dashboard-options">
      <a href="upload_recipe.php">📤 Upload Recipe</a>
      <a href="search_recipes.php">🔍 Search Recipes</a>
      <a href="search_chefs.php">👨‍🍳 Find Chefs</a>
      <a href="liked_recipes.php">❤️ Liked Recipes</a>
      <a href="bookmarked_recipes.php">🔖 Bookmarked Recipes</a>
      <a href="my_recipes.php">📚 My Recipes</a>
      <a href="my_comments.php">💬 My Comments</a>
      <a href="edit_profile.php">🖊️ Edit Profile</a>

    </div>
  </div>

</body>
</html>
