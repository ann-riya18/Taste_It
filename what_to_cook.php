<?php
// what_to_cook.php
session_start();
include 'db.php'; // your mysqli connection in $conn

// ---------- Date Helpers & Rolling Logic ----------

// Get the current timestamp
$current_timestamp = time();
$current_day_of_week = date('N', $current_timestamp); // 1 (Mon) through 7 (Sun)
$current_time = date('H:i', $current_timestamp);

/**
 * Determines the start date of the target week's Monday.
 * The core logic ensures the "Current Week" (offset 0) is always the Mon-Sun cycle 
 * containing the current date/time, providing the seamless transition at Monday midnight.
 * @param int $offset 0 for current, 1 for next.
 * @return string The Y-m-d date of the target Monday.
 */
function get_start_monday($offset = 0) {
    global $current_timestamp;

    // strtotime('monday this week') consistently gives the most recent past or current Monday (Y-m-d).
    $base_monday_timestamp = strtotime('monday this week', $current_timestamp);
    
    if ($offset === 0) {
        return date('Y-m-d', $base_monday_timestamp);
    }

    // The "Next Week" plan starts exactly 7 days after the base Monday.
    return date('Y-m-d', strtotime('+1 week', $base_monday_timestamp));
}

/**
 * Since "Next Week" is always clickable and its status is derived from the URL offset, 
 * this function is simplified to always return true for clickability.
 * @return array ['next_week_is_clickable' => bool]
 */
function get_availability_status() {
    // Next Week is now always clickable, regardless of the time or day.
    return [
        'next_week_is_clickable' => true, 
    ];
}


$availability = get_availability_status();

// Determine the selected week (0 for current, 1 for next)
$selected_week_offset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
if ($selected_week_offset < 0 || $selected_week_offset > 1) {
    $selected_week_offset = 0;
}

// NOTE: We no longer check if next week is clickable here.
// The user can always select ?week=1, and the resulting plan will be blurred/locked.


// Get the Monday of the selected week for plan generation
$start_monday_date = get_start_monday($selected_week_offset);

// Use the start date to generate a consistent session key
$session_key = "meal_plan_week_{$start_monday_date}";
$courses = ['Breakfast', 'Lunch', 'Snacks', 'Dinner'];


// Build the 7 days for the selected week
$days_y = [];
$days_text = [];
$week_start_timestamp = strtotime($start_monday_date);

for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("+$i days", $week_start_timestamp));
    $days_y[] = $date;
    $days_text[] = date('l, M j', strtotime($date));
}

// Lock logic: Current week is unlocked (offset 0). Next week (offset 1) is ALWAYS blurred/locked.
$is_locked_view = ($selected_week_offset === 1); 

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// ---------- AJAX Handlers (Modified Session Key access) ----------
$action = $_REQUEST['action'] ?? '';
if ($action === 'mark_done') {
    header('Content-Type: application/json');
    
    $ajax_week_offset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
    
    // PREVENT marking done on the locked 'Next Week' view (offset 1)
    if ($ajax_week_offset === 1) {
        echo json_encode(['success' => false, 'message' => 'Cannot mark meals as done on a locked plan.']);
        exit;
    }

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
    
    // The AJAX handler must derive the session key from the provided week offset (via $_GET)
    $ajax_start_monday = get_start_monday($ajax_week_offset);
    $ajax_session_key = "meal_plan_week_{$ajax_start_monday}";

    if (!isset($_SESSION['meal_done'])) $_SESSION['meal_done'] = [];
    if (!isset($_SESSION['meal_done'][$ajax_session_key])) $_SESSION['meal_done'][$ajax_session_key] = [];
    if (!isset($_SESSION['meal_done'][$ajax_session_key][$day])) $_SESSION['meal_done'][$ajax_session_key][$day] = [];
    
    $_SESSION['meal_done'][$ajax_session_key][$day][$course] = $done ? 1 : 0;
    echo json_encode(['success' => true]);
    exit;
}

// ---------- Load or Build Meal Plan (Updated SQL) ----------
// Only generate the plan if the session key isn't set.
if (!isset($_SESSION[$session_key])) {
    $used = [];
    $meal_plan = [];
    
    foreach ($courses as $course) {
        $like = "%".$course."%";
        
        // Only select recipes with no diet tags
        $stmt = $conn->prepare("
            SELECT r.id, r.title, r.image_path, r.description, r.ingredients, r.diet 
            FROM recipes r 
            WHERE r.course LIKE ? 
              AND r.status = 'approved' 
              AND (r.diet IS NULL OR TRIM(r.diet) = '')
            ORDER BY RAND() 
            LIMIT 20
        ");
        
        if ($stmt) {
            $stmt->bind_param('s', $like);
            $stmt->execute();
            $res = $stmt->get_result();
            $available_recipes = [];
            
            while ($r = $res->fetch_assoc()) {
                // Unique recipe ID constraint per plan generation
                if (!in_array((int)$r['id'], $used)) { 
                    $available_recipes[] = $r;
                    $used[] = (int)$r['id'];
                    if (count($available_recipes) >= 7) break;
                }
            }
            
            $stmt->close();
            
            // Assign recipes to days (Mon-Sun)
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
$weekTitle = ($selected_week_offset === 0) ? 'Current Week Plan' : 'Next Week Plan';
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
    --muted:#555;
    --white: #fff;
}
*{box-sizing:border-box}
body{
    margin:0;
    font-family:'Poppins',sans-serif;
    background: url('img/bg19.jpg') center/cover no-repeat fixed;
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
/* Style for unavailable Next Week (removed in PHP, but kept for general styling) */
.week-tab.unavailable { opacity:0.45; filter:grayscale(.2); cursor:not-allowed; }

/* Active tab style */
.week-tab.active {
    background: var(--primary);
    color: var(--white);
    box-shadow: 0 4px 12px rgba(176,195,100,0.4);
}

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
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    gap:12px;
}
.day-label {
    display:inline-block;
    background:var(--primary);
    color:var(--white);
    padding:10px 18px;
    border-radius:12px;
    font-weight:700;
    font-size:16px;
    width:150px; /* Kept fixed width for consistency */
    text-align:center;
}
.day-date {
    margin-right:auto; /* Push other elements to the right */
    color:var(--muted); 
    font-size:14px; 
    font-weight:500;
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
    /* Requested Border */
    border: 1px solid var(--primary); 
}
.card:hover { transform:translateY(-6px); }

.card .course-pill {
    position:absolute;
    top:12px; left:12px;
    background:rgba(176,195,100,0.95); color:#fff;
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

/* Blur style for the locked card */
.locked-card { filter: blur(3px); opacity:0.75; pointer-events:none; user-select:none; }

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

        <button class="icon-btn" data-title="Shuffle" onclick="goToShuffle()" title="Shuffle">
            <i class="fa-solid fa-arrows-rotate"></i>
        </button>
    </aside>

    <main class="main">
        <div class="top-panel">
            <div class="title-section">
                <h1><?php echo htmlspecialchars($weekTitle); ?></h1>
                <p>Your health-focused weekly meal plan — nutritious, balanced, and delicious.</p>
            </div>

            <div class="weeks-section" role="tablist" aria-label="Weeks">
                <?php for ($w=0;$w<=1;$w++):
                    $label = ($w === 0) ? 'Current Week' : 'Next Week';
                    
                    $tabClass = 'week-tab';
                    if ($w === $selected_week_offset) $tabClass .= ' active';
                    // NOTE: The 'unavailable' class is no longer added for next week since it's always clickable.

                    $href = '?week='.$w;
                ?>
                    <button class="<?php echo $tabClass; ?>" onclick="location.href='<?php echo $href; ?>'">
                        <i class="fa-solid fa-calendar-days"></i> <?php echo $label; ?>
                    </button>
                <?php endfor; ?>
            </div>
        </div>

        <section class="planner" role="main">
            <?php foreach ($days_y as $d_idx => $dayDate): ?>
                <article class="day-row" data-day="<?php echo htmlspecialchars($dayDate); ?>">
                    <div class="day-header">
                        <div class="day-label"><?php echo date('l', strtotime($dayDate)); ?></div>
                        <div class="day-date"><?php echo $days_text[$d_idx]; ?></div>
                        <?php if (!$is_locked_view): // Only show mark complete for current/unlocked week ?>
                            <div style="margin-left:auto;">
                                <button class="mark-complete-btn" onclick="markDayComplete('<?php echo $dayDate; ?>')">Mark Day as Complete</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="cards-row" role="list">
                        <?php foreach ($courses as $course):
                            $meal = $meal_plan[$dayDate][$course] ?? ['id'=>0,'title'=>'No recipe','image_path'=>'assets/no-recipe.png','diet'=>''];
                            $locked_card_class = $is_locked_view ? 'locked-card' : '';
                            // Only link if not locked AND a valid recipe
                            $card_link = (!$is_locked_view && (int)$meal['id'] > 0) ? "view_recipe.php?id=" . (int)$meal['id'] : '#';
                        ?>
                            <div class="card <?php echo $locked_card_class; ?>" data-date="<?php echo htmlspecialchars($dayDate); ?>" data-course="<?php echo htmlspecialchars($course); ?>" onclick="<?php if(!$is_locked_view && (int)$meal['id'] > 0) echo 'location.href=\''.$card_link.'\''; ?>">
                                <div class="course-pill"><?php echo htmlspecialchars($course); ?></div>

                                <div class="done-box" <?php if(!$is_locked_view) echo 'onclick="event.stopPropagation();"'; ?> title="<?php echo $is_locked_view ? 'Locked' : 'Mark Done'; ?>">
                                    <input type="checkbox" class="custom-checkbox" 
                                            <?php if($is_locked_view) echo 'disabled'; ?>
                                            <?php if(is_done($session_key,$dayDate,$course)) echo 'checked'; ?> 
                                            onchange="<?php if(!$is_locked_view) echo 'toggleDone(event,\''.$dayDate.'\',\''.$course.'\')'; ?>"/>
                                </div>

                                <div class="img-wrap">
                                    <img src="<?php echo htmlspecialchars($meal['image_path'] ?: 'assets/no-recipe.png'); ?>" alt="">
                                </div>

                                <div class="card-body">
                                    <div class="card-title"><?php echo htmlspecialchars($meal['title']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="day-sep"></div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>

<script>
// Updated JS constants for new date logic
const SELECTED_WEEK_OFFSET = <?php echo json_encode($selected_week_offset); ?>;
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
    // Passing the 'week' offset to the AJAX handler
    fetch('?action=mark_done&week=' + SELECTED_WEEK_OFFSET, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({day:day, course:course, done:done})
    }).then(r=>r.json()).then(data=>{
        if (!data.success) {
            alert(data.message || 'Could not save state. Meals can only be marked on the Current Week plan.');
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
    
    // Safety check: Prevents running if currently viewing the locked plan
    if (SELECTED_WEEK_OFFSET === 1) {
         alert('Cannot mark meals as done on the Next Week plan.');
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
            // Passing the 'week' offset to the AJAX handler
            fetch('?action=mark_done&week=' + SELECTED_WEEK_OFFSET, {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({day:day, course:course, done:1})
            });
        }
    });
}

function goToShuffle() {
    if (!IS_LOGGED_IN) {
        alert('Please login to use the shuffle feature.');
        return;
    }
    // Passing the 'week' offset
    window.location.href = 'shuffle_meal.php?week=' + SELECTED_WEEK_OFFSET;
}

function goToGrocery() {
    if (!IS_LOGGED_IN) {
        alert('Please login to generate a grocery list.');
        return;
    }
    // Passing the 'week' offset
    window.location.href = 'weekly_grocery.php?week=' + SELECTED_WEEK_OFFSET;
}

function goHome(){ 
    window.location.href = 'index.php'; 
}
</script>
</body>
</html>