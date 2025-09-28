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

// Fetch username, email, and profile picture
$result = $conn->query("SELECT username, email, profile_pic FROM users");

// Close connection
if ($conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Total Users</title>
    <style>
        /* Define Theme Colors */
        :root {
            --theme-color: #B0C364;
            --theme-light: #f3f7e8; /* Very light version for row separation */
            --theme-dark-accent: #8e9e51; /* Darker accent for border/hover */
            --background-body: #F9F9F9; /* Off-White background for better contrast with white rows */
            --text-dark: #333;
            --font-family: 'Inter', 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            --row-radius: 8px;
        }

        body {
            font-family: var(--font-family);
            padding: 30px;
            background-color: var(--background-body);
            color: var(--text-dark);
            -webkit-font-smoothing: antialiased;
        }

        /* Heading Style */
        h2 {
            color: var(--theme-color);
            font-size: 2.2em;
            font-weight: 700;
            border-bottom: 2px solid var(--theme-color);
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        /* Table Container and General Cell Style */
        .user-table-container {
            width: 100%;
            overflow-x: auto;
            border-radius: var(--row-radius);
            /* Removed primary shadow/border from container to make row shadows stand out */
        }
        
        table {
            width: 100%;
            border-collapse: separate; /* CRITICAL: Allows border-spacing */
            border-spacing: 0 10px; /* Increased vertical spacing for clear separation */
            background: transparent; /* Allows body background to show through gaps */
        }

        /* Header Row */
        thead tr {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
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
        /* Apply rounding only to header corners */
        th:first-child { border-top-left-radius: var(--row-radius); }
        th:last-child { border-top-right-radius: var(--row-radius); }

        /* Table Data Cells (The Row Card Style) */
        td {
            padding: 18px 20px;
            text-align: left;
            vertical-align: middle;
            background: #fff; /* White background for cell content */
            /* UPDATED: Increased thickness and slightly darker color for a clear outline */
            border: 2px solid #ddd; 
            box-shadow: 0 1px 5px rgba(0,0,0,0.05); /* Subtle cell shadow */
            transition: all 0.2s ease-in-out;
        }
        
        /* Remove inner vertical borders to make cells look like one solid row block */
        tbody tr td:not(:first-child) {
            border-left: none;
        }
        /* Apply border-radius to the first and last cells to round the row ends */
        tbody tr td:first-child {
            border-top-left-radius: var(--row-radius);
            border-bottom-left-radius: var(--row-radius);
        }
        tbody tr td:last-child {
            border-top-right-radius: var(--row-radius);
            border-bottom-right-radius: var(--row-radius);
        }

        /* Row Hover Effect - MUST target TD to change the background */
        tbody tr:hover td {
            background: var(--theme-light);
            /* Hover border color remains theme accent for emphasis */
            border-color: var(--theme-dark-accent);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Profile Picture Styling */
        img.profile-pic {
            width: 50px; 
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--theme-color);
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <h2>Registered Users</h2>
    
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
                                $profileImage = "https://placehold.co/50x50/B0C364/ffffff?text=U"; // Fallback placeholder
                                
                                // Simple logic to determine the image path
                                if (!empty($row['profile_pic'])) {
                                    // SECURITY: Use real path, but ensure input is sanitized/validated
                                    $profileImage = htmlspecialchars($row['profile_pic']);
                                }
                                ?>
                                <img src="<?php echo $profileImage; ?>" alt="Profile Picture" class="profile-pic"
                                    onerror="this.onerror=null; this.src='https://placehold.co/50x50/B0C364/ffffff?text=U'">
                            </td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; border-radius: var(--row-radius); background-color: #fcfcfc;">No users registered yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
