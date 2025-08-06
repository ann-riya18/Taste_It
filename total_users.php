<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$result = $conn->query("SELECT username, email FROM users");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Total Users</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f7f7f7; }
    table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
    th { background: #B0C364; color: white; }
  </style>
</head>
<body>
  <h2>👥 Registered Users</h2>
  <table>
    <tr>
      <th>Username</th>
      <th>Email</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?php echo htmlspecialchars($row['username']); ?></td>
        <td><?php echo htmlspecialchars($row['email']); ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</body>
</html>
