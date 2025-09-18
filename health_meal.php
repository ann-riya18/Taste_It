<?php
// what_to_cook_health.php
session_start();
include 'db.php'; // your mysqli connection in $conn

// ---------- Helpers ----------
function week_of_month($date) {
    $d = (int)date('j', strtotime($date));
    return (int)ceil($d / 7);
}

function int_array_for_sql($arr) {
    $arr = array_map('intval', $arr);
    return $arr ? implode(',', $arr) : '0';
}

// ---------- Main Logic ----------
// Compute current month/year/week
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$selected_week = isset($_GET['week']) ? max(1, min(4, (int)$_GET['week'])) : week_of_month(date('Y-m-d'));
$session_key = "meal_plan_health_{$year}_{$month}_week{$selected_week}";

// Allowed week logic
$today = date('Y-m-d');
$allowed_week = week_of_month($today);
if ($allowed_week > 4) $allowed_week = 4;

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// Handle AJAX actions
$action = $_REQUEST['action'] ?? '';
if ($action === 'mark_done') {
    header('Content-Type: application/json');
    if (!$is_logged_in) {
        echo json_encode(['success' => false, 'message' => 'Please login to mark meals as done.']);
        exit;
    }
    
    $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $day = $payload['day'] ?? null;
    $course = $payload['course'] ?? null;
    $done = isset($payload['done']) && ($payload['done'] == 1 || $payload['done'] === '1') ? 1 : 0;
    
    if (!$day || !$course) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }
    
    if (!isset($_SESSION['meal_done'])) $_SESSION['meal_done'] = [];
    if (!isset($_SESSION['meal_done'][$session_key])) $_SESSION['meal_done'][$session_key] = [];
    if (!isset($_SESSION['meal_done'][$session_key][$day])) $_SESSION['meal_done'][$session_key][$day] = [];
    
    $_SESSION['meal_done'][$session_key][$day][$course] = $done ? 1 : 0;
    echo json_encode(['success' => true]);
    exit;
}

// Build days of the week
$days_y = [];
$first_of_month = strtotime("$year-$month-01");
$start_offset = ($selected_week - 1) * 7;
$week_start = strtotime("+{$start_offset} days", $first_of_month);

for ($i = 0; $i < 7; $i++) {
    $days_y[] = date('Y-m-d', strtotime("+$i days", $week_start));
}

$courses = ['Breakfast', 'Lunch', 'Snacks', 'Dinner'];

// Load or build meal plan
if (!isset($_SESSION[$session_key]) || isset($_GET['refresh_plan'])) {
    $used = [];
    $meal_plan = [];
    
    foreach ($courses as $course) {
        $like = "%".$course."%";
        $stmt = $conn->prepare("
            SELECT r.id, r.title, r.image_path, r.description, r.ingredients, r.diet, u.username 
            FROM recipes r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.course LIKE ? AND r.status = 'approved' 
            ORDER BY RAND() 
            LIMIT 20
        ");
        
        if ($stmt) {
            $stmt->bind_param('s', $like);
            $stmt->execute();
            $res = $stmt->get_result();
            $available_recipes = [];
            
            while ($r = $res->fetch_assoc()) {
                // Include all recipes regardless of diet restrictions
                if (!in_array((int)$r['id'], $used)) {
                    $available_recipes[] = $r;
                    $used[] = (int)$r['id'];
                    if (count($available_recipes) >= 7) break;
                }
            }
            
            $stmt->close();
            
            // Assign recipes to days
            for ($d = 0; $d < 7; $d++) {
                $dayKey = $days_y[$d];
                if (!isset($meal_plan[$dayKey])) $meal_plan[$dayKey] = [];
                
                if (isset($available_recipes[$d])) {
                    $meal_plan[$dayKey][$course] = $available_recipes[$d];
                } else {
                    $meal_plan[$dayKey][$course] = [
                        'id' => 0, 
                        'title' => 'No recipe available', 
                        'image_path' => 'assets/no-recipe.png',
                        'username' => '',
                        'diet' => ''
                    ];
                }
            }
        }
    }
    
    $_SESSION[$session_key] = $meal_plan;
} else {
    $meal_plan = $_SESSION[$session_key];
}

// Initialize done states
if (!isset($_SESSION['meal_done'])) $_SESSION['meal_done'] = [];
if (!isset($_SESSION['meal_done'][$session_key])) $_SESSION['meal_done'][$session_key] = [];

function is_done($session_key, $day, $course) {
    if (!isset($_SESSION['meal_done'][$session_key])) return false;
    return !empty($_SESSION['meal_done'][$session_key][$day][$course]);
}

// UI variables
$currentMonth = date('F', strtotime("$year-$month-01"));
$weekTitle = "{$currentMonth} – Week {$selected_week} (Health Focus)";
$is_locked_view = ($selected_week > $allowed_week);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>What to Cook — Health Focus | TasteIt</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --primary:#b0c364; /* Changed to a health-focused green */
  --primary-dark:#b0c364;
  --muted:#777;
  --white: #fff;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:'Poppins',sans-serif;
  background: url('img/bg26.jpg') center/cover no-repeat fixed; /* Changed to a health-themed background */
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}

.app-overlay {
  background: rgba(255,255,255,0.62);
  min-height:100vh;
  display:flex;
  overflow:auto;
}

.left-bar {
  position: fixed;
  top: 0;
  left: 0;
  width:72px;
  height: 100vh;
  background:var(--white);
  border-right:1px solid #eee;
  display:flex;
  flex-direction:column;
  align-items:center;
  padding:30px 8px;
  gap:14px;
  z-index: 100;
}
.brand {
  font-weight:700; color:var(--primary); font-size:14px;
  margin-bottom:8px; text-align:center; width:100%;
}
.icon-btn {
  width:44px; height:44px; border-radius:10px; display:flex;
  align-items:center; justify-content:center; color:var(--primary);
  cursor:pointer; position:relative; background:transparent; border:none; font-size:18px;
}
.icon-btn:hover { background:#fbfbfb; transform:translateY(-3px); box-shadow:0 6px 16px rgba(0,0,0,0.04); }

.icon-btn::after {
  content: attr(data-title);
  position: absolute;
  left: 60px;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(0,0,0,0.8);
  color:#fff;
  padding:6px 10px;
  border-radius:6px;
  font-size:12px;
  white-space:nowrap;
  opacity:0;
  pointer-events:none;
  transition:all .12s ease;
}
.icon-btn:hover::after { opacity:1; transform: translateY(-50%) translateX(6px); }

.main {
  flex:1;
  padding:22px 28px;
  margin-left: 72px;
}

.top-panel {
  background:var(--white);
  border:2px solid var(--primary);
  color:var(--primary);
  padding:16px 20px;
  border-radius:10px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:20px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.04);
}

.title-section {
  flex:1;
}
.title-section h1 { 
  margin:0; 
  font-size:28px; 
  color:var(--primary); 
}
.title-section p { 
  margin:6px 0 0; 
  color:var(--muted); 
  font-size:15px; 
}

.weeks-section {
  display:flex;
  gap:10px;
}

.week-tab {
  background:transparent;
  color:var(--primary);
  border:1px solid var(--primary);
  padding:10px 16px;
  border-radius:8px;
  cursor:pointer;
  font-weight:600;
  font-size:15px;
  display:flex;
  align-items:center;
  gap:6px;
}
.week-tab.locked { opacity:0.45; filter:grayscale(.2); cursor:not-allowed; }

.mark-complete-btn {
  background:var(--white);
  color:var(--primary);
  border:1px solid var(--primary);
  padding:8px 16px;
  border-radius:8px;
  cursor:pointer;
  font-weight:600;
  font-size:14px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.mark-complete-btn:hover {
  background:var(--primary);
  color:var(--white);
}

.planner { max-width:1200px; margin:14px auto; }
.day-row {
  background:transparent;
  padding:18px 10px;
  display:flex;
  flex-direction:column;
  gap:12px;
  margin-bottom:18px;
}

.day-label {
  display:inline-block;
  background:var(--primary);
  color:var(--white);
  padding:10px 18px;
  border-radius:12px;
  font-weight:700;
  font-size:16px;
  width:150px;
  text-align:center;
}

.cards-row {
  display:flex;
  gap:14px;
  align-items:flex-start;
}

.card {
  flex:1;
  min-width:200px;
  background:var(--white);
  border-radius:12px;
  box-shadow:0 8px 30px rgba(0,0,0,0.06);
  overflow:hidden;
  position:relative;
  cursor:pointer;
  transition:transform .18s ease;
  text-decoration:none;
  color:inherit;
}
.card:hover { transform:translateY(-6px); }

.card .course-pill {
  position:absolute;
  top:12px; left:12px;
  background:#b0c364; color:#fff; /* Changed to health green */
  padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px;
  box-shadow:0 6px 16px rgba(0,0,0,0.06);
}

.card .img-wrap { 
  width:100%; 
  height:220px;
  background:#f6f6f6; 
  display:flex; 
  align-items:center; 
  justify-content:center; 
  overflow:hidden; 
}
.card .img-wrap img { 
  width:100%; 
  height:100%; 
  object-fit:cover; 
  display:block; 
}

.card .card-body { padding:12px; text-align:left; }
.card .card-title { font-weight:700; font-size:15px; color:#222; margin:0 0 4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.card .chef-name { font-size:12px; color:var(--muted); margin:0 0 6px; }

.card .done-box {
  position:absolute; top:12px; right:12px;
  background:var(--white); border-radius:8px; padding:6px; box-shadow:0 6px 16px rgba(0,0,0,0.06);
  z-index:5;
}
.custom-checkbox {
  appearance:none; -webkit-appearance:none;
  width:18px; height:18px; border-radius:5px; display:inline-block;
  border:2px solid #ddd; position:relative; cursor:pointer;
}
.custom-checkbox:checked { background:var(--primary); border-color:var(--primary); }
.custom-checkbox:checked::after {
  content: '\f00c';
  font-family: "Font Awesome 6 Free";
  font-weight:900;
  position:absolute; left:2px; top:-2px; color:#fff; font-size:12px;
}

.locked-card { filter: blur(6px); opacity:0.8; pointer-events:none; user-select:none; }

.day-sep { height:8px; border-bottom:1px solid #eee; margin-top:12px; }

@media (max-width: 980px) {
  .cards-row { flex-direction:column; }
  .day-label { width:auto; }
  .left-bar { display:none; }
  .main { 
    padding:14px;
    margin-left: 0;
  }
  .top-panel { flex-direction:column; gap:15px; }
  .title-section { text-align:center; }
  .weeks-section { justify-content:center; }
}
</style>
</head>
<body>

<div class="app-overlay">
  <aside class="left-bar" aria-label="Main">
    <div class="brand">TasteIt</div>

    <button class="icon-btn" data-title="Home" onclick="goHome()" title="Home">
      <i class="fa-solid fa-house"></i>
    </button>

    <button class="icon-btn" data-title="Grocery List" onclick="goToGrocery()" title="Grocery List">
      <i class="fa-solid fa-basket-shopping"></i>
    </button>
  </aside>

  <main class="main">
    <div class="top-panel">
      <div class="title-section">
        <h1><?php echo htmlspecialchars($weekTitle); ?></h1>
        <p>Your health-focused weekly meal plan — nutritious, balanced, and delicious.</p>
      </div>

      <div class="weeks-section" role="tablist" aria-label="Weeks">
        <?php for ($w=1;$w<=4;$w++):
          $isLocked = ($w > $allowed_week);
          $tabClass = $isLocked ? 'week-tab locked' : 'week-tab';
          $href = $isLocked ? '#' : '?week='.$w;
        ?>
          <button class="<?php echo $tabClass; ?>" onclick="<?php echo $isLocked ? 'return false;' : 'location.href=\''.$href.'\''; ?>">
            <i class="fa-solid fa-calendar-days"></i> Week <?php echo $w; ?>
          </button>
        <?php endfor; ?>
      </div>
    </div>

    <section class="planner" role="main">
      <?php foreach ($days_y as $dayDate): ?>
        <article class="day-row" data-day="<?php echo htmlspecialchars($dayDate); ?>">
          <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
            <div class="day-label"><?php echo date('l', strtotime($dayDate)); ?></div>
            <div style="margin-left:auto; color:var(--muted); font-size:13px;">
              Day <?php echo array_search($dayDate,$days_y)+1; ?>
            </div>
            <div style="margin-left:12px;">
              <button class="mark-complete-btn" onclick="markDayComplete('<?php echo $dayDate; ?>')">Mark Day as Complete</button>
            </div>
          </div>

          <div class="cards-row" role="list">
            <?php foreach ($courses as $course):
              $meal = $meal_plan[$dayDate][$course] ?? ['id'=>0,'title'=>'No recipe','image_path'=>'assets/no-recipe.png','username'=>'','diet'=>''];
              $locked_card_class = $is_locked_view ? 'locked-card' : '';
            ?>
              <?php if ($is_locked_view): ?>
                <div class="card <?php echo $locked_card_class; ?>" data-date="<?php echo htmlspecialchars($dayDate); ?>" data-course="<?php echo htmlspecialchars($course); ?>">
                  <div class="course-pill"><?php echo htmlspecialchars($course); ?></div>

                  <div class="done-box" title="Locked">
                    <input type="checkbox" class="custom-checkbox" disabled />
                  </div>

                  <div class="img-wrap">
                    <img src="<?php echo htmlspecialchars($meal['image_path'] ?: 'assets/no-recipe.png'); ?>" alt="">
                  </div>

                  <div class="card-body">
                    <div class="card-title"><?php echo htmlspecialchars($meal['title']); ?></div>
                    <?php if (!empty($meal['username'])): ?>
                      <div class="chef-name">By <?php echo htmlspecialchars($meal['username']); ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php else: ?>
                <a class="card" href="view_recipe.php?id=<?php echo (int)$meal['id']; ?>" data-date="<?php echo htmlspecialchars($dayDate); ?>" data-course="<?php echo htmlspecialchars($course); ?>">
                  <div class="course-pill"><?php echo htmlspecialchars($course); ?></div>

                  <div class="done-box" onclick="event.stopPropagation();">
                    <input type="checkbox" class="custom-checkbox" <?php if(is_done($session_key,$dayDate,$course)) echo 'checked'; ?> onchange="toggleDone(event,'<?php echo $dayDate; ?>','<?php echo $course; ?>')"/>
                  </div>

                  <div class="img-wrap">
                    <img src="<?php echo htmlspecialchars($meal['image_path'] ?: 'assets/no-recipe.png'); ?>" alt="">
                  </div>

                  <div class="card-body">
                    <div class="card-title"><?php echo htmlspecialchars($meal['title']); ?></div>
                    <?php if (!empty($meal['username'])): ?>
                      <div class="chef-name">By <?php echo htmlspecialchars($meal['username']); ?></div>
                    <?php endif; ?>
                  </div>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>

          <div class="day-sep"></div>
        </article>
      <?php endforeach; ?>
    </section>
  </main>
</div>

<script>
const SESSION_KEY = <?php echo json_encode($session_key); ?>;
const SELECTED_WEEK = <?php echo json_encode($selected_week); ?>;
const CURRENT_MONTH = <?php echo json_encode($month); ?>;
const CURRENT_YEAR = <?php echo json_encode($year); ?>;
const IS_LOGGED_IN = <?php echo json_encode($is_logged_in); ?>;

function toggleDone(e, day, course) {
  e.stopPropagation();
  
  if (!IS_LOGGED_IN) {
    alert('Please login to mark meals as done.');
    e.target.checked = false;
    return;
  }
  
  const chk = e.target;
  const done = chk.checked ? 1 : 0;
  fetch('?action=mark_done&week=' + SELECTED_WEEK + '&month=' + CURRENT_MONTH + '&year=' + CURRENT_YEAR, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({day:day, course:course, done:done})
  }).then(r=>r.json()).then(data=>{
    if (!data.success) {
      alert('Could not save state.');
      chk.checked = !chk.checked;
    }
  }).catch(err=>{
    console.error(err);
    alert('Network error saving state.');
    chk.checked = !chk.checked;
  });
}

function markDayComplete(day) {
  if (!IS_LOGGED_IN) {
    alert('Please login to mark meals as done.');
    return;
  }
  
  const cards = document.querySelectorAll('.card[data-date="'+day+'"]');
  if (!cards.length) return;
  if (!confirm('Mark all meals for this day as cooked?')) return;
  cards.forEach(card=>{
    const course = card.getAttribute('data-course');
    const chk = card.querySelector('.custom-checkbox');
    if (chk && !chk.checked) {
      chk.checked = true;
      fetch('?action=mark_done&week=' + SELECTED_WEEK + '&month=' + CURRENT_MONTH + '&year=' + CURRENT_YEAR, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({day:day, course:course, done:1})
      });
    }
  });
}

function goToGrocery() {
  if (!IS_LOGGED_IN) {
    alert('Please login to generate a grocery list.');
    return;
  }
  window.location.href = 'weekly_grocery.php?week=' + SELECTED_WEEK + '&month=' + CURRENT_MONTH + '&year=' + CURRENT_YEAR;
}

function goHome(){ 
  window.location.href = 'index.php'; 
}
</script>
</body>
</html>