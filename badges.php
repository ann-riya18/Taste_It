<?php
// badges.php
session_start();
include('db.php'); // your DB connection file

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Step 1: Get user stats from DB ---
// Adjust column names to your actual DB schema
$sql_stats = "SELECT 
                COUNT(DISTINCT id) AS recipes,
                COALESCE(SUM(likes), 0) AS total_likes,
                (SELECT COUNT(*) FROM comments WHERE user_id = $user_id) AS comments,
                (SELECT COUNT(*) FROM bookmarks WHERE user_id = $user_id) AS bookmarks,
                (SELECT created_at FROM users WHERE id = $user_id) AS joined_date
              FROM recipes WHERE user_id = $user_id";

$result = mysqli_query($conn, $sql_stats);
$stats = mysqli_fetch_assoc($result);

$recipes = $stats['recipes'] ?? 0;
$total_likes = $stats['total_likes'] ?? 0;
$comments = $stats['comments'] ?? 0;
$bookmarks = $stats['bookmarks'] ?? 0;
$joined_date = $stats['joined_date'] ?? date('Y-m-d');

// Calculate years for anniversary
$years = floor((time() - strtotime($joined_date)) / (365*24*60*60));

// --- Step 2: Extra checks for achievements ---
$sql_top_like = "SELECT MAX(likes) AS top_like FROM recipes WHERE user_id = $user_id";
$result_like = mysqli_query($conn, $sql_top_like);
$row_like = mysqli_fetch_assoc($result_like);
$top_like = $row_like['top_like'] ?? 0;

// --- Step 3: Define Badges ---
$badges = [
    [
        "name" => "Beginner",
        "icon" => "🟢",
        "desc" => "Signed up and joined TasteIt community.",
        "unlocked" => true
    ],
    [
        "name" => "Bronze Contributor",
        "icon" => "🥉",
        "desc" => "Uploaded your first recipe.",
        "unlocked" => $recipes >= 1,
        "progress" => min(100, ($recipes/1)*100)
    ],
    [
        "name" => "Silver Contributor",
        "icon" => "🥈",
        "desc" => "Shared 5 recipes.",
        "unlocked" => $recipes >= 5,
        "progress" => min(100, ($recipes/5)*100)
    ],
    [
        "name" => "Gold Chef",
        "icon" => "🥇",
        "desc" => "10 recipes with 500+ likes combined.",
        "unlocked" => ($recipes >= 10 && $total_likes >= 500),
        "progress" => min(100, ($recipes/10)*100)
    ],
    [
        "name" => "Platinum Master",
        "icon" => "💎",
        "desc" => "Community legend – share 50 recipes.",
        "unlocked" => $recipes >= 50,
        "progress" => min(100, ($recipes/50)*100)
    ],
    [
        "name" => "Most Liked Recipe",
        "icon" => "❤️",
        "desc" => "One of your recipes got 100+ likes total.",
        "unlocked" => $total_likes >= 100
    ],
    [
        "name" => "Top Liked Dish",
        "icon" => "🔥",
        "desc" => "A single recipe of yours reached 50+ likes.",
        "unlocked" => $top_like >= 50
    ],
    [
        "name" => "Community Helper",
        "icon" => "🤝",
        "desc" => "Commented on 20+ recipes.",
        "unlocked" => $comments >= 20,
        "progress" => min(100, ($comments/20)*100)
    ],
    [
        "name" => "Explorer",
        "icon" => "🌍",
        "desc" => "Bookmarked 5 recipes to try later.",
        "unlocked" => $bookmarks >= 5,
        "progress" => min(100, ($bookmarks/5)*100)
    ],
    [
        "name" => "Anniversary",
        "icon" => "🎉",
        "desc" => "Completed 1 year with TasteIt.",
        "unlocked" => $years >= 1
    ]
];

$earned = count(array_filter($badges, function($b){ return $b['unlocked']; }));
$total = count($badges);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Badges</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Poppins', sans-serif; background: #f8f9fa; margin:0; padding:0; color:#333; }
    .container { max-width:1200px; margin:40px auto; padding:20px; }
    h1 { color:#B0C364; font-size:2.2rem; margin-bottom:10px; }
    .subtitle { font-size:1rem; color:#666; margin-bottom:30px; }
    .stats { background:white; padding:20px; border-radius:15px; box-shadow:0 4px 10px rgba(0,0,0,0.05); margin-bottom:30px; text-align:center; }
    .stats h2 { margin:0; color:#B0C364; }
    .badge-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:20px; }
    .badge { background:white; padding:20px; border-radius:15px; box-shadow:0 4px 10px rgba(0,0,0,0.05); text-align:center; transition:transform 0.2s ease; }
    .badge:hover { transform:translateY(-5px); }
    .badge .icon { font-size:40px; margin-bottom:10px; }
    .locked { opacity:0.4; filter:grayscale(100%); }
    .progress-bar { background:#eee; border-radius:10px; overflow:hidden; height:8px; margin-top:8px; }
    .progress-fill { background:#B0C364; height:100%; }
    .highlight { background:#B0C364; color:white; padding:20px; border-radius:15px; margin:40px 0; text-align:center; }
    .highlight h2 { margin-bottom:10px; }
    .actions { margin-top:20px; text-align:center; }
    .actions button { background:#B0C364; border:none; padding:10px 20px; margin:5px; border-radius:25px; color:white; cursor:pointer; font-family:'Poppins', sans-serif; transition:background 0.3s; }
    .actions button:hover { background:#9aad59; }
  </style>
</head>
<body>
  <div class="container">
    <h1>My Badges</h1>
    <p class="subtitle">Your achievements on TasteIt</p>

    <!-- Stats -->
    <div class="stats">
      <h2><?php echo $earned . " / " . $total . " Badges Earned"; ?></h2>
      <p>Keep cooking, sharing, and earning!</p>
    </div>

    <!-- Highlight Section -->
    <div class="highlight">
      <h2>Featured Badge of the Month</h2>
      <p>🥇 Gold Chef – Awarded to the user with the most liked recipe this month!</p>
    </div>

    <!-- Badge Grid -->
    <div class="badge-grid">
      <?php foreach ($badges as $badge): ?>
        <div class="badge <?php echo !$badge['unlocked'] ? 'locked' : ''; ?>">
          <div class="icon"><?php echo $badge['icon']; ?></div>
          <h3><?php echo $badge['name']; ?></h3>
          <p><?php echo $badge['desc']; ?></p>
          <?php if (isset($badge['progress'])): ?>
            <div class="progress-bar">
              <div class="progress-fill" style="width: <?php echo $badge['progress']; ?>%"></div>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Interaction Features -->
    <div class="actions">
      <button>⭐ Pin Favorite Badges</button>
      <button>🏆 View Leaderboard</button>
    </div>
  </div>
</body>
</html>
