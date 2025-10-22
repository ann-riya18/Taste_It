<?php
session_start();
// Disable caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['admin_email'])) {
    header("Location:login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->query("DELETE FROM recipes WHERE status='rejected' AND TIMESTAMPDIFF(DAY, updated_at, NOW()) >= 1");

// Stats
$userCount = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$chefCount = $conn->query("SELECT COUNT(DISTINCT user_id) AS chefs FROM recipes WHERE status='approved'")->fetch_assoc()['chefs'];
$approvedRecipes = $conn->query("SELECT COUNT(*) AS total FROM recipes WHERE status='approved'")->fetch_assoc()['total'];
$pendingRecipes = $conn->query("SELECT COUNT(*) AS total FROM recipes WHERE status='pending'")->fetch_assoc()['total'];
$declinedRecipes = $conn->query("SELECT COUNT(*) AS total FROM recipes WHERE status='rejected'")->fetch_assoc()['total'];

// Most liked from approved
$mostLiked = $conn->query("SELECT title, likes FROM recipes WHERE status='approved' ORDER BY likes DESC LIMIT 1")->fetch_assoc();
$mostLikedTitle = $mostLiked['title'] ?? "N/A";
$mostLikedLikes = $mostLiked['likes'] ?? 0;

// Recent approved activity - CHANGED LIMIT TO 7 AND ADDED RECIPE ID (r.id)
$recent = $conn->query("
    SELECT r.id, r.title, u.username, r.created_at 
    FROM recipes r JOIN users u ON r.user_id = u.id 
    WHERE r.status = 'approved'
    ORDER BY r.created_at DESC LIMIT 7
");

// Top contributors - CHANGED LIMIT TO 5
$topUsersQuery = $conn->query("
    SELECT 
        u.id, 
        u.username, 
        u.profile_pic, 
        COUNT(r.id) AS count
    FROM users u 
    JOIN recipes r ON u.id = r.user_id 
    WHERE r.status = 'approved'
    GROUP BY u.id 
    ORDER BY count DESC 
    LIMIT 5
");
$topUsers = $topUsersQuery->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> 
    <style>
    /* Global Reset and Font */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    /* Define Color Variables - Strict Monochromatic Theme */
    :root {
        --theme-color: #B0C364; /* Primary Theme Color (Used for text, borders, accents) */
        --text-color-dark: #333; /* Standard dark text */
        --bg-white-transparent: rgba(255, 255, 255, 0.55); 
        --border-subtle: 1px; /* Subtle border thickness */
        --border-color-light: #ddd; /* Light gray outline color */
        --card-shadow: 0 4px 12px rgba(0,0,0,0.08);
        --hover-bg: #e6edd1; /* A very light shade of the theme color for hover effects */
        /* NEW: Light background for activity cards and contributor links */
        --activity-bg: #f5f8eb; 
    }

    body {
        font-family: 'Poppins', sans-serif;
        display: flex;
        min-height: 100vh;
        background: url('img/bg20.jpg') no-repeat center center fixed; 
        background-size: cover;
        position: relative; 
    }

    body::before {
        content: '';
        position: fixed; 
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--bg-white-transparent);
        z-index: -1; 
    }
    
    /* 1. Sidebar - FIXED POSITION UPDATE HERE */
    .sidebar {
        width: 260px;
        background-color: white; 
        padding: 0; 
        /* UPDATED: Fixed position to prevent scrolling */
        position: fixed; 
        top: 0;
        left: 0;
        height: 100vh; /* Make it take up the full viewport height */
        /* Ensure content inside sidebar can scroll if it overflows */
        overflow-y: auto; 
        
        border-right: var(--border-subtle) solid var(--theme-color); 
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
        flex-shrink: 0; 
        z-index: 100; /* Ensure it stays above other content */
    }
    
    .sidebar h2 {
        font-size: 22px;
        color: var(--theme-color); 
        background-color: white; 
        padding: 20px 20px;
        margin-bottom: 0; 
        text-align: center;
        border-bottom: var(--border-subtle) solid var(--theme-color); 
        font-weight: 700;
        /* Sticky header for fixed sidebar */
        position: sticky;
        top: 0;
        z-index: 10; 
    }

    .sidebar-nav {
        padding: 40px 20px; 
    }
    
    /* Sidebar Links (Buttons) */
    .sidebar a {
        display: flex;
        align-items: center;
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        background-color: transparent; 
        color: var(--text-color-dark); 
        font-weight: 500;
        padding: 14px 10px;
        margin-bottom: 10px;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.3s ease;
        border: var(--border-subtle) solid var(--theme-color); 
    }
    
    .sidebar a i {
        margin-right: 8px;
        font-size: 16px;
        color: var(--theme-color); 
        flex-shrink: 0; 
    }

    .sidebar a:hover {
        background-color: var(--hover-bg); 
        color: var(--text-color-dark); 
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        border-color: var(--theme-color); 
        transform: scale(1.01);
    }

    .sidebar a:hover i {
         color: var(--theme-color); 
    }
    
    /* 2. Main Content Area - MARGIN ADJUSTMENT IS CRITICAL */
    .main-content {
        flex: 1;
        padding: 40px;
        /* CRITICAL FIX: Add margin-left to offset the fixed sidebar width (260px) */
        margin-left: 260px; 
    }
    
    /* 3. Header - BORDER OUTLINE WITH THEME COLOR */
    .header {
        background: white; 
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: var(--card-shadow);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        color: var(--text-color-dark);
        border: var(--border-subtle) solid var(--theme-color); 
    }
    
    .header h2 {
        font-size: 26px;
        font-weight: 600;
        color: var(--theme-color); 
    }
    
    .header p {
        color: #555;
        font-weight: 500;
        font-size: 14px;
    }

    /* 4. Minimalist Summary Cards - BORDER OUTLINE WITH THEME COLOR */
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(3, 1fr); 
        gap: 25px;
        margin-bottom: 40px;
    }
    
    .card {
        background: white;
        text-align: center;
        padding: 25px;
        border-radius: 8px;
        box-shadow: var(--card-shadow); 
        min-height: 160px; 
        transition: all 0.2s ease; 
        border: var(--border-subtle) solid var(--theme-color); 
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    .card:hover {
        transform: translateY(-2px); 
        box-shadow: 0 6px 20px rgba(0,0,0,0.15); 
        background-color: var(--hover-bg); 
        border-color: var(--theme-color); 
    }

    .card h3 {
        color: var(--theme-color); 
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .card p {
        margin-top: 10px; 
        font-size: 36px;
        color: var(--theme-color); 
        font-weight: 700;
    }


    /* 5. Professional Recent Activity & Top Contributor Sections - BORDER OUTLINE WITH THEME COLOR */
    .section {
        background: white; 
        padding: 25px;
        border-radius: 8px;
        margin-bottom: 30px;
        box-shadow: var(--card-shadow);
        border: var(--border-subtle) solid var(--theme-color); 
    }
    
    .section h3 {
        color: var(--theme-color); 
        font-size: 20px;
        margin-bottom: 15px;
        border-bottom: 2px solid var(--theme-color); 
        padding-bottom: 8px;
        font-weight: 700;
    }
    
    /* Icons in Section Titles */
    .section h3 i {
         color: var(--theme-color); 
    }

    /* Recent Activity Styling */
    .activity-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    /* Link wrapper for activity card to enable redirection on click */
    .activity-link {
        text-decoration: none; /* Remove underline from link */
        color: inherit;
    }

    /* Activity Card Background Change */
    .activity-card {
        display: flex;
        align-items: center;
        background: var(--activity-bg); /* Use new lighter background color */
        border-radius: 6px;
        padding: 12px 15px;
        transition: all 0.2s ease;
        border-left: 4px solid var(--theme-color); 
    }
    
    .activity-link:hover .activity-card {
        background: var(--hover-bg); 
        transform: translateX(3px);
    }
    
    .activity-details {
        font-size: 14px;
        color: #555;
        line-height: 1.4;
    }
    
    .activity-details strong {
        color: var(--text-color-dark);
        font-weight: 600;
    }

    .activity-details em {
        color: var(--theme-color); 
        font-style: normal;
        font-weight: 600;
    }
    
    .activity-details .time {
        font-size: 11px;
        color: #999;
        display: block;
        margin-top: 2px;
    }
    
    /* Top Contributors List Styling - Activity Card Background Change */
    .section ul {
        list-style: none;
        padding-left: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .section ul li {
        margin-bottom: 0;
    }

    /* Contributor Link Background Change */
    .contributor-link { 
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--activity-bg); /* Use new lighter background color */
        border-radius: 6px;
        padding: 12px 15px;
        transition: all 0.2s ease;
        border-left: 4px solid var(--theme-color); 
        text-decoration: none;
        color: inherit;
        border-bottom: none;
    }
    
    .contributor-link:hover {
        background-color: var(--hover-bg);
        transform: translateX(3px);
    }

    .contributor-details {
        display: flex;
        align-items: center;
        flex-grow: 1;
    }

    .contributor-link strong {
        color: var(--text-color-dark);
        font-weight: 600; 
        font-size: 15px;
    }

    .contributor-image {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 15px;
        margin-left: 0;
        border: 2px solid var(--theme-color);
        box-shadow: none; 
        flex-shrink: 0;
        order: -1;
    }

    .contributor-recipe-count {
        font-size: 13px;
        color: var(--theme-color);
        font-weight: 600;
        margin-left: auto;
        background-color: transparent; 
        padding: 0;
        border-radius: 0;
    }
</style>
</head>
<body>

    <div class="sidebar">
        <h2>TasteIt Admin</h2>
        
        <div class="sidebar-nav">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="graph_insights.php"><i class="fas fa-chart-line"></i> Graphical Insights</a>
            <a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h2>Admin Dashboard</h2>
            <p><i class="fas fa-user-shield"></i> Logged in as: <strong><?php echo htmlspecialchars($_SESSION['admin_email']); ?></strong></p>
        </div>

        <div class="summary-cards">
            <a href="most_liked_recipe.php" class="card">
                <h3>Most Liked Recipe</h3>
                <p><?php echo htmlspecialchars($mostLikedTitle) . "<br><span style='font-size: 18px; font-weight: 500; color: #777;'>($mostLikedLikes likes)</span>"; ?></p>
            </a>
            <a href="approved_recipes.php" class="card">
                <h3>Approved Recipes</h3>
                <p><?php echo htmlspecialchars($approvedRecipes); ?></p>
            </a>
            <a href="pending_recipes.php" class="card">
                <h3>Pending Recipes</h3>
                <p><?php echo htmlspecialchars($pendingRecipes); ?></p>
            </a>
            <a href="declined_recipes.php" class="card">
                <h3>Declined Recipes</h3>
                <p><?php echo htmlspecialchars($declinedRecipes); ?></p>
            </a>
            <a href="total_users.php" class="card">
                <h3>Total Users</h3>
                <p><?php echo htmlspecialchars($userCount); ?></p>
            </a>
            <a href="total_chefs.php" class="card">
                <h3>Total Chefs</h3>
                <p><?php echo htmlspecialchars($chefCount); ?></p>
            </a>
        </div>

        <div style="display: flex; gap: 30px;">
            <div class="section" style="flex: 2;" id="activity-section">
                <h3><i class="fas fa-bell"></i> Recent Recipes</h3>
                <div class="activity-container">
                    <?php if ($recent->num_rows > 0): ?>
                        <?php while ($row = $recent->fetch_assoc()): ?>
                            <a href="view_recipe.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="activity-link">
                                <div class="activity-card">
                                    <div class="activity-details">
                                        <strong><?php echo htmlspecialchars($row['username']); ?></strong> 
                                        uploaded <em><?php echo htmlspecialchars($row['title']); ?></em> 
                                        <span class="time"><i class="far fa-clock"></i> <?php echo date("M d, Y H:i", strtotime($row['created_at'])); ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No recent activity.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section" style="flex: 1;">
                <h3><i class="fas fa-medal"></i> Top Contributors</h3>
                <ul>
                    <?php foreach ($topUsers as $row): ?>
                        <li>
                            <a href="profile.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="contributor-link">
                                <?php 
                                    $imagePath = !empty($row['profile_pic']) ? htmlspecialchars($row['profile_pic']) : 'path/to/default/chef_avatar.png'; 
                                ?>
                                <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($row['username']); ?>'s profile picture" class="contributor-image">
                                
                                <strong><?php echo htmlspecialchars($row['username']); ?></strong> 
                                
                                <span class="contributor-recipe-count"><?php echo htmlspecialchars($row['count']); ?> recipes</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($topUsers)): ?>
                        <li>No top contributors yet.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    


</body>
</html>