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

// Get user ID
$email = $_SESSION['user_email'];
$result = $conn->query("SELECT id, username FROM users WHERE email = '$email'");
$user = $result->fetch_assoc();
$user_id = $user['id'];
$username = $user['username'];

// Fetch recipes
$recipes = $conn->query("SELECT * FROM recipes WHERE user_id = $user_id ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>My Recipes</title>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #fff8f2;
      padding: 40px;
    }
    h2 {
      color: #D7263D;
      margin-bottom: 30px;
    }
    .recipe {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      margin-bottom: 25px;
    }
    .recipe img {
      max-width: 100%;
      height: auto;
      border-radius: 10px;
      margin-top: 10px;
    }
    .status {
      font-weight: bold;
      padding: 6px 12px;
      border-radius: 5px;
      display: inline-block;
      margin-top: 10px;
    }
    .approved { background-color: #d4edda; color: #155724; }
    .pending { background-color: #fff3cd; color: #856404; }
    .declined { background-color: #f8d7da; color: #721c24; }
    .message {
      margin-top: 10px;
      color: #721c24;
      font-style: italic;
    }
  </style>
</head>
<body>

  <h2>👩‍🍳 <?php echo htmlspecialchars($username); ?>'s Recipes</h2>

  <?php if ($recipes->num_rows > 0): ?>
    <?php while ($row = $recipes->fetch_assoc()): ?>
      <div class="recipe">
        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
        <?php if ($row['image_path']): ?>
          <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="Recipe Image">
        <?php endif; ?>
        <p><strong>Category:</strong> <?php echo htmlspecialchars($row['category']); ?></p>
        <div class="status 
          <?php 
            echo ($row['status'] == 'approved') ? 'approved' : 
                 (($row['status'] == 'pending') ? 'pending' : 'declined'); 
          ?>">
          <?php echo ucfirst($row['status']); ?>
        </div>

        <?php if ($row['status'] == 'declined'): ?>
          <div class="message">⚠️ Your recipe was declined by the admin. Sorry!</div>
        <?php elseif ($row['status'] == 'pending'): ?>
          <div class="message">⏳ This recipe is pending admin review.</div>
        <?php endif; ?>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p>You haven't uploaded any recipes yet.</p>
  <?php endif; ?>

</body>
</html>
<?php $conn->close(); ?>
