<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
  header("Location: admin_login.html");
  exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$sql = "SELECT r.*, u.username FROM recipes r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.status='declined'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Declined Recipes</title>
  <style>
    body { font-family: 'Poppins', sans-serif; padding: 30px; background-color: #fff6f6; }
    .recipe {
      background: #fff; padding: 20px; margin-bottom: 25px;
      border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .recipe img { max-width: 100%; border-radius: 10px; margin-top: 10px; }
    h2 { color: #b04545; }
  </style>
</head>
<body>

<h2>❌ Declined Recipes</h2>

<?php if ($result->num_rows > 0): ?>
  <?php while ($row = $result->fetch_assoc()): ?>
    <div class="recipe">
      <h3><?php echo htmlspecialchars($row['title']); ?></h3>
      <p><strong>By:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
      <p><strong>Category:</strong> <?php echo htmlspecialchars($row['category']); ?></p>
      <p><strong>Ingredients:</strong><br><?php echo nl2br(htmlspecialchars($row['ingredients'])); ?></p>
      <p><strong>Steps:</strong><br><?php echo nl2br(htmlspecialchars($row['steps'])); ?></p>
      <?php if (!empty($row['image_path'])): ?>
        <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="Recipe Image">
      <?php endif; ?>
      <p style="color: #dc3545;"><strong>Status:</strong> Declined by admin</p>
    </div>
  <?php endwhile; ?>
<?php else: ?>
  <p>No declined recipes to show.</p>
<?php endif; ?>

</body>
</html>
