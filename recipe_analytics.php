<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo "Please login to view analytics.";
    exit;
}
$user_id = intval($_SESSION['user_id']);

/* ---------- AJAX FOR SINGLE RECIPE DOUGHNUT ---------- */
if(isset($_GET['ajax']) && isset($_GET['recipe_id'])){
    $rid = intval($_GET['recipe_id']);
    // Only allow approved recipes for analysis
    $check = $conn->query("SELECT id FROM recipes WHERE id=$rid AND user_id=$user_id AND status='approved'");
    if(!$check->num_rows){
        echo json_encode(['error'=>'Recipe not approved or not found']);
        exit;
    }

    $likes = $conn->query("SELECT COUNT(*) AS c FROM likes WHERE recipe_id=$rid")->fetch_assoc()['c'] ?? 0;
    $comments = $conn->query("SELECT COUNT(*) AS c FROM comments WHERE recipe_id=$rid")->fetch_assoc()['c'] ?? 0;
    $bookmarks = $conn->query("SELECT COUNT(*) AS c FROM bookmarks WHERE recipe_id=$rid")->fetch_assoc()['c'] ?? 0;
    $avg_rating = $conn->query("SELECT ROUND(AVG(rating),2) AS r FROM recipe_ratings WHERE recipe_id=$rid")->fetch_assoc()['r'] ?? 0;

    echo json_encode(['likes'=>$likes,'comments'=>$comments,'bookmarks'=>$bookmarks,'avg_rating'=>$avg_rating]);
    exit;
}

/* ---------- SUMMARY STATS ---------- */
$total_recipes = $conn->query("SELECT COUNT(*) AS c FROM recipes WHERE user_id=$user_id AND status='approved'")->fetch_assoc()['c'] ?? 0;
$total_likes = $conn->query("SELECT COUNT(*) AS c FROM likes l JOIN recipes r ON l.recipe_id=r.id WHERE r.user_id=$user_id AND r.status='approved'")->fetch_assoc()['c'] ?? 0;
$total_comments = $conn->query("SELECT COUNT(*) AS c FROM comments c JOIN recipes r ON c.recipe_id=r.id WHERE r.user_id=$user_id AND r.status='approved'")->fetch_assoc()['c'] ?? 0;
$total_bookmarks = $conn->query("SELECT COUNT(*) AS c FROM bookmarks b JOIN recipes r ON b.recipe_id=r.id WHERE r.user_id=$user_id AND r.status='approved'")->fetch_assoc()['c'] ?? 0;
$avg_rating = $conn->query("SELECT ROUND(AVG(rr.rating),2) AS avgr FROM recipe_ratings rr JOIN recipes r ON rr.recipe_id=r.id WHERE r.user_id=$user_id AND r.status='approved'")->fetch_assoc()['avgr'] ?? 0;

/* ---------- MOST POPULAR RECIPE ---------- */
$popular_q = $conn->query("
  SELECT r.id, r.title,
    (SELECT COUNT(*) FROM likes WHERE recipe_id=r.id)+
    (SELECT COUNT(*) FROM comments WHERE recipe_id=r.id)+
    (SELECT COUNT(*) FROM bookmarks WHERE recipe_id=r.id) AS engagement
  FROM recipes r
  WHERE r.user_id=$user_id AND r.status='approved'
  ORDER BY engagement DESC
  LIMIT 1
");
$popular = $popular_q->fetch_assoc();

/* ---------- TOP 5 RECIPES ---------- */
$top_table=[];
$per_recipe_q = $conn->query("
  SELECT r.id,r.title,
    (SELECT COUNT(*) FROM likes WHERE recipe_id=r.id) AS likes,
    (SELECT COUNT(*) FROM comments WHERE recipe_id=r.id) AS comments,
    (SELECT COUNT(*) FROM bookmarks WHERE recipe_id=r.id) AS bookmarks,
    (SELECT ROUND(AVG(rating),2) FROM recipe_ratings WHERE recipe_id=r.id) AS avg_rating,
    ((SELECT COUNT(*) FROM likes WHERE recipe_id=r.id)+
     (SELECT COUNT(*) FROM comments WHERE recipe_id=r.id)+
     (SELECT COUNT(*) FROM bookmarks WHERE recipe_id=r.id)) AS total_engagement
  FROM recipes r
  WHERE r.user_id=$user_id AND r.status='approved'
  ORDER BY total_engagement DESC
  LIMIT 5
");
while($row=$per_recipe_q->fetch_assoc()) $top_table[]=$row;

/* ---------- TIME SERIES (last 6 months) ---------- */
$months=[];$likes_series=[];$comments_series=[];$bookmarks_series=[];
for($i=5;$i>=0;$i--){
    $ym=date('Y-m', strtotime("-$i month"));
    $months[]=date('M Y', strtotime($ym.'-01'));
    $likes_series[]=intval($conn->query("SELECT COUNT(*) AS c FROM likes l JOIN recipes r ON l.recipe_id=r.id WHERE r.user_id=$user_id AND r.status='approved' AND DATE_FORMAT(l.created_at,'%Y-%m')='$ym'")->fetch_assoc()['c'] ?? 0);
    $comments_series[]=intval($conn->query("SELECT COUNT(*) AS c FROM comments c JOIN recipes r ON c.recipe_id=r.id WHERE r.user_id=$user_id AND r.status='approved' AND DATE_FORMAT(c.created_at,'%Y-%m')='$ym'")->fetch_assoc()['c'] ?? 0);
    $bookmarks_series[]=intval($conn->query("SELECT COUNT(*) AS c FROM bookmarks b JOIN recipes r ON b.recipe_id=r.id WHERE r.user_id=$user_id AND r.status='approved' AND DATE_FORMAT(b.created_at,'%Y-%m')='$ym'")->fetch_assoc()['c'] ?? 0);
}

/* ---------- HEATMAP (6 time slots for recipe creation) ---------- */
$heat_slots=["12AM-4AM","4AM-8AM","8AM-12PM","12PM-4PM","4PM-8PM","8PM-12AM"];
$heat=array_fill(0,6,array_fill(0,7,0)); // 6x7 grid

$res=$conn->query("
  SELECT HOUR(created_at) hr, DAYOFWEEK(created_at)-1 dow, COUNT(*) c
  FROM recipes
  WHERE user_id=$user_id AND status='approved'
  GROUP BY hr,dow
");
while($r=$res->fetch_assoc()){
    $slot=intdiv(intval($r['hr']),4);
    $dow=intval($r['dow']);
    $heat[$slot][$dow]+=intval($r['c']);
}
$heat_max=max(array_map('max',$heat));
$slot_labels=$heat_slots;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Recipe Analytics</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
:root{--accent:#B0C364;--card:#fff;--muted:#f4f6f8;--text:#333;}
body{font-family:'Poppins',sans-serif;background:var(--muted);margin:0;padding:20px;color:var(--text);}
.wrap{max-width:1250px;margin:auto;}
h1{font-size:26px;margin-bottom:20px;}
.top-cards{display:flex;gap:15px;flex-wrap:wrap;}
.card{flex:1;min-width:150px;background:var(--card);border-radius:12px;padding:18px;text-align:center;box-shadow:0 4px 10px rgba(0,0,0,0.06);transition:.2s;}
.card:hover{transform:translateY(-4px);}
.card .num{font-size:22px;font-weight:700;color:var(--accent);}
.card .label{font-size:13px;color:#666;margin-top:6px;}
.grid{display:grid;grid-template-columns:1fr 1.2fr;gap:18px;margin-top:20px;}
.panel{background:var(--card);padding:16px;border-radius:12px;box-shadow:0 4px 8px rgba(0,0,0,0.05);}
.panel h3{margin:0 0 10px;font-size:16px;color:#444;}
.chart-wrap{height:260px;}
.doughnut-wrap{width:280px;height:280px;margin:auto;}
.table-top{width:100%;border-collapse:collapse;margin-top:12px;}
.table-top th,.table-top td{padding:8px;font-size:13px;border-bottom:1px solid #eee;}
.heatmap td{width:12.5%;height:26px;text-align:center;border-radius:4px;position:relative;}
.heatmap td:hover::after{content:attr(data-tooltip);position:absolute;top:-20px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,0.7);color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;white-space:nowrap;}
@media(max-width:950px){.grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="wrap">
<h1>📊 Recipe Analytics</h1>
<div class="top-cards">
<div class="card"><div class="num"><?=$total_recipes?></div><div class="label">Total Recipes</div></div>
<div class="card"><div class="num"><?=$total_likes?></div><div class="label">Total Likes</div></div>
<div class="card"><div class="num"><?=$total_comments?></div><div class="label">Total Comments</div></div>
<div class="card"><div class="num"><?=$total_bookmarks?></div><div class="label">Total Bookmarks</div></div>
<div class="card"><div class="num"><?=$avg_rating?> / 5</div><div class="label">Avg Rating</div></div>
<div class="card"><div class="num"><?=htmlspecialchars($popular['title']??'—')?></div><div class="label">Most Popular Recipe</div></div>
</div>

<div class="grid">
<div>
  <div class="panel">
    <h3>Performance over last 6 months</h3>
    <div class="chart-wrap"><canvas id="lineChart"></canvas></div>
  </div>
  <div class="panel" style="margin-top:16px">
    <h3>Top Recipes</h3>
    <table class="table-top">
      <thead><tr><th>Recipe</th><th>Likes</th><th>Comments</th><th>Bookmarks</th><th>Rating</th></tr></thead>
      <tbody>
      <?php foreach($top_table as $r){
        echo "<tr><td>".htmlspecialchars($r['title'])."</td><td>{$r['likes']}</td><td>{$r['comments']}</td><td>{$r['bookmarks']}</td><td>".($r['avg_rating']??'—')."</td></tr>";
      }?>
      </tbody>
    </table>
  </div>
</div>

<div>
  <div class="panel">
    <h3>Activity Heatmap</h3>
    <table class="heatmap" style="margin-top:8px">
      <thead><tr><th></th><?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d) echo "<th>$d</th>";?></tr></thead>
      <tbody>
      <?php for($s=0;$s<6;$s++){
        echo "<tr><td style='font-size:12px;color:#666'>{$slot_labels[$s]}</td>";
        for($d=0;$d<7;$d++){
          $v=$heat[$s][$d]; $int=$heat_max?($v/$heat_max):0; $alpha=0.1+$int*0.8; $bg="background:rgba(176,195,100,$alpha);";
          echo "<td style='$bg' data-tooltip='$v recipe".($v!=1?'s':'')."'>".($v?:'')."</td>";
        }
        echo "</tr>";
      }?>
      </tbody>
    </table>
  </div>

  <div class="panel" style="margin-top:16px">
    <h3>Analyze Single Recipe</h3>
    <select id="singleRecipeSelect" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd;">
      <option value="">-- choose recipe --</option>
      <?php 
      $rl=$conn->query("SELECT id,title FROM recipes WHERE user_id=$user_id AND status='approved'"); 
      while($r=$rl->fetch_assoc()){
          echo "<option value='{$r['id']}'>".htmlspecialchars($r['title'])."</option>";
      } 
      ?>
    </select>
    <div class="doughnut-wrap" style="margin-top:12px"><canvas id="singleRecipeChartSmall"></canvas></div>
  </div>
</div>
</div>

<script>
const months = <?=json_encode($months)?>;
new Chart(document.getElementById('lineChart'), {
  type:'line',
  data:{labels:months,datasets:[
    {label:'Likes',data:<?=json_encode($likes_series)?>,borderColor:'rgba(176,195,100,1)',backgroundColor:'rgba(176,195,100,0.2)',tension:.3},
    {label:'Comments',data:<?=json_encode($comments_series)?>,borderColor:'rgba(255,159,64,1)',backgroundColor:'rgba(255,159,64,0.2)',tension:.3},
    {label:'Bookmarks',data:<?=json_encode($bookmarks_series)?>,borderColor:'rgba(54,162,235,1)',backgroundColor:'rgba(54,162,235,0.2)',tension:.3}
  ]},
  options:{plugins:{legend:{position:'bottom'}},responsive:true,scales:{y:{beginAtZero:true}}}
});

let singleChart;
$('#singleRecipeSelect').on('change',function(){
  const rid=$(this).val();
  if(!rid){if(singleChart) singleChart.destroy(); return;}
  $.get('recipe_analytics.php',{ajax:1,recipe_id:rid},function(resp){
    const d=typeof resp==='object'?resp:JSON.parse(resp);
    if(d.error){alert(d.error); return;}
    if(singleChart) singleChart.destroy();
    singleChart=new Chart(document.getElementById('singleRecipeChartSmall'),{
      type:'doughnut',
      data:{labels:['Likes','Comments','Bookmarks','Avg Rating'],
            datasets:[{data:[d.likes||0,d.comments||0,d.bookmarks||0,d.avg_rating||0],
            backgroundColor:['#B0C364','#FF9F40','#36A2EB','#FF6384']}]},
      options:{plugins:{legend:{position:'bottom'},tooltip:{callbacks:{label:ctx=>ctx.label+': '+ctx.raw}}},responsive:true,maintainAspectRatio:true}
    });
  });
});
</script>
</body>
</html>

