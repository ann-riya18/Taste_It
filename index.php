<?php
// DB connection
$conn = new mysqli("localhost", "root", "", "tasteit"); // adjust if needed
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
    :root{
      --brand:#B0C364;
      --text:#fff;
      --muted:#666;
    }
    * {
        box-sizing:border-box;
        margin:0;
        padding:0
    }

    html,body{
        height:100%
    }

    body{
      font-family:'Poppins',sans-serif;
      background:#f9f9f9;
      color:#222;
      overflow-x:hidden;
    }

    /* ===== NAV / HEADER (transparent over hero) ===== */
    .transparent-header {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%; /* makes full width */
  background: rgba(0, 0, 0, 0.4); /* transparent black */
  padding: 15px 40px; /* increased spacing */
  z-index: 1000;
}

.transparent-header .container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 1400px; /* increased width */
  margin: 0 auto;
}

.transparent-header .logo {
  font-size: 28px;
  font-weight: bold;
  color: #fff;
  font-family: 'Poppins', sans-serif;
}

.transparent-header nav ul {
  list-style: none;
  display: flex;
  gap: 25px;
}

.transparent-header nav ul li a {
  text-decoration: none;
  color: #fff;
  font-weight: 500;
  font-family: 'Poppins', sans-serif;
  transition: 0.3s;
}

.transparent-header nav ul li a:hover {
  color: #B0C364;
}

/* Hero Section */
.hero {
  height: 100vh; /* full screen height */
  display: flex;
  justify-content: center;
  align-items: center;

}

.hero-content h2 {
  color: #b0c365;
  font-size: 50px;
  font-family: 'Poppins', sans-serif;
  font-weight: bold;
  margin: 0;
}
    
    
    nav a{
      color:#fff; text-decoration:none; margin-left:28px; font-weight:500;
      transition:opacity .15s ease;
    }
    nav a:hover{opacity:.85}
    @media (max-width:768px){
      header{padding:0 20px}
      nav a{margin-left:16px}
    }

    /* ===== HERO / SLIDESHOW ===== */
    .slideshow{
      position: absolute;
      top: 0; left: 0;
      width: 100%;
      height: 100%;
      z-index: 1;
    }
    .slide{
        display:none; 
        width:100%; 
        height:100%
    }
    .slide img{
        width:100%; 
        height:100%;
        object-fit:cover
    }

    .hero-content {
         background: rgba(176, 195, 100, 0.8); /* transparent box like header */
         padding: 20px 40px;
         border-radius: 15px;
         box-shadow: 0 4px 10px rgba(0,0,0,0.3);
         text-align: center;
    }



    section{
    scroll-margin-top:72px
    }

    /* ===== RECIPES ===== */
    .recipes{
      background:#fff;
      padding:56px 10%;
      text-align:center;
    }
    .recipes h2{
      font-size:28px; 
      color:#333;
      margin-bottom:28px; 
      font-weight:600;
    }
    .grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
      gap:24px;
    }
    .card{
      background:#fff; 
      border-radius:12px;
      overflow:hidden;
      box-shadow:0 6px 18px rgba(0,0,0,.08);
      transition:transform .25s ease;
    }
    .card:hover{
        transform:translateY(-6px)
    }
    .card img{
        width:100%; 
        height:180px; 
        object-fit:cover
    }
    .card-body{
     padding:14px; 
     text-align:left
    }
    .card-title{
        font-size:18px; 
        color:#444;
        margin-bottom:8px
    }
    .card-desc{
        font-size:14px; 
        color:#666; 
        line-height:1.45
    }

    /* ===== ABOUT ===== */
    .about{
      background:#f3f3f3;
      text-align:center; 
      padding:56px 10%;
    }
    .about h2{
        font-size:28px; 
        color:#333; 
        margin-bottom:16px
    }
    .about p{
        max-width:720px;
        margin:0 auto;
        color:#555; 
        line-height:1.7
    }

    /* ===== FOOTER ===== */
    footer{
      background:var(--brand);
      color:#fff; 
      text-align:center; 
      padding:20px;
    }
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
        <li><a href="#">Recipes</a></li>
        <li><a href="#">About</a></li>
        <li><a href="user_login.php">Login</a></li>
      </ul>
    </nav>
  </div>
</header>

  <section class="hero">
  <div class="slideshow" id="home">
    <div class="slide"><img src="img/bg1.jpg"  alt="Slide 1"></div>
    <div class="slide"><img src="img/bg.jpg"   alt="Slide 2"></div>
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
          // Safe image path: works for 'hummus.jpg' OR 'uploads/hummus.jpg' OR full URL
          $img = trim($row['image_path'] ?? '');
          if ($img === '') {
            $img = 'img/placeholder.png'; // optional fallback if you have one
          } elseif (!preg_match('#^(https?://|/|uploads/)#i', $img)) {
            $img = 'uploads/' . $img;
          }
          $title = htmlspecialchars($row['title'] ?? 'Recipe', ENT_QUOTES, 'UTF-8');
          $desc  = htmlspecialchars(mb_strimwidth($row['description'] ?? '', 0, 120, '...'), ENT_QUOTES, 'UTF-8');
          echo "
          <div class='card'>
            <img src='{$img}' alt='{$title}'>
            <div class='card-body'>
              <div class='card-title'>{$title}</div>
              <div class='card-desc'>{$desc}</div>
            </div>
          </div>";
        }
      } else {
        echo "<p>No recipes found.</p>";
      }
      ?>
    </div>
  </section>

  <!-- ABOUT -->
  <section id="about" class="about">
    <h2>About TasteIt</h2>
    <p>
      TasteIt is a platform for food lovers to discover, share, and enjoy recipes from all over the world.
      Whether you are a home cook or a professional chef, this space helps you connect
      through food and culture.
    </p>
  </section>

  <!-- FOOTER -->
  <footer>
    <p>&copy; <?php echo date('Y'); ?> TasteIt. All Rights Reserved.</p>
  </footer>

  <!-- JS: simple crossfade-style slideshow (kept minimal) -->
  <script>
    let slideIndex = 0;
    const slides = document.getElementsByClassName("slide");
    function showSlides(){
      for (let i = 0; i < slides.length; i++) slides[i].style.display = "none";
      slideIndex = (slideIndex % slides.length) + 1;
      slides[slideIndex - 1].style.display = "block";
      setTimeout(showSlides, 3000);
    }
    if (slides.length) showSlides();
  </script>
</body>
</html>
<?php $conn->close(); ?>
