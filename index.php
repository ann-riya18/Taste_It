<?php
// DB connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch latest recipes (limit 6 for homepage cards)
$sql = "SELECT id, title, description, image_path FROM recipes ORDER BY id DESC LIMIT 6";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TasteIt - Home</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
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
    .hero{height:100vh;display:flex;justify-content:center;align-items:center}
    .hero-content{background:rgba(176,195,100,0.8);padding:20px 40px;border-radius:15px;box-shadow:0 4px 10px rgba(0,0,0,0.3);text-align:center;z-index:2}
    .hero-content h2{color:#fff;font-size:50px;font-weight:bold}
    .slideshow{position:absolute;top:0;left:0;width:100%;height:100%;z-index:1}
    .slide{display:none;width:100%;height:100%}
    .slide img{width:100%;height:100%;object-fit:cover}

    /* RECIPES */
    .recipes{background:#fff;padding:56px 10%;text-align:center}
    .recipes h2{font-size:28px;margin-bottom:28px;font-weight:600}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:24px}
    .card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,.08);transition:transform .25s ease, box-shadow .25s ease;cursor:pointer;text-decoration:none;color:inherit;display:block}
    .card:hover{transform:translateY(-6px);box-shadow:0 10px 20px rgba(0,0,0,.12)}
    .card img{width:100%;height:180px;object-fit:cover}
    .card-body{padding:14px;text-align:left}
    .card-title{font-size:18px;color:#444;margin-bottom:8px}
    .card-desc{font-size:14px;color:#666;line-height:1.45}

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
  <div class="slideshow" id="home">
    <div class="slide"><img src="img/bg1.jpg" alt="Slide 1"></div>
    <div class="slide"><img src="img/bg.jpg" alt="Slide 2"></div>
    <div class="slide"><img src="img/bg10.jpg" alt="Slide 3"></div>
  </div>
  <div class="hero-content">
    <h2>Welcome to TasteIt</h2>
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
        $desc  = htmlspecialchars(mb_strimwidth($row['description'] ?? '', 0, 120, '...'), ENT_QUOTES, 'UTF-8');

        echo "
        <a href='view_recipe.php?id={$id}' class='card'>
          <img src='{$img}' alt='{$title}'>
          <div class='card-body'>
            <div class='card-title'>{$title}</div>
            <div class='card-desc'>{$desc}</div>
          </div>
        </a>";
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
    Whether you are a home cook or a professional chef, this space helps you connect through food and culture.
  </p>
</section>

<!-- FOOTER -->
<footer>
  <p>&copy; <?php echo date('Y'); ?> TasteIt. All Rights Reserved.</p>
</footer>

<!-- JS: Slideshow + Smooth scroll -->
<script>
  // Slideshow
  let slideIndex = 0;
  const slides = document.getElementsByClassName("slide");
  function showSlides(){
    for (let i = 0; i < slides.length; i++) slides[i].style.display = "none";
    slideIndex = (slideIndex % slides.length) + 1;
    slides[slideIndex - 1].style.display = "block";
    setTimeout(showSlides, 3000);
  }
  if (slides.length) showSlides();

  // Smooth scroll for navbar links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener("click", function(e){
      e.preventDefault();
      document.querySelector(this.getAttribute("href")).scrollIntoView({
        behavior:"smooth"
      });
    });
  });
</script>
</body>
</html>
<?php $conn->close(); ?>
