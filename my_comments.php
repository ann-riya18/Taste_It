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
    // Sanitize the input to ensure it's an integer
    $remove_id = filter_var($_GET['remove_id'], FILTER_SANITIZE_NUMBER_INT);

    // The prepared statement already handles SQL injection, but this adds a layer of safety.
    if ($remove_id) {
        $del_sql = "DELETE FROM comments WHERE id=? AND user_id=?";
        $del_stmt = $conn->prepare($del_sql);
        $del_stmt->bind_param("ii", $remove_id, $user_id);
        $del_stmt->execute();
    }
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
<link rel="stylesheet" href="style.css"> <style>
    /* ... (Your existing CSS here, unchanged) ... */
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
        font-size: 22px;
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

    /* Horizontal Comment Card */
    .comment-card {
        display: flex;
        justify-content: space-between;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
        transition: transform 0.2s;
        margin-bottom: 20px;
    }

    .comment-card:hover {
        transform: translateY(-5px);
    }

    .comment-content {
        padding: 15px;
        flex: 1;
    }

    .comment-content h3 {
        margin: 0 0 5px 0;
        color: #B0C364;
        font-size: 18px;
    }

    .comment-content p.comment-text {
        font-size: 14px;
        color: #333;
        background: #f0f9e8; /* Highlighted */
        padding: 10px;
        border-radius: 5px;
        margin: 5px 0;
    }

    .comment-content p.date {
        font-size: 12px;
        color: #777;
        margin-bottom: 10px;
    }

    .comment-content .actions a {
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

    .comment-content .actions a:hover {
        background: #B0C364;
        color: #fff;
    }

    .comment-card img {
        width: 180px;
        object-fit: cover;
    }

    .no-comments {
        margin-top: 40px;
        text-align: center;
        color: #555;
        font-size: 16px;
    }

    @media(max-width: 768px){
        .comment-card {
            flex-direction: column;
        }
        .comment-card img {
            width: 100%;
            height: 180px;
        }
    }
</style>

<script>
/**
 * Displays a confirmation dialog before proceeding with the comment deletion.
 * @param {string} deleteUrl The URL to redirect to if the user confirms.
 * @returns {void}
 */
function confirmDelete(deleteUrl) {
    if (confirm("Are you sure you want to delete this comment? This action cannot be undone.")) {
        window.location.href = deleteUrl;
    }
}
</script>

</head>
<body>

<div class="top-panel">
    <h2>My Comments</h2>
    <div class="top-links">
        <a href="index.php">Home</a>
        <a href="user_dashboard.php">Dashboard</a>
    </div>
</div>

<div class="main-content">
<?php
if($result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        // Prepare the delete URL for use in the JavaScript function
        $delete_url = "comments.php?remove_id=" . $row['id'];
        
        echo '<div class="comment-card">';
        echo '<div class="comment-content">';
        echo '<h3>'.$row['title'].'</h3>';
        
        // Note: Consider displaying the full comment text or providing a "Read More"
        // as truncating to 150 chars might cut off mid-word.
        // For now, keeping your original truncation:
        $comment_display = strlen($row['comment_text']) > 150 ? 
                           substr($row['comment_text'], 0, 150) . '...' :
                           $row['comment_text'];

        echo '<p class="comment-text">'.htmlentities($comment_display).'</p>';
        echo '<p class="date">'.date('d M Y, H:i', strtotime($row['created_at'])).'</p>';
        echo '<div class="actions">';
        echo '<a href="view_recipe.php?id='.$row['recipe_id'].'">View Recipe</a>';
        
        // REPLACED THE DIRECT LINK WITH A JAVASCRIPT FUNCTION CALL
        // Note: The 'return false;' is no longer strictly necessary since 'href' is '#'
        // and the action is controlled by the JS function.
        echo '<a href="#" onclick="confirmDelete(\''.$delete_url.'\'); return false;">Delete</a>';
        
        echo '</div></div>';
        echo '<img src="'.htmlentities($row['image_path']).'" alt="'.htmlentities($row['title']).'">';
        echo '</div>';
    }
}else{
    echo "<p class='no-comments'>You haven’t commented on any recipes yet.</p>";
}
?>
</div>

</body>
</html>