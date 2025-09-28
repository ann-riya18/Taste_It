<?php
session_start();
// Check if the admin is logged in, redirect if not
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin_login.html");
    exit();
}

// Database connection
// SECURITY NOTE: Please use prepared statements (PDO or mysqli) for production code!
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle approve/decline action
if (isset($_GET['action']) && isset($_GET['id'])) {
    // Sanitize input using intval() for the ID
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    // Use prepared statements for safer query execution
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE recipes SET status='approved' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    } elseif ($action === 'decline') {
        $stmt = $conn->prepare("UPDATE recipes SET status='declined' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    // Closing the connection here is good practice before redirect
    $conn->close();
    header("Location: pending_recipes.php");
    exit();
}

// Fetch pending recipes
$sql = "SELECT r.id, r.title, r.ingredients, r.steps, r.image_path, u.username 
        FROM recipes r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.status='pending'";
$result = $conn->query($sql);

// Check if $result is valid before fetching
if ($result === false) {
    die("Error executing query: " . $conn->error);
}

// Closing the connection after fetching results
// If result is empty, $result->num_rows will be 0, which is fine.
if ($conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pending Recipes</title>
    <style>
        /* Define Theme Colors */
        :root {
            --primary-color: #B0c364; /* Heading color */
            --accent-color: #b0c364; /* Circle image outline color */
            --card-outline-color: #5bb84dff; /* Professional, extended card outline */
            --background-body: #FFFFFF; /* Pure White Background */
            --text-dark: #333;
            --text-medium: #555;
            --font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
        }

        body {
            font-family: var(--font-family);
            padding: 30px;
            background-color: var(--background-body); /* Updated to pure white */
            color: var(--text-dark);
            line-height: 1.6;
        }

        h2 {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 30px;
            font-weight: 300;
            font-size:2.2em;
        }

        /* Recipe Card Styling */
        .recipe {
            display: flex;
            justify-content: space-between;
            gap: 30px;
            background: #FFFFFF; /* Ensures card background is pure white */
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 12px;
            border: 1px solid var(--card-outline-color); 
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            transition: box-shadow 0.3s ease;
        }

        .recipe:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .recipe-details {
            flex-grow: 1; 
        }

        .recipe-details h3 {
            color: var(--primary-color);
            margin-top: 0;
            font-size: 1.6em;
            margin-bottom: 10px;
        }

        .recipe-details p {
            margin-bottom: 8px;
            font-size: 0.95em;
            color: var(--text-medium);
        }

        .recipe-details strong {
            color: var(--text-dark);
        }

        /* Image Container on the Right - Increased Size */
        .image-container {
            width: 250px; /* Increased from 200px */
            height: 250px; /* Increased from 200px */
            flex-shrink: 0; 
            position: relative;
        }
        
        /* Circular Image Style */
        .recipe img {
            width: 100%;
            height: 100%;
            border-radius: 50%; 
            object-fit: cover;
            border: 4px solid var(--accent-color); 
            box-shadow: 0 0 0 8px rgba(176, 195, 100, 0.2); 
        }

        /* Action Buttons */
        .actions {
            margin-top: 20px;
        }
        .actions a {
            text-decoration: none;
            padding: 10px 22px;
            margin-right: 15px;
            border-radius: 25px; 
            font-weight: 600;
            transition: opacity 0.2s, transform 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .actions a:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Specific Button Colors */
        .approve { 
            background: #28a745; 
            color: #fff; 
        }
        .decline { 
            background: #dc3545; 
            color: #fff; 
        }

        .no-pending {
            font-size: 1.1em;
            color: var(--text-medium);
            padding: 20px;
            text-align: center;
            background: #fff;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<h2>Pending Recipes Review</h2>

<?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
        <div class="recipe">
            
            <div class="recipe-details">
                <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                <p><strong>By:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
                
                <p><strong>Ingredients:</strong><br><?php echo nl2br(htmlspecialchars($row['ingredients'])); ?></p>
                <p><strong>Steps:</strong><br><?php echo nl2br(htmlspecialchars($row['steps'])); ?></p>
                
                <div class="actions">
                    <a href="?action=approve&id=<?php echo htmlspecialchars($row['id']); ?>" class="approve">Approve</a>
                    <a href="?action=decline&id=<?php echo htmlspecialchars($row['id']); ?>" class="decline">Decline</a>
                </div>
            </div>

            <?php if (!empty($row['image_path'])): ?>
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="Recipe Image">
                </div>
            <?php endif; ?>

        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p class="no-pending">No pending recipes at the moment. Time for a coffee break! ☕</p>
<?php endif; ?>

</body>
</html>