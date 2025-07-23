<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "tasteit";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Get number of recipes per category
$categoryData = $conn->query("SELECT category, COUNT(*) AS count FROM recipes GROUP BY category");

$labels = [];
$counts = [];

while ($row = $categoryData->fetch_assoc()) {
  $labels[] = $row['category'];
  $counts[] = $row['count'];
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Graphical Insights</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #fefae0;
      padding: 30px;
    }
    h2 {
      color: #D7263D;
      text-align: center;
      margin-bottom: 40px;
    }
    .chart-container {
      width: 80%;
      margin: auto;
    }
    a.back {
      display: block;
      margin: 20px auto;
      width: fit-content;
      text-decoration: none;
      background: #ffc107;
      color: black;
      padding: 10px 20px;
      border-radius: 10px;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <h2>📊 Recipes by Category</h2>

  <div class="chart-container">
    <canvas id="categoryChart"></canvas>
  </div>

  <a class="back" href="admin_dashboard.php">⬅ Back to Dashboard</a>

  <script>
    const ctx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($labels); ?>,
        datasets: [{
          label: 'Number of Recipes',
          data: <?php echo json_encode($counts); ?>,
          backgroundColor: '#B0C364'
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          title: {
            display: true,
            text: 'Recipe Count per Category'
          }
        }
      }
    });
  </script>
</body>
</html>
