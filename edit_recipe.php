<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// DB connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if recipe ID is passed
if (!isset($_GET['id'])) {
    die("Recipe ID is missing.");
}

$recipe_id = intval($_GET['id']);
$user_id = $_SESSION['user_id']; // Ensure user only edits their recipe

// Fetch recipe details
$sql = "SELECT * FROM recipes WHERE id = $recipe_id AND user_id = $user_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Recipe not found or you don't have permission to edit it.");
}

$recipe = $result->fetch_assoc();

// Update recipe when form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $category = $conn->real_escape_string($_POST['category']);
    $ingredients = $conn->real_escape_string($_POST['ingredients']);
    $steps = $conn->real_escape_string($_POST['steps']);

    $update_sql = "UPDATE recipes 
                   SET title = '$title', 
                       description = '$description', 
                       category = '$category',
                       ingredients = '$ingredients',
                       steps = '$steps'
                   WHERE id = $recipe_id AND user_id = $user_id";

    if ($conn->query($update_sql) === TRUE) {
        echo "<script>alert('Recipe updated successfully!'); window.location='userdashboard.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Recipe</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .form-container {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            width: 500px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        label {
            font-weight: bold;
            margin-top: 10px;
            display: block;
        }
        input[type="text"], textarea, select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        button {
            background-color: #B0C364;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            margin-top: 15px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        button:hover {
            background-color: #9AB14C;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Edit Recipe</h2>
        <form method="POST">
            <label>Title</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($recipe['title']); ?>" required>

            <label>Description</label>
            <textarea name="description" required><?php echo htmlspecialchars($recipe['description']); ?></textarea>

            <label>Category</label>
            <select name="category" required>
                <option value="Veg" <?php if($recipe['category']=="Veg") echo "selected"; ?>>Veg</option>
                <option value="Non-Veg" <?php if($recipe['category']=="Non-Veg") echo "selected"; ?>>Non-Veg</option>
                <option value="Dessert" <?php if($recipe['category']=="Dessert") echo "selected"; ?>>Dessert</option>
                <option value="Snack" <?php if($recipe['category']=="Snack") echo "selected"; ?>>Snack</option>
                <option value="Drink" <?php if($recipe['category']=="Drink") echo "selected"; ?>>Drink</option>
            </select>

            <label>Ingredients</label>
            <textarea name="ingredients" required><?php echo htmlspecialchars($recipe['ingredients']); ?></textarea>

            <label>Steps</label>
            <textarea name="steps" required><?php echo htmlspecialchars($recipe['steps']); ?></textarea>

            <button type="submit">Update Recipe</button>
        </form>
    </div>
</body>
</html>
