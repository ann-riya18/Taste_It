<?php
session_start();
include 'db.php'; // mysqli $conn

if (!isset($_SESSION['user_id'])) {
    echo "Please login to view analytics.";
    exit;
}
$user_id = intval($_SESSION['user_id']);

/*
  NOTE: This script assumes the following:
   - recipes: id, user_id, title, created_at
   - likes: id, recipe_id, user_id, created_at
   - comments: id, recipe_id, user_id, comment_text, created_at
   - bookmarks: id, recipe_id, user_id, created_at
   - recipe_ratings: id, recipe_id, user_id, rating, created_at
  If your column names differ, update the SQL below accordingly.
*/

// ---------- SUMMARY STATS ----------
$total_recipes = $conn->query("SELECT COUNT(*) AS c FROM recipes WHERE user_id = $user_id")->fetch_assoc()['c'] ?? 0;
$total_likes = $conn->query("SELECT COUNT(*) AS c FROM likes l JOIN recipes r ON l.recipe_id=r.id WHERE r.user_id = $user_id")->fetch_assoc()['c'] ?? 0;
$total_comments = $conn->query("SELECT COUNT(*) AS c FROM comments c JOIN recipes r ON c.recipe_id=r.id WHERE r.user_id = $user_id")->fetch_assoc()['c'] ?? 0;
$total_bookmarks = $conn->query("SELECT COUNT(*) AS c FROM bookmarks b JOIN recipes r ON b.recipe_id=r.id WHERE r.user_id = $user_id")->fetch_assoc()['c'] ?? 0;
$avg_rating_row = $conn->query("SELECT ROUND(AVG(rr.rating),2) AS avgr FROM recipe_ratings rr JOIN recipes r ON rr.recipe_id=r.id WHERE r.user_id=$user_id")->fetch_assoc();
$avg_rating = $avg_rating_row['avgr'] ?? 0;

// Most popular recipe (by likes)
$popular_q = $conn->query("
  SELECT r.id, r.title, COUNT(l.id) AS like_count
  FROM recipes r
  LEFT JOIN likes l ON r.id = l.recipe_id
  WHERE r.user_id = $user_id
  GROUP BY r.id
  ORDER BY like_count DESC
  LIMIT 1
");
$popular = $popular_q->fetch_assoc();

// ---------- PER-RECIPE COMPARISON (for horizontal bar chart & top table) ----------
$per_recipe_q = $conn->query("
  SELECT r.id, r.title,
    (SELECT COUNT(*) FROM likes WHERE recipe_id = r.id) AS likes,
    (SELECT COUNT(*) FROM comments WHERE recipe_id = r.id) AS comments,
    (SELECT COUNT(*) FROM bookmarks WHERE recipe_id = r.id) AS bookmarks,
    (SELECT ROUND(AVG(rating),2) FROM recipe_ratings WHERE recipe_id = r.id) AS avg_rating
  FROM recipes r
  WHERE r.user_id = $user_id
  ORDER BY (SELECT COUNT(*) FROM likes WHERE recipe_id = r.id) DESC
");
$recipe_titles = [];
$recipe_likes = [];
$recipe_comments = [];
$recipe_bookmarks = [];
$top_table = [];
while ($row = $per_recipe_q->fetch_assoc()) {
    $recipe_titles[] = $row['title'];
    $recipe_likes[] = intval($row['likes']);
    $recipe_comments[] = intval($row['comments']);
    $recipe_bookmarks[] = intval($row['bookmarks']);
    $top_table[] = $row;
}

// ---------- TIME SERIES (last 6 months) ----------
$months = [];
$likes_series = [];
$comments_series = [];
$bookmarks_series = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i month"));
    $months[] = date('M Y', strtotime($ym . "-01"));
    // likes
    $lres = $conn->query("SELECT COUNT(*) AS c FROM likes l JOIN recipes r ON l.recipe_id=r.id WHERE r.user_id=$user_id AND DATE_FORMAT(l.created_at, '%Y-%m') = '$ym'");
    $likes_series[] = intval($lres->fetch_assoc()['c'] ?? 0);
    // comments
    $cres = $conn->query("SELECT COUNT(*) AS c FROM comments c JOIN recipes r ON c.recipe_id=r.id WHERE r.user_id=$user_id AND DATE_FORMAT(c.created_at, '%Y-%m') = '$ym'");
    $comments_series[] = intval($cres->fetch_assoc()['c'] ?? 0);
    // bookmarks
    $bres = $conn->query("SELECT COUNT(*) AS c FROM bookmarks b JOIN recipes r ON b.recipe_id=r.id WHERE r.user_id=$user_id AND DATE_FORMAT(b.created_at, '%Y-%m') = '$ym'");
    $bookmarks_series[] = intval($bres->fetch_assoc()['c'] ?? 0);
}

// ---------- FUNNEL (aggregated totals across all recipes) ----------
$f_likes = $total_likes;
$f_comments = $total_comments;
$f_bookmarks = $total_bookmarks;
$f_ratings_row = $conn->query("SELECT COUNT(*) AS c FROM recipe_ratings rr JOIN recipes r ON rr.recipe_id=r.id WHERE r.user_id=$user_id")->fetch_assoc();
$f_ratings = intval($f_ratings_row['c'] ?? 0);

// ---------- HEATMAP: interactions by weekday (Sun-Sat) and hour (0-23) ----------
/*
 We'll combine likes + comments + bookmarks counts by weekday and hour.
 Result table: rows = hours (0-23), columns = Sun..Sat
*/
$heat = []; // heat[hour][weekday] => count
for ($h = 0; $h < 24; $h++) {
    for ($d = 0; $d < 7; $d++) $heat[$h][$d] = 0;
}

// likes
$res = $conn->query("SELECT HOUR(l.created_at) AS hr, DAYOFWEEK(l.created_at)-1 AS dow, COUNT(*) AS c
    FROM likes l JOIN recipes r ON l.recipe_id=r.id
    WHERE r.user_id=$user_id
    GROUP BY hr, dow");
while ($r = $res->fetch_assoc()) $heat[intval($r['hr'])][intval($r['dow'])] += intval($r['c']);

// comments
$res = $conn->query("SELECT HOUR(c.created_at) AS hr, DAYOFWEEK(c.created_at)-1 AS dow, COUNT(*) AS c
    FROM comments c JOIN recipes r ON c.recipe_id=r.id
    WHERE r.user_id=$user_id
    GROUP BY hr, dow");
while ($r = $res->fetch_assoc()) $heat[intval($r['hr'])][intval($r['dow'])] += intval($r['c']);

// bookmarks
$res = $conn->query("SELECT HOUR(b.created_at) AS hr, DAYOFWEEK(b.created_at)-1 AS dow, COUNT(*) AS c
    FROM bookmarks b JOIN recipes r ON b.recipe_id=r.id
    WHERE r.user_id=$user_id
    GROUP BY hr, dow");
while ($r = $res->fetch_assoc()) $heat[intval($r['hr'])][intval($r['dow'])] += intval($r['c']);

// Find max for heatmap coloring
$heat_max = 0;
foreach ($heat as $h => $cols) foreach ($cols as $d => $v) if ($v > $heat_max) $heat_max = $v;

// ---------- Prepares for frontend (json) ----------
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Recipe Analytics Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
  :root{--accent:#B0C364;--muted:#f4f6f8;--card:#fff;--text:#333;}
  body{font-family:'Poppins',sans-serif;background:#f4f6f8;color:var(--text);margin:0;padding:22px;}
  .wrap{max-width:1200px;margin:0 auto;}
  h1{text-align:left;margin:6px 0 18px;font-size:26px;}
  .top-cards{display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;}
  .card{background:var(--card);padding:16px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.06);min-width:160px;flex:1;}
  .card .num{font-size:22px;color:var(--accent);font-weight:700;}
  .card .label{font-size:13px;color:#666;margin-top:6px;}
  .grid{display:grid;grid-template-columns:1fr 1.3fr;gap:20px;margin-top:12px;}
  .panel{background:var(--card);padding:16px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.04);}
  .panel h3{margin:0 0 10px;font-size:16px;color:#444;}
  .row{display:flex;gap:14px;align-items:center;}
  .funnel-bar{height:18px;background:linear-gradient(90deg,var(--accent),#9bb04c);border-radius:8px; margin-top:6px;}
  .bar-label{display:flex;justify-content:space-between;font-size:13px;color:#666;margin-top:8px;}
  .bar-small{height:10px;border-radius:6px;background:#e6eef0;overflow:hidden;}
  .bar-fill{height:100%;background:var(--accent);}
  .chart-wrap{height:260px}
  .heatmap{width:100%;border-collapse:collapse;margin-top:10px;}
  .heatmap td{width:12.5%;height:26px;text-align:center;border-radius:4px;}
  .table-top{width:100%;border-collapse:collapse;margin-top:12px;}
  .table-top th, .table-top td{padding:8px;text-align:left;border-bottom:1px solid #eee;font-size:14px;}
  @media(max-width:900px){ .grid{grid-template-columns:1fr; } .top-cards{flex-direction:column;} .chart-wrap{height:220px;} }
</style>
</head>
<body>
<div class="wrap">
  <h1>📊 Recipe Analytics</h1>

  <!-- Top cards -->
  <div class="top-cards">
    <div class="card"><div class="num"><?php echo $total_recipes; ?></div><div class="label">Total Recipes</div></div>
    <div class="card"><div class="num"><?php echo $total_likes; ?></div><div class="label">Total Likes</div></div>
    <div class="card"><div class="num"><?php echo $total_comments; ?></div><div class="label">Total Comments</div></div>
    <div class="card"><div class="num"><?php echo $total_bookmarks; ?></div><div class="label">Total Bookmarks</div></div>
    <div class="card"><div class="num"><?php echo $avg_rating; ?> / 5</div><div class="label">Avg Rating</div></div>
    <div class="card"><div class="num"><?php echo htmlspecialchars($popular['title'] ?? '—'); ?></div><div class="label">Most Popular Recipe</div></div>
  </div>

  <!-- Grid -->
  <div class="grid">
    <!-- Left: line + bar + table -->
    <div>
      <div class="panel">
        <h3>Performance over last 6 months</h3>
        <div class="chart-wrap">
          <canvas id="lineChart"></canvas>
        </div>
      </div>

      <div class="panel" style="margin-top:16px;">
        <h3>Recipe comparison</h3>
        <div style="height:260px">
          <canvas id="barChart"></canvas>
        </div>
      </div>

      <div class="panel" style="margin-top:16px;">
        <h3>Top Recipes</h3>
        <table class="table-top">
          <thead><tr><th>Recipe</th><th>Likes</th><th>Comments</th><th>Bookmarks</th><th>Avg Rating</th></tr></thead>
          <tbody>
            <?php
            $cnt = 0;
            foreach ($top_table as $r) {
                if ($cnt++ >= 5) break;
                echo "<tr>
                        <td>".htmlspecialchars($r['title'])."</td>
                        <td>".$r['likes']."</td>
                        <td>".$r['comments']."</td>
                        <td>".$r['bookmarks']."</td>
                        <td>".($r['avg_rating']===null ? '—' : $r['avg_rating'])."</td>
                      </tr>";
            }
            if ($cnt==0) echo "<tr><td colspan='5'>No recipes yet</td></tr>";
            ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Right: funnel + heatmap + recipe select -->
    <div>
      <div class="panel">
        <h3>Engagement Funnel</h3>
        <div class="row">
          <div style="flex:1">
            <div class="bar-label"><span>Likes</span><span><?php echo $f_likes; ?></span></div>
            <div class="bar-small"><div class="bar-fill" style="width:<?php echo ($f_likes==0?0:min(100, round($f_likes / max(1,$total_likes) * 100))); ?>%"></div></div>

            <div class="bar-label"><span>Comments</span><span><?php echo $f_comments; ?></span></div>
            <div class="bar-small"><div class="bar-fill" style="width:<?php echo ($total_likes? round($f_comments / max(1,$total_likes) * 100) : 0); ?>%"></div></div>

            <div class="bar-label"><span>Bookmarks</span><span><?php echo $f_bookmarks; ?></span></div>
            <div class="bar-small"><div class="bar-fill" style="width:<?php echo ($total_likes? round($f_bookmarks / max(1,$total_likes) * 100) : 0); ?>%"></div></div>

            <div class="bar-label"><span>Ratings (count)</span><span><?php echo $f_ratings; ?></span></div>
            <div class="bar-small"><div class="bar-fill" style="width:<?php echo ($total_likes? round($f_ratings / max(1,$total_likes) * 100) : 0); ?>%"></div></div>
          </div>
        </div>
      </div>

      <div class="panel" style="margin-top:16px;">
        <h3>Activity heatmap (hour vs day)</h3>
        <small style="color:#666;">Darker = more activity</small>
        <table class="heatmap" id="heatmap">
          <thead>
            <tr>
              <th style="text-align:left">Hour \ Day</th>
              <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d) echo "<th>$d</th>"; ?>
            </tr>
          </thead>
          <tbody>
            <?php
            for ($h = 0; $h < 24; $h++) {
                echo "<tr><td style='padding:6px 8px;font-size:13px;color:#666;'>$h:00</td>";
                for ($d = 0; $d < 7; $d++) {
                    $v = $heat[$h][$d] ?? 0;
                    // compute intensity 0..1
                    $int = $heat_max ? ($v / $heat_max) : 0;
                    // color from very light to accent
                    $bg = $int ? "linear-gradient(90deg, rgba(176,195,100, ".(0.2+$int*0.65)."), rgba(176,195,100, ".(0.2+$int*0.65)."))" : 'transparent';
                    // we will use style background-color with rgba
                    $alpha = 0.15 + $int * 0.7;
                    $r = 176; $g = 195; $b = 100;
                    $bgcolor = $int ? "background: rgba($r,$g,$b,$alpha);" : "";
                    echo "<td style='border-radius:6px; $bgcolor'>".($v? $v : '')."</td>";
                }
                echo "</tr>";
            }
            ?>
          </tbody>
        </table>
      </div>

      <div class="panel" style="margin-top:16px;">
        <h3>Analyze Single Recipe</h3>
        <select id="singleRecipeSelect" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ddd;">
          <option value="">-- choose recipe --</option>
          <?php
          // reuse per_recipe_q results: but we consumed. Re-query titles
          $rl = $conn->query("SELECT id, title FROM recipes WHERE user_id=$user_id");
          while ($r = $rl->fetch_assoc()) {
              echo "<option value='".intval($r['id'])."'>".htmlspecialchars($r['title'])."</option>";
          }
          ?>
        </select>
        <div id="singleRecipePanel" style="display:none;margin-top:12px;">
          <canvas id="singleRecipeChartSmall" style="max-height:220px;"></canvas>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// ---------- Line Chart (time series) ----------
const months = <?php echo json_encode($months); ?>;
const likesSeries = <?php echo json_encode($likes_series); ?>;
const commentsSeries = <?php echo json_encode($comments_series); ?>;
const bookmarksSeries = <?php echo json_encode($bookmarks_series); ?>;

const lineCtx = document.getElementById('lineChart').getContext('2d');
new Chart(lineCtx, {
    type: 'line',
    data: {
        labels: months,
        datasets: [
            { label: 'Likes', data: likesSeries, borderColor: 'rgba(176,195,100,0.95)', backgroundColor: 'rgba(176,195,100,0.15)', tension:0.3 },
            { label: 'Comments', data: commentsSeries, borderColor: 'rgba(255,159,64,0.95)', backgroundColor: 'rgba(255,159,64,0.12)', tension:0.3 },
            { label: 'Bookmarks', data: bookmarksSeries, borderColor: 'rgba(54,162,235,0.95)', backgroundColor: 'rgba(54,162,235,0.12)', tension:0.3 },
        ]
    },
    options: {
        responsive:true,
        plugins:{legend:{position:'bottom'}},
        scales:{y:{beginAtZero:true}}
    }
});

// ---------- Horizontal bar chart (per recipe) ----------
const barCtx = document.getElementById('barChart').getContext('2d');
const recipeLabels = <?php echo json_encode($recipe_titles); ?>;
const recipeLikes = <?php echo json_encode($recipe_likes); ?>;
const recipeComments = <?php echo json_encode($recipe_comments); ?>;
const recipeBookmarks = <?php echo json_encode($recipe_bookmarks); ?>;

new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: recipeLabels,
        datasets: [
            { label:'Likes', data: recipeLikes, backgroundColor: 'rgba(176,195,100,0.9)' },
            { label:'Comments', data: recipeComments, backgroundColor: 'rgba(255,159,64,0.9)' },
            { label:'Bookmarks', data: recipeBookmarks, backgroundColor: 'rgba(54,162,235,0.9)' }
        ]
    },
    options: {
        indexAxis: 'y',
        responsive:true,
        plugins:{legend:{position:'bottom'}},
        scales:{x:{beginAtZero:true}}
    }
});

// ---------- Single recipe small doughnut via AJAX ----------
let singleChart;
$('#singleRecipeSelect').on('change', function(){
    const rid = $(this).val();
    if(!rid){ $('#singleRecipePanel').hide(); if(singleChart){ singleChart.destroy(); } return; }
    // fetch stats via same file (ajax param)
    $.get('recipe_analytics.php', { ajax:1, recipe_id: rid }, function(resp){
        try {
            const data = typeof resp === 'object' ? resp : JSON.parse(resp);
            $('#singleRecipePanel').show();
            if(singleChart) singleChart.destroy();
            const ctx = document.getElementById('singleRecipeChartSmall').getContext('2d');
            singleChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Likes','Comments','Bookmarks','Avg Rating'],
                    datasets: [{
                        data: [data.likes||0, data.comments||0, data.bookmarks||0, data.avg_rating||0],
                        backgroundColor: ['rgba(176,195,100,0.9)', 'rgba(255,159,64,0.9)', 'rgba(54,162,235,0.9)', 'rgba(255,99,132,0.9)']
                    }]
                },
                options: {
                    responsive:true,
                    plugins:{
                        tooltip:{
                            callbacks:{
                                label: function(ctx){ return ctx.label + ': ' + ctx.raw; }
                            }
                        },
                        legend:{position:'bottom'}
                    }
                }
            });
        } catch (e) {
            console.error(e, resp);
        }
    });
});
</script>
</body>
</html>


