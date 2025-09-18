<?php
session_start();

// 🔒 Prevent cache issues
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_email = $_SESSION['user_email'];

// fetch logged-in user
$stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $username = $user['username'];
    $profileImage = !empty($user['profile_pic']) ? $user['profile_pic'] : 'uploads/user_images/default.jpg';
} else {
    session_destroy();
    header("Location: user_login.php");
    exit();
}

// search logic
$searchTerm = $_GET['q'] ?? '';
if (!empty($searchTerm)) {
    $stmt = $conn->prepare("SELECT id, username, profile_pic FROM users WHERE username LIKE ? ORDER BY id DESC");
    $like = "%".$searchTerm."%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $chefs = $stmt->get_result();
} else {
    $chefs = $conn->query("SELECT id, username, profile_pic FROM users ORDER BY id DESC LIMIT 8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Find Chefs | Taste It</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<style>
body{margin:0;font-family:'Poppins',sans-serif;display:flex;background:#fefaf3}
.sidebar{width:240px;background-color:#B0C364;color:white;min-height:100vh;padding:30px 20px;box-sizing:border-box;display:flex;flex-direction:column;align-items:center}
.profile-pic{width:100px;height:100px;border-radius:50%;object-fit:cover;margin-bottom:15px;border:3px solid white}
.welcome{text-align:center;font-size:18px;margin-bottom:30px;font-weight:600}
.sidebar a{text-decoration:none;color:#f9f9f9;padding:12px 15px;margin:8px 0;width:100%;display:flex;align-items:center;border-radius:8px;transition:0.3s}
.sidebar a:hover{background-color:#a2b759;color:#fff}
.main{flex-grow:1;padding:40px 60px}
.main h2{color:#c93e4f;font-size:32px;margin-bottom:10px}
.search-box{margin:20px 0;display:flex;justify-content:center}
.search-box input{padding:12px 20px;width:300px;border:1px solid #ddd;border-radius:25px;font-size:15px}
.search-box button{padding:12px 20px;margin-left:10px;background:#B0C364;color:#fff;border:none;border-radius:25px;cursor:pointer}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;margin-top:30px}
.card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 6px 15px rgba(0,0,0,.08);transition:.25s;text-align:center;padding:20px}
.card:hover{transform:translateY(-6px)}
.card img{width:100px;height:100px;object-fit:cover;border-radius:50%;margin-bottom:12px}
.card-title{font-size:18px;color:#333;font-weight:600}
</style>
</head>
<body>

<div class="sidebar">
  <img src="<?php echo htmlspecialchars($profileImage); ?>" class="profile-pic" alt="Profile" onerror="this.src='uploads/user_images/default.jpg'">
  <div class="welcome">Welcome,<br><?php echo htmlspecialchars($username); ?></div>
  <a href="index.php"><i class="fas fa-home"></i>Home</a>
  <a href="search_recipes.php"><i class="fas fa-search"></i>Search Recipes</a>
  <a href="findchefs.php"><i class="fas fa-user-friends"></i>Find Chefs</a>
  <a href="recipe_analytics.php"><i class="fas fa-chart-line"></i>My Recipe Analytics</a>
  <a href="edit_profile.php"><i class="fas fa-user-edit"></i>Edit Profile</a>
  <a href="user_logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
</div>

<div class="main">
  <h2>Find Chefs</h2>

  <form method="get" class="search-box">
    <input type="text" name="q" placeholder="Search chefs by name..." value="<?php echo htmlspecialchars($searchTerm); ?>">
    <button type="submit"><i class="fas fa-search"></i></button>
  </form>

  <?php if (!empty($searchTerm)): ?>
    <h3>Search Results for "<?php echo htmlspecialchars($searchTerm); ?>"</h3>
  <?php else: ?>
    <h3>Popular Chefs</h3>
  <?php endif; ?>

  <div class="grid">
    <?php
    if ($chefs && $chefs->num_rows > 0) {
      while ($chef = $chefs->fetch_assoc()) {
        $chefImg = !empty($chef['profile_pic']) ? $chef['profile_pic'] : 'uploads/user_images/default.jpg';
        echo "
        <div class='card'>
          <img src='".htmlspecialchars($chefImg)."' alt='Chef' onerror=\"this.src='uploads/user_images/default.jpg'\">
          <div class='card-title'>".htmlspecialchars($chef['username'])."</div>
        </div>";
      }
    } else {
      echo "<p>No chefs found.</p>";
    }
    ?>
  </div>
</div>

</body>
</html>
<?php $conn->close(); ?>
