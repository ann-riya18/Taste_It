<?php 
session_start();
include 'db.php'; // your DB connection file

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

// Handle recipe deletion if "delete_id" is passed
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $user_id = $_SESSION['user_id'];

    // Ensure only the owner can delete
    $sql = "DELETE FROM recipes WHERE id = $delete_id AND user_id = $user_id";
    if ($conn->query($sql)) {
        echo "<script>alert('Recipe deleted successfully!'); window.location.href='my_recipes.php';</script>";
        exit();
    } else {
        echo "<script>alert('Error deleting recipe.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Recipes</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background: #f9f9f9;
        }

        /* Top Panel */
        .top-panel {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            border-bottom: 2px solid #B0C364;
            background: #fff;
            height: 70px;
        }

        .top-panel h2 {
            margin: 0;
            color: #B0C364;
            font-size: 28px;
        }

        .top-links {
            display: flex;
            gap: 15px;
        }

        .top-links a {
            border: 2px solid #B0C364;
            color: #B0C364;
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 5px;
            font-size: 14px;
            background: #fff;
            transition: all 0.3s ease;
        }

        .top-links a:hover {
            background: #B0C364;
            color: #fff;
        }

        /* Main Content */
        .main-content {
            padding: 20px 40px;
        }

        /* Recipe Grid */
        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .recipe-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s;
        }

        .recipe-card:hover {
            transform: translateY(-5px);
        }

        .recipe-card a.card-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .recipe-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }

        .recipe-card h3 {
            margin: 10px;
            color: #B0C364;
        }

        .recipe-card p {
            margin: 10px;
            font-size: 14px;
            color: #333;
            min-height: 40px;
        }

        .recipe-card .actions {
            margin: 10px;
        }

        .recipe-card .actions a {
            display: inline-block;
            margin-right: 8px;
            padding: 6px 12px;
            border: 2px solid #B0C364;
            color: #B0C364;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            background: #fff;
            transition: all 0.3s ease;
        }

        .recipe-card .actions a:hover {
            background: #B0C364;
            color: #fff;
        }

        .no-recipes {
            margin-top: 40px;
            text-align: center;
            color: #555;
            font-size: 16px;
        }
    </style>
</head>
<body>

    <!-- Top Panel -->
    <div class="top-panel">
        <h2>My Recipes</h2>
        <div class="top-links">
            <a href="index.php">Home</a>
            <a href="user_dashboard.php">Dashboard</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT id, title, description, image_path 
                FROM recipes 
                WHERE user_id = $user_id AND status='approved' 
                ORDER BY id DESC";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            echo '<div class="recipe-grid">';
            while($row = $result->fetch_assoc()) {
                echo '<div class="recipe-card">';
                
                // Make image + title + description clickable
                echo '<a class="card-link" href="view_recipe.php?id='.$row['id'].'">';
                echo '<img src="'.$row['image_path'].'" alt="'.$row['title'].'">';
                echo '<h3>'.$row['title'].'</h3>';
                echo '<p>'.substr($row['description'], 0, 100).'...</p>';
                echo '</a>';

                // Actions
                echo '<div class="actions">';
                echo '<a href="edit_recipe.php?id='.$row['id'].'">Edit</a>';
                echo '<a href="my_recipes.php?delete_id='.$row['id'].'" onclick="return confirm(\'Are you sure you want to delete this recipe?\');">Delete</a>';
                echo '</div>';

                echo '</div>';
            }
            echo '</div>';
        } else {
            echo "<p class='no-recipes'>You haven't uploaded any approved recipes yet.</p>";
        }
        ?>
    </div>

</body>
</html>
