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

// Handle status updates
if (isset($_GET['action']) && isset($_GET['id'])) {
  $id = intval($_GET['id']);
  $action = $_GET['action'];

  if (in_array($action, ['approve', 'flag'])) {
    $conn->query("UPDATE comments SET status='$action' WHERE id=$id");
  } elseif ($action === 'delete') {
    $conn->query("DELETE FROM comments WHERE id=$id");
  }
  header("Location: review_comments.php");
  exit();
}

// Fetch all comments
$sql = "SELECT comments.*, users.username, recipes.title AS recipe_title
        FROM comments
        JOIN users ON comments.user_id = users.id
        JOIN recipes ON comments.recipe_id = recipes.id
        ORDER BY comments.created_at DESC";

$result = $conn->query($sql);

// List of sensitive words
$sensitiveWords = ['worst', 'trash', 'idiot', 'hate', 'stupid'];
?>

<!DOCTYPE html>
<html>
<head>
  <title>Review Comments</title>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      padding: 40px;
      background-color: #fffaf4;
    }
    h2 {
      color: #D7263D;
    }
    .comment {
      background: #fff;
      padding: 20px;
      margin-bottom: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }
    .comment p {
      margin: 5px 0;
    }
    .flag {
      color: red;
      font-weight: bold;
    }
    .actions a {
      margin-right: 10px;
      text-decoration: none;
      padding: 8px 12px;
      font-weight: 600;
      border-radius: 5px;
    }
    .approve { background: #28a745; color: white; }
    .flag-btn { background: #ffc107; color: black; }
    .delete { background: #dc3545; color: white; }
  </style>
</head>
<body>

<h2>Admin Comment Review</h2>

<?php if ($result->num_rows > 0): ?>
  <?php while ($row = $result->fetch_assoc()):
    $isSensitive = false;
    foreach ($sensitiveWords as $word) {
      if (stripos($row['comment_text'], $word) !== false) {
        $isSensitive = true;
        break;
      }
    }
  ?>
    <div class="comment">
      <p><strong>User:</strong> <?= htmlspecialchars($row['username']) ?></p>
      <p><strong>Recipe:</strong> <?= htmlspecialchars($row['recipe_title']) ?></p>
      <p><strong>Comment:</strong> <?= htmlspecialchars($row['comment_text']) ?>
        <?php if ($isSensitive): ?><span class="flag">⚠ Sensitive</span><?php endif; ?>
      </p>
      <p><strong>Status:</strong> <?= htmlspecialchars($row['status']) ?></p>
      <div class="actions">
        <a class="approve" href="?action=approve&id=<?= $row['id'] ?>">Approve</a>
        <a class="flag-btn" href="?action=flag&id=<?= $row['id'] ?>">Flag</a>
        <a class="delete" href="?action=delete&id=<?= $row['id'] ?>">Delete</a>
      </div>
    </div>
  <?php endwhile; ?>
<?php else: ?>
  <p>No comments available.</p>
<?php endif; ?>

</body>
</html>

<?php $conn->close(); ?>
