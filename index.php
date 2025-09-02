<?php
// DB connection
session_start();
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'] ?? 0; // assuming user_id stored in session

// Fetch latest recipes (limit 8 for homepage cards)
$sql = "SELECT id, title, image_path FROM recipes ORDER BY id DESC LIMIT 8";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TasteIt - Home</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{ --brand:#B0C364; --text:#fff; --muted:#666; }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Poppins',sans-serif;background:#f9f9f9;color:#222;overflow-x:hidden}
    /* HEADER */
    .transparent-header{position:fixed;top:0;left:0;width:100%;background:rgba(0,0,0,0.4);padding:15px 40px;z-index:1000}
    .transparent-header .container{display:flex;justify-content:space-between;align-items:center;max-width:1400px;margin:0 auto}
    .transparent-header .logo{font-size:28px;font-weight:bold;color:#fff}
    .transparent-header nav ul{list-style:none;display:flex;gap:25px}
    .transparent-header nav ul li a{text-decoration:none;color:#fff;font-weight:500;transition:0.3s}
    .transparent-header nav ul li a:hover{color:#B0C364}
    /* HERO */
    .hero{height:100vh;position:relative;display:flex;justify-content:center;align-items:center;overflow:hidden}
    .hero video{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:1}
    .hero::after{content:"";position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:2}
    .hero-content{position:relative;z-index:3;background:rgba(176,195,100,0.8);padding:20px 40px;border-radius:15px;box-shadow:0 4px 10px rgba(0,0,0,0.3);text-align:center}
    .hero-content h2{color:#fff;font-size:50px;font-weight:bold}
    .hero-content p {color:#fff;font-size:18px;margin:15px 0;}
    .hero-content .btn {display:inline-block;padding:12px 28px;background-color:#B0C364;color:#fff;text-decoration:none;border-radius:8px;font-weight:500;transition:0.3s;}
    .hero-content .btn:hover {background-color:#97b24e;}
    /* RECIPES */
    .recipes{background:#fff;padding:56px 10%;text-align:center}
    .recipes h2{font-size:28px;margin-bottom:28px;font-weight:600}
    .grid{display:grid;grid-template-columns:repeat(4, 1fr);gap:24px}
    .card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,.08);transition:transform .25s ease, box-shadow .25s ease;text-decoration:none;color:inherit;display:block}
    .card:hover{transform:translateY(-6px);box-shadow:0 10px 20px rgba(0,0,0,.12)}
    .card img{width:100%;height:180px;object-fit:cover}
    .card-body{padding:14px;display:flex;justify-content:space-between;align-items:center}
    .card-title{font-size:18px;color:#444;font-weight:600}
    .card-icons{display:flex;gap:12px;font-size:18px;color:#B0C364;cursor:pointer}
    .card-icons i.liked, .card-icons i.saved {color:#e63946;} /* red for heart, default for bookmark */
    .card-icons i.saved {color:#b0c364;}
    .card-icons i:hover{color:#222}
    /* ABOUT */
    .about{background:#f3f3f3;text-align:center;padding:56px 10%}
    .about h2{font-size:28px;margin-bottom:16px}
    .about p{max-width:720px;margin:0 auto;color:#555;line-height:1.7}
    /* FOOTER */
    footer{background:var(--brand);color:#fff;text-align:center;padding:20px}
  </style>
</head>
<body>
<!-- HEADER -->
<header class="transparent-header">
  <div class="container">
    <h1 class="logo">TasteIt</h1>
    <nav>
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="#recipe">Recipes</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="login.html">Login</a></li>
      </ul>
    </nav>
  </div>
</header>
<!-- HERO -->
<section class="hero">
  <video autoplay muted loop playsinline>
    <source src="img/bgvideo.mp4" type="video/mp4">
  </video>
  <div class="hero-content">
    <h2>Welcome to TasteIt</h2>
    <p>Discover delicious recipes from around the world</p>
    <a href="search.php" class="btn">Explore Recipes</a>
  </div>
</section>
<!-- RECIPES -->
<section id="recipe" class="recipes">
  <h2>Popular Recipes</h2>
  <div class="grid">
    <?php
    if ($result && $result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
        $id    = $row['id'];
        $img   = trim($row['image_path'] ?? '');
        if ($img === '') { $img = 'img/placeholder.png'; }
        elseif (!preg_match('#^(https?://|/|uploads/)#i', $img)) { $img = 'uploads/'.$img; }
        $title = htmlspecialchars($row['title'] ?? 'Recipe', ENT_QUOTES, 'UTF-8');

        // check if liked/bookmarked by current user
        $liked = $bookmarked = false;
        if ($user_id) {
          $chk1 = $conn->query("SELECT 1 FROM likes WHERE user_id=$user_id AND recipe_id=$id");
          if ($chk1 && $chk1->num_rows > 0) $liked = true;
          $chk2 = $conn->query("SELECT 1 FROM bookmarks WHERE user_id=$user_id AND recipe_id=$id");
          if ($chk2 && $chk2->num_rows > 0) $bookmarked = true;
        }

        $heartClass = $liked ? "fa-solid fa-heart liked" : "fa-regular fa-heart";
        $bookmarkClass = $bookmarked ? "fa-solid fa-bookmark saved" : "fa-regular fa-bookmark";

        echo "
        <div class='card'>
          <a href='view_recipe.php?id={$id}'>
            <img src='{$img}' alt='{$title}'>
          </a>
          <div class='card-body'>
            <div class='card-title'>{$title}</div>
            <div class='card-icons'>
              <i class='{$heartClass}' data-id='{$id}' data-action='like'></i>
              <i class='{$bookmarkClass}' data-id='{$id}' data-action='bookmark'></i>
            </div>
          </div>
        </div>";
      }
    } else {
      echo '<p>No recipes found.</p>';
    }
    ?>
  </div>
</section>
<!-- ABOUT -->
<section id="about" class="about">
  <h2>About TasteIt</h2>
  <p>
    TasteIt is a platform for food lovers to discover, share, and enjoy recipes from all over the world.
  </p>
</section>
<!-- FOOTER -->
<footer class="site-footer">
  <div class="footer-container"> ... (same footer code you had) ... </div>
</footer>
<script>
document.querySelectorAll('.card-icons i').forEach(icon=>{
  icon.addEventListener('click', function(e){
    e.preventDefault();
    const recipeId = this.getAttribute('data-id');
    const action = this.getAttribute('data-action');
    fetch('toggle_action.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`id=${recipeId}&action=${action}`
    }).then(res=>res.json()).then(data=>{
      if(data.success){
        if(action==='like'){
          this.classList.toggle('fa-regular');
          this.classList.toggle('fa-solid');
          this.classList.toggle('liked');
        } else {
          this.classList.toggle('fa-regular');
          this.classList.toggle('fa-solid');
          this.classList.toggle('saved');
        }
      }
    });
  });
});
</script>
</body>
</html>
<?php $conn->close(); ?>
