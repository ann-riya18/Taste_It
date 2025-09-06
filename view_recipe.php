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
    $uploaded_by = $row['user_id'];

    // New category fields
    $cuisine = htmlspecialchars($row['cuisine']);
    $course  = htmlspecialchars($row['course']);
    $diet    = htmlspecialchars($row['diet']);
    $quick   = htmlspecialchars($row['quick_recipe']);

    // Fetch uploader details
    $sql_user = "SELECT username, profile_pic, bio FROM users WHERE id=$uploaded_by";
    $res_user = $conn->query($sql_user);
    $user = $res_user->fetch_assoc();
    $uploader_name = $user['username'] ?? "Unknown Chef";
    $uploader_pic = $user['profile_pic'] ?: "img/default_user.png";
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
    body {font-family: 'Poppins', sans-serif; margin:0; padding:0; background:#f9f9f9;}
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

    /* Uploaded By Card */
    .uploader-card {
        display:flex;
        align-items:center;
        background:#f3f3f3;
        padding:12px;
        border-radius:8px;
        margin-top:20px;
        transition:0.2s;
        cursor:pointer;
    }
    .uploader-card:hover {background:#e0e0e0;}
    .uploader-card img {
        width:50px;
        height:50px;
        border-radius:50%;
        margin-right:15px;
        object-fit:cover;
        border:2px solid #5A6E2D;
    }
    .uploader-card h3 {margin:0; font-size:18px; color:#333;}
    .uploader-card p {margin:2px 0 0; font-size:14px; color:#666;}

    /* Categories */
    .categories ul {list-style:none; padding:0;}
    .categories li {margin-bottom:8px;}
    .categories a {color:#B0C364; text-decoration:none; font-weight:500;}
    .categories a:hover {text-decoration:underline;}
  </style>
</head>
<body>
  <div class="container">
    <h1><?php echo $title; ?></h1>
    <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>">

    <div class="section categories">
      <h2>📂 Categories</h2>
      <ul>
        <?php if ($cuisine): ?>
          <li>Cuisine: <a href="search.php?cuisine=<?php echo urlencode($cuisine); ?>"><?php echo $cuisine; ?></a></li>
        <?php endif; ?>
        <?php if ($course): ?>
          <li>Course: <a href="search.php?course=<?php echo urlencode($course); ?>"><?php echo $course; ?></a></li>
        <?php endif; ?>
        <?php if ($diet): ?>
          <li>Diet: <a href="search.php?diet=<?php echo urlencode($diet); ?>"><?php echo $diet; ?></a></li>
        <?php endif; ?>
        <?php if ($quick): ?>
          <li>Quick Recipe: <a href="search.php?quick=<?php echo urlencode($quick); ?>"><?php echo $quick; ?></a></li>
        <?php endif; ?>
      </ul>
    </div>

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

    <!-- Uploaded By Card -->
    <div class="section">
      <h2>👨‍🍳 Uploaded By</h2>
      <a href="profile.php?id=<?php echo $uploaded_by; ?>" style="text-decoration:none;">
        <div class="uploader-card">
          <img src="<?php echo $uploader_pic; ?>" alt="<?php echo $uploader_name; ?>">
          <div>
            <h3><?php echo $uploader_name; ?></h3>
            <p>View Profile</p>
          </div>
        </div>
      </a>
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
