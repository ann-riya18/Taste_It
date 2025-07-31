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
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    font-family: 'Poppins', sans-serif;
    display: flex;
    min-height: 100vh;
    background: linear-gradient(to right, #fcfafa, #fdf7ee);
  }

  .sidebar {
    width: 260px;
    background: linear-gradient(180deg, #b0c364, #9fb050);
    color: white;
    padding: 30px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
    border-top-right-radius: 20px;
    border-bottom-right-radius: 20px;
  }

  .sidebar img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 15px;
    border: 3px solid white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
  }

  .sidebar h3 {
    font-size: 20px;
    margin-bottom: 25px;
    text-align: center;
  }

  .sidebar a {
    text-decoration: none;
    color: white;
    width: 100%;
    padding: 12px 15px;
    margin: 6px 0;
    border-radius: 10px;
    display: flex;
    align-items: center;
    font-weight: 500;
    transition: 0.3s;
  }

  .sidebar a i {
    margin-right: 10px;
  }

  .sidebar a:hover {
    background-color: rgba(255, 255, 255, 0.2);
  }

  .main {
    flex: 1;
    padding: 40px 60px;
  }

  .main h1 {
    font-size: 36px;
    color: #D7263D;
    margin-bottom: 10px;
  }

  .main h3 {
    font-size: 20px;
    color: #555;
    margin-bottom: 40px;
  }

  .dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 25px;
  }

  .dashboard-cards a {
    background: linear-gradient(135deg, #fefefe, #f8f8f8);
    border-radius: 16px;
    padding: 22px;
    text-decoration: none;
    color: #333;
    font-weight: 600;
    display: flex;
    align-items: center;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    border: none;
    position: relative;
    overflow: hidden;
  }

  .dashboard-cards a::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    height: 100%; width: 100%;
    background: linear-gradient(135deg, #b0c364, #a0b85a);
    opacity: 0.06;
    z-index: 0;
  }

  .dashboard-cards a i {
    font-size: 22px;
    margin-right: 12px;
    color: #B0C364;
    z-index: 1;
  }

  .dashboard-cards a span {
    z-index: 1;
  }

  .dashboard-cards a:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
  }

  @media(max-width: 768px) {
    body {
      flex-direction: column;
    }

    .sidebar {
      width: 100%;
      flex-direction: row;
      justify-content: space-around;
      padding: 15px;
      border-radius: 0;
    }

    .sidebar img, .sidebar h3 {
      display: none;
    }

    .sidebar a {
      font-size: 14px;
      padding: 10px;
      margin: 0 5px;
    }

    .main {
      padding: 20px;
    }

    .main h1 {
      font-size: 28px;
    }

    .main h3 {
      font-size: 16px;
    }
  }
</style>

</head>
<body>

  <div class="sidebar">
    <img src="<?php echo $profilePic; ?>" alt="Profile Picture">
    <h3>Welcome, <?php echo htmlspecialchars($username); ?></h3>
    <a href="index.html"><i class="fas fa-home"></i> Home</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>

  <div class="main">
    <h1>User Dashboard</h1>
    <h3>What would you like to do today?</h3>

    <div class="dashboard-cards">
      <a href="upload_recipe.php"><i class="fas fa-upload"></i> Upload Recipe</a>
      <a href="search_recipes.php"><i class="fas fa-search"></i> Search Recipes</a>
      <a href="search_chefs.php"><i class="fas fa-user-friends"></i> Find Chefs</a>
      <a href="liked_recipes.php"><i class="fas fa-heart"></i> Liked Recipes</a>
      <a href="bookmarked_recipes.php"><i class="fas fa-bookmark"></i> Bookmarked Recipes</a>
      <a href="my_recipes.php"><i class="fas fa-book"></i> My Recipes</a>
      <a href="my_comments.php"><i class="fas fa-comment-dots"></i> My Comments</a>
      <a href="edit_profile.php"><i class="fas fa-user-edit"></i> Edit Profile</a>
    </div>
  </div>

</body>
</html>
