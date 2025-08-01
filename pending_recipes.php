<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
  header("Location: admin_login.html");
  exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Handle approve/decline
if (isset($_GET['action']) && isset($_GET['id'])) {
  $id = intval($_GET['id']);
  $action = $_GET['action'];
  if ($action === 'approve') {
    $conn->query("UPDATE recipes SET status='approved' WHERE id=$id");
  } elseif ($action === 'decline') {
    $conn->query("UPDATE recipes SET status='declined' WHERE id=$id");
  }
  header("Location: pending_recipes.php");
  exit();
}

$sql = "SELECT r.*, u.username FROM recipes r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.status='pending'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Pending Recipes</title>
  <style>
    body { font-family: 'Poppins', sans-serif; padding: 30px; background-color: #fdfaf5; }
    .recipe {
      background: #fff; padding: 20px; margin-bottom: 25px;
      border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .recipe img { max-width: 100%; border-radius: 10px; margin-top: 10px; }
    h2 { color: #B05D57; }
    .actions a {
      text-decoration: none; padding: 8px 16px; margin-right: 10px;
      border-radius: 6px; font-weight: 600;
    }
    .approve { background: #28a745; color: #fff; }
    .decline { background: #dc3545; color: #fff; }
  </style>
</head>
<body>

<h2>📌 Pending Recipes</h2>

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
      <div class="actions">
        <a href="?action=approve&id=<?php echo $row['id']; ?>" class="approve">Approve</a>
        <a href="?action=decline&id=<?php echo $row['id']; ?>" class="decline">Decline</a>
      </div>
    </div>
  <?php endwhile; ?>
<?php else: ?>
  <p>No pending recipes at the moment.</p>
<?php endif; ?>

</body>
</html>
