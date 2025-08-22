<?php
session_start();
// DB connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = $_GET['id'] ?? 0;
$sql = "SELECT * FROM recipes WHERE id=$id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $title = htmlspecialchars($row['title']);
    $desc = nl2br(htmlspecialchars($row['description']));
    $steps = nl2br(htmlspecialchars($row['steps']));
    $ingredients = explode(",", $row['ingredients']);
    $img = $row['image_path'] ?: "img/placeholder.png";
} else {
    echo "Recipe not found.";
    exit;
}

// --- Handle Likes ---
if (isset($_POST['like'])) {
    if (!isset($_SESSION['user_id'])) {
        echo "<script>alert('Only registered users can like recipes. Please login first.'); window.location.href='login.html';</script>";
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $check = $conn->query("SELECT * FROM likes WHERE user_id=$user_id AND recipe_id=$id");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO likes (user_id, recipe_id) VALUES ($user_id, $id)");
    }
}

// --- Handle Bookmarks ---
if (isset($_POST['bookmark'])) {
    if (!isset($_SESSION['user_id'])) {
        echo "<script>alert('Only registered users can save recipes. Please login first.'); window.location.href='login.html';</script>";
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $check = $conn->query("SELECT * FROM bookmarks WHERE user_id=$user_id AND recipe_id=$id");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO bookmarks (user_id, recipe_id) VALUES ($user_id, $id)");
    }
}

// --- Handle Comments ---
if (isset($_POST['comment_text'])) {
    if (!isset($_SESSION['user_id'])) {
        echo "<script>alert('Only registered users can comment. Please login first.'); window.location.href='login.html';</script>";
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $comment = $conn->real_escape_string($_POST['comment_text']);
    $conn->query("INSERT INTO comments (recipe_id, user_id, comment_text, status) 
                  VALUES ($id, $user_id, '$comment', 'approved')");
}

// Fetch likes count
$res_likes = $conn->query("SELECT COUNT(*) as total FROM likes WHERE recipe_id=$id");
$likes_count = $res_likes->fetch_assoc()['total'];

// Fetch comments
$sql_comments = "SELECT c.comment_text, u.username 
                 FROM comments c 
                 JOIN users u ON c.user_id=u.id 
                 WHERE c.recipe_id=$id 
                 ORDER BY c.created_at DESC";
$res_comments = $conn->query($sql_comments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo $title; ?> - TasteIt</title>
  <style>
    body {font-family: Arial, sans-serif; margin:0; padding:0; background:#f9f9f9;}
    .container {width:80%; margin:50px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1);}
    h1 {color:#333;}
    img {max-width:100%; border-radius:10px; margin-bottom:20px;}
    .section {margin-bottom:25px;}
    ul {list-style: disc; padding-left:20px;}
    ul li {margin:6px 0; font-size:16px; color:#444;}
    table {width:100%; border-collapse: collapse; margin-top:15px;}
    table th, table td {border:1px solid #ddd; padding:10px; text-align:left;}
    table th {background:#5A6E2D; color:#fff;}
    table td a {
        margin-right:8px;
        text-decoration:none; 
        padding:6px 10px; 
        border-radius:5px; 
        font-size:14px; 
        background:#5A6E2D; 
        color:#fff;
        display:inline-block;
    }
    table td a:hover {background:#3d4e1f;}
    .actions {margin-top:20px;}
    .actions form {display:inline-block; margin-right:10px;}
    .comment-box {margin-top:20px;}
    .comment-box textarea {width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;}
    .comment-box button {margin-top:10px; background:#5A6E2D; color:#fff; border:none; padding:8px 14px; border-radius:5px; cursor:pointer;}
    .comment {background:#f3f3f3; padding:8px; margin:5px 0; border-radius:6px;}
  </style>
</head>
<body>
  <div class="container">
    <h1><?php echo $title; ?></h1>
    <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>">

    <div class="section">
      <h2>Description</h2>
      <p><?php echo $desc; ?></p>
    </div>

    <div class="section">
      <h2>Ingredients</h2>
      <ul>
        <?php foreach ($ingredients as $ing): ?>
          <li><?php echo htmlspecialchars(trim($ing)); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="section">
      <h2>Steps</h2>
      <p><?php echo $steps; ?></p>
    </div>

    <div class="section">
      <h2>🛒 Buy Ingredients</h2>
      <table>
        <tr>
          <th>Ingredient</th>
          <th>Buy Links</th>
        </tr>
        <?php foreach ($ingredients as $ing): 
            $ing = trim($ing);
            $search = urlencode($ing);
        ?>
        <tr>
          <td><?php echo htmlspecialchars($ing); ?></td>
          <td>
            <a href="https://www.amazon.in/s?k=<?php echo $search; ?>&i=grocery" target="_blank">Amazon Fresh</a>
            <a href="https://www.bigbasket.com/ps/?q=<?php echo $search; ?>" target="_blank">BigBasket</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="section actions">
      <form method="post">
        <button type="submit" name="like">👍 Like (<?php echo $likes_count; ?>)</button>
      </form>

      <form method="post">
        <button type="submit" name="bookmark">🔖 Save</button>
      </form>
    </div>

    <div class="section comment-box">
      <h2>💬 Comments</h2>
      <form method="post">
        <textarea name="comment_text" rows="3" placeholder="Write a comment..."></textarea>
        <button type="submit">Post Comment</button>
      </form>

      <div>
        <?php while ($com = $res_comments->fetch_assoc()): ?>
          <div class="comment"><b><?php echo htmlspecialchars($com['username']); ?>:</b> <?php echo htmlspecialchars($com['comment_text']); ?></div>
        <?php endwhile; ?>
      </div>
    </div>
  </div>
</body>
</html>
