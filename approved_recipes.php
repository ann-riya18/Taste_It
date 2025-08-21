<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$result = $conn->query("SELECT r.title, u.username 
                        FROM recipes r 
                        JOIN users u ON r.user_id = u.id 
                        WHERE r.status = 'approved'");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Approved Recipes</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f9f9f9; }
    .card {
      background: #fff; padding: 15px; margin-bottom: 15px; border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .card h3 { margin: 0; color: #5A6E2D; }
    .card p { margin: 5px 0; color: #333; }
  </style>
</head>
<body>
  <h2>✅ Approved Recipes</h2>
  <?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="card">
        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
        <p><strong>Chef:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p>No approved recipes yet.</p>
  <?php endif; ?>
</body>
</html>
