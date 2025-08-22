<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 🔥 Auto delete rejected recipes older than 1 day
$conn->query("DELETE FROM recipes WHERE status='rejected' AND TIMESTAMPDIFF(DAY, updated_at, NOW()) >= 1");

// Fetch remaining rejected recipes
$result = $conn->query("SELECT r.*, u.username 
                        FROM recipes r 
                        JOIN users u ON r.user_id = u.id 
                        WHERE r.status = 'rejected'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Rejected Recipes</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f9f9f9; }
    h2 { color: #B22222; }
    .card {
      background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .card h3 { margin-top: 0; color: #B22222; }
    .card img { max-width: 100%; border-radius: 8px; margin-top: 10px; }
  </style>
</head>
<body>
  <h2>❌ Rejected Recipes</h2>
  <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="card">
          <h3><?php echo htmlspecialchars($row['title']); ?></h3>
          <p><strong>By:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
          <p><strong>Category:</strong> <?php echo htmlspecialchars($row['category']); ?></p>
          <p><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
          <?php if (!empty($row['image_path'])): ?>
            <img src="<?php echo $row['image_path']; ?>" alt="Recipe Image">
          <?php endif; ?>
          <p style="color: red; font-weight: bold;">This recipe was rejected by the admin.</p>
        </div>
      <?php endwhile; ?>
  <?php else: ?>
    <p>No rejected recipes available.</p>
  <?php endif; ?>
</body>
</html>
