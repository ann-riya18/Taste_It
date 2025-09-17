<?php
// what_to_cook.php
session_start();
include 'db.php'; // your mysqli connection in $conn

// ---------- Helpers ----------
function week_of_month($date) {
    $d = (int)date('j', strtotime($date));
    return (int)ceil($d / 7);
}
function int_array_for_sql($arr){
    $arr = array_map('intval', $arr);
    return $arr ? implode(',', $arr) : '0';
}
// UPDATED: use LIKE to include recipes whose course contains the word (e.g., "Lunch, Dinner")
function pick_random_recipe_unique($conn, $course, $exclude_ids = []) {
    $exclude_str = int_array_for_sql($exclude_ids);
    // use LIKE to include multi-course fields (e.g., "Lunch, Dinner")
    $sql = "SELECT id, title, image_path, description, ingredients FROM recipes WHERE course LIKE ? AND status='approved' AND id NOT IN ($exclude_str) ORDER BY RAND() LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $like = "%".$course."%";
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows) {
            $r = $res->fetch_assoc();
            $stmt->close();
            return $r;
        }
        $stmt->close();
    }
    // fallback: any approved recipe that contains the course
    $sql2 = "SELECT id, title, image_path, description, ingredients FROM recipes WHERE course LIKE ? AND status='approved' ORDER BY RAND() LIMIT 1";
    if ($stmt2 = $conn->prepare($sql2)) {
        $like2 = "%".$course."%";
        $stmt2->bind_param('s', $like2);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2 && $res2->num_rows) {
            $r2 = $res2->fetch_assoc();
            $stmt2->close();
            return $r2;
        }
        $stmt2->close();
    }
    return null;
}

// ---------- Handle AJAX actions ----------
$action = $_REQUEST['action'] ?? '';

// compute current month/year/week for session keys & behavior
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$selected_week = isset($_GET['week']) ? max(1, min(4, (int)$_GET['week'])) : week_of_month(date('Y-m-d'));
$session_key = "meal_plan_{$year}_{$month}_week{$selected_week}";

// for allowed / unlocked logic
$today = date('Y-m-d');
$allowed_week = week_of_month($today);
if ($allowed_week > 4) $allowed_week = 4;

// AJAX: shuffle a single recipe (swap)
if ($action === 'shuffle') {
    header('Content-Type: application/json');
    $day = $_GET['day'] ?? null; // 'YYYY-MM-DD'
    $course = $_GET['course'] ?? null;
    if (!$day || !$course) {
        echo json_encode(['success'=>false,'message'=>'Missing day or course']); exit;
    }
    // get used ids in this week's session to avoid duplicates
    $used = [];
    if (isset($_SESSION[$session_key]) && is_array($_SESSION[$session_key])) {
        foreach ($_SESSION[$session_key] as $d => $arr) {
            foreach ($arr as $c => $m) {
                if (!empty($m['id'])) $used[] = (int)$m['id'];
            }
        }
    }
    $used = array_unique($used);
    // also exclude the current id for this slot if present (to avoid picking same)
    $current_id = 0;
    if (isset($_SESSION[$session_key][$day][$course]['id'])) $current_id = (int)$_SESSION[$session_key][$day][$course]['id'];
    if ($current_id) $used[] = $current_id;
    // pick
    $pick = pick_random_recipe_unique($conn, $course, $used);
    if ($pick) {
        if (!isset($_SESSION[$session_key])) $_SESSION[$session_key] = [];
        if (!isset($_SESSION[$session_key][$day])) $_SESSION[$session_key][$day] = [];
        $_SESSION[$session_key][$day][$course] = $pick;
        echo json_encode(['success'=>true,'recipe'=>$pick]);
    } else {
        echo json_encode(['success'=>false,'message'=>'No recipe available to swap']);
    }
    exit;
}

// AJAX: mark done toggle
if ($action === 'mark_done') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $day = $payload['day'] ?? null;
    $course = $payload['course'] ?? null;
    $done = isset($payload['done']) && ($payload['done']==1 || $payload['done']==='1') ? 1 : 0;
    if (!$day || !$course) { echo json_encode(['success'=>false,'message'=>'Missing']); exit; }
    if (!isset($_SESSION['meal_done'])) $_SESSION['meal_done'] = [];
    if (!isset($_SESSION['meal_done'][$session_key])) $_SESSION['meal_done'][$session_key] = [];
    if (!isset($_SESSION['meal_done'][$session_key][$day])) $_SESSION['meal_done'][$session_key][$day] = [];
    $_SESSION['meal_done'][$session_key][$day][$course] = $done ? 1 : 0;
    echo json_encode(['success'=>true]);
    exit;
}

// AJAX: generate grocery for next week (returns aggregated list) — unchanged behavior
if ($action === 'generate_grocery') {
    header('Content-Type: application/json');
    $isSunday = (date('N') == 7);
    if (!$isSunday || $selected_week != $allowed_week) {
        echo json_encode(['success'=>false,'message'=>'Grocery generation locked until Sunday for the active week.']); exit;
    }
    $next_week = $selected_week + 1;
    if ($next_week > 4) {
        echo json_encode(['success'=>false,'message'=>'Next week not available']); exit;
    }
    $session_key_next = "meal_plan_{$year}_{$month}_week{$next_week}";
    $ingredients_map = [];
    $add_ing = function($txt) use (&$ingredients_map) {
        if (!$txt) return;
        $parts = preg_split('/[\r\n,]+/', $txt);
        foreach ($parts as $p) {
            $it = trim($p);
            if ($it === '') continue;
            $ingredients_map[$it] = ($ingredients_map[$it] ?? 0) + 1;
        }
    };
    if (isset($_SESSION[$session_key_next]) && is_array($_SESSION[$session_key_next])) {
        foreach ($_SESSION[$session_key_next] as $d => $arr) {
            foreach ($arr as $c => $m) {
                if (!empty($m['ingredients'])) {
                    $add_ing($m['ingredients']);
                } else if (!empty($m['id'])) {
                    $stmt = $conn->prepare("SELECT ingredients FROM recipes WHERE id=? LIMIT 1");
                    $stmt->bind_param('i', $m['id']);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows) {
                        $row = $res->fetch_assoc();
                        $add_ing($row['ingredients']);
                    }
                    $stmt->close();
                }
            }
        }
    } else {
        $first_of_month = strtotime("$year-$month-01");
        $start_offset = ($next_week - 1) * 7;
        $week_start = strtotime("+{$start_offset} days", $first_of_month);
        for ($i=0;$i<7;$i++){
            $d = date('Y-m-d', strtotime("+$i days", $week_start));
            foreach (['Breakfast','Lunch','Snacks','Dinner'] as $c) {
                $stmt = $conn->prepare("SELECT ingredients FROM recipes WHERE course LIKE ? AND status='approved' ORDER BY RAND() LIMIT 1");
                $like = "%".$c."%";
                $stmt->bind_param('s', $like);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows) {
                    $row = $res->fetch_assoc();
                    $add_ing($row['ingredients']);
                }
                $stmt->close();
            }
        }
    }
    if (empty($ingredients_map)) {
        echo json_encode(['success'=>false,'message'=>'No ingredients found to build grocery list']); exit;
    }
    $list_lines = [];
    foreach ($ingredients_map as $ing => $count) {
        $list_lines[] = "{$ing} (approx. {$count})";
    }
    $list_text = implode("\n", $list_lines);
    echo json_encode(['success'=>true,'for_week'=>$next_week,'list_text'=>$list_text,'list_html'=>nl2br(htmlspecialchars($list_text))]);
    exit;
}

// ---------- Build or load meal plan for display ----------
$days_y = [];
$first_of_month = strtotime("$year-$month-01");
$start_offset = ($selected_week - 1) * 7;
$week_start = strtotime("+{$start_offset} days", $first_of_month);
for ($i=0;$i<7;$i++){
    $days_y[] = date('Y-m-d', strtotime("+$i days", $week_start));
}
$courses = ['Breakfast','Lunch','Snacks','Dinner'];

// load from session if exists else build unique week plan using LIKE and avoiding duplicates
if (!isset($_SESSION[$session_key]) || isset($_GET['refresh_plan'])) {
    $used = [];
    $meal_plan = [];
    // For each course, fetch up to 7 unique recipes (that have the course in their course field)
    foreach ($courses as $course) {
        // prepared: select up to 7 unique recipes matching the course string
        $like = "%".$course."%";
        $stmt = $conn->prepare("SELECT id, title, image_path, description, ingredients FROM recipes WHERE course LIKE ? AND status='approved' ORDER BY RAND() LIMIT 7");
        if ($stmt) {
            $stmt->bind_param('s', $like);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    // ensure it's not already used by another course/day
                    if (!in_array((int)$r['id'], $used)) {
                        $rows[] = $r;
                        $used[] = (int)$r['id'];
                    }
                }
            }
            $stmt->close();
        }
        // assign rows to days (one per day) for this course
        for ($d = 0; $d < 7; $d++) {
            $dayKey = $days_y[$d];
            if (!isset($meal_plan[$dayKey])) $meal_plan[$dayKey] = [];
            if (isset($rows[$d])) {
                $meal_plan[$dayKey][$course] = $rows[$d];
            } else {
                // If not enough unique recipes found for this course, try fetching any remaining approved recipes (may repeat across courses if impossible)
                $stmt2 = $conn->prepare("SELECT id, title, image_path, description, ingredients FROM recipes WHERE course LIKE ? AND status='approved' AND id NOT IN (?) ORDER BY RAND() LIMIT 1");
                // if $used is empty, pass 0
                $exclude_str = int_array_for_sql($used);
                // fallback query without exclude if prepare fails with dynamic IN; instead try a simple LIKE single pick
                if ($stmt2) {
                    // this prepare will fail because we can't bind IN as string; skip using it
                    $stmt2->close();
                    $fallbackStmt = $conn->prepare("SELECT id, title, image_path, description, ingredients FROM recipes WHERE course LIKE ? AND status='approved' ORDER BY RAND() LIMIT 1");
                    if ($fallbackStmt) {
                        $fallbackStmt->bind_param('s', $like);
                        $fallbackStmt->execute();
                        $resfb = $fallbackStmt->get_result();
                        if ($resfb && $resfb->num_rows) {
                            $meal_plan[$dayKey][$course] = $resfb->fetch_assoc();
                            $used[] = (int)$meal_plan[$dayKey][$course]['id'];
                        } else {
                            $meal_plan[$dayKey][$course] = ['id'=>0,'title'=>'No recipe available','image_path'=>'assets/no-recipe.png','description'=>'','ingredients'=>''];
                        }
                        $fallbackStmt->close();
                    } else {
                        $meal_plan[$dayKey][$course] = ['id'=>0,'title'=>'No recipe available','image_path'=>'assets/no-recipe.png','description'=>'','ingredients'=>''];
                    }
                } else {
                    $meal_plan[$dayKey][$course] = ['id'=>0,'title'=>'No recipe available','image_path'=>'assets/no-recipe.png','description'=>'','ingredients'=>''];
                }
            }
        }
    }
    $_SESSION[$session_key] = $meal_plan;
} else {
    $meal_plan = $_SESSION[$session_key];
}

// done states
if (!isset($_SESSION['meal_done'])) $_SESSION['meal_done'] = [];
if (!isset($_SESSION['meal_done'][$session_key])) $_SESSION['meal_done'][$session_key] = [];

function is_done($session_key, $day, $course) {
    if (!isset($_SESSION['meal_done'][$session_key])) return false;
    return !empty($_SESSION['meal_done'][$session_key][$day][$course]);
}

// UI variables
$currentMonth = date('F', strtotime("$year-$month-01"));
$weekTitle = "{$currentMonth} – Week {$selected_week}";
$isSunday = (date('N') == 7);
$is_locked_view = ($selected_week > $allowed_week);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>What to Cook — TasteIt</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --primary:#B0C364;
  --primary-dark:#97b24e;
  --muted:#777;
  --white: #fff;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:'Poppins',sans-serif;
  background: url('img/bg10.jpg') center/cover no-repeat fixed;
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}

/* translucent overlay over bg (reduced to let bg show more) */
.app-overlay {
  background: rgba(255,255,255,0.62);
  min-height:100vh;
  display:flex;
  overflow:auto;
}

/* Left narrow vertical bar (compact) */
.left-bar {
  width:72px;
  background:var(--white);
  border-right:1px solid #eee;
  display:flex;
  flex-direction:column;
  align-items:center;
  padding:30px 8px; /* lowered icons by increasing top padding */
  gap:14px;
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

/* tooltip on hover */
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

/* top area + content */
.main {
  flex:1;
  padding:22px 28px;
}

/* Panels: week panel above title panel */
.panel-row { display:flex; gap:16px; align-items:center; margin-bottom:14px; }

/* panel style */
.panel {
  background:var(--white);
  border:2px solid var(--primary);
  color:var(--primary);
  padding:8px 12px;
  border-radius:10px;
  display:flex;
  gap:8px;
  align-items:center;
  box-shadow: 0 6px 18px rgba(0,0,0,0.04);
}

/* week tabs inside panel */
.week-tab {
  background:transparent;
  color:var(--primary);
  border:1px solid var(--primary);
  padding:8px 12px;
  border-radius:8px;
  cursor:pointer;
  font-weight:600;
}
.week-tab.locked { opacity:0.45; filter:grayscale(.2); cursor:not-allowed; }

/* title panel */
.title-panel { margin-bottom:10px; padding:14px 18px; border-radius:10px; border:2px solid var(--primary); background:var(--white); color:var(--primary); box-shadow:0 6px 18px rgba(0,0,0,0.04); }

/* topbar title (inside title panel area) */
.title-block h1 { margin:0; font-size:24px; color:var(--primary); }
.title-block p { margin:6px 0 0; color:var(--muted); font-size:14px; }

/* planner */
.planner { max-width:1200px; margin:14px auto; }
.day-row {
  background:transparent;
  padding:18px 10px;
  display:flex;
  flex-direction:column;
  gap:12px;
  margin-bottom:18px;
}

/* day label style (pill), same colour scheme */
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

/* cards row (one horizontal line with 4 cards) */
.cards-row {
  display:flex;
  gap:14px;
  align-items:flex-start;
}

/* meal card */
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

/* course label pill inside card */
.card .course-pill {
  position:absolute;
  top:12px; left:12px;
  background:rgba(176,195,100,0.95); color:#fff;
  padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px;
  box-shadow:0 6px 16px rgba(0,0,0,0.06);
}

/* image area: full within card */
.card .img-wrap { width:100%; height:160px; background:#f6f6f6; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.card .img-wrap img { width:100%; height:100%; object-fit:cover; display:block; }

/* title area */
.card .card-body { padding:12px; text-align:left; }
.card .card-title { font-weight:700; font-size:15px; color:#222; margin:0 0 6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* checkbox area lowered (bottom-right) */
.card .done-box {
  position:absolute; bottom:12px; right:12px;
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
  content: '\f00c'; /* FontAwesome check */
  font-family: "Font Awesome 6 Free";
  font-weight:900;
  position:absolute; left:2px; top:-2px; color:#fff; font-size:12px;
}

/* locked/blurred view for future weeks */
.locked-card { filter: blur(6px); opacity:0.8; pointer-events:none; user-select:none; }

/* small separator */
.day-sep { height:8px; border-bottom:1px solid #eee; margin-top:12px; }

/* grocery message box — only message (no button) per your instruction */
.grocery-msg {
  margin-top:20px; padding:14px; background:#fff; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.04);
  border-left:4px solid var(--primary); color:#333;
}

/* responsive */
@media (max-width: 980px) {
  .cards-row { flex-direction:column; }
  .day-label { width:auto; }
  .left-bar { display:none; }
  .main { padding:14px; }
}
</style>
</head>
<body>

<div class="app-overlay">
  <!-- LEFT BAR -->
  <aside class="left-bar" aria-label="Main">
    <div class="brand">TasteIt</div>

    <button class="icon-btn" data-title="Home" onclick="goHome()" title="Home">
      <i class="fa-solid fa-house"></i>
    </button>

    <button class="icon-btn" data-title="Grocery List" id="groceryBtn" title="Grocery List">
      <i class="fa-solid fa-basket-shopping"></i>
    </button>

    <button class="icon-btn" data-title="Shuffle" id="shuffleBtn" title="Shuffle">
      <i class="fa-solid fa-arrows-rotate"></i>
    </button>

  </aside>

  <!-- MAIN -->
  <main class="main">

    <!-- Panels row: week panel above title panel -->
    <div class="panel-row">
      <!-- Week panel -->
      <div class="panel" role="tablist" aria-label="Weeks">
        <?php for ($w=1;$w<=4;$w++):
          $isLocked = ($w > $allowed_week);
          $tabClass = $isLocked ? 'week-tab locked' : 'week-tab';
          $href = $isLocked ? '#' : '?week='.$w;
        ?>
          <button class="<?php echo $tabClass; ?>" onclick="<?php echo $isLocked ? 'return false;' : 'location.href=\''.$href.'\''; ?>">
            <i class="fa-solid fa-calendar-days" style="margin-right:8px;"></i> Week <?php echo $w; ?>
          </button>
        <?php endfor; ?>
      </div>

      <!-- Title panel -->
      <div class="title-panel" style="flex:1;">
        <div class="title-block">
          <h1><?php echo htmlspecialchars($weekTitle); ?></h1>
          <p>Your weekly meal plan — simple, tasty, and ready to cook.</p>
        </div>
      </div>
    </div>

    <!-- Planner container -->
    <section class="planner" role="main">
      <?php foreach ($days_y as $dayDate):
        $dayLabel = date('l, d M', strtotime($dayDate));
      ?>
        <article class="day-row" data-day="<?php echo htmlspecialchars($dayDate); ?>">
          <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
            <div class="day-label"><?php echo date('l', strtotime($dayDate)); ?></div>
            <div style="margin-left:auto; color:var(--muted); font-size:13px;">
              Day <?php echo array_search($dayDate,$days_y)+1; ?>
            </div>
            <div style="margin-left:12px;">
              <button class="week-tab" onclick="markDayComplete('<?php echo $dayDate; ?>')">Mark Day as Complete</button>
            </div>
          </div>

          <div class="cards-row" role="list">
            <?php foreach ($courses as $course):
              $meal = $meal_plan[$dayDate][$course] ?? ['id'=>0,'title'=>'No recipe','image_path'=>'assets/no-recipe.png'];
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
                  </div>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>

          <div class="day-sep"></div>
        </article>
      <?php endforeach; ?>

      <!-- Grocery message block (only message; removed generation button per your request) -->
      <div class="grocery-msg" id="groceryMsg">
        <?php if ($isSunday && $selected_week == $allowed_week): ?>
          <strong>Grocery generator:</strong> You can now generate the grocery list for the next week (available via Grocery List button).
        <?php else: ?>
          <strong>Grocery list locked:</strong> Next week’s grocery list will be available on Sunday.
        <?php endif; ?>
      </div>

    </section>

  </main>
</div>

<!-- Shuffle modal (unchanged) -->
<div id="shuffleModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); align-items:center; justify-content:center; z-index:80;">
  <div style="width:94%; max-width:980px; background:#fff; border-radius:12px; padding:16px; box-shadow:0 12px 40px rgba(0,0,0,0.25); max-height:85vh; overflow:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <h3 style="margin:0">Weekly Recipes — Click Swap to replace</h3>
      <button onclick="closeShuffle()" style="border:none; background:#eee; padding:8px 12px; border-radius:8px; cursor:pointer;">Close</button>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
      <?php foreach ($days_y as $d): foreach ($courses as $c):
          $m = $meal_plan[$d][$c] ?? null;
          $label = date('D', strtotime($d)) . " - $c: " . ($m['title'] ?? 'No recipe');
      ?>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-radius:8px; background:#fafafa; border:1px solid #f0f0f0;">
          <div style="font-size:14px; color:#333; max-width:80%;"><?php echo htmlspecialchars($label); ?></div>
          <div>
            <button onclick="swapRecipe('<?php echo $d; ?>','<?php echo $c; ?>', this)" data-day="<?php echo $d; ?>" data-course="<?php echo $c; ?>" class="swap-btn" style="padding:6px 10px; border-radius:8px; background:var(--primary); color:#fff; border:none; cursor:pointer;">
              Swap
            </button>
          </div>
        </div>
      <?php endforeach; endforeach; ?>
    </div>
  </div>
</div>

<script>
const SESSION_KEY = <?php echo json_encode($session_key); ?>;
const SELECTED_WEEK = <?php echo json_encode($selected_week); ?>;
const CURRENT_MONTH = <?php echo json_encode($month); ?>;
const CURRENT_YEAR = <?php echo json_encode($year); ?>;
const IS_SUNDAY = <?php echo json_encode($isSunday ? true : false); ?>;

// Toggle done state (AJAX)
function toggleDone(e, day, course) {
  e.stopPropagation();
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

// Mark Day as Complete: check all checkboxes in that day
function markDayComplete(day) {
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

// Shuffle modal open/close
document.getElementById('shuffleBtn').addEventListener('click', function(){ openShuffle(); });
function openShuffle(){ document.getElementById('shuffleModal').style.display='flex'; }
function closeShuffle(){ document.getElementById('shuffleModal').style.display='none'; }

// Swap recipe for a day/course (AJAX)
function swapRecipe(day, course, btn) {
  btn.disabled = true;
  btn.innerText = 'Swapping...';
  fetch('?action=shuffle&day=' + encodeURIComponent(day) + '&course=' + encodeURIComponent(course) + '&week=' + SELECTED_WEEK + '&month=' + CURRENT_MONTH + '&year=' + CURRENT_YEAR)
  .then(res => res.json())
  .then(data => {
    if (data.success && data.recipe) {
      const selector = '.card[data-date="'+day+'"][data-course="'+course+'"]';
      const card = document.querySelector(selector);
      if (card) {
        card.setAttribute('data-id', data.recipe.id);
        const img = card.querySelector('.img-wrap img');
        if (img) img.src = data.recipe.image_path || 'assets/no-recipe.png';
        const title = card.querySelector('.card-title');
        if (title) title.innerText = data.recipe.title;
      }
    } else {
      alert(data.message || 'No replacement found.');
    }
  }).catch(err => {
    console.error(err);
    alert('Error while swapping.');
  }).finally(() => {
    btn.disabled = false;
    btn.innerText = 'Swap';
  });
}

// Grocery click: show existing behavior (message / generation elsewhere)
document.getElementById('groceryBtn').addEventListener('click', function(){
  if (!IS_SUNDAY) {
    alert("Next week's grocery list will be available on Sunday.");
    return;
  }
  // If Sunday, user can generate via separate flow — keeping behavior unchanged
  if (!confirm('Generate grocery list for the next week?')) return;
  fetch('?action=generate_grocery&week=' + SELECTED_WEEK + '&month=' + CURRENT_MONTH + '&year=' + CURRENT_YEAR)
  .then(r=>r.json()).then(data=>{
    if (data.success) {
      const win = window.open('','_blank');
      win.document.write('<h2>Grocery List — Week '+data.for_week+'</h2><pre>'+data.list_text.replace(/</g,'&lt;')+'</pre>');
    } else {
      alert(data.message || 'Could not generate grocery list');
    }
  }).catch(err=>{ console.error(err); alert('Error generating grocery'); });
});

// Home button
function goHome(){ location.href='?week=<?php echo $selected_week; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>'; }
</script>
</body>
</html>
