<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$result = $conn->query("SELECT r.*, u.username FROM recipes r JOIN users u ON r.user_id = u.id WHERE status='approved' ORDER BY likes DESC LIMIT 1");
$recipe = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Most Liked Recipe</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f7f7f7; }
    .card {
      background: #fff; padding: 30px; border-radius: 10px;
      box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    }
    .card h2 { color: #5A6E2D; margin-top: 0; }
    .card img { max-width: 100%; border-radius: 10px; margin-top: 10px; }
  </style>
</head>
<body>
  <div class="card">
    <h2>🌟 Most Liked Recipe</h2>
    <?php if ($recipe): ?>
      <p><strong>Title:</strong> <?php echo htmlspecialchars($recipe['title']); ?></p>
      <p><strong>Uploaded by:</strong> <?php echo htmlspecialchars($recipe['username']); ?></p>
      <p><strong>Likes:</strong> <?php echo $recipe['likes']; ?></p>
      <p><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></p>
      <?php if (!empty($recipe['image_path'])): ?>
        <img src="<?php echo $recipe['image_path']; ?>" alt="Recipe Image">
      <?php endif; ?>
    <?php else: ?>
      <p>No liked recipes yet.</p>
    <?php endif; ?>
  </div>
</body>
</html>
