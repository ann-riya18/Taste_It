<?php
session_start();
// DB connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_GET['id'] ?? 0;

// Fetch user info
$sql_user = "SELECT username, profile_pic, bio FROM users WHERE id=$user_id";
$res_user = $conn->query($sql_user);
if (!$res_user || $res_user->num_rows == 0) {
    echo "User not found.";
    exit;
}
$user = $res_user->fetch_assoc();

// Fetch user recipes
$sql_recipes = "SELECT id, title, image_path FROM recipes WHERE user_id=$user_id AND status='approved'";
$res_recipes = $conn->query($sql_recipes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($user['username']); ?> - Profile</title>
  <style>
    body {font-family: Arial, sans-serif; margin:0; padding:0; background:#f8f8f8;}
    .container {width:85%; margin:30px auto;}
    .profile-header {display:flex; align-items:center; gap:30px; margin-bottom:40px;}
    .profile-header img {
      width:140px; height:140px; border-radius:50%; object-fit:cover; border:3px solid #ddd;
    }
    .profile-info h2 {margin:0; font-size:26px; color:#333;}
    .profile-info p {margin-top:10px; font-size:15px; color:#555;}
    .recipes-grid {
      display:grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap:20px;
    }
    .recipe-card {
      background:#fff; border-radius:10px; overflow:hidden; 
      box-shadow:0 2px 8px rgba(0,0,0,0.1);
      transition: transform 0.2s;
    }
    .recipe-card:hover {transform:scale(1.03);}
    .recipe-card img {width:100%; height:180px; object-fit:cover;}
    .recipe-card h3 {margin:10px; font-size:18px; color:#333;}
    .recipe-card a {text-decoration:none; color:inherit;}
  </style>
</head>
<body>
  <div class="container">
    <!-- Profile Header -->
    <div class="profile-header">
      <img src="<?php echo $user['profile_pic'] ?: 'img/default_profile.png'; ?>" alt="Profile Picture">
      <div class="profile-info">
        <h2><?php echo htmlspecialchars($user['username']); ?></h2>
        <p><?php echo htmlspecialchars($user['bio'] ?: 'No bio available.'); ?></p>
      </div>
    </div>

    <!-- Recipes Grid -->
    <h2><?php echo htmlspecialchars($user['username']); ?>'s Recipes</h2>
    <div class="recipes-grid">
      <?php while ($rec = $res_recipes->fetch_assoc()): ?>
        <div class="recipe-card">
          <a href="view_recipe.php?id=<?php echo $rec['id']; ?>">
            <img src="<?php echo $rec['image_path'] ?: 'img/placeholder.png'; ?>" alt="<?php echo htmlspecialchars($rec['title']); ?>">
            <h3><?php echo htmlspecialchars($rec['title']); ?></h3>
          </a>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</body>
</html>
