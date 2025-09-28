<?php
session_start();
// Check if the admin is logged in, redirect if not
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

// Database connection
// SECURITY NOTE: Please use prepared statements (PDO or mysqli) for production code!
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 🔥 Auto delete rejected recipes older than 1 day
// This query is fine for this purpose, but using prepared statements for deletes is safer in general.
$conn->query("DELETE FROM recipes WHERE status='rejected' AND TIMESTAMPDIFF(DAY, updated_at, NOW()) >= 1");

// Fetch remaining rejected recipes
$sql = "SELECT r.id, r.title, r.description, r.category, r.image_path, u.username 
        FROM recipes r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.status = 'rejected'";
$result = $conn->query($sql);

if ($result === false) {
    die("Error executing query: " . $conn->error);
}

if ($conn->ping()) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Declined Recipes</title>
    <style>
        /* Define Theme Colors - Using a Red/Maroon theme for 'Declined' */
        :root {
            --theme-color: #B0c364; /* Crimson/Firebrick for 'Declined' */
            --accent-color: #b0c364; /* Specific outline color requested */
            --card-outline-color: #A0522D; /* Subtle brown/sienna for professional outline */
            --background-body: #FFFFFF; /* Pure White Background */
            --text-dark: #333;
            --text-medium: #555;
            --font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
        }

        body {
            font-family: var(--font-family);
            padding: 30px;
            background-color: var(--background-body);
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* Heading Style */
        h2 {
            color: var(--theme-color);
            font-size: 2.2em;
            font-weight: 700;
            border-bottom: 2px solid var(--theme-color);
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        /* Recipe Card Styling (Similar to Pending) */
        .card {
            display: flex;
            justify-content: space-between;
            gap: 30px;
            background: #FFFFFF;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 12px;
            /* Extended card outline with theme color */
            border: 1px solid var(--card-outline-color); 
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            transition: box-shadow 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .recipe-details {
            flex-grow: 1; /* Allows details to take up remaining space */
        }

        .recipe-details h3 {
            color: var(--theme-color);
            margin-top: 0;
            font-size: 1.6em;
            margin-bottom: 10px;
        }

        .recipe-details p {
            margin-bottom: 8px;
            font-size: 0.95em;
            color: var(--text-medium);
        }

        .recipe-details strong {
            color: var(--text-dark);
        }
        
        .decline-note {
            color: var(--theme-color);
            font-weight: 700;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #DDD;
        }

        /* Image Container on the Right */
        .image-container {
            width: 250px; /* Increased size */
            height: 250px; /* Increased size */
            flex-shrink: 0;
            position: relative;
        }
        
        /* Circular Image Style */
        .card img {
            width: 100%;
            height: 100%;
            border-radius: 50%; /* Makes the image circular */
            object-fit: cover;
            /* Circular outline with requested accent color */
            border: 4px solid var(--accent-color); 
            box-shadow: 0 0 0 8px rgba(176, 195, 100, 0.2); 
            margin: 0; /* Remove default margin */
        }

        /* Style for No Declined Recipes */
        .no-recipes {
            font-size: 1.2em;
            color: #777;
            padding: 40px;
            text-align: center;
            background: #fff;
            border: 2px solid #EEE;
            border-radius: 10px;
            /* Subtle themed border */
            border-left: 10px solid var(--theme-color); 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .no-recipes strong {
            color: var(--theme-color);
            display: block;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    
    <h2> Declined Recipes</h2>

    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="card">
                <div class="recipe-details">
                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                    <p><strong>By:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($row['category']); ?></p>
                    <p><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                    
                    <p class="decline-note">This recipe was permanently archived after 1 day.</p>
                </div>

                <?php if (!empty($row['image_path'])): ?>
                    <div class="image-container">
                        <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="Recipe Image">
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-recipes">
            All reviews are up to date! There are no recipes currently marked as declined.
        </div>
    <?php endif; ?>

</body>
</html>