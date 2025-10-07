<?php
session_start();
// Check if the admin is logged in, redirect if not
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle search
$search = "";
$whereClause = "WHERE r.status = 'approved'";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = trim($_GET['search']);
    $safeSearch = $conn->real_escape_string($search);
    $whereClause .= " AND (r.title LIKE '%$safeSearch%' OR u.username LIKE '%$safeSearch%')";
}

// Fetch approved recipes with username
$query = "
    SELECT r.id, r.title, r.image_path, u.username 
    FROM recipes r 
    JOIN users u ON r.user_id = u.id 
    $whereClause
";
$result = $conn->query($query);

// Close connection later after HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approved Recipes</title>
    <style>
        :root {
            --primary-color: #B0C364;
            --text-color: #333;
            --background-light: #f9f9f9;
            --background-white: #fff;
            --font-main: 'Poppins', sans-serif;
        }

        body {
            font-family: var(--font-main);
            margin: 0;
            padding: 0;
            background: var(--background-light);
            color: var(--text-color);
        }

        /* Header Panel */
        .header-panel {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--background-white);
            padding: 15px 40px;
            border-bottom: 2px solid var(--primary-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-panel h2 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.8em;
        }

        /* Top Right Buttons */
        .top-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }

        .top-buttons button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-family: var(--font-main);
            font-size: 0.9em;
            transition: background 0.3s ease;
        }

        .top-buttons button:hover {
            background: #9bb256;
        }

        /* Search Box */
        .search-box {
            display: none;
            position: absolute;
            top: 45px;
            right: 0;
            background: var(--background-white);
            padding: 10px;
            border: 1px solid var(--primary-color);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .search-box input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            width: 200px;
            font-family: var(--font-main);
            outline: none;
        }

        .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 5px var(--primary-color);
        }

        /* Recipe Grid */
        .recipe-grid {
            padding: 20px 40px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .card {
            background: var(--background-white);
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-decoration: none;
            color: inherit;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .card-image-container {
            width: 100%;
            padding-top: 60%;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid #eee;
        }

        .card img {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: cover;
        }

        .card-content {
            padding: 15px;
            text-align: center;
        }

        .card h3 {
            margin: 0 0 5px;
            color: var(--primary-color);
            font-size: 1.1em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card p {
            margin: 0;
            font-size: 0.9em;
            color: #777;
        }

        .no-recipes {
            text-align: center;
            padding: 40px;
            font-size: 1.1em;
            color: #777;
        }
    </style>
</head>
<body>

<div class="header-panel">
    <h2>Approved Recipes</h2>
    <div class="top-buttons">
        <button id="searchToggle">Search</button>
        <button onclick="window.location.href='admin_dashboard.php'">Dashboard </button>
        <div id="searchBox" class="search-box">
            <form method="GET" action="">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search recipe or chef...">
            </form>
        </div>
    </div>
</div>

<div class="recipe-grid">
    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <a class="card" href="view_recipe.php?id=<?php echo htmlspecialchars($row['id']); ?>">
                <div class="card-image-container">
                    <?php if (!empty($row['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="Recipe Image">
                    <?php else: ?>
                        <img src="placeholder.jpg" alt="No Image Available">
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                    <p><strong>By:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
                </div>
            </a>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-recipes">No approved recipes found.</div>
    <?php endif; ?>
</div>

<script>
    const searchToggle = document.getElementById('searchToggle');
    const searchBox = document.getElementById('searchBox');
    searchToggle.addEventListener('click', () => {
        searchBox.style.display = (searchBox.style.display === 'block') ? 'none' : 'block';
        if (searchBox.style.display === 'block') {
            searchBox.querySelector('input').focus();
        }
    });
</script>

</body>
</html>

<?php $conn->close(); ?>
