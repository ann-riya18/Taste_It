<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$sql = "SELECT u.username, u.email, COUNT(r.id) AS recipe_count
        FROM users u
        JOIN recipes r ON u.id = r.user_id
        WHERE r.status = 'approved'
        GROUP BY u.id";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Total Chefs</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f2f2f2; }
    table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #B0C364; color: white; }
    tr:hover { background-color: #f5f5f5; }
    h2 { color: #5A6E2D; }
  </style>
</head>
<body>
  <h2>👨‍🍳 Total Chefs</h2>

  <?php if ($result->num_rows > 0): ?>
    <table>
      <tr>
        <th>Username</th>
        <th>Email</th>
        <th>Approved Recipes</th>
      </tr>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($row['username']); ?></td>
          <td><?php echo htmlspecialchars($row['email']); ?></td>
          <td><?php echo $row['recipe_count']; ?></td>
        </tr>
      <?php endwhile; ?>
    </table>
  <?php else: ?>
    <p>No chefs found yet.</p>
  <?php endif; ?>
</body>
</html>
