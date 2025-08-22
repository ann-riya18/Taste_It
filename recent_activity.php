<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
  header("Location: admin_login.html");
  exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$recent = $conn->query("
  SELECT r.title, u.username, r.status, r.created_at 
  FROM recipes r 
  JOIN users u ON r.user_id = u.id 
  ORDER BY r.created_at DESC 
  LIMIT 20
");
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Recent Activity - Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: url('img/bg15.jpg') no-repeat center center/cover;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 1000px;
      margin: 50px auto;
      background: rgba(255,255,255,0.95);
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 5px 25px rgba(0,0,0,0.1);
    }
    h2 {
      color: #5A6E2D;
      margin-bottom: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th, td {
      padding: 12px 15px;
      text-align: left;
    }
    th {
      background-color: #B0C364;
      color: #fff;
    }
    tr:nth-child(even) {
      background-color: #f2f2f2;
    }
    .status-approved { color: green; font-weight: bold; }
    .status-pending { color: orange; font-weight: bold; }
    .status-declined { color: red; font-weight: bold; }
    a.back-link {
      display: inline-block;
      margin-top: 20px;
      color: #5A6E2D;
      text-decoration: none;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>🕒 Recent Recipe Activity</h2>
    <table>
      <thead>
        <tr>
          <th>Title</th>
          <th>Uploaded By</th>
          <th>Status</th>
          <th>Date Uploaded</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recent->num_rows > 0): ?>
          <?php while ($row = $recent->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['title']); ?></td>
              <td><?php echo htmlspecialchars($row['username']); ?></td>
              <td class="status-<?php echo strtolower($row['status']); ?>">
                <?php echo ucfirst($row['status']); ?>
              </td>
              <td><?php echo date("M d, Y H:i", strtotime($row['created_at'])); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="4">No recent activity found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>
  </div>
</body>
</html>
