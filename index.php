<?php
// index.php (updated) - TasteIt homepage with dynamic Meal Planner integration
session_start();
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'] ?? 0; // assuming user_id stored in session

// --- MEAL PLANNER AJAX HANDLER (generate / get / shuffle) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mp_action'])) {
    header('Content-Type: application/json; charset=utf-8');

    // create meal_plans table if not exists
    $create_sql = "CREATE TABLE IF NOT EXISTS meal_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        planner_type ENUM('health','what_to_cook') NOT NULL,
        week_start DATE NOT NULL,
        day_index TINYINT NOT NULL, -- 1=Mon .. 7=Sun
        meal_type ENUM('Breakfast','Lunch','Dinner','Snack') NOT NULL,
        recipe_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($create_sql);

    $action = $_POST['mp_action'];

    // helper: get monday of this week (consistent week key)
    function get_week_start_date() {
        // Monday as start
        $monday = date('Y-m-d', strtotime('monday this week'));
        return $monday;
    }

    // helper: fetch recipe details by id
    function fetch_recipe_by_id($conn, $rid) {
        $rid = intval($rid);
        $res = $conn->query("SELECT id, title, image_path, ingredients FROM recipes WHERE id=$rid LIMIT 1");
        if ($res && $res->num_rows) {
            return $res->fetch_assoc();
        }
        return null;
    }

    if ($action === 'generate_plan') {
        $planner_type = $_POST['planner_type'] === 'health' ? 'health' : 'what_to_cook';
        $week_start = get_week_start_date();

        // clear existing for same user/planner_type/week
        $stmt = $conn->prepare("DELETE FROM meal_plans WHERE user_id=? AND planner_type=? AND week_start=?");
        $stmt->bind_param("iss", $user_id, $planner_type, $week_start);
        $stmt->execute();
        $stmt->close();

        // meals per day (you can extend to include 'Snack' later)
        $meals = ['Breakfast','Lunch','Dinner'];
        $used_ids = [];

        // For each day (1..7) pick random recipes (no repeats in the week)
        for ($day=1; $day<=7; $day++) {
            foreach ($meals as $meal) {
                // try to find a random recipe that's not used yet
                // simple strategy: query random one, repeat up to N tries
                $recipe_id = null;
                $tries = 0;
                while ($tries < 10) {
                    $q = $conn->query("SELECT id FROM recipes ORDER BY RAND() LIMIT 1");
                    if ($q && $q->num_rows) {
                        $row = $q->fetch_assoc();
                        if (!in_array($row['id'], $used_ids)) {
                            $recipe_id = intval($row['id']);
                            $used_ids[] = $recipe_id;
                            break;
                        }
                    }
                    $tries++;
                }
                // fallback: allow repeats if not enough recipes
                if (!$recipe_id) {
                    $q2 = $conn->query("SELECT id FROM recipes LIMIT 1");
                    if ($q2 && $q2->num_rows) {
                        $row2 = $q2->fetch_assoc();
                        $recipe_id = intval($row2['id']);
                    } else {
                        // no recipes in DB
                        echo json_encode(['success'=>false, 'message'=>'No recipes found in DB.']);
                        exit;
                    }
                }

                $ins = $conn->prepare("INSERT INTO meal_plans (user_id, planner_type, week_start, day_index, meal_type, recipe_id) VALUES (?,?,?,?,?,?)");
                $ins->bind_param("issisi", $user_id, $planner_type, $week_start, $day, $meal, $recipe_id);
                $ins->execute();
                $ins->close();
            }
        }

        // return the saved plan
        $plans = [];
        $res = $conn->query("SELECT mp.day_index, mp.meal_type, r.id as recipe_id, r.title, r.image_path, r.ingredients
                             FROM meal_plans mp
                             LEFT JOIN recipes r ON r.id = mp.recipe_id
                             WHERE mp.user_id=$user_id AND mp.planner_type='{$planner_type}' AND mp.week_start='$week_start'
                             ORDER BY mp.day_index, FIELD(mp.meal_type,'Breakfast','Lunch','Dinner','Snack')");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $plans[] = $r;
            }
        }

        echo json_encode(['success'=>true, 'planner_type'=>$planner_type, 'week_start'=>$week_start, 'plan'=>$plans]);
        exit;
    }

    if ($action === 'get_plan') {
        $planner_type = $_POST['planner_type'] === 'health' ? 'health' : 'what_to_cook';
        $week_start = get_week_start_date();

        $plans = [];
        $res = $conn->query("SELECT mp.day_index, mp.meal_type, r.id as recipe_id, r.title, r.image_path, r.ingredients
                             FROM meal_plans mp
                             LEFT JOIN recipes r ON r.id = mp.recipe_id
                             WHERE mp.user_id=$user_id AND mp.planner_type='{$planner_type}' AND mp.week_start='$week_start'
                             ORDER BY mp.day_index, FIELD(mp.meal_type,'Breakfast','Lunch','Dinner','Snack')");
        if ($res && $res->num_rows>0) {
            while ($r = $res->fetch_assoc()) $plans[] = $r;
            echo json_encode(['success'=>true, 'planner_type'=>$planner_type, 'week_start'=>$week_start, 'plan'=>$plans]);
        } else {
            echo json_encode(['success'=>true, 'planner_type'=>$planner_type, 'week_start'=>$week_start, 'plan'=>[]]);
        }
        exit;
    }

    if ($action === 'shuffle_meal') {
        // Only for what_to_cook planner as per your rules
        $planner_type = 'what_to_cook';
        $week_start = get_week_start_date();
        $day_index = intval($_POST['day_index'] ?? 1);
        $meal_type = in_array($_POST['meal_type'] ?? '', ['Breakfast','Lunch','Dinner','Snack']) ? $_POST['meal_type'] : 'Dinner';

        // collect currently used recipe ids for this plan to avoid duplicates
        $used_ids = [];
        $res = $conn->query("SELECT recipe_id FROM meal_plans WHERE user_id=$user_id AND planner_type='{$planner_type}' AND week_start='$week_start'");
        if ($res) while ($rr = $res->fetch_assoc()) $used_ids[] = intval($rr['recipe_id']);

        // pick a random recipe not in used_ids
        $recipe_id = null;
        $tries = 0;
        while ($tries < 15) {
            $q = $conn->query("SELECT id FROM recipes ORDER BY RAND() LIMIT 1");
            if ($q && $q->num_rows) {
                $row = $q->fetch_assoc();
                if (!in_array($row['id'], $used_ids)) {
                    $recipe_id = intval($row['id']);
                    break;
                }
            }
            $tries++;
        }
        if (!$recipe_id) {
            // fallback to any recipe (allow duplicate)
            $q2 = $conn->query("SELECT id FROM recipes LIMIT 1");
            if ($q2 && $q2->num_rows) {
                $row2 = $q2->fetch_assoc();
                $recipe_id = intval($row2['id']);
            } else {
                echo json_encode(['success'=>false, 'message'=>'No recipes available to shuffle.']);
                exit;
            }
        }

        // update the meal_plans row for that day+meal_type
        // if row exists update, else insert
        $chk = $conn->query("SELECT id FROM meal_plans WHERE user_id=$user_id AND planner_type='{$planner_type}' AND week_start='$week_start' AND day_index=$day_index AND meal_type='{$meal_type}' LIMIT 1");
        if ($chk && $chk->num_rows>0) {
            $rowchk = $chk->fetch_assoc();
            $upd = $conn->prepare("UPDATE meal_plans SET recipe_id=? WHERE id=?");
            $upd->bind_param("ii", $recipe_id, $rowchk['id']);
            $upd->execute();
            $upd->close();
        } else {
            $ins = $conn->prepare("INSERT INTO meal_plans (user_id, planner_type, week_start, day_index, meal_type, recipe_id) VALUES (?,?,?,?,?,?)");
            $ins->bind_param("issisi", $user_id, $planner_type, $week_start, $day_index, $meal_type, $recipe_id);
            $ins->execute();
            $ins->close();
        }

        $rec = fetch_recipe_by_id($conn, $recipe_id);
        echo json_encode(['success'=>true, 'new_recipe'=>$rec, 'day_index'=>$day_index, 'meal_type'=>$meal_type]);
        exit;
    }

    // unknown action
    echo json_encode(['success'=>false, 'message'=>'Unknown action']);
    exit;
}

// --- Fetch latest recipes (limit 8 for homepage cards) ---
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
    .menu-toggle{font-size:26px;color:#fff;cursor:pointer;}
    
    .transparent-header .container {
  display: flex;
  justify-content: space-between; /* logo left, nav+menu right */
  align-items: center;
  max-width: 1400px;
  margin: 0 auto;
}

.nav-wrapper {
  display: flex;
  align-items: center;
  gap: 20px; /* spacing between nav links and the hamburger */
}

.nav-links ul {
  list-style: none;
  display: flex;
  gap: 25px;
}

.nav-links ul li a {
  color: #fff;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.3s;
}

.nav-links ul li a:hover {
  color: #B0C364;
}

.menu-toggle {
  font-size: 22px;
  color: #fff;
  cursor: pointer;
}

    /* SIDE MENU */
    .side-menu{position:fixed;top:0;right:-300px;width:280px;height:100%;background:#fff;box-shadow:-2px 0 10px rgba(0,0,0,0.2);padding:20px;transition:0.3s;z-index:1100;overflow-y:auto}
    .side-menu.active{right:0}
    .side-menu h3{color:#B0C364;font-size:20px;margin:15px 0 10px}
    .side-menu ul{list-style:none;margin-bottom:20px}
    .side-menu ul li a{text-decoration:none;color:#333;display:block;padding:8px 0;transition:0.3s}
    .side-menu ul li a:hover{color:#B0C364}
    .close-btn{font-size:22px;color:#333;cursor:pointer;float:right}

    /* HERO */
    .hero{height:100vh;position:relative;display:flex;justify-content:center;align-items:center;overflow:hidden}
    .hero video{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:1}
    .hero::after{content:"";position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:2}
    .hero-content{position:relative;z-index:3;background:rgba(176,195,100,0.8);padding:20px 40px;border-radius:15px;box-shadow:0 4px 10px rgba(0,0,0,0.3);text-align:center}
    .hero-content h2{color:#fff;font-size:50px;font-weight:bold}
    .hero-content p {color:#fff;font-size:18px;margin:15px 0;}
    .hero-content .btn {display:inline-block;padding:12px 28px;background-color:#B0C364;color:#fff;text-decoration:none;border-radius:8px;font-weight:500;transition:0.3s;}
    .hero-content .btn:hover {background-color:#97b24e;}

    /* MEAL PLANNER (carousel-like) */
    .meal-planner-banner {
  position: relative;
  width: 100%;
  height: 90vh;
  overflow: hidden;
  font-family: 'Poppins', sans-serif;
}

.meal-planner-banner .slider {
  display: flex;
  width: 100%;
  height: 100%;
  transition: transform 1s ease-in-out;
}

.meal-planner-banner .slide {
  min-width: 100%;
  height: 100%;
  background-size: cover;
  background-position: center;
  display: flex;
  justify-content: center;
  align-items: center;
}

.meal-planner-banner .overlay {
  background: rgba(0, 0, 0, 0.6);
  padding: 40px 60px;
  border-radius: 20px;
  text-align: center;
  color: #fff;
  max-width: 650px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

.meal-planner-banner h2 {
  font-size: 2.5rem;
  margin-bottom: 15px;
}

.meal-planner-banner p {
  font-size: 1.2rem;
  margin-bottom: 20px;
  line-height: 1.6;
}

.meal-planner-banner button {
  background: #B0C364;
  color: #fff;
  padding: 12px 25px;
  font-size: 1rem;
  border: none;
  border-radius: 30px;
  cursor: pointer;
  transition: background 0.3s;
}

.meal-planner-banner button:hover {
  background: #9bb14c;
}

/* Dots */
.meal-planner-banner .controls {
  position: absolute;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
}

.meal-planner-banner .dot {
  height: 14px;
  width: 14px;
  margin: 0 6px;
  background-color: rgba(255, 255, 255, 0.5);
  border-radius: 50%;
  display: inline-block;
  cursor: pointer;
  transition: background-color 0.4s;
}

.meal-planner-banner .dot.active {
  background-color: #B0C364;
}


  
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
    .card-icons i.liked, .card-icons i.saved {color:#e63946;}
    .card-icons i.saved {color:#b0c364;}
    .card-icons i:hover{color:#222}

    /* ABOUT */
    .about{background:#f3f3f3;text-align:center;padding:56px 10%}
    .about h2{font-size:28px;margin-bottom:16px}
    .about p{max-width:720px;margin:0 auto;color:#555;line-height:1.7}

    /* FOOTER */
    footer{background:#222;color:#fff;padding:40px 10% 20px}
    .footer-container{display:flex;justify-content:space-between;flex-wrap:wrap;gap:30px}
    .footer-logo{font-size:24px;font-weight:bold;color:#B0C364;margin-bottom:10px}
    .footer-section h4{font-size:18px;margin-bottom:12px;color:#B0C364}
    .footer-section ul{list-style:none}
    .footer-section ul li{margin-bottom:8px}
    .footer-section ul li a{text-decoration:none;color:#fff;transition:0.3s}
    .footer-section ul li a:hover{color:#B0C364}
    .social-icons a{color:#fff;margin-right:12px;font-size:20px;transition:0.3s}
    .social-icons a:hover{color:#B0C364}
    .footer-bottom{text-align:center;margin-top:30px;font-size:14px;color:#aaa}

    /* responsive tweaks */
    @media (max-width: 980px) {
      .grid{grid-template-columns:repeat(2,1fr)}
      .planner-grid{grid-template-columns:repeat(2,1fr);grid-auto-rows:1fr}
    }
    @media (max-width: 600px) {
      .planner-grid{grid-template-columns:1fr;grid-auto-rows:1fr}
      .transparent-header{padding:12px}
      .hero-content h2{font-size:36px}
    }
  </style>
</head>
<body>
<!-- HEADER -->
<header class="transparent-header">
  <div class="container">
    <h1 class="logo">TasteIt</h1>

    <div class="nav-wrapper">
      <nav class="nav-links">
        <ul>
          <li><a href="index.php">Home</a></li>
          <li><a href="#recipe">Recipes</a></li>
          <li><a href="#about">About</a></li>
          <li><a href="login.html">Login</a></li>
        </ul>
      </nav>
      <div class="menu-toggle"><i class="fas fa-bars"></i></div>
    </div>
  </div>
</header>


<!-- SIDE MENU -->
<div class="side-menu" id="sideMenu">
  <span class="close-btn" id="closeBtn"><i class="fas fa-times"></i></span>

  <h3>By Cuisine</h3>
  <ul>
    <li><a href="search.php?cuisine=Indian">Indian</a></li>
    <li><a href="search.php?cuisine=Italian">Italian</a></li>
    <li><a href="search.php?cuisine=Chinese">Chinese</a></li>
    <li><a href="search.php?cuisine=Continental">Continental</a></li>
  </ul>

  <h3>By Course</h3>
  <ul>
    <li><a href="search.php?course=Breakfast">Breakfast</a></li>
    <li><a href="search.php?course=Lunch">Lunch</a></li>
    <li><a href="search.php?course=Dinner">Dinner</a></li>
    <li><a href="search.php?course=Snacks">Snacks</a></li>
    <li><a href="search.php?course=Desserts">Desserts</a></li>
    <li><a href="search.php?course=Drinks">Drinks</a></li>
  </ul>

  <h3>By Diet</h3>
  <ul>
    <li><a href="search.php?diet=Veg">Veg</a></li>
    <li><a href="search.php?diet=Non-Veg">Non-Veg</a></li>
    <li><a href="search.php?diet=Vegan">Vegan</a></li>
    <li><a href="search.php?diet=Gluten-Free">Gluten-Free</a></li>
    <li><a href="search.php?diet=Keto">Keto</a></li>
    <li><a href="search.php?diet=Healthy">Healthy</a></li>
  </ul>

  <h3>Quick Recipes</h3>
  <ul>
    <li><a href="search.php?time=15">Under 15 minutes</a></li>
    <li><a href="search.php?time=30">Under 30 minutes</a></li>
  </ul>
</div>

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

<!-- MEAL PLANNER (inserted before Popular Recipes) -->
<!-- MEAL PLANNER BANNER -->
<section class="meal-planner-banner">
  <div class="slider">
    <!-- Slide 1: Health Planner -->
    <div class="slide active" style="background-image: url('img/bg16.jpg');">
      <div class="overlay">
        <h2>Health Meal Planner</h2>
        <p>Get a balanced weekly plan designed to keep you fit and energized.</p>
        <button onclick="openPlanner('health')">View Health Planner</button>
      </div>
    </div>

    <!-- Slide 2: What to Cook Planner -->
    <div class="slide" style="background-image: url('img/bg16.jpg');">
      <div class="overlay">
        <h2>What to Cook Planner</h2>
        <p>Confused about what to make? Let us plan it for you!</p>
        <button onclick="openPlanner('what_to_cook')">View What to Cook Planner</button>
      </div>
    </div>
  </div>

  <!-- Dots -->
  <div class="controls">
    <span class="dot active" onclick="showSlide(0)"></span>
    <span class="dot" onclick="showSlide(1)"></span>
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
    TasteIt is a platform for food lovers to discover, share, and enjoy recipes from all over the world.
  </p>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-container">
    <div class="footer-section">
      <div class="footer-logo">TasteIt</div>
      <p>Discover, share, and enjoy recipes from around the world.</p>
    </div>
    <div class="footer-section">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="#recipe">Recipes</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="login.html">Login</a></li>
      </ul>
    </div>
    <div class="footer-section">
      <h4>Follow Us</h4>
      <div class="social-icons">
        <a href="#"><i class="fab fa-facebook"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    © <?php echo date("Y"); ?> TasteIt. All rights reserved.
  </div>
</footer>

<script>
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
menuToggle.addEventListener('click', ()=> sideMenu.classList.add('active'));
closeBtn.addEventListener('click', ()=> sideMenu.classList.remove('active'));

// --- Slider / Meal Planner Banner JS ---
let currentSlide = 0;
const slider = document.querySelector(".meal-planner-banner .slider");
const slides = document.querySelectorAll(".meal-planner-banner .slide");
const dots = document.querySelectorAll(".meal-planner-banner .dot");

function showSlide(index) {
  currentSlide = index;
  slider.style.transform = `translateX(-${index * 100}%)`;
  dots.forEach((dot, i) => dot.classList.toggle("active", i === index));
}

// Auto slide every 5s
setInterval(() => {
  currentSlide = (currentSlide + 1) % slides.length;
  showSlide(currentSlide);
}, 5000);

function openPlanner(type) {
  window.location.href = `meal_planner.php?planner=${type}`;
}


 
function showGroceryList(plannerType) {
  const form = new URLSearchParams();
  form.append('mp_action','get_plan');
  form.append('planner_type', plannerType);
  fetch(window.location.pathname, { method:'POST', body: form })
    .then(r=>r.json()).then(data=>{
      if (data.success && data.plan.length>0) {
        let allIngredients = [];
        data.plan.forEach(item=>{
          if (item.ingredients) {
            allIngredients = allIngredients.concat(item.ingredients.split(',').map(x=>x.trim()));
          }
        });
        const uniqueIngredients = [...new Set(allIngredients.filter(x=>x))];
        alert("🛒 Grocery List:\n\n" + uniqueIngredients.join("\n"));
      } else {
        alert("No plan found. Please generate a weekly plan first.");
      }
    });
}
</script>

</body>
</html>
<?php $conn->close(); ?>
