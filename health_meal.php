<?php
// what_to_cook_health.php
session_start();
// NOTE: Assumes 'db.php' is included and sets up $conn (mysqli connection)
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
    // The 'Health Focus' requires two or more diet values
    return count($diet_array) >= 2;
}

// ---------- Main Logic (Time Check Confirmed) ----------

// Compute current week's Monday and Next Week's Monday
$today_date = new DateTime(); // Current datetime for comparison
$today_str = $today_date->format('Y-m-d');

// Determine the date of the previous Monday (ISO standard, 1=Monday, 7=Sunday)
$current_monday_date = (clone $today_date)->modify('this monday');
$current_week_start = $current_monday_date->format('Y-m-d');

// Determine Next Week's Monday
$next_monday_date = (clone $current_monday_date)->modify('+7 days');
$next_week_start = $next_monday_date->format('Y-m-d');

// Determine which week to display based on GET parameter (Default: Current Week)
$start_date_param = $_GET['start_date'] ?? $current_week_start;
$start_date = (new DateTime($start_date_param))->format('Y-m-d');

// Set the displayed week based on the validated start_date
if ($start_date === $current_week_start) {
    $selected_week = 'current';
    $session_key = "meal_plan_health_{$current_week_start}";
} elseif ($start_date === $next_week_start) {
    $selected_week = 'next';
    $session_key = "meal_plan_health_{$next_week_start}";
} else {
    $selected_week = 'current';
    $start_date = $current_week_start;
    $session_key = "meal_plan_health_{$current_week_start}";
}

// --- Next Week Unlock Logic ---
$is_locked_view = false;
// Calculates Sunday at 3:00 PM (15:00:00) of the *current* week
$unlock_datetime = (new DateTime($current_monday_date->format('Y-m-d') . ' 15:00:00'))->modify('+6 days'); 

if ($selected_week === 'next') {
    // If the current time has NOT passed Sunday 3 PM, the view is locked
    $current_timestamp = $today_date->getTimestamp();
    $unlock_timestamp = $unlock_datetime->getTimestamp();
    
    if ($current_timestamp < $unlock_timestamp) {
        $is_locked_view = true;
    }
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// Handle AJAX actions (mark_done)
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

// Build days of the week (Monday to Sunday)
$days_y = [];
$start_of_week = strtotime($start_date); // This is the Monday of the selected week

for ($i = 0; $i < 7; $i++) {
    $days_y[] = date('Y-m-d', strtotime("+$i days", $start_of_week));
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
            
            // If we don't have enough recipes with multiple diets, use fallback recipes
            if (count($available_recipes) < 7 && !empty($fallback_recipes)) {
                $needed = 7 - count($available_recipes);
                for ($i = 0; $i < min($needed, count($fallback_recipes)); $i++) {
                    $available_recipes[] = $fallback_recipes[$i];
                }
            }
            
            // Assign recipes to days (7 days of the week)
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
$weekTitle = ($selected_week === 'current' ? 'Current Week' : 'Next Week') . ' (' . date('M j', strtotime($start_date)) . ' - ' . date('M j', strtotime($days_y[6])) . ')';
$weekDescription = "Nutritious, balanced, and delicious — plan runs from Monday to Sunday.";

// --- Final variables for UI ---
$current_week_label = 'Current Week';
$next_week_label = 'Next Week';
$next_week_url = '?start_date=' . $next_week_start;
$current_week_url = '?start_date=' . $current_week_start;

// Lock visual state for the Next Week tab: only visually dim the tab if the time hasn't passed
$is_next_week_locked_for_display = ($today_date->getTimestamp() < $unlock_datetime->getTimestamp());

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
    --primary:#b0c364; 
    --primary-dark:#b0c364;
    --muted:#777;
    --white: #fff;
}
*{box-sizing:border-box}
body{
    margin:0;
    font-family:'Poppins',sans-serif;
    background: url('img/bg26.jpg') center/cover no-repeat fixed;
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
    transition: all .15s ease;
}
.week-tab.locked { opacity:0.45; filter:grayscale(.2); } 
.week-tab.active { background:var(--primary); color:var(--white); }
.week-tab:hover:not(.active) { background: #f0f5e1; } 


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

.day-header {
    display: flex;
    align-items: center;
    gap: 12px;
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

.day-date {
    font-size: 14px;
    font-weight: 500;
    color: #444;
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
    background:#b0c364; color:#fff;
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
.locked-card a { pointer-events:none; }

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
                <p><?php echo htmlspecialchars($weekDescription); ?></p>
            </div>

            <div class="weeks-section" role="tablist" aria-label="Weeks">
                
                <?php 
                    $isCurrentActive = ($selected_week === 'current');
                    $currentClass = 'week-tab' . ($isCurrentActive ? ' active' : '');
                ?>
                <button class="<?php echo $currentClass; ?>" onclick="location.href='<?php echo $current_week_url; ?>'">
                    <i class="fa-solid fa-calendar-week"></i> <?php echo $current_week_label; ?>
                </button>
                
                <?php 
                    $isNextActive = ($selected_week === 'next');
                    $nextClass = 'week-tab' . ($isNextActive ? ' active' : '');
                    // Visually dim the NEXT WEEK tab only if the time hasn't passed
                    if ($is_next_week_locked_for_display) {
                        $nextClass .= ' locked';
                    }
                ?>
                <button class="<?php echo $nextClass; ?>" onclick="location.href='<?php echo $next_week_url; ?>'">
                    <i class="fa-solid fa-calendar-days"></i> <?php echo $next_week_label; ?>
                </button>
            </div>
        </div>

        <section class="planner" role="main">
            <?php foreach ($days_y as $dayDate): ?>
                <article class="day-row" data-day="<?php echo htmlspecialchars($dayDate); ?>">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                        <div class="day-header">
                            <span class="day-date"><?php echo date('M jS', strtotime($dayDate)); ?></span>
                            <div class="day-label"><?php echo date('l', strtotime($dayDate)); ?></div>
                        </div>
                        <div style="margin-left:auto; color:var(--muted); font-size:13px;">
                            Day <?php echo array_search($dayDate,$days_y)+1; ?>
                        </div>
                        <?php if (!$is_locked_view): ?>
                            <div style="margin-left:12px;">
                                <button class="mark-complete-btn" onclick="markDayComplete('<?php echo $dayDate; ?>')">Mark Day as Complete</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="cards-row" role="list">
                        <?php foreach ($courses as $course):
                            $meal = $meal_plan[$dayDate][$course] ?? ['id'=>0,'title'=>'No recipe','image_path'=>'assets/no-recipe.png','username'=>'','diet'=>''];
                            // Apply the blurred lock class only if $is_locked_view is TRUE
                            $locked_card_class = $is_locked_view ? 'locked-card' : '';
                            
                            $card_tag = ($is_locked_view || (int)$meal['id'] === 0) ? 'div' : 'a';
                            $card_href = ($is_locked_view || (int)$meal['id'] === 0) ? '' : 'href="view_recipe.php?id=' . (int)$meal['id'] . '"';
                        ?>
                            <<?php echo $card_tag; ?> class="card <?php echo $locked_card_class; ?>" <?php echo $card_href; ?> data-date="<?php echo htmlspecialchars($dayDate); ?> " data-course="<?php echo htmlspecialchars($course); ?>">
                                <div class="course-pill"><?php echo htmlspecialchars($course); ?></div>

                                <div class="done-box" <?php if(!$is_locked_view) echo 'onclick="event.stopPropagation();"'; ?> title="<?php echo $is_locked_view ? 'Locked' : 'Mark Done'; ?>">
                                    <input type="checkbox" class="custom-checkbox" 
                                        <?php if($is_locked_view) echo 'disabled'; ?>
                                        <?php if(!$is_locked_view && is_done($session_key,$dayDate,$course)) echo 'checked'; ?> 
                                        <?php if(!$is_locked_view) echo 'onchange="toggleDone(event,\''.$dayDate.'\',\''.$course.'\')"'; ?>
                                    />
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
                            </<?php echo $card_tag; ?>>
                        <?php endforeach; ?>
                    </div>

                    <div class="day-sep"></div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>

<script>
// PHP variables passed to JavaScript are adjusted for the new logic
const SELECTED_WEEK_START_DATE = <?php echo json_encode($start_date); ?>;
const IS_LOGGED_IN = <?php echo json_encode($is_logged_in); ?>;
const IS_LOCKED_VIEW = <?php echo json_encode($is_locked_view); ?>;

function toggleDone(e, day, course) {
    e.stopPropagation();
    
    if (!IS_LOGGED_IN) {
        alert('Please login to mark meals as done.');
        e.target.checked = false;
        return;
    }
    
    if (IS_LOCKED_VIEW) {
        alert('The plan is currently locked and cannot be modified.');
        e.target.checked = false;
        return;
    }
    
    const chk = e.target;
    const done = chk.checked ? 1 : 0;
    fetch('?action=mark_done&start_date=' + SELECTED_WEEK_START_DATE, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({day:day, course:course, done:done})
    }).then(r=>r.json()).then(data=>{
        if (!data.success) {
            alert(data.message || 'Could not save state.');
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

    if (IS_LOCKED_VIEW) {
        alert('The plan is currently locked and cannot be modified.');
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
            fetch('?action=mark_done&start_date=' + SELECTED_WEEK_START_DATE, {
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
    window.location.href = 'weekly_grocery.php?start_date=' + SELECTED_WEEK_START_DATE;
}

function goHome(){ 
    window.location.href = 'index.php'; 
}
</script>
</body>
</html>