<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$sql = "SELECT u.id, u.username, u.email, u.profile_pic, COUNT(r.id) AS recipe_count
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
    body {
        font-family: Arial, sans-serif;
        padding: 20px;
        background: #f2f2f2;
    }
    h2 {
        color: #5A6E2D;
        margin-bottom: 20px;
    }
    .container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    .card {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    .profile-pic {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 15px;
    }
    .username {
        font-size: 18px;
        font-weight: bold;
        color: #333;
        margin-bottom: 5px;
    }
    .email {
        font-size: 14px;
        color: #666;
        margin-bottom: 10px;
    }
    .recipes {
        font-size: 14px;
        font-weight: bold;
        color: #5A6E2D;
    }
  </style>
</head>
<body>
  <h2>👨‍🍳 Total Chefs</h2>

  <div class="container">
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="card" onclick="window.location.href='profile.php?id=<?php echo $row['id']; ?>'">
                <?php if (!empty($row['profile_pic'])): ?>
                    <img src="<?php echo htmlspecialchars($row['profile_pic']); ?>" alt="Profile" class="profile-pic">
                <?php else: ?>
                    <img src="uploads/default.png" alt="Default Profile" class="profile-pic">
                <?php endif; ?>
                <div class="username"><?php echo htmlspecialchars($row['username']); ?></div>
                <div class="email"><?php echo htmlspecialchars($row['email']); ?></div>
                <div class="recipes">Approved Recipes: <?php echo $row['recipe_count']; ?></div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No chefs found yet.</p>
    <?php endif; ?>
  </div>
</body>
</html>
