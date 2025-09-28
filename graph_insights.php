<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "tasteit";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define groups based on your requirement
$cuisines = ["Indian", "American", "Chinese", "Mexican", "Asian", "Middle Eastern", "Continental"];
$courses = ["Breakfast", "Lunch", "Dinner", "Snacks", "Desserts", "Drinks"];
$diets = ["Gluten-Free", "Lactose-Free", "Sugar-Free", "High-Protein", "Low-Fat", "Low-Carb"];
$quickRecipes = ["Under 15 minutes", "Under 30 minutes"];

// Helper function to count values + NULL with error handling
function getGroupedCountsWithNull($conn, $columnName, $definedItems, $nullLabel) {
    $labels = [];
    $counts = [];
    $errorOccurred = false;

    foreach ($definedItems as $item) {
        $safeItem = $conn->real_escape_string($item);
        
        // MODIFIED: Use FIND_IN_SET to match the item if it's within a comma-separated string 
        // in the database column (handles multiple values per recipe).
        // This ensures a recipe is counted for every matching constraint (e.g., 'Sugar-Free' counts 
        // a recipe even if the column holds 'Sugar-Free, Lactose-Free').
        $sql = "SELECT COUNT(*) AS count FROM recipes WHERE FIND_IN_SET('$safeItem', $columnName)";
        
        $countResult = $conn->query($sql);
        
        if ($countResult === false) {
            error_log("SQL Error on column '$columnName': " . $conn->error);
            $errorOccurred = true;
            break;
        }
        
        if ($row = $countResult->fetch_assoc()) {
            $labels[] = $item;
            $counts[] = (int)$row['count'];
        } else {
            $labels[] = $item;
            $counts[] = 0;
        }
    }

    if (!$errorOccurred) {
        // Count recipes where the column is NULL or empty (no constraint/value set)
        $sqlNull = "SELECT COUNT(*) AS count FROM recipes WHERE $columnName IS NULL OR $columnName = ''";
        $nullResult = $conn->query($sqlNull);
        
        if ($nullResult === false) {
            error_log("SQL Error on NULL check for '$columnName': " . $conn->error);
            $errorOccurred = true;
        } elseif ($row = $nullResult->fetch_assoc()) {
            $labels[] = $nullLabel;
            $counts[] = (int)$row['count'];
        }
    }

    if ($errorOccurred) {
        // If error occurred, provide labels but zero counts to prevent chart crash
        $labels = array_merge($definedItems, [$nullLabel]);
        $counts = array_fill(0, count($labels), 0);
    }
    
    return ['labels' => $labels, 'counts' => $counts];
}

// Fetch Data for all four charts
$cuisineData = getGroupedCountsWithNull($conn, 'cuisine', $cuisines, 'No Cuisine');
$courseData = getGroupedCountsWithNull($conn, 'course', $courses, 'No Course');
$dietData = getGroupedCountsWithNull($conn, 'diet', $diets, 'No Diet');
$quickRecipeData = getGroupedCountsWithNull($conn, 'quick_recipe', $quickRecipes, 'No Info');

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Graphical Insights - Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --theme-primary: #B0C364; /* Theme Green */
            --theme-accent: #5A6E2D; /* Darker Green Accent */
            --theme-light-bg: #F5F8EC; /* Soft off-white background */
            --background-body: #FFFFFF; /* Pure White Content Background */
            --font-family: 'Inter', sans-serif;
            --header-height: 80px; /* Define header height */
        }
        
        body {
            font-family: var(--font-family);
            background: var(--theme-light-bg);
            padding: 0; /* Remove default body padding */
            margin: 0;
            /* Padding for content below the fixed header */
            padding-top: var(--header-height); 
        }

        /* --- Header/Top Panel Styling (Fixed to top) --- */
        .dashboard-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: var(--header-height);
            background-color: var(--background-body); /* White BG */
            border-bottom: 3px solid var(--theme-primary); /* B0C364 Outline */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px; /* Adjusted from 30px to 40px to move button slightly left */
            z-index: 100;
        }

        .dashboard-header h2 {
            color: var(--theme-primary);
            margin: 0;
            font-size: 2em;
            font-weight: 700;
            /* Reset previous H2 styles */
            border-bottom: none;
            padding-bottom: 0;
        }

        /* --- Back Button Styling (Top Right) --- */
        a.back {
            text-decoration: none;
            /* New Style: White BG, Theme Text, Theme Outline */
            background: var(--background-body); 
            color: var(--theme-primary); /* B0C364 Text */
            border: 2px solid var(--theme-primary); /* B0C364 Outline */
            padding: 8px 18px; 
            
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: inline-block;
            margin: 0; 
            width: auto;
        }
        a.back:hover {
            /* Hover: Inverted colors for strong feedback */
            background: var(--theme-primary); /* B0C364 BG on hover */
            color: var(--background-body); /* White text on hover */
            transform: translateY(-1px);
        }
        
        /* --- Chart Grid and Cards --- */
        .charts-container {
             padding: 30px; /* Padding for the area below the header */
        }

        .charts-grid {
            display: grid;
            /* Ensures 2 charts per line on desktop/tablet, but stays flexible */
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); 
            gap: 30px;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Mobile responsiveness: stack charts vertically */
        @media (max-width: 900px) {
             .charts-grid {
                grid-template-columns: 1fr;
             }
        }

        .chart-card {
            background: var(--background-body); /* White card background */
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 2px solid var(--theme-primary); /* B0C364 Outline */
            transition: box-shadow 0.3s;
        }
        .chart-card:hover {
             box-shadow: 0 8px 20px rgba(0,0,0,0.15); 
        }

        .chart-card h3 {
            text-align: center;
            color: var(--theme-primary); /* CHANGED to primary theme color */
            margin-bottom: 15px;
            font-size: 1.4em;
            font-weight: 600;
            border-bottom: 2px dashed var(--theme-primary); /* Dashed separator */
            padding-bottom: 10px;
        }
    </style>
</head>
<body>
    
    <!-- TOP PANEL / HEADER -->
    <div class="dashboard-header">
        <h2>Recipe Data Insights</h2>
        <a class="back" href="admin_dashboard.php">Dashboard</a>
    </div>

    <!-- MAIN CONTENT AREA -->
    <div class="charts-container">
        <div class="charts-grid">
            <div class="chart-card">
                <h3>Recipes by Cuisine</h3>
                <canvas id="cuisineChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>Recipes by Course</h3>
                <canvas id="courseChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>Recipes by Diet</h3>
                <canvas id="dietChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>Quick Recipes</h3>
                <canvas id="quickRecipeChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        const cuisineData = <?php echo json_encode($cuisineData); ?>;
        const courseData = <?php echo json_encode($courseData); ?>;
        const dietData = <?php echo json_encode($dietData); ?>;
        const quickRecipeData = <?php echo json_encode($quickRecipeData); ?>;

        // Custom color palette based on the B0C364 theme for visual appeal
        const colorPalette = [
            '#B0C364', '#8DA346', '#C5D68B', '#5A6E2D',
            '#E0E9C1', '#9B9B64', '#6A7A3A', '#4E5B28'
        ];

        function createChart(elementId, type, data, colorSet) {
            const ctx = document.getElementById(elementId).getContext('2d');
            new Chart(ctx, {
                type: type,
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Total Recipes',
                        data: data.counts,
                        backgroundColor: colorSet.slice(0, data.labels.length),
                        borderColor: colorSet.map(c => c+'AA'),
                        borderWidth: 1.5,
                        borderRadius: 8,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1.8, // Decreased the height relative to the width
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            ticks: { precision: 0, color: '#666' },
                            grid: { color: '#eee' }
                        },
                        x: { 
                            grid: { display: false },
                            ticks: { color: '#666' } 
                        }
                    }
                }
            });
        }

        createChart('cuisineChart', 'bar', cuisineData, colorPalette);
        createChart('courseChart', 'bar', courseData, colorPalette);
        createChart('dietChart', 'bar', dietData, colorPalette);
        createChart('quickRecipeChart', 'bar', quickRecipeData, colorPalette);
    </script>
</body>
</html>
