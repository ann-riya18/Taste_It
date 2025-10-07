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
    $stmt = $conn->prepare("SELECT id, username, email, profile_pic FROM users WHERE username LIKE ? OR email LIKE ?");
    $search_term = "%" . $search_query . "%";
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT id, username, email, profile_pic FROM users");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Total Users</title>
    <style>
        :root {
            --theme-color: #B0C364;
            --theme-light: #f3f7e8;
            --theme-dark-accent: #8e9e51;
            --background-body: #F9F9F9;
            --text-dark: #333;
            --font-family: 'Poppins', sans-serif;
            --row-radius: 8px;
        }

        body {
            font-family: var(--font-family);
            padding: 30px;
            background-color: var(--background-body);
            color: var(--text-dark);
        }

        .header-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--theme-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        h2 {
            color: var(--theme-color);
            font-size: 2.2em;
            font-weight: 700;
            margin: 0;
        }

        .top-controls {
            display: flex;
            gap: 10px;
        }

        .btn {
            background-color: var(--theme-color);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn:hover {
            background-color: var(--theme-dark-accent);
        }

        .search-container {
            display: none;
            margin-top: 10px;
            text-align: right;
        }

        .search-container input[type="text"] {
            padding: 8px 12px;
            border: 2px solid var(--theme-color);
            border-radius: 6px;
            outline: none;
            width: 250px;
            font-family: var(--font-family);
        }

        .user-table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        thead tr { box-shadow: 0 2px 10px rgba(0,0,0,0.1); }

        th {
            background: var(--theme-color);
            color: white;
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th:first-child { border-top-left-radius: var(--row-radius); }
        th:last-child { border-top-right-radius: var(--row-radius); }

        td {
            padding: 18px 20px;
            text-align: left;
            background: #fff;
            border: 2px solid #ddd;
            transition: all 0.2s ease;
        }

        tbody tr td:first-child { border-top-left-radius: var(--row-radius); border-bottom-left-radius: var(--row-radius); }
        tbody tr td:last-child { border-top-right-radius: var(--row-radius); border-bottom-right-radius: var(--row-radius); }

        tbody tr:hover td {
            background: var(--theme-light);
            border-color: var(--theme-dark-accent);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        img.profile-pic {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--theme-color);
            transition: transform 0.2s ease;
        }

        img.profile-pic:hover { transform: scale(1.1); }

        a.profile-link { text-decoration: none; }
    </style>
</head>
<body>

    <div class="header-line">
        <h2>Registered Users</h2>
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

    <div class="user-table-container">
        <table>
            <thead>
                <tr>
                    <th>Profile Picture</th>
                    <th>Username</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php 
                                $profileImage = "https://placehold.co/50x50/B0C364/ffffff?text=U";
                                if (!empty($row['profile_pic'])) { $profileImage = htmlspecialchars($row['profile_pic']); }
                                $userId = $row['id'];
                                ?>
                                <a href="profile.php?id=<?php echo $userId; ?>" class="profile-link">
                                    <img src="<?php echo $profileImage; ?>" alt="Profile Picture" class="profile-pic"
                                    onerror="this.onerror=null; this.src='https://placehold.co/50x50/B0C364/ffffff?text=U'">
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; background-color: #fcfcfc;">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        function toggleSearch() {
            const searchDiv = document.getElementById("searchContainer");
            searchDiv.style.display = searchDiv.style.display === "none" || searchDiv.style.display === "" ? "block" : "none";
        }
    </script>

</body>
</html>

<?php
$conn->close();
?>
