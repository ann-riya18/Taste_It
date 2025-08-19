<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_email = $_SESSION['user_email'];

$query = "SELECT username, profile_pic FROM users WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $username = $user['username'];
    $profileImage = !empty($user['profile_pic']) 
    ? $user['profile_pic'] 
    : 'uploads/user_images/default.jpg';


} else {
    session_destroy();
    header("Location: user_login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Dashboard | Taste It</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    body {
      margin: 0;
      font-family: 'Poppins', sans-serif;
      display: flex;
      background: #fefaf3;
    }

    .sidebar {
      width: 240px;
      background-color: #B0C364;
      color: white;
      min-height: 100vh;
      padding: 30px 20px;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    

    .profile-pic {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 15px;
      border: 3px solid white;
    }

    .welcome {
      text-align: center;
      font-size: 18px;
      margin-bottom: 30px;
      font-weight: 600;
    }

    .sidebar a {
      text-decoration: none;
      color: #f9f9f9;
      padding: 12px 15px;
      margin: 8px 0;
      width: 100%;
      display: flex;
      align-items: center;
      border-radius: 8px;
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    .sidebar a i {
      margin-right: 10px;
    }

    .sidebar a:hover {
      background-color: #a2b759;
      color: #fff;
    }

    .main {
      flex-grow: 1;
      padding: 40px 60px;
    }

    .main h2 {
      color: #c93e4f;
      font-size: 32px;
      margin-bottom: 10px;
    }

    .main p {
      font-size: 18px;
      margin-bottom: 40px;
      color: #333;
    }

    .card-container {
      display: grid;
      grid-template-columns: repeat(3,1fr);
      gap: 30px;
    }
    .card-container a 
    {
       text-decoration: none;
       color: inherit;
       display: block;           /* Important: Makes <a> behave like a block */
    }


    .card {
      background-color: #d8ded8ff;
      border-radius: 18px;
      padding: 30px 20px;
      text-align: center;
      color: #333;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.07);
      transition: transform 0.2s;
      cursor: pointer;
    }

    .card:hover {
      transform: scale(1.03);
    }

    .card i {
      font-size: 32px;
      color: #B0C364;
      margin-bottom: 12px;
    }

    .card p {
      font-size: 16px;
      font-weight: 500;
    }
  </style>
</head>
<body>

  <div class="sidebar">
     <img src="<?php echo htmlspecialchars($profileImage); ?>" 
     alt="Profile" class="profile-pic"
     onerror="this.src='https://via.placeholder.com/100'">
   

     <div class="welcome">Welcome,<br><?php echo htmlspecialchars($user['username']); ?></div>
     <a href="index.html"><i class="fas fa-home"></i>Home</a>
     <a href="search_recipes.php"><i class="fas fa-search"></i>Search Recipes</a>
     <a href="find_chefs.php"><i class="fas fa-user-friends"></i>Find Chefs</a>
     <a href="recipe_analytics.php"><i class="fas fa-chart-line"></i>My Recipe Analytics</a>
     <a href="edit_profile.php"><i class="fas fa-user-edit"></i>Edit Profile</a>
     <a href="user_logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>

  </div>

  <div class="main">
    <h2>User Dashboard</h2>
    <p>What would you like to do today?</p>
    <!-- CARD CONTAINER WITH REDIRECTION LINKS -->
<div class="card-container">
  <a href="upload_recipe.php" class="card">
    <i class="fas fa-upload"></i>
    <p>Upload Recipe</p>
  </a>
  <a href="bookmarked_recipes.php" class="card">
    <i class="fas fa-bookmark"></i>
    <p>Bookmarked Recipes</p>
  </a>
  <a href="my_recipes.php" class="card">
    <i class="fas fa-utensils"></i>
    <p>My Recipes</p>
  </a>
  <a href="liked_recipes.php" class="card">
    <i class="fas fa-heart"></i>
    <p>Liked Recipes</p>
  </a>
  <a href="badges.php" class="card">
    <i class="fas fa-award"></i>
    <p>Badges</p>
  </a>
  <a href="my_comments.php" class="card">
    <i class="fas fa-comment-dots"></i>
    <p>My Comments</p>
  </a>
</div>
</div>
   

</body>
</html>
