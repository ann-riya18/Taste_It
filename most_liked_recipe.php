<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ✅ Fetch top 5 most liked recipes
$result = $conn->query("
    SELECT r.*, u.username, COUNT(l.id) AS like_count
    FROM recipes r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN likes l ON r.id = l.recipe_id
    WHERE r.status='approved'
    GROUP BY r.id
    ORDER BY like_count DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Most Liked Recipes</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f7f7f7; }
    .card {
      background: #fff; padding: 30px; border-radius: 10px;
      box-shadow: 0 3px 15px rgba(0,0,0,0.1); margin-bottom: 20px;
    }
    .card h2 { color: #5A6E2D; margin-top: 0; }
    .card img { max-width: 100%; border-radius: 10px; margin-top: 10px; }
  </style>
</head>
<body>
  <h2>🌟 Top 5 Most Liked Recipes</h2>

  <?php if ($result->num_rows > 0): ?>
    <?php while ($recipe = $result->fetch_assoc()): ?>
      <div class="card">
        <p><strong>Title:</strong> <?php echo htmlspecialchars($recipe['title']); ?></p>
        <p><strong>Uploaded by:</strong> <?php echo htmlspecialchars($recipe['username']); ?></p>
        <p><strong>Likes:</strong> <?php echo $recipe['like_count']; ?></p>
        <p><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></p>
        <?php if (!empty($recipe['image_path'])): ?>
          <img src="<?php echo $recipe['image_path']; ?>" alt="Recipe Image">
        <?php endif; ?>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p>No liked recipes yet.</p>
  <?php endif; ?>
</body>
</html>
