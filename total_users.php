<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch username, email, and profile picture
$result = $conn->query("SELECT username, email, profile_pic FROM users");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Total Users</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f7f7f7; }
    table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: middle; }
    th { background: #B0C364; color: white; }
    img.profile-pic {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
    }
  </style>
</head>
<body>
  <h2>👥 Registered Users</h2>
  <table>
    <tr>
      <th>Profile Picture</th>
      <th>Username</th>
      <th>Email</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td>
          <?php 
            $profileImage = "uploads/default.png"; // fallback default

            if (!empty($row['profile_pic'])) {
                // If it already contains 'uploads/', assume it's a full path
                if (strpos($row['profile_pic'], 'uploads/') === 0) {
                    $profileImage = htmlspecialchars($row['profile_pic']);
                } else {
                    // Otherwise, assume it's just a filename
                    $profileImage = "uploads/user_images/" . htmlspecialchars($row['profile_pic']);
                }
            }
          ?>
          <img src="<?php echo $profileImage; ?>" alt="Profile Picture" class="profile-pic"
               onerror="this.onerror=null; this.src='uploads/default.png'">
        </td>
        <td><?php echo htmlspecialchars($row['username']); ?></td>
        <td><?php echo htmlspecialchars($row['email']); ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</body>
</html>
