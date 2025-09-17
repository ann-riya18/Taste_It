<?php
// index.php (updated) - TasteIt homepage with full-width Meal Planner slider
session_start();
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$user_id = $_SESSION['user_id'] ?? 0; // assuming user_id stored in session
// --- Fetch latest recipes (limit 8 for homepage cards) ---
$sql = "SELECT id, title, image_path FROM recipes ORDER BY id DESC LIMIT 10";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TasteIt - Home</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{ --brand:#B0C364; --text:#fff; --muted:#666; }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Poppins',sans-serif;background:#f9f9f9;color:#222;overflow-x:hidden}
    /* ---------------- SPLASH SCREEN ---------------- */
    #splash {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: #000;
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 2000;
    }
    #splash img {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      animation: zoomIn 1.5s ease-in-out;
      object-fit: cover;
    }
    @keyframes zoomIn {
      from { transform: scale(0.5); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
    #splash.fade-out {
      opacity: 0;
      transition: opacity 0.8s ease;
      pointer-events: none;
    }
    /* ---------------- END SPLASH ---------------- */
    /* Transparent header */
    .transparent-header {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      background: rgba(0,0,0,0.4);
      padding: 15px 40px;
      z-index: 1000;
    }
    .transparent-header .container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      max-width: 1400px;
      margin: 0 auto;
    }
    .transparent-header .logo {
      font-size: 28px;
      font-weight: bold;
      color: #b0c364;
      font-family: 'Poppins', sans-serif;
    }
    /* Nav links */
    .nav-links ul {
      list-style: none;
      margin: 0;
      padding: 0;
      display: flex;
      align-items: center;
    }
    .nav-links ul li {
      position: relative;
      margin-left: 25px;
    }
    .nav-links ul li a {
      text-decoration: none;
      color: #fff;
      font-family: 'Poppins', sans-serif;
      font-weight: 500;
      font-size: 16px;
      padding: 6px 10px;
      transition: color 0.3s ease;
    }
    .nav-links ul li a:hover {
      color: #B0C364;
    }
    /* Dropdown Menu Container */
    .nav-links ul li ul.dropdown-menu {
      display: none;
      position: absolute;
      top: 100%;
      left: 0;
      background: rgba(255, 255, 255, 0.4); /* light transparent */
      padding: 15px;
      list-style: none;
      width: 280px;
      max-height: 400px;
      overflow-y: auto;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      z-index: 999;
      backdrop-filter: blur(6px); /* subtle blur to enhance transparency */
    }
    /* Show dropdown on hover */
    .nav-links ul li.dropdown:hover ul.dropdown-menu {
      display: block;
    }
    /* Category container */
    .category {
      display: block;
      margin-bottom: 10px;
      border-bottom: 1px solid rgba(0,0,0,0.1);
      padding-bottom: 6px;
    }
    /* Category title styling */
    .category-title {
      font-family: 'Poppins', sans-serif;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      padding: 8px 12px;
      color: #B0C364; /* olive green */
      background: rgba(246, 246, 246, 0.94); /* subtle transparent bg */
      border-radius: 5px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: background 0.3s ease, color 0.3s ease;
    }
    .category-title:hover {
      background: rgba(176, 195, 100, 0.15); /* slightly darker transparent bg */
      color: #566711; /* darker olive green on hover */
    }
    /* Arrow icon */
    .category-title .arrow {
      font-size: 12px;
      transition: transform 0.3s ease;
    }
    .category.open .category-title .arrow {
      transform: rotate(90deg);
    }
    /* Subcategories hidden by default */
    .sub-categories {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease;
      margin: 6px 0 0 12px;
      padding-left: 0;
      list-style: none;
      display: block;
      flex-direction: column;
    }
    /* Show subcategories when open */
    .category.open .sub-categories {
      max-height: 500px;
      display: block;
    }
    /* Each subcategory */
    .sub-categories li {
      display: block;
      margin: 4px 0;
    }
    .sub-categories li a {
      display: block;
      padding: 6px 12px;
      font-size: 14px;
      text-decoration: none;
      color: #B0C364; /* olive green text */
      font-family: 'Poppins', sans-serif;
      border-radius: 4px;
      transition: background 0.2s ease, color 0.2s ease;
      width: 100%;
    }
    .sub-categories li a:hover {
      background: rgba(176, 195, 100, 0.15); /* slightly darker transparent bg */
      color: #566711; /* darker olive green text */
    }
    /* Optional scrollbar styling for dropdown */
    .nav-links ul li ul.dropdown-menu::-webkit-scrollbar {
      width: 6px;
    }
    .nav-links ul li ul.dropdown-menu::-webkit-scrollbar-thumb {
      background: rgba(176, 195, 100, 0.4);
      border-radius: 3px;
    }
    .nav-links ul li ul.dropdown-menu::-webkit-scrollbar-track {
      background: rgba(0, 0, 0, 0.05);
    }
    .hero{height:100vh;position:relative;display:flex;justify-content:center;align-items:center;overflow:hidden}
    .hero video{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:1}
    .hero::after{content:"";position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:2}
    .hero-content{position:relative;z-index:3;background:rgba(0,0,0,0.5);backdrop-filter:blur(5px);padding:30px 40px;border-radius:15px;box-shadow:0 4px 10px rgba(0,0,0,0.3);text-align:center;max-width:800px}
    .hero-icon{font-size:40px;color:#B0C364;margin-bottom:10px;background:rgba(255,255,255,0.9);width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 15px;box-shadow:0 10px 30px rgba(0,0,0,0.2)}
    .hero-content h2{color:#fff;font-size:50px;font-weight:700;margin-bottom:12px;text-shadow:0 2px 4px rgba(0,0,0,0.3)}
    .hero-content p {color:#fff;font-size:18px;line-height:1.4;margin-bottom:15px;max-width:600px;margin-left:auto;margin-right:auto}
     
   body, html {
  margin: 0;
  padding: 0;
  font-family: 'Poppins', sans-serif;
  min-height: 100%;
  overflow-x: hidden; 
  overflow-y: auto;
}

/* MEAL PLANNER SECTION - FULL WIDTH SLIDER */
.meal-planner {
  position: relative;
  width: 100%;
  height: 70vh;
  max-height: 800px;
  overflow: hidden;
}

.planner-slider {
  position: relative;
  width: 100%;
  height: 100%;
}

.planner-slide {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  transition: opacity 1.5s ease-in-out;
  display: flex;
  align-items: center;
  justify-content: center;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
}

.planner-slide.active {
  opacity: 1;
  z-index: 2;
}

.planner-slide::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: radial-gradient(circle at center, rgba(0, 0, 0, 0.3) 0%, rgba(0, 0, 0, 0.7) 100%);
  z-index: 1;
}

.slide-content {
  position: relative;
  z-index: 2;
  text-align: center;
  color: #fff;
  max-width: 800px;
  padding: 20 30px;
  background: rgba(0, 0, 0, 0.5);
  border-radius: 15px;
  backdrop-filter: blur(5px);
}

.slide-content h2 {
  font-size: 42px;
  font-weight: 700;
  margin-bottom: 12px;
  text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.slide-content p {
  font-size: 16px;
  line-height: 1.4;
  margin-bottom: 15px;
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
}

.planner-btn {
  display: inline-block;
  padding: 10px 25px;
  background:#B0C364;
  color: white;
  text-decoration: none;
  border-radius: 50px;
  font-weight: 600;
  font-size: 16px;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.planner-btn:hover {
  background: #97b24e;
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(176, 195, 100, 0.4);
}

.slide-icon {
  font-size: 40px;
  color: #B0C364;
  margin-bottom: 10px;
  background: rgba(255, 255, 255, 0.9);
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 15px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

/* Slider Navigation */
.slider-nav {
  position: absolute;
  bottom: 30px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 15px;
  z-index: 10;
}

.slider-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.5);
  cursor: pointer;
  transition: all 0.3s ease;
}

.slider-dot.active {
  background: white;
  transform: scale(1.2);
}

.slider-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 60px;
  height: 60px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 24px;
  cursor: pointer;
  z-index: 10;
  transition: all 0.3s ease;
  backdrop-filter: blur(5px);
}

.slider-arrow:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateY(-50%) scale(1.1);
}

.slider-arrow.prev {
  left: 30px;
}

.slider-arrow.next {
  right: 30px;
}

    /* RECIPES */
    .recipes{background:#fff;padding:56px 10%;text-align:center;}
    .recipes h2{font-size:28px;margin-bottom:28px;font-weight:600;}
    .grid{display:grid;grid-template-columns:repeat(5, 1fr);gap:24px;}
    .card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,.08);transition:transform .25s ease, box-shadow .25s ease;text-decoration:none;color:inherit;display:block;}
    .card:hover{transform:translateY(-6px);box-shadow:0 10px 20px rgba(0,0,0,.12);}
    .card img{width:100%;height:180px;object-fit:cover;}
    .card-body{padding:14px;display:flex;justify-content:space-between;align-items:center;}
    .card-title{font-size:18px;color:#444;font-weight:600;}
    .card-icons{display:flex;gap:12px;font-size:18px;color:#B0C364;cursor:pointer;}
    .card-icons i.liked, .card-icons i.saved {color:#e63946;}
    .card-icons i.saved {color:#b0c364;}
    .card-icons i:hover{color:#222;}
    /* ABOUT */
    .about{background:linear-gradient(to bottom, #f8f8f8, #f0f0f0);text-align:center;padding:60px 10%;border-top:1px solid #e0e0e0}
    .about h2{font-size:32px;margin-bottom:20px;color:#333;position:relative;display:inline-block}
    .about h2:after{content:"";position:absolute;bottom:-8px;left:50%;transform:translateX(-50%);width:60px;height:3px;background:#B0C364}
    .about p{max-width:800px;margin:0 auto;color:#555;line-height:1.8;font-size:18px}
    /* FOOTER */
    footer{background:#1a1a1a;color:#fff;padding:50px 10% 20px;border-top:3px solid #B0C364}
    .footer-container{display:flex;justify-content:space-between;flex-wrap:wrap;gap:30px}
    .footer-logo{font-size:24px;font-weight:bold;color:#B0C364;margin-bottom:10px}
    .footer-section h4{font-size:18px;margin-bottom:12px;color:#B0C364}
    .footer-section ul{list-style:none}
    .footer-section ul li{margin-bottom:8px}
    .footer-section ul li a{text-decoration:none;color:#fff;transition:0.3s}
    .footer-section ul li a:hover{color:#B0C364}
    .social-icons a{color:#fff;margin-right:12px;font-size:20px;transition:0.3s}
    .social-icons a:hover{color:#B0C364}
    .footer-bottom{text-align:center;margin-top:40px;font-size:14px;color:#aaa;padding-top:20px;border-top:1px solid #333}
    /* responsive tweaks */
    @media (max-width: 980px) {
      .grid{grid-template-columns:repeat(2,1fr)}
    }
    @media (max-width: 768px) {
      .meal-planner {
        height: 70vh;
      }
      .slide-content {
        padding: 30px 20px;
      }
      .slide-content h2 {
        font-size: 42px;
      }
      .slide-content p {
        font-size: 18px;
      }
      .planner-btn {
        padding: 14px 30px;
        font-size: 16px;
      }
      .slide-icon {
        width: 100px;
        height: 100px;
        font-size: 48px;
      }
      .slider-arrow {
        width: 50px;
        height: 50px;
        font-size: 20px;
      }
      .slider-arrow.prev {
        left: 15px;
      }
      .slider-arrow.next {
        right: 15px;
      }
    }
    @media (max-width: 600px) {
      .transparent-header{padding:12px}
      .hero-content h2{font-size:36px}
      .meal-planner {
        height: 60vh;
      }
      .slide-content {
        padding: 20px 15px;
      }
      .slide-content h2 {
        font-size: 36px;
      }
      .slide-content p {
        font-size: 16px;
      }
      .slider-dot {
        width: 10px;
        height: 10px;
      }
    }
  </style>
</head>
<body>
<!-- SPLASH -->
<div id="splash">
  <img src="img/logo.png" alt="TasteIt Logo">
</div>
<header class="transparent-header">
  <div class="container">
    <h1 class="logo">TasteIt</h1>
    <div class="nav-wrapper">
      <nav class="nav-links">
        <ul>
          <li><a href="index.php">Home</a></li>
          <!-- Recipes Dropdown -->
          <li class="dropdown">
            <a href="#">Recipes</a>
            <ul class="dropdown-menu">
              
              <!-- By Cuisine -->
              <li class="category">
                <div class="category-title">By Cuisine <span class="arrow">▶</span></div>
                <ul class="sub-categories">
                  <li><a href="search.php?cuisine=Indian">Indian</a></li>
                  <li><a href="search.php?cuisine=American">American</a></li>
                  <li><a href="search.php?cuisine=Chinese">Chinese</a></li>
                  <li><a href="search.php?cuisine=Mexican">Mexican</a></li>
                  <li><a href="search.php?cuisine=Asian">Asian</a></li>
                  <li><a href="search.php?cuisine=Middle Eastern">Middle Eastern</a></li>
                  <li><a href="search.php?cuisine=Continental">Continental</a></li>
                </ul>
              </li>
              <!-- By Course -->
              <li class="category">
                <div class="category-title">By Course <span class="arrow">▶</span></div>
                <ul class="sub-categories">
                  <li><a href="search.php?course=Breakfast">Breakfast</a></li>
                  <li><a href="search.php?course=Lunch">Lunch</a></li>
                  <li><a href="search.php?course=Dinner">Dinner</a></li>
                  <li><a href="search.php?course=Snacks">Snacks</a></li>
                  <li><a href="search.php?course=Desserts">Desserts</a></li>
                  <li><a href="search.php?course=Drinks">Drinks</a></li>
                </ul>
              </li>
              <!-- By Diet -->
              <li class="category">
                <div class="category-title">By Diet <span class="arrow">▶</span></div>
                <ul class="sub-categories">
                  <li><a href="search.php?diet=Gluten-Free">Gluten-Free</a></li>
                  <li><a href="search.php?diet=Lactose-Free">Lactose-Free</a></li>
                  <li><a href="search.php?diet=Sugar-Free">Sugar-Free</a></li>
                  <li><a href="search.php?diet=High-Protein">High-Protein</a></li>
                  <li><a href="search.php?diet=Low-Fat">Low-Fat</a></li>
                  <li><a href="search.php?diet=Low-Carb">Low-Carb</a></li>
                </ul>
              </li>
              <!-- Quick Recipes -->
              <li class="category">
                <div class="category-title">Quick Recipes <span class="arrow">▶</span></div>
                <ul class="sub-categories">
                  <li><a href="search.php?time=15">Under 15 minutes</a></li>
                  <li><a href="search.php?time=30">Under 30 minutes</a></li>
                </ul>
              </li>
            </ul>
          </li>
          <li><a href="#about">About</a></li>
          <li><a href="login.html">Login</a></li>
        </ul>
      </nav>
    </div>
  </div>
</header>
<!-- HERO -->
<section class="hero">
  <video autoplay muted loop playsinline>
    <source src="img/bgvideo.mp4" type="video/mp4">
  </video>
  <div class="hero-content">
    <div class="hero-icon">
      <i class="fas fa-utensils"></i>
    </div>
    <h2>Welcome to TasteIt</h2>
    <p>Discover delicious recipes from around the world</p>
    <a href="search.php" class="planner-btn">Explore Recipes</a>
  </div>
</section>
<!-- MEAL PLANNER SECTION - FULL WIDTH SLIDER -->
<section class="meal-planner">
  <div class="planner-slider">
    <!-- What to Cook Planner Slide -->
    <div class="planner-slide active" style="background-image: url('img/bg16.jpg');">
      <div class="slide-content">
        <div class="slide-icon">
          <i class="fas fa-utensils"></i>
        </div>
        <h2>What to Cook Planner</h2>
        <p>Confused about what to cook? Let our Meal Planner do the thinking!</p>
        <a href="what_to_cook.php" class="planner-btn">What To Cook</a>
      </div>
    </div>
    
    <!-- Health Meal Planner Slide -->
    <div class="planner-slide" style="background-image: url('img/bg16.jpg');">
      <div class="slide-content">
        <div class="slide-icon">
          <i class="fas fa-apple-alt"></i>
        </div>
        <h2>Health Meal Planner</h2>
        <p>healthy meals? because your body deserves that!</p>
        <a href="health_meal.php" class="planner-btn">Healthy Meal</a>
      </div>
    </div>
    
    <!-- Slider Navigation -->
    <div class="slider-nav">
      <span class="slider-dot active" data-slide="0"></span>
      <span class="slider-dot" data-slide="1"></span>
    </div>
    
    <div class="slider-arrow prev">&#10094;</div>
    <div class="slider-arrow next">&#10095;</div>
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
    TasteIt is a premier culinary platform connecting food enthusiasts with diverse recipes from around the globe. Our mission is to inspire creativity in the kitchen and make cooking an enjoyable experience for everyone.
  </p>
</section>
<!-- FOOTER -->
<footer>
  <div class="footer-container">
    <div class="footer-section">
      <div class="footer-logo">TasteIt</div>
      <p>Your gateway to culinary exploration and delicious discoveries.</p>
    </div>
    <div class="footer-section">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="search.php">Recipes</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="login.html">Login</a></li>
      </ul>
    </div>
    <div class="footer-section">
      <h4>Connect With Us</h4>
      <div class="social-icons">
        <a href="#"><i class="fab fa-facebook"></i></a>
        <a href="https://www.instagram.com/__tasteit.__/" target="_blank"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    © <?php echo date("Y"); ?> TasteIt. All rights reserved.
  </div>
</footer>
<script>
  // --- SPLASH LOGIC ---
  window.addEventListener("load", () => {
    const splash = document.getElementById("splash");
    if (splash) {
      setTimeout(() => {
        splash.classList.add("fade-out");
        setTimeout(() => splash.style.display = 'none', 800);
      }, 2500); // 2.5s
    }
  });
document.querySelectorAll(".category-title").forEach(title => {
    title.addEventListener("click", function () {
      const category = this.parentElement;
      category.classList.toggle("open");
    });
  });
// existing icons toggle (like/bookmark)
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
// Side menu toggle
const menuToggle = document.querySelector('.menu-toggle');
const sideMenu = document.getElementById('sideMenu');
const closeBtn = document.getElementById('closeBtn');
if (menuToggle && sideMenu && closeBtn) {
  menuToggle.addEventListener('click', ()=> sideMenu.classList.add('active'));
  closeBtn.addEventListener('click', ()=> sideMenu.classList.remove('active'));
}

// Meal Planner Slider
document.addEventListener('DOMContentLoaded', function() {
  // Get all the elements
  const slides = document.querySelectorAll('.planner-slide');
  const dots = document.querySelectorAll('.slider-dot');
  const prevBtn = document.querySelector('.slider-arrow.prev');
  const nextBtn = document.querySelector('.slider-arrow.next');
  
  // Check if elements exist
  if (!slides.length || !dots.length || !prevBtn || !nextBtn) {
    console.error('Slider elements not found');
    return;
  }
  
  let currentSlide = 0;
  let slideInterval;
  
  // Function to show a specific slide
  function showSlide(index) {
    // Handle wrap-around
    if (index >= slides.length) currentSlide = 0;
    if (index < 0) currentSlide = slides.length - 1;
    
    // Hide all slides and remove active class from dots
    slides.forEach(slide => slide.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));
    
    // Show current slide and activate corresponding dot
    slides[currentSlide].classList.add('active');
    dots[currentSlide].classList.add('active');
  }
  
  // Function to start the auto-slide
  function startSlideShow() {
    // Clear any existing interval
    if (slideInterval) clearInterval(slideInterval);
    
    // Set new interval
    slideInterval = setInterval(() => {
      currentSlide++;
      showSlide(currentSlide);
    }, 6000); // 6 seconds
  }
  
  // Function to stop the auto-slide
  function stopSlideShow() {
    if (slideInterval) {
      clearInterval(slideInterval);
      slideInterval = null;
    }
  }
  
  // Event listeners for navigation arrows
  prevBtn.addEventListener('click', () => {
    stopSlideShow();
    currentSlide--;
    showSlide(currentSlide);
    startSlideShow();
  });
  
  nextBtn.addEventListener('click', () => {
    stopSlideShow();
    currentSlide++;
    showSlide(currentSlide);
    startSlideShow();
  });
  
  // Event listeners for dots
  dots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
      stopSlideShow();
      currentSlide = index;
      showSlide(currentSlide);
      startSlideShow();
    });
  });
  
  // Start the slideshow
  startSlideShow();
  
  // Pause on hover
  const slider = document.querySelector('.planner-slider');
  if (slider) {
    slider.addEventListener('mouseenter', stopSlideShow);
    slider.addEventListener('mouseleave', startSlideShow);
  }
});
</script>
</body>
</html>
<?php $conn->close(); ?>