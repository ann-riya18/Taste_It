<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// also fetch image_path
$result = $conn->query("SELECT r.title, r.image_path, u.username 
                        FROM recipes r 
                        JOIN users u ON r.user_id = u.id 
                        WHERE r.status = 'approved'");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Approved Recipes</title>
  <style>
    body { 
      font-family: Arial, sans-serif; 
      padding: 20px; 
      background: #f9f9f9; 
    }
    h2 { color: #5A6E2D; }

    .card {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #fff;
      padding: 15px;
      margin-bottom: 15px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .card-details {
      flex: 1;
    }

    .card h3 { 
      margin: 0; 
      color: #5A6E2D; 
    }
    .card p { 
      margin: 5px 0; 
      color: #333; 
    }

    .card img {
      width: 120px;
      height: 90px;
      border-radius: 6px;
      object-fit: cover;   /* crop without distortion */
      margin-left: 15px;
    }
  </style>
</head>
<body>
  <h2>✅ Approved Recipes</h2>
  <?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="card">
        <div class="card-details">
          <h3><?php echo htmlspecialchars($row['title']); ?></h3>
          <p><strong>Chef:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
        </div>
        <?php if (!empty($row['image_path'])): ?>
          <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="Recipe Image">
        <?php endif; ?>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p>No approved recipes yet.</p>
  <?php endif; ?>
</body>
</html>
