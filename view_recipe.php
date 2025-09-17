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

// --- Ratings ---
$res_rating = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM recipe_ratings WHERE recipe_id=$id");
$rating_data = $res_rating->fetch_assoc();
$avg_rating = round($rating_data['avg_rating'],1);
$total_ratings = $rating_data['total_ratings'];

// Handle rating submission
if(isset($_POST['rating']) && isset($_SESSION['user_id'])){
    $user_id = $_SESSION['user_id'];
    $rating_value = intval($_POST['rating']);
    $check = $conn->query("SELECT * FROM recipe_ratings WHERE user_id=$user_id AND recipe_id=$id");
    if($check->num_rows > 0){
        $conn->query("UPDATE recipe_ratings SET rating=$rating_value, rated_at=NOW() WHERE user_id=$user_id AND recipe_id=$id");
    } else {
        $conn->query("INSERT INTO recipe_ratings (recipe_id,user_id,rating) VALUES ($id,$user_id,$rating_value)");
    }
    echo "<script>window.location.href='view_recipe.php?id=$id';</script>";
    exit;
}

// Fetch user's previous rating
$user_rating = 0;
if(isset($_SESSION['user_id'])){
    $user_id = $_SESSION['user_id'];
    $res_user_rating = $conn->query("SELECT rating FROM recipe_ratings WHERE recipe_id=$id AND user_id=$user_id");
    if($res_user_rating->num_rows > 0){
        $user_rating = intval($res_user_rating->fetch_assoc()['rating']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo $title; ?> - TasteIt</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { 
      font-family: 'Poppins', sans-serif; 
      margin:0; 
      padding:0; 
      background:#f9f9f9 url('img/bg17.jpg') no-repeat center center fixed;
      background-size: cover;
      position: relative;
    }
    
    /* Transparent white overlay for dusky professional look */
    body::before {
      content: "";
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(255, 255, 255, 0.85);
      z-index: -1;
    }
    
    .container {
      width:90%;
      max-width:1200px;
      margin:30px auto;
      display:flex;
      flex-wrap:wrap;
      gap:20px;
    }
    
    .recipe-header {
      flex:1 1 100%;
      display:flex;
      gap:30px;
      background:#fff;
      border-radius:12px;
      overflow:hidden;
      box-shadow:0 4px 15px rgba(0,0,0,0.08);
    }
    
    .recipe-image {
      flex:0 0 350px;
      padding:30px;
      display:flex;
      align-items:center;
      justify-content:center;
    }
    
    .recipe-image img {
      width:300px;
      height:300px;
      border-radius:50%;
      object-fit:cover;
      border:5px solid #b0c364;
      box-shadow:0 5px 15px rgba(0,0,0,0.1);
    }
    
    .recipe-info {
      flex:1;
      padding:30px;
      display:flex;
      flex-direction:column;
    }
    
    .recipe-title {
      font-size:32px;
      color:#333;
      margin:0 0 10px;
    }
    
    .recipe-meta {
      display:flex;
      align-items:center;
      margin-bottom:20px;
      color:#666;
    }
    
    .recipe-meta i {
      margin-right:8px;
      color:#b0c364;
    }
    
    .content-section {
      flex:1 1 calc(50% - 10px);
      background:#fff;
      border-radius:12px;
      padding:25px;
      box-shadow:0 4px 15px rgba(0,0,0,0.08);
    }
    
    .full-width {
      flex:1 1 100%;
    }
    
    .section-title {
      font-size:20px;
      color:#333;
      margin:0 0 15px;
      padding-bottom:10px;
      border-bottom:2px solid #f0f0f0;
      display:flex;
      align-items:center;
    }
    
    .section-title i {
      margin-right:10px;
      color:#b0c364;
    }
    
    .categories-list {
      list-style:none;
      padding:0;
      margin:0;
    }
    
    .categories-list li {
      margin-bottom:10px;
      padding:8px 12px;
      background:#f8f8f8;
      border-radius:6px;
      border-left:3px solid #b0c364;
    }
    
    .categories-list span {
      color:#333;
      font-weight:500;
    }
    
    .ingredients-list {
      list-style:none;
      padding:0;
      margin:0;
      display:grid;
      grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));
      gap:10px;
    }
    
    .ingredients-list li {
      padding:10px;
      background:#f8f8f8;
      border-radius:6px;
      border-left:3px solid #b0c364;
    }
    
    .steps-content {
      line-height:1.6;
    }
    
    .action-buttons {
      display:flex;
      gap:15px;
      margin-top:20px;
    }
    
    .action-button {
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:10px 15px;
      border-radius:8px;
      border:none;
      font-size:16px;
      cursor:pointer;
      transition:all 0.3s ease;
    }
    
    .like-button {
      background:#fff;
      color:#b0c364;
      border:1px solid #b0c364;
    }
    
    .save-button {
      background:#fff;
      color:#b0c364;
      border:1px solid #b0c364;
    }
    
    .action-button:hover {
      transform:translateY(-2px);
      box-shadow:0 4px 8px rgba(0,0,0,0.1);
      background:#b0c364;
      color:#fff;
    }
    
    .star-rating {
      font-size:24px;
      color:#ddd;
      margin:15px 0;
    }
    
    .star-rating .star {
      cursor:pointer;
      transition:color 0.2s;
    }
    
    .star-rating .star:hover,
    .star-rating .star.hover,
    .star-rating .star.selected {
      color:#b0c364;
    }
    
    .rating-submit {
      background:#b0c364;
      color:#fff;
      border:none;
      padding:8px 15px;
      border-radius:6px;
      cursor:pointer;
      margin-top:10px;
      transition:all 0.3s ease;
    }
    
    .rating-submit:hover {
      background:#9caf58;
    }
    
    .chef-card {
      background:linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
      border-radius:12px;
      padding:25px;
      box-shadow:0 4px 15px rgba(0,0,0,0.08);
      border:1px solid rgba(176,195,100,0.2);
      transition:all 0.3s ease;
      position:relative;
      overflow:hidden;
    }
    
    .chef-card::before {
      content:"";
      position:absolute;
      top:0;
      left:0;
      width:5px;
      height:100%;
      background-color:#b0c364;
    }
    
    .chef-card:hover {
      transform:translateY(-5px);
      box-shadow:0 8px 25px rgba(0,0,0,0.12);
    }
    
    .chef-profile {
      display:flex;
      align-items:center;
      gap:15px;
    }
    
    .chef-profile img {
      width:80px;
      height:80px;
      border-radius:50%;
      object-fit:cover;
      border:3px solid #b0c364;
      box-shadow:0 4px 10px rgba(0,0,0,0.1);
    }
    
    .chef-info h3 {
      margin:0 0 5px 0;
      font-size:18px;
      color:#333;
      font-weight:600;
    }
    
    .chef-info p {
      margin:0;
      color:#666;
      font-size:14px;
    }
    
    .chef-link {
      text-decoration:none;
      color:inherit;
      display:block;
    }
    
    .chef-link:hover .chef-info h3 {
      color:#b0c364;
    }
    
    table {
      width:100%;
      border-collapse:collapse;
      margin-top:15px;
    }
    
    table th, table td {
      border:1px solid #eee;
      padding:12px;
      text-align:left;
    }
    
    table th {
      background:#f8f8f8;
      font-weight:600;
      color:#333;
    }
    
    .bigbasket-btn {
      display:inline-block;
      padding:6px 12px;
      background:#fff;
      color:#b0c364;
      border:1px solid #b0c364;
      text-decoration:none;
      border-radius:4px;
      transition:all 0.3s ease;
    }
    
    .bigbasket-btn:hover {
      background:#b0c364;
      color:#fff;
    }
    
    .comment-form {
      margin-bottom:20px;
    }
    
    .comment-form textarea {
      width:100%;
      padding:12px;
      border:1px solid #ddd;
      border-radius:8px;
      resize:vertical;
      min-height:100px;
      font-family:inherit;
    }
    
    .comment-form button {
      background:#fff;
      color:#b0c364;
      border:1px solid #b0c364;
      padding:10px 15px;
      border-radius:8px;
      cursor:pointer;
      margin-top:10px;
      transition:all 0.3s ease;
    }
    
    .comment-form button:hover {
      background:#b0c364;
      color:#fff;
    }
    
    .comment {
      background:#f8f8f8;
      padding:15px;
      border-radius:8px;
      margin-bottom:10px;
      border-left:3px solid #b0c364;
    }
    
    .comment b {
      color:#333;
      display:block;
      margin-bottom:5px;
    }
    
    @media (max-width:768px) {
      .recipe-header {
        flex-direction:column;
      }
      
      .recipe-image {
        flex:0 0 auto;
      }
      
      .content-section {
        flex:1 1 100%;
      }
    }
  </style>
</head>
<body>
<div class="container">
  <div class="recipe-header">
    <div class="recipe-image">
      <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>">
    </div>
    <div class="recipe-info">
      <h1 class="recipe-title"><?php echo $title; ?></h1>
      <div class="recipe-meta">
        <i class="fas fa-user"></i> Shared by <?php echo $uploader_name; ?>
      </div>
      
      <!-- Categories -->
      <div class="section-title">
        <i class="fas fa-folder"></i> Categories
      </div>
      <ul class="categories-list">
        <?php if ($cuisine): ?><li><span>Cuisine: <?php echo $cuisine; ?></span></li><?php endif; ?>
        <?php if ($course): ?><li><span>Course: <?php echo $course; ?></span></li><?php endif; ?>
        <?php if ($diet): ?><li><span>Diet: <?php echo $diet; ?></span></li><?php endif; ?>
        <?php if ($quick): ?><li><span>Quick Recipe: <?php echo $quick; ?></span></li><?php endif; ?>
      </ul>
      
      <!-- Ratings -->
      <div class="section-title">
        <i class="fas fa-star"></i> Rating
      </div>
      <div>Average Rating: <b><?php echo $avg_rating; ?></b> (<?php echo $total_ratings; ?> ratings)</div>
      <form method="post" id="ratingForm">
        <div class="star-rating">
          <?php for($i=1;$i<=5;$i++): ?>
            <span class="star <?php echo ($i <= $user_rating) ? 'selected' : ''; ?>" data-value="<?php echo $i; ?>">&#9733;</span>
          <?php endfor; ?>
        </div>
        <?php if(isset($_SESSION['user_id'])): ?>
          <input type="hidden" name="rating" id="ratingInput" value="<?php echo $user_rating; ?>">
          <button type="submit" class="rating-submit">Submit Rating</button>
        <?php endif; ?>
      </form>
      
      <!-- Action Buttons -->
      <div class="action-buttons">
        <form method="post" style="display:inline;">
          <button type="submit" name="like" class="action-button like-button">
            <i class="fas fa-thumbs-up"></i> Like (<?php echo $likes_count; ?>)
          </button>
        </form>
        <form method="post" style="display:inline;">
          <button type="submit" name="bookmark" class="action-button save-button">
            <i class="fas fa-bookmark"></i> Save
          </button>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Chef Card -->
  <div class="content-section">
    <div class="chef-card">
      <div class="section-title">
        <i class="fas fa-user-chef"></i> Uploaded By
      </div>
      <a href="profile.php?id=<?php echo $uploaded_by; ?>" class="chef-link">
        <div class="chef-profile">
          <img src="<?php echo $uploader_pic; ?>" alt="<?php echo $uploader_name; ?>">
          <div class="chef-info">
            <h3><?php echo $uploader_name; ?></h3>
            <p>View Profile</p>
          </div>
        </div>
      </a>
    </div>
  </div>
  
  <!-- Description -->
  <div class="content-section">
    <div class="section-title">
      <i class="fas fa-align-left"></i> Description
    </div>
    <div class="steps-content"><?php echo $desc; ?></div>
  </div>
  
  <!-- Ingredients -->
  <div class="content-section">
    <div class="section-title">
      <i class="fas fa-list"></i> Ingredients
    </div>
    <ul class="ingredients-list">
      <?php foreach ($ingredients as $ing): ?>
        <li><?php echo htmlspecialchars(trim($ing)); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  
  <!-- Steps -->
  <div class="content-section">
    <div class="section-title">
      <i class="fas fa-tasks"></i> Steps
    </div>
    <div class="steps-content"><?php echo $steps; ?></div>
  </div>
  
  <!-- Buy Ingredients -->
  <div class="content-section full-width">
    <div class="section-title">
      <i class="fas fa-shopping-cart"></i> Buy Ingredients
    </div>
    <table>
      <tr><th>Ingredient</th><th>Buy Links</th></tr>
      <?php foreach ($ingredients as $ing): 
          $ing = trim($ing);
          $search = urlencode($ing);
      ?>
      <tr>
        <td><?php echo htmlspecialchars($ing); ?></td>
        <td>
          <a href="https://www.bigbasket.com/ps/?q=<?php echo $search; ?>" target="_blank" class="bigbasket-btn">BigBasket</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  
  <!-- Comments -->
  <div class="content-section full-width">
    <div class="section-title">
      <i class="fas fa-comments"></i> Comments
    </div>
    <div class="comment-form">
      <form method="post">
        <textarea name="comment_text" placeholder="Write a comment..."></textarea>
        <button type="submit">Post Comment</button>
      </form>
    </div>
    
    <div>
      <?php while ($com = $res_comments->fetch_assoc()): ?>
        <div class="comment"><b><?php echo htmlspecialchars($com['username']); ?>:</b> <?php echo htmlspecialchars($com['comment_text']); ?></div>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<script>
  const stars = document.querySelectorAll('.star-rating .star');
  <?php if(isset($_SESSION['user_id'])): ?>
  const ratingInput = document.getElementById('ratingInput');
  stars.forEach(star => {
    star.addEventListener('mouseover', () => {
      stars.forEach(s => s.classList.remove('hover'));
      let val = parseInt(star.getAttribute('data-value'));
      for(let i=0;i<val;i++) stars[i].classList.add('hover');
    });
    star.addEventListener('mouseout', () => { stars.forEach(s => s.classList.remove('hover')); });
    star.addEventListener('click', () => {
      let val = parseInt(star.getAttribute('data-value'));
      ratingInput.value = val;
      stars.forEach(s => s.classList.remove('selected'));
      for(let i=0;i<val;i++) stars[i].classList.add('selected');
    });
  });
  <?php else: ?>
  stars.forEach(star => {
    star.addEventListener('click', () => { alert('Please login to rate this recipe.'); });
  });
  <?php endif; ?>
</script>
</body>
</html>