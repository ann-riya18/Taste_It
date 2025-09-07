<?php
// DB connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$searchTerm = $_GET['q'] ?? '';

// If search term exists → search in recipes & chefs
if (!empty($searchTerm)) {
    $stmt = $conn->prepare("
        SELECT r.id, r.title, r.description, r.image_path 
        FROM recipes r 
        JOIN users u ON r.user_id = u.id 
        WHERE (r.title LIKE ? OR u.username LIKE ?) 
        AND r.status = 'approved'
        ORDER BY r.id DESC
    ");
    $like = "%" . $searchTerm . "%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Otherwise → show 6 latest approved recipes
    $sql = "SELECT id, title, description, image_path 
            FROM recipes 
            WHERE status = 'approved'
            ORDER BY id DESC LIMIT 6";
    $result = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TasteIt - Search Recipes</title>
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

    /* SEARCH */
    .search-section{padding:120px 10% 40px;text-align:center}
    .search-section h2{font-size:28px;margin-bottom:20px}
    .search-box{margin-bottom:30px}
    .search-box input{padding:12px 20px;width:60%;max-width:500px;border:2px solid #ccc;border-radius:8px;font-size:16px}
    .search-box button{padding:12px 24px;margin-left:10px;background:var(--brand);color:#fff;border:none;border-radius:8px;font-size:16px;cursor:pointer}
    .search-box button:hover{background:#97b24e}

    /* CARDS */
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:24px}
    .card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,.08);transition:transform .25s ease, box-shadow .25s ease;cursor:pointer;text-decoration:none;color:inherit;display:block}
    .card:hover{transform:translateY(-6px);box-shadow:0 10px 20px rgba(0,0,0,.12)}
    .card img{width:100%;height:180px;object-fit:cover}
    .card-body{padding:14px;text-align:left}
    .card-title{font-size:18px;color:#444;margin-bottom:8px}
    .card-desc{font-size:14px;color:#666;line-height:1.45}

    /* FOOTER */
    footer{background:var(--brand);color:#fff;text-align:center;padding:20px;margin-top:50px}
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
        <li><a href="search.php">Explore Recipes</a></li>
        <li><a href="login.html">Login</a></li>
      </ul>
    </nav>
  </div>
</header>

<!-- SEARCH -->
<section class="search-section">
  <h2>Find Recipes & Chefs</h2>
  <form class="search-box" method="get" action="search.php">
    <input type="text" name="q" placeholder="Search recipes or chefs..." value="<?php echo htmlspecialchars($searchTerm); ?>" required>
    <button type="submit">Search</button>
  </form>

  <?php if (empty($searchTerm)) { ?>
    <h3>Popular Recipes</h3>
  <?php } else { ?>
    <h3>Search Results for "<?php echo htmlspecialchars($searchTerm); ?>"</h3>
  <?php } ?>

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

<!-- FOOTER -->
<footer>
  <p>&copy; <?php echo date('Y'); ?> TasteIt. All Rights Reserved.</p>
</footer>

</body>
</html>
<?php $conn->close(); ?>
