<?php
session_start();
// Check if the admin is logged in, redirect if not
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

// Database connection
// IMPORTANT: In a real-world scenario, you should use a configuration file and PDO/prepared statements for security.
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch approved recipes with the username of the submitter
// This query is inherently vulnerable to SQL Injection, which should be fixed with prepared statements in a production environment.
$result = $conn->query("SELECT r.id, r.title, r.image_path, u.username FROM recipes r JOIN users u ON r.user_id = u.id WHERE status = 'approved'");

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Approved Recipes</title>
    <style>
        :root {
            /* Define the primary color based on the request */
            --primary-color: #b0c364;
            --text-color: #333;
            --link-color: #b0c364;
            --background-light: #f9f9f9;
            --background-white: #fff;
        }

        body {
            font-family: 'Arial', sans-serif;
            margin: 0; /* Remove default body margin */
            padding: 0;
            background: var(--background-light);
            color: var(--text-color);
        }

        /* Fixed Header Panel */
        .header-panel {
            position: sticky; /* Fixed header */
            top: 0;
            z-index: 1000; /* Ensure it stays on top */
            background: var(--background-white);
            padding: 15px 40px;
            border-bottom: 2px solid var(--primary-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
        }

        .header-panel h2 {
            margin: 0;
            color: var(--link-color);
            font-size: 1.8em;
        }

        /* Recipe Grid Container */
        .recipe-grid {
            padding: 20px 40px; /* Add padding to the main content area */
            display: grid;
            /* Create a grid with 6 columns of equal width */
            grid-template-columns: repeat(6, 1fr);
            gap: 20px; /* Space between grid items */
        }

        /* Individual Recipe Card Style */
        .card {
            background: var(--background-white);
            border: 2px solid var(--primary-color); /* Outline with #b0c364 */
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden; /* Keep content within the card */
            text-decoration: none; /* Remove underline from the link */
            color: inherit; /* Inherit text color */
            display: flex; /* Flex container for content */
            flex-direction: column;
            text-align: center;
        }

        /* Hover effect for interaction */
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .card-image-container {
            width: 100%;
            padding-top: 60%; /* 5:3 Aspect Ratio for the image area */
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid #eee;
        }

        .card img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover; /* Crop and center the image */
            border-radius: 8px 8px 0 0;
        }

        .card-content {
            padding: 15px;
            flex-grow: 1; /* Allow content to grow to fill space */
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 5px;
            color: var(--link-color);
            font-size: 1.1em;
            line-height: 1.3;
            /* Truncate title if it's too long */
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .card p {
            margin: 0;
            font-size: 0.9em;
            color: #777;
        }

        /* Message for no recipes */
        .no-recipes {
            padding: 20px 40px;
            text-align: center;
            font-size: 1.1em;
            color: #777;
        }
    </style>
</head>
<body>

    <div class="header-panel">
        <h2> Approved Recipes</h2>
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
            <div class="no-recipes">No approved recipes yet.</div>
        <?php endif; ?>
    </div>

</body>
</html>