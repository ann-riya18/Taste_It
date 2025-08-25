<?php
session_start();
include 'db.php'; // Your DB connection

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Remove bookmark if requested
if (isset($_GET['remove_id'])) {
    $remove_id = $_GET['remove_id'];
    $del_sql = "DELETE FROM bookmarks WHERE user_id=? AND recipe_id=?";
    $del_stmt = $conn->prepare($del_sql);
    $del_stmt->bind_param("ii", $user_id, $remove_id);
    $del_stmt->execute();
    header("Location: bookmarked_recipes.php");
    exit();
}

// Fetch bookmarked recipes
$sql = "SELECT r.id, r.title, r.description, r.image_path
        FROM recipes r
        JOIN bookmarks b ON r.id = b.recipe_id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookmarked Recipes</title>
    <link rel="stylesheet" href="style.css"> <!-- Your theme CSS -->
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 20px; background: #f9f9f9; }
        h2 { color: #B0C364; text-align: center; }
        .recipe-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr); /* 4 cards per row */
    gap: 20px;
    justify-content: start; /* Align cards to the left */
    margin-top: 20px;
}

        .recipe-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); width: 300px; overflow: hidden; transition: transform 0.2s; }
        .recipe-card:hover { transform: translateY(-5px); }
        .recipe-card img { width: 100%; height: 200px; object-fit: cover; }
        .recipe-card h3 { margin: 10px; color: #B0C364; }
        .recipe-card p { margin: 10px; font-size: 14px; color: #333; }
        .recipe-card a { display: inline-block; margin: 10px; padding: 6px 12px; background: #B0C364; color: #fff; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .recipe-card a:hover { background: #9aa94f; }
        .no-bookmarks { margin-top: 40px; text-align: center; color: #555; font-size: 16px; }
    </style>
</head>
<body>
    <h2>Bookmarked Recipes</h2>

    <?php if($result->num_rows > 0): ?>
        <div class="recipe-grid">
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="recipe-card">
                    <img src="<?= $row['image_path']; ?>" alt="<?= $row['title']; ?>">
                    <h3><?= $row['title']; ?></h3>
                    <p><?= substr($row['description'], 0, 100) . '...'; ?></p>
                    <a href="view_recipe.php?id=<?= $row['id']; ?>">View Recipe</a>
                    <a href="bookmarked_recipes.php?remove_id=<?= $row['id']; ?>">Remove</a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="no-bookmarks">You haven’t bookmarked any recipes yet.</p>
    <?php endif; ?>
</body>
</html>
