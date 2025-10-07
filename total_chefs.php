<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle search
$search_query = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
    $stmt = $conn->prepare("SELECT u.id, u.username, u.email, u.profile_pic, COUNT(r.id) AS recipe_count
                            FROM users u
                            LEFT JOIN recipes r ON u.id = r.user_id AND r.status = 'approved'
                            WHERE u.username LIKE ? OR u.email LIKE ?
                            GROUP BY u.id, u.username, u.email, u.profile_pic
                            ORDER BY recipe_count DESC");
    $search_term = "%" . $search_query . "%";
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT u.id, u.username, u.email, u.profile_pic, COUNT(r.id) AS recipe_count
            FROM users u
            LEFT JOIN recipes r ON u.id = r.user_id AND r.status = 'approved'
            GROUP BY u.id, u.username, u.email, u.profile_pic
            ORDER BY recipe_count DESC";
    $result = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Total Chefs</title>
    <style>
        :root {
            --theme-color-primary: #B0C364;
            --theme-color-secondary: #b0c364;
            --background-body: #FFFFFF;
        }

        body {
            font-family: 'Poppins', sans-serif;
            padding: 20px;
            background: var(--background-body);
        }

        .header-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--theme-color-primary);
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        h2 {
            color: var(--theme-color-secondary);
            font-size: 2.2em;
            margin: 0;
        }

        .top-controls {
            display: flex;
            gap: 10px;
        }

        .btn {
            background-color: var(--theme-color-primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn:hover {
            background-color: var(--theme-color-secondary);
        }

        .search-container {
            display: none;
            margin-top: 10px;
            text-align: right;
        }

        .search-container input[type="text"] {
            padding: 8px 12px;
            border: 2px solid var(--theme-color-primary);
            border-radius: 6px;
            outline: none;
            width: 250px;
        }

        .container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            border: 2px solid var(--theme-color-primary);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border-color: var(--theme-color-secondary);
        }

        .profile-pic {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid var(--theme-color-secondary);
        }

        .username { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 5px; }
        .email { font-size: 14px; color: #666; margin-bottom: 10px; }
        .recipes { font-size: 14px; font-weight: bold; color: var(--theme-color-secondary); }
    </style>
</head>
<body>

    <div class="header-line">
        <h2>Total Chefs</h2>
        <div class="top-controls">
            <button class="btn" onclick="toggleSearch()">Search</button>
            <button class="btn" onclick="window.location.href='admin_dashboard.php'">Dashboard</button>
        </div>
    </div>

    <div class="search-container" id="searchContainer">
        <form method="GET" action="">
            <input type="text" name="search" placeholder="Search by username or email..." value="<?php echo htmlspecialchars($search_query); ?>">
        </form>
    </div>

    <div class="container">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="card" onclick="window.location.href='profile.php?id=<?php echo $row['id']; ?>'">
                    <?php 
                    $profileImage = "https://placehold.co/80x80/999999/ffffff?text=U";
                    if (!empty($row['profile_pic'])) { $profileImage = htmlspecialchars($row['profile_pic']); }
                    ?>
                    <img src="<?php echo $profileImage; ?>" alt="Profile" class="profile-pic"
                        onerror="this.onerror=null; this.src='https://placehold.co/80x80/999999/ffffff?text=U'">
                    <div class="username"><?php echo htmlspecialchars($row['username']); ?></div>
                    <div class="email"><?php echo htmlspecialchars($row['email']); ?></div>
                    <div class="recipes">Approved Recipes: <?php echo $row['recipe_count']; ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No chefs found.</p>
        <?php endif; ?>
    </div>

    <script>
        function toggleSearch() {
            const searchDiv = document.getElementById("searchContainer");
            searchDiv.style.display = searchDiv.style.display === "none" || searchDiv.style.display === "" ? "block" : "none";
        }
    </script>

</body>
</html>

<?php $conn->close(); ?>
