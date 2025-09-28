<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

// Database connection
// SECURITY NOTE: Please use prepared statements (PDO or mysqli) for production code!
// NOTE: For a real application, consider using environment variables for credentials.
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user data, counting only 'approved' recipes
$sql = "SELECT u.id, u.username, u.email, u.profile_pic, COUNT(r.id) AS recipe_count
        FROM users u
        LEFT JOIN recipes r ON u.id = r.user_id AND r.status = 'approved'
        GROUP BY u.id, u.username, u.email, u.profile_pic
        ORDER BY recipe_count DESC";

$result = $conn->query($sql);

// Close connection
if ($conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Total Chefs</title>
    <style>
        :root {
            --theme-color-primary: #B0C364; /* Requested primary color for header/outline */
            --theme-color-secondary: #b0c364; /* Recipe count/accent color */
            --background-body: #FFFFFF; /* Change: White background for main area */
            --header-height: 70px; 
        }

        /* 2. BODY ADJUSTMENT FOR FIXED PANEL & BACKGROUND */
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            /* Adjust padding to accommodate fixed header + buffer */
            padding-top: calc(var(--header-height) + 30px); 
            background: var(--background-body); /* Set body background to white */
        }
        
        /* 1. TOP PANEL STYLING: Fixed and holds the title */
        .top-panel {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: var(--header-height);
            background-color: var(--background-body); /* Changed to White BG */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Reduced shadow */
            border-bottom: 3px solid var(--theme-color-primary); /* Added B0C364 outline (bottom border) */
            z-index: 1000;
            display: flex; /* Align title vertically */
            align-items: center;
            padding: 0 30px;
        }

        /* Title inside the top panel */
        h2 {
            color: var(--theme-color-secondary); /* Changed to dark theme color for visibility on white BG */
            font-size: 2.2em;
            font-weight: bold;
            margin: 0; 
        }
        
        .container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        /* 3. CARD OUTLINE ADDITION: B0C364 outline */
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            /* Changed border color to the primary theme color */
            border: 2px solid var(--theme-color-primary); 
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); 
            /* Optional: Change border color slightly on hover for visual feedback */
            border-color: var(--theme-color-secondary); 
        }
        
        .profile-pic {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            /* Changed profile picture border to secondary color to stand out from the card border */
            border: 3px solid var(--theme-color-secondary);
        }
        
        .username {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .email {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .recipes {
            font-size: 14px;
            font-weight: bold;
            color: var(--theme-color-secondary);
        }
    </style>
</head>
<body>
    <!-- Top Panel Element (Now contains the title) -->
    <div class="top-panel">
        <h2>Total Chefs</h2>
    </div>

    <div class="container">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="card" onclick="window.location.href='profile.php?id=<?php echo $row['id']; ?>'">
                    <?php 
                    $profileImage = "https://placehold.co/80x80/999999/ffffff?text=U"; // Fallback placeholder
                    if (!empty($row['profile_pic'])) {
                        $profileImage = htmlspecialchars($row['profile_pic']);
                    }
                    ?>
                    <img src="<?php echo $profileImage; ?>" alt="Profile" class="profile-pic"
                        onerror="this.onerror=null; this.src='https://placehold.co/80x80/999999/ffffff?text=U'">
                    <div class="username"><?php echo htmlspecialchars($row['username']); ?></div>
                    <div class="email"><?php echo htmlspecialchars($row['email']); ?></div>
                    <div class="recipes">Approved Recipes: <?php echo $row['recipe_count']; ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No chefs found yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>