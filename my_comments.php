<?php
session_start();
include 'db.php'; // Your DB connection

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Remove comment if requested
if (isset($_GET['remove_id'])) {
    $remove_id = $_GET['remove_id'];
    $del_sql = "DELETE FROM comments WHERE id=? AND user_id=?";
    $del_stmt = $conn->prepare($del_sql);
    $del_stmt->bind_param("ii", $remove_id, $user_id);
    $del_stmt->execute();
    header("Location: comments.php");
    exit();
}

// Fetch user comments
$sql = "SELECT c.id, c.comment_text, c.created_at, r.id AS recipe_id, r.title, r.image_path
        FROM comments c
        JOIN recipes r ON c.recipe_id = r.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC";
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
    <title>My Comments</title>
    <link rel="stylesheet" href="style.css"> <!-- Your theme CSS -->
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 20px; background: #f9f9f9; }
        h2 { color: #B0C364; text-align: center; }
        .comment-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; justify-content: start; margin-top: 20px; }
        .comment-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.2s; padding: 10px; }
        .comment-card:hover { transform: translateY(-5px); }
        .comment-card img { width: 100%; height: 150px; object-fit: cover; border-radius: 8px; }
        .comment-card h3 { margin: 10px 0 5px 0; color: #B0C364; font-size: 16px; }
        .comment-card p { font-size: 14px; color: #333; margin: 5px 0; }
        .comment-card .date { font-size: 12px; color: #777; margin-bottom: 5px; }
        .comment-card a { display: inline-block; margin-top: 5px; padding: 5px 10px; background: #B0C364; color: #fff; text-decoration: none; border-radius: 5px; font-size: 13px; }
        .comment-card a:hover { background: #9aa94f; }
        .no-comments { margin-top: 40px; text-align: center; color: #555; font-size: 16px; }
    </style>
</head>
<body>
    <h2>My Comments</h2>

    <?php if($result->num_rows > 0): ?>
        <div class="comment-grid">
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="comment-card">
                    <img src="<?= $row['image_path']; ?>" alt="<?= $row['title']; ?>">
                    <h3><?= $row['title']; ?></h3>
                    <p><?= substr($row['comment_text'], 0, 100) . '...'; ?></p>
                    <p class="date"><?= date('d M Y, H:i', strtotime($row['created_at'])); ?></p>
                    <a href="view_recipe.php?id=<?= $row['recipe_id']; ?>">View Recipe</a>
                    <a href="comments.php?remove_id=<?= $row['id']; ?>">Delete</a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="no-comments">You haven’t commented on any recipes yet.</p>
    <?php endif; ?>
</body>
</html>
