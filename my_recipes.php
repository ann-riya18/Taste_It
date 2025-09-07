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
            margin: 20px;
            background: #f8f9fa;
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

        .recipe-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            padding: 10px;
        }

        .recipe-card {
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 15px;
            text-align: center;
            transition: transform 0.2s ease-in-out;
        }

        .recipe-card:hover {
            transform: translateY(-5px);
        }

        .recipe-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 10px;
        }

        .recipe-card h3 {
            font-size: 18px;
            margin: 10px 0;
            color: #333;
        }

        .recipe-card p {
            font-size: 14px;
            color: #666;
            margin-bottom: 12px;
            min-height: 40px;
        }

        .recipe-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .btn {
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            color: #fff;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-edit {
            background-color: #B0C364;
        }
        .btn-edit:hover {
            background-color: #9bb050;
        }

        .btn-delete {
            background-color: #E74C3C;
        }
        .btn-delete:hover {
            background-color: #c0392b;
        }
    </style>
</head>
<body>

<h2>My Recipes</h2>
<div class="recipe-container">
    <?php
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT id, title, description, image_path FROM recipes WHERE user_id = $user_id ORDER BY id DESC";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo '<div class="recipe-card">';
            echo '<img src="'.$row['image_path'].'" alt="'.$row['title'].'">';
            echo '<h3>'.$row['title'].'</h3>';
            echo '<p>'.$row['description'].'</p>';

            // Edit & Delete buttons
            echo '<div class="recipe-actions">';
            echo '<a href="edit_recipe.php?id='.$row['id'].'" class="btn btn-edit">Edit</a>';
            echo '<a href="my_recipes.php?delete_id='.$row['id'].'" class="btn btn-delete" onclick="return confirm(\'Are you sure you want to delete this recipe?\');">Delete</a>';
            echo '</div>';

            echo '</div>';
        }
    } else {
        echo "<p style='text-align:center;'>You haven't uploaded any recipes yet.</p>";
    }
    ?>
</div>

</body>
</html>
