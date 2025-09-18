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
    body { 
      font-family: Arial, sans-serif; 
      padding: 20px; 
      background-image: url('img/bg20.jpg');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      margin: 0;
    }
    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(255, 255, 255, 0.85);
      z-index: -1;
    }
    .container {
      max-width: 1000px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }
    .card {
      background: rgba(255, 255, 255, 0.9);
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 3px 15px rgba(0,0,0,0.1);
      margin-bottom: 20px;
      display: flex;
      align-items: center;
    }
    .card h2 { color: #5A6E2D; margin-top: 0; }
    .card img { 
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 20px;
      border: 3px solid #5A6E2D;
    }
    .card-content {
      flex: 1;
    }
    .card-content p {
      margin: 5px 0;
    }
    .page-title {
      text-align: center;
      color: #5A6E2D;
      margin-bottom: 30px;
      font-size: 28px;
    }
  </style>
</head>
<body>
  <div class="overlay"></div>
  <div class="container">
    <h2 class="page-title">🌟 Top 5 Most Liked Recipes</h2>

    <?php if ($result->num_rows > 0): ?>
      <?php while ($recipe = $result->fetch_assoc()): ?>
        <div class="card">
          <?php if (!empty($recipe['image_path'])): ?>
            <img src="<?php echo $recipe['image_path']; ?>" alt="Recipe Image">
          <?php else: ?>
            <img src="img/placeholder.png" alt="Recipe Image">
          <?php endif; ?>
          <div class="card-content">
            <h2><?php echo htmlspecialchars($recipe['title']); ?></h2>
            <p><strong>Uploaded by:</strong> <?php echo htmlspecialchars($recipe['username']); ?></p>
            <p><strong>Likes:</strong> <?php echo $recipe['like_count']; ?></p>
            <p><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></p>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p>No liked recipes yet.</p>
    <?php endif; ?>
  </div>
</body>
</html>