<?php
session_start();
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$id = $_GET['id'] ?? 0;

// --- HANDLE LIKES ---
if(isset($_POST['like']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $check = $conn->query("SELECT * FROM likes WHERE user_id=$user_id AND recipe_id=$id");
    if($check->num_rows == 0){
        $conn->query("INSERT INTO likes (user_id, recipe_id) VALUES ($user_id, $id)");
    } else {
        $conn->query("DELETE FROM likes WHERE user_id=$user_id AND recipe_id=$id");
    }
    header("Location: view_recipe.php?id=$id");
    exit();
}

// --- HANDLE BOOKMARKS ---
if(isset($_POST['bookmark']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $check = $conn->query("SELECT * FROM bookmarks WHERE user_id=$user_id AND recipe_id=$id");
    if($check->num_rows == 0){
        $conn->query("INSERT INTO bookmarks (user_id, recipe_id) VALUES ($user_id, $id)");
    }
    header("Location: view_recipe.php?id=$id");
    exit();
}

// --- FETCH RECIPE ---
$sql = "SELECT * FROM recipes WHERE id=$id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $title = htmlspecialchars($row['title']);
    $desc = nl2br(htmlspecialchars($row['description']));
    $steps = nl2br(htmlspecialchars($row['steps']));
    $ingredients = array_map('trim', explode(",", $row['ingredients']));
    $ingredients_line = implode(', ', $ingredients); // single line
    $img = $row['image_path'] ?: "img/placeholder.png";
} else { echo "Recipe not found."; exit; }

// --- FETCH LIKES COUNT ---
$res_likes = $conn->query("SELECT COUNT(*) as total FROM likes WHERE recipe_id=$id");
$likes_count = $res_likes->fetch_assoc()['total'];

// --- FETCH COMMENTS ---
$sql_comments = "SELECT c.id, c.comment_text, c.user_id, u.username 
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
<title><?= $title ?> - TasteIt</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {font-family:'Poppins',sans-serif; margin:0; padding:0; background:#f9f9f9;}
.container {width:90%; margin:40px auto; background:#fff; padding:20px; border-radius:10px; display:flex; gap:30px;}
.left {flex:1;}
.right {flex: 0 0 40%;}
img {width:100%; border-radius:10px;}
.right img {
    width: 100%;       /* makes it as wide as the right column */
    height: 400px;     /* fixed height */
    object-fit: cover; /* crops proportionally without stretching */
    border-radius: 10px;
}

h1,h2 {color:#333;}
.ingredients {font-weight:bold; margin:10px 0;}
.actions i {cursor:pointer; font-size:24px; margin-right:15px; color:#555;}
.actions i:hover {color:#B0C364;}
.comment-box {margin-top:20px;}
.comment {background:#f3f3f3; padding:10px; margin:5px 0; border-radius:6px;}
.comment-actions {margin-top:5px;}
.comment-actions i {cursor:pointer; margin-right:10px; color:#555;}
.reply-box {margin-left:20px; margin-top:5px;}
textarea {width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;}
button {margin-top:5px; background:#B0C364; color:#fff; border:none; padding:6px 12px; border-radius:5px; cursor:pointer;}

.buy-ingredients table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-family: 'Poppins', sans-serif;
}

.buy-ingredients th, .buy-ingredients td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: left;
}

.buy-ingredients th {
    background: #B0C364;
    color: #fff;
}

.buy-ingredients .buy-btn {
    text-decoration: none;
    background: #B0C364;
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 14px;
    margin-right: 8px;
    display: inline-block;
    transition: background 0.3s;
}

.buy-ingredients .buy-btn:hover {
    background: #8a9a45;
}
</style>
</head>
<body>

<h1 style="text-align:center; color:#333; font-family:'Poppins',sans-serif; margin-top:20px;">Recipe Overview</h1>

<div class="container">
  <div class="left">
    <h1><?= $title ?></h1>
    <div class="section">
      <h2>Description</h2>
      <p><?= $desc ?></p>
    </div>

    <div class="section">
      <h2>Ingredients</h2>
      <p class="ingredients"><?= $ingredients_line ?></p>
    </div>

    <div class="section">
      <h2>Steps</h2>
      <p><?= $steps ?></p>
    </div>

    <div class="section buy-ingredients">
      <h2>🛒 Buy Ingredients</h2>
      <table>
        <tr>
          <th>Ingredient</th>
          <th>Buy Links</th>
        </tr>
        <?php foreach ($ingredients as $ing): 
            $search = urlencode($ing);
        ?>
        <tr>
          <td><?= htmlspecialchars($ing); ?></td>
          <td>
            <a href="https://www.amazon.in/s?k=<?= $search ?>&i=grocery" target="_blank" class="buy-btn">Amazon Fresh</a>
            <a href="https://www.bigbasket.com/ps/?q=<?= $search ?>" target="_blank" class="buy-btn">BigBasket</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="section actions">
      <form method="post" style="display:inline;">
        <button type="submit" name="like"><i class="fa-regular fa-heart"></i> <?= $likes_count ?></button>
      </form>
      <form method="post" style="display:inline;">
        <button type="submit" name="bookmark"><i class="fa-regular fa-bookmark"></i> Save</button>
      </form>
    </div>

    <div class="section comment-box">
      <h2>Comments</h2>
      <form method="post">
        <textarea name="comment_text" rows="2" placeholder="Write a comment..."></textarea>
        <button type="submit">Post</button>
      </form>

      <?php while($com = $res_comments->fetch_assoc()): ?>
        <div class="comment">
          <b><?= htmlspecialchars($com['username']); ?>:</b> <?= htmlspecialchars($com['comment_text']); ?>
          <div class="comment-actions">
            <i class="fa-regular fa-heart" title="Like comment"></i>
            <i class="fa-solid fa-reply" title="Reply"></i>
            <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id']==$com['user_id']): ?>
              <i class="fa-solid fa-trash" title="Delete"></i>
            <?php endif; ?>
          </div>
          <div class="reply-box"></div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>

  <div class="right">
    <img src="<?= $img ?>" alt="<?= $title ?>">
  </div>
</div>

</body>
</html>
