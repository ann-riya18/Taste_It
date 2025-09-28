<?php
session_start();
// DB connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// **SECURITY NOTE**: Use prepared statements for user input ($user_id) in a real application.
$user_id = $_GET['id'] ?? 0;
$user_id = intval($user_id); // Basic sanitization

// Fetch user info
$sql_user = "SELECT username, profile_pic, bio FROM users WHERE id=$user_id";
$res_user = $conn->query($sql_user);
if (!$res_user || $res_user->num_rows == 0) {
    echo "User not found.";
    exit;
}
$user = $res_user->fetch_assoc();

// Fetch user recipes
$sql_recipes = "SELECT id, title, image_path FROM recipes WHERE user_id=$user_id AND status='approved'";
$res_recipes = $conn->query($sql_recipes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($user['username']); ?> - Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #B0C364; /* Your accent color */
            --bg-light: #f4f6f8;
            --card-bg: #ffffff;
            --text-dark: #333;
            --text-muted: #666;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --radius: 12px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* --- Profile Header Styling (Bio Card) --- */
        .profile-header {
            /* Flex layout for image and text */
            display: flex;
            align-items: center;
            gap: 30px;
            padding: 30px;
            margin-bottom: 40px;
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            
            /* FIXED: Permanent B0C364 outline for the bio card */
            border: 1px solid var(--primary-color);
        }

        .profile-header img {
            width: 150px;
            height: 150px;
            flex-shrink: 0; /* Prevents image from shrinking */
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--primary-color); 
            box-shadow: 0 0 0 5px var(--card-bg); 
        }

        .profile-info {
            /* EXTENDED: Allows info section to fill remaining space */
            flex-grow: 1; 
        }

        .profile-info h2 {
            margin: 0;
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .profile-info p {
            margin-top: 8px;
            font-size: 16px;
            color: var(--text-muted);
            /* Removed max-width to allow bio to fully extend */
        }

        h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 5px;
            display: inline-block;
        }
        
        /* --- Recipes Grid Styling --- */
        .recipes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
        }

        .recipe-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            overflow: hidden;
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            
            /* FIXED: Permanent B0C364 outline for recipe card, combined with shadow */
            border: 1px solid var(--primary-color);
            box-shadow: var(--shadow);
        }

        .recipe-card:hover {
            transform: translateY(-5px);
            /* Enhanced shadow on hover */
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15), 0 0 0 2px var(--primary-color);
        }

        .recipe-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
            transition: opacity 0.3s;
        }
        
        .recipe-card a {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .recipe-card h3 {
            margin: 15px 15px 20px 15px;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            line-height: 1.3;
            transition: color 0.2s;
        }

        .recipe-card a:hover h3 {
            color: var(--primary-color);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
                padding: 20px;
            }
            .profile-header img {
                width: 120px;
                height: 120px;
            }
            .profile-info h2 {
                font-size: 28px;
            }
            /* Two columns on mobile */
            .recipes-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-header">
            <img src="<?php echo htmlspecialchars($user['profile_pic'] ?: 'img/default_profile.png'); ?>" alt="Profile Picture">
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                <p><?php echo htmlspecialchars($user['bio'] ?: 'A passionate home chef sharing their favorite recipes!'); ?></p>
            </div>
        </div>

        <h2><?php echo htmlspecialchars($user['username']); ?>'s Approved Recipes</h2>
        <div class="recipes-grid">
            <?php if ($res_recipes && $res_recipes->num_rows > 0): ?>
                <?php while ($rec = $res_recipes->fetch_assoc()): ?>
                    <div class="recipe-card">
                        <a href="view_recipe.php?id=<?php echo htmlspecialchars($rec['id']); ?>">
                            <img src="<?php echo htmlspecialchars($rec['image_path'] ?: 'img/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($rec['title']); ?>">
                            <h3><?php echo htmlspecialchars($rec['title']); ?></h3>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="grid-column: 1 / -1; color: var(--text-muted);">This user hasn't published any approved recipes yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>