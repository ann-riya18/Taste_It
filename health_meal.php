<?php
// health_meal.php
session_start();
include 'db.php'; 

// ---------- Helpers ----------
function int_array_for_sql($arr) {
    $arr = array_map('intval', $arr);
    return $arr ? implode(',', $arr) : '0';
}

function has_multiple_diets($diet_string) {
    if (empty($diet_string) || $diet_string === null) return false;
    $diet_array = explode(',', $diet_string);
    $diet_array = array_map('trim', $diet_array);
    $diet_array = array_filter($diet_array, function($value) {
        return !empty($value);
    });
    return count($diet_array) >= 2;
}

// ---------- Main Logic ----------
$today_date = new DateTime();
$today_str = $today_date->format('Y-m-d');

// Current week's Monday
$current_monday_date = (clone $today_date)->modify('this monday');
$current_week_start = $current_monday_date->format('Y-m-d');

// Next week's Monday
$next_monday_date = (clone $current_monday_date)->modify('+7 days');
$next_week_start = $next_monday_date->format('Y-m-d');

// Week 0 / Week 1 / Week 2 dates
// Adjust these according to your calendar logic
$week0_start = (clone $current_monday_date)->modify('-7 days')->format('Y-m-d'); // previous week
$week1_start = $current_week_start; // current week
$week2_start = $next_week_start; // upcoming week

// Determine selected week based on GET
$start_date_param = $_GET['start_date'] ?? $current_week_start;
$start_date = (new DateTime($start_date_param))->format('Y-m-d');

if ($start_date === $week0_start) {
    $selected_week = 'week0';
    $session_key = "meal_plan_health_{$week0_start}";
} elseif ($start_date === $week1_start) {
    $selected_week = 'week1';
    $session_key = "meal_plan_health_{$week1_start}";
} elseif ($start_date === $week2_start) {
    $selected_week = 'week2';
    $session_key = "meal_plan_health_{$week2_start}";
} else {
    $selected_week = 'week1';
    $start_date = $week1_start;
    $session_key = "meal_plan_health_{$week1_start}";
}

// ---------- Week Unlock Logic ----------
$is_locked_view = false;
$now = new DateTime();

if ($selected_week === 'week0' || $selected_week === 'week1') {
    // Completed weeks always unlocked
    $is_locked_view = false;
} elseif ($selected_week === 'week2') {
    // Unlock Sunday 3 PM before the week starts
    $unlock_datetime = new DateTime(date('Y-m-d', strtotime($week2_start . ' -1 day')) . ' 15:00:00');
    $is_locked_view = ($now < $unlock_datetime);
}

// Check login
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// AJAX: mark_done
$action = $_REQUEST['action'] ?? '';
if ($action === 'mark_done') {
    header('Content-Type: application/json');
    if (!$is_logged_in) {
        echo json_encode(['success' => false, 'message' => 'Please login to mark meals as done.']);
        exit;
    }
    
    if ($is_locked_view) {
        echo json_encode(['success' => false, 'message' => 'Cannot mark meals in a locked week.']);
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

// ---------- Build Week Days ----------
$days_y = [];
$start_of_week = strtotime($start_date);
for ($i = 0; $i < 7; $i++) {
    $days_y[] = date('Y-m-d', strtotime("+$i days", $start_of_week));
}

$courses = ['Breakfast', 'Lunch', 'Snacks', 'Dinner'];

// ---------- Load Meal Plan ----------
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
            LIMIT 100
        ");
        
        if ($stmt) {
            $stmt->bind_param('s', $like);
            $stmt->execute();
            $res = $stmt->get_result();
            $available_recipes = [];
            $fallback_recipes = [];
            
            while ($r = $res->fetch_assoc()) {
                $diet_value = $r['diet'] ?? '';
                
                if (has_multiple_diets($diet_value) && !in_array((int)$r['id'], $used)) {
                    $available_recipes[] = $r;
                    $used[] = (int)$r['id'];
                } elseif (!in_array((int)$r['id'], $used)) {
                    $fallback_recipes[] = $r;
                    $used[] = (int)$r['id'];
                }
                
                if (count($available_recipes) >= 7) break;
            }
            
            $stmt->close();
            
            if (count($available_recipes) < 7 && !empty($fallback_recipes)) {
                $needed = 7 - count($available_recipes);
                for ($i = 0; $i < min($needed, count($fallback_recipes)); $i++) {
                    $available_recipes[] = $fallback_recipes[$i];
                }
            }
            
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

// ---------- Done States ----------
if (!isset($_SESSION['meal_done'])) $_SESSION['meal_done'] = [];
if (!isset($_SESSION['meal_done'][$session_key])) $_SESSION['meal_done'][$session_key] = [];

function is_done($session_key, $day, $course) {
    if (!isset($_SESSION['meal_done'][$session_key])) return false;
    return !empty($_SESSION['meal_done'][$session_key][$day][$course]);
}

// ---------- UI Variables ----------
$weekTitle = 'Week ' . ($selected_week === 'week0' ? '0' : ($selected_week === 'week1' ? '1' : '2')) 
    . ' (' . date('M j', strtotime($start_date)) . ' - ' . date('M j', strtotime($days_y[6])) . ')';
$weekDescription = "Nutritious, balanced, and delicious — plan runs from Monday to Sunday.";

$current_week_label = 'Week 1';
$next_week_label = 'Week 2';
$week0_label = 'Week 0';

$week0_url = '?start_date=' . $week0_start;
$current_week_url = '?start_date=' . $week1_start;
$next_week_url = '?start_date=' . $week2_start;

$is_next_week_locked_for_display = $is_locked_view;
$is_week0_locked_for_display = false;
$is_current_week_locked_for_display = false;

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>What to Cook — Health Focus | TasteIt</title>
<!-- Google Fonts & Font Awesome -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!-- STYLES (All original preserved) -->
<style>
:root{
    --primary:#b0c364; --primary-dark:#b0c364;
    --muted:#777; --white: #fff;
}
*{box-sizing:border-box}
body{
    margin:0; font-family:'Poppins',sans-serif;
    background: url('img/bg26.jpg') center/cover no-repeat fixed;
    -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
}
.app-overlay {
    background: rgba(255,255,255,0.62);
    min-height:100vh;
    display:flex;
    overflow:auto;
}
.left-bar { position: fixed; top:0; left:0; width:72px; height:100vh; background:var(--white); border-right:1px solid #eee; display:flex; flex-direction:column; align-items:center; padding:30px 8px; gap:14px; z-index:100;}
.brand { font-weight:700; color:var(--primary); font-size:14px; margin-bottom:8px; text-align:center; width:100%; }
.icon-btn { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--primary); cursor:pointer; position:relative; background:transparent; border:none; font-size:18px;}
.icon-btn:hover { background:#fbfbfb; transform:translateY(-3px); box-shadow:0 6px 16px rgba(0,0,0,0.04); }
.icon-btn::after { content: attr(data-title); position: absolute; left: 60px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.8); color:#fff; padding:6px 10px; border-radius:6px; font-size:12px; white-space:nowrap; opacity:0; pointer-events:none; transition:all .12s ease; }
.icon-btn:hover::after { opacity:1; transform: translateY(-50%) translateX(6px); }
.main { flex:1; padding:22px 28px; margin-left:72px;}
.top-panel { background:var(--white); border:2px solid var(--primary); color:var(--primary); padding:16px 20px; border-radius:10px; display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; box-shadow: 0 6px 18px rgba(0,0,0,0.04);}
.title-section { flex:1;}
.title-section h1 { margin:0; font-size:28px; color:var(--primary);}
.title-section p { margin:6px 0 0; color:var(--muted); font-size:15px; }
.weeks-section { display:flex; gap:10px; }
.week-tab { background:transparent; color:var(--primary); border:1px solid var(--primary); padding:10px 16px; border-radius:8px; cursor:pointer; font-weight:600; font-size:15px; display:flex; align-items:center; gap:6px; transition: all .15s ease; }
.week-tab.locked { opacity:0.45; filter:grayscale(.2); } 
.week-tab.active { background:var(--primary); color:var(--white); }
.week-tab:hover:not(.active) { background: #f0f5e1; } 
.mark-complete-btn { background:var(--white); color:var(--primary); border:1px solid var(--primary); padding:8px 16px; border-radius:8px; cursor:pointer; font-weight:600; font-size:14px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
.mark-complete-btn:hover { background:var(--primary); color:var(--white); }
.planner { max-width:1200px; margin:14px auto; }
.day-row { background:transparent; padding:18px 10px; display:flex; flex-direction:column; gap:12px; margin-bottom:18px;}
.day-header { display: flex; align-items: center; gap: 12px;}
.day-label { display:inline-block; background:var(--primary); color:var(--white); padding:10px 18px; border-radius:12px; font-weight:700; font-size:16px; width:150px; text-align:center;}
.day-date { font-size: 14px; font-weight: 500; color: #444; }
.cards-row { display:flex; gap:14px; align-items:flex-start; }
.card { flex:1; min-width:200px; background:var(--white); border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,0.06); overflow:hidden; position:relative; cursor:pointer; transition:transform .18s ease; text-decoration:none; color:inherit; }
.card:hover { transform:translateY(-6px); }
.card .course-pill { position:absolute; top:12px; left:12px; background:#b0c364; color:#fff; padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px; box-shadow:0 6px 16px rgba(0,0,0,0.06); }
.card .img-wrap { width:100%; height:220px; background:#f6f6f6; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.card .img-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
.card .card-body { padding:12px; text-align:left; }
.card .card-title { font-weight:700; font-size:15px; color:#222; margin:0 0 4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.card .chef-name { font-size:12px; color:var(--muted); margin:0 0 6px; }
.card .done-box { position:absolute; top:12px; right:12px; background:var(--white); border-radius:8px; padding:6px; box-shadow:0 6px 16px rgba(0,0,0,0.06); z-index:5;}
.custom-checkbox { appearance:none; -webkit-appearance:none; width:18px; height:18px; border-radius:5px; display:inline-block; border:2px solid #ddd; position:relative; cursor:pointer; }
.custom-checkbox:checked { background:var(--primary); border-color:var(--primary); }
.custom-checkbox:checked::after { content: '\f00c'; font-family: "Font Awesome 6 Free"; font-weight:900; position:absolute; left:2px; top:-2px; color:#fff; font-size:12px; }
.locked-card { filter: blur(6px); opacity:0.8; pointer-events:none; user-select:none; }
.locked-card a { pointer-events:none; }
.day-sep { height:8px; border-bottom:1px solid #eee; margin-top:12px; }
@media (max-width: 980px) {
    .cards-row { flex-direction:column; }
    .day-label { width:auto; }
    .left-bar { display:none; }
    .main { padding:14px; margin-left:0; }
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
            <p><?php echo htmlspecialchars($weekDescription); ?></p>
        </div>
        <div class="weeks-section" role="tablist" aria-label="Weeks">
            <button class="week-tab<?php echo $selected_week==='week0'?' active':'';?>" onclick="location.href='<?php echo $week0_url;?>'"><i class="fa-solid fa-calendar-week"></i> <?php echo $week0_label;?></button>
            <button class="week-tab<?php echo $selected_week==='week1'?' active':'';?>" onclick="location.href='<?php echo $current_week_url;?>'"><i class="fa-solid fa-calendar-week"></i> <?php echo $current_week_label;?></button>
            <button class="week-tab<?php echo $selected_week==='week2'?' active':'';?><?php echo $is_next_week_locked_for_display?' locked':'';?>" <?php echo $is_next_week_locked_for_display?'disabled':'';?> onclick="location.href='<?php echo $next_week_url;?>'"><i class="fa-solid fa-calendar-week"></i> <?php echo $next_week_label;?></button>
        </div>
    </div>

    <div class="planner">
        <?php foreach ($days_y as $day): ?>
            <div class="day-row">
                <div class="day-header">
                    <div class="day-label"><?php echo date('D', strtotime($day));?></div>
                    <div class="day-date"><?php echo date('M j', strtotime($day));?></div>
                </div>
                <div class="cards-row">
                    <?php foreach ($courses as $course):
                        $meal = $meal_plan[$day][$course];
                        $done = is_done($session_key,$day,$course);
                        $locked_class = $is_locked_view ? 'locked-card' : '';
                    ?>
                    <div class="card <?php echo $locked_class;?>">
                        <span class="course-pill"><?php echo htmlspecialchars($course);?></span>
                        <div class="img-wrap"><img src="<?php echo htmlspecialchars($meal['image_path']);?>" alt="<?php echo htmlspecialchars($meal['title']);?>"></div>
                        <div class="card-body">
                            <h3 class="card-title"><?php echo htmlspecialchars($meal['title']);?></h3>
                            <?php if(!empty($meal['username'])): ?>
                                <div class="chef-name">By <?php echo htmlspecialchars($meal['username']);?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_logged_in && !$is_locked_view): ?>
                        <div class="done-box">
                            <input type="checkbox" class="custom-checkbox" <?php echo $done?'checked':'';?> onchange="markDone('<?php echo $day;?>','<?php echo $course;?>',this.checked)">
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="day-sep"></div>
            </div>
        <?php endforeach; ?>
    </div>
</main>
</div>

<script>
function markDone(day, course, done){
    fetch('health_meal.php?action=mark_done', {
        method:'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({day: day, course: course, done: done?1:0})
    }).then(r=>r.json()).then(res=>{
        if(!res.success) alert(res.message);
    });
}
function goHome(){location.href='index.php';}
function goToGrocery(){location.href='healthy_grocery.php';}
</script>
</body>
</html>
