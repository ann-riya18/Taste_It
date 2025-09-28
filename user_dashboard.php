<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_email = $_SESSION['user_email'];

$query = "SELECT username, profile_pic FROM users WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $username = $user['username'];
    $profileImage = !empty($user['profile_pic']) 
    ? $user['profile_pic'] 
    : 'uploads/user_images/default.jpg';
} else {
    session_destroy();
    header("Location: user_login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>User Dashboard | Taste It</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<style>
    body {
        margin: 0;
        font-family: 'Poppins', sans-serif;
        display: flex;
        background: #fefaf3;
    }

    /* Sidebar */
    .sidebar {
        width: 240px;
        background-color: #fff;
        color: #B0C364;
        min-height: 100vh;
        padding: 30px 20px;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        align-items: center;
        /* The border is moved to the .main element for a clean join */
    }

    .profile-pic {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 15px;
        border: 3px solid #B0C364;
    }

    .welcome {
        text-align: center;
        font-size: 18px;
        margin-bottom: 30px;
        font-weight: 600;
        color: #B0C364;
    }

    .sidebar a {
        text-decoration: none;
        color: #B0C364;
        padding: 12px 15px;
        margin: 8px 0;
        width: 100%;
        display: flex;
        align-items: center;
        border: 2px solid #B0C364;
        border-radius: 8px;
        justify-content: center;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .sidebar a i {
        margin-right: 10px;
    }

    .sidebar a:hover {
        background-color: #B0C364;
        color: #fff;
    }

    /* Main content */
    .main {
        flex-grow: 1;
        background: url('img/bg20.jpg') center/cover no-repeat;
        position: relative;
        border-left: 2px solid #B0C364; /* This creates the vertical separation */
    }

    .main::before {
        content: '';
        position: absolute;
        top: 0; 
        left: 0; 
        right: 0; 
        bottom: 0;
        background: rgba(255,255,255,0.45); /* low transparent white */
        z-index: 1;
    }

    /* Top panel */
    .top-panel {
        position: relative;
        background-color: #fff;
        border-bottom: 2px solid #B0C364;
        padding: 20px 60px;
        text-align: left;
        display: flex;
        align-items: center;
        height: 80px;
        z-index: 2; /* Ensure the top panel is above the main overlay */
    }

    .top-panel h2 {
        position: relative;
        color: #B0C364;
        font-size: 28px;
        margin: 0;
    }

    /* Caption styling */
    .caption {
        text-align: center;
        padding: 20px;
        position: relative;
        z-index: 2; /* Ensure the caption is above the overlay */
        color: #B0C364;
        font-size: 20px;
        font-weight: 500;
    }

    .card-container {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
        padding: 30px 60px;
        position: relative;
        z-index: 2;
    }

    .card-container a {
        text-decoration: none;
        color: inherit;
        display: block;
    }

    .card {
        background-color: #fff;
        border: 2px solid #B0C364;
        border-radius: 18px;
        padding: 30px 20px;
        text-align: center;
        color: #B0C364;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.07);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .card:hover {
        background-color: #B0C364;
        color: #fff;
    }

    .card i {
        font-size: 32px;
        border: 2px solid #B0C364;
        border-radius: 50%;
        padding: 12px;
        margin-bottom: 12px;
        color: #B0C364;
        transition: all 0.3s ease;
    }

    .card:hover i {
        background-color: #fff;
        color: #B0C364;
        border-color: #fff;
    }

    .card p {
        font-size: 16px;
        font-weight: 500;
    }
</style>
</head>
<body>

    <div class="sidebar">
        <img src="<?php echo htmlspecialchars($profileImage); ?>" 
        alt="Profile" class="profile-pic"
        onerror="this.src='https://via.placeholder.com/100'">

        <div class="welcome">Welcome,<br><?php echo htmlspecialchars($user['username']); ?></div>
        <a href="index.php"><i class="fas fa-home"></i>Home</a>
        <a href="find_chefs.php"><i class="fas fa-user-friends"></i>Find Chefs</a>
        <a href="edit_profile.php"><i class="fas fa-user-edit"></i>Edit Profile</a>
        <a href="user_logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
    </div>

    <div class="main">
        <div class="top-panel">
            <h2>User Dashboard</h2>
        </div>
        
        <div class="caption">
            Discover, Create, and Share Your Culinary Journey!
        </div>

        <div class="card-container">
            <a href="upload_recipe.php" class="card">
                <i class="fas fa-upload"></i>
                <p>Upload Recipe</p>
            </a>
            <a href="bookmarked_recipes.php" class="card">
                <i class="fas fa-bookmark"></i>
                <p>Bookmarked Recipes</p>
            </a>
            <a href="my_recipes.php" class="card">
                <i class="fas fa-utensils"></i>
                <p>My Recipes</p>
            </a>
            <a href="liked_recipes.php" class="card">
                <i class="fas fa-heart"></i>
                <p>Liked Recipes</p>
            </a>
            <a href="recipe_analytics.php" class="card">
                <i class="fas fa-chart-line"></i>
                <p>Recipe Analytics</p>
            </a>
            <a href="my_comments.php" class="card">
                <i class="fas fa-comment-dots"></i>
                <p>My Comments</p>
            </a>
        </div>
    </div>

</body>
</html>