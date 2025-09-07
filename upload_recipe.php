<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_email'])) {
    header("Location: user_login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user ID
$email = $_SESSION['user_email'];
$result = $conn->query("SELECT id FROM users WHERE email = '$email'");
$user = $result->fetch_assoc();
$user_id = $user['id'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $ingredients = $_POST['ingredients'];
    $steps = $_POST['steps'];

    // Collect category selections (optional)
    $cuisine = $_POST['cuisine'] ?? '';
    $course  = $_POST['course'] ?? '';
    $diet    = $_POST['diet'] ?? '';
    $quick_recipe = $_POST['quick_recipe'] ?? '';

    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir);
        $image_path = $targetDir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $image_path);
    }

    $stmt = $conn->prepare("INSERT INTO recipes 
        (user_id, title, description, ingredients, steps, cuisine, course, diet, quick_recipe, image_path) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssssss", $user_id, $title, $description, $ingredients, $steps, 
                      $cuisine, $course, $diet, $quick_recipe, $image_path);

    if ($stmt->execute()) {
        echo "<script>alert('Recipe uploaded successfully! It will be reviewed by an admin.'); window.location.href='user_dashboard.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Upload Recipe | Taste It</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #fff8f0;
      padding: 40px;
    }

    .container {
      max-width: 750px;
      margin: auto;
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    h2 {
      text-align: center;
      color: #B0C364;
    }

    form {
      margin-top: 30px;
    }

    label {
      font-weight: 600;
      display: block;
      margin-top: 15px;
    }

    input[type="text"],
    textarea {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border-radius: 5px;
      border: 1px solid #ccc;
    }

    input[type="file"] {
      margin-top: 10px;
    }

    button {
      margin-top: 25px;
      padding: 12px 25px;
      background: #B0C364;
      color: white;
      border: none;
      font-weight: bold;
      font-size: 16px;
      border-radius: 5px;
      cursor: pointer;
      width: 100%;
    }

    button:hover {
      background: #98aa4f;
    }

    /* Category toggles */
    .category-block {
      margin-top: 20px;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 15px;
    }

    .category-title {
      cursor: pointer;
      font-weight: 600;
      padding: 10px;
      background: #f6f6f6;
      border-radius: 5px;
    }

    .subcategory {
      display: none;
      margin-top: 10px;
      padding-left: 15px;
    }
    .subcategory label {
      font-weight: normal;
      display: block;
      margin: 5px 0;
    }

    .category-block.active .subcategory {
      display: block;
    }
  </style>
</head>
<body>

  <div class="container">
    <h2>Upload Your Recipe</h2>
    <form action="upload_recipe.php" method="POST" enctype="multipart/form-data">
      <label>Title</label>
      <input type="text" name="title" required>

      <label>Description</label>
      <textarea name="description" rows="3" required></textarea>

      <label>Ingredients</label>
      <textarea name="ingredients" rows="4" required></textarea>

      <label>Steps</label>
      <textarea name="steps" rows="5" required></textarea>

      <!-- Main Categories -->
      <div class="category-block">
        <div class="category-title">Cuisine</div>
        <div class="subcategory">
          <label><input type="radio" name="cuisine" value="Indian"> Indian</label>
          <label><input type="radio" name="cuisine" value="American"> American</label>
          <label><input type="radio" name="cuisine" value="Chinese"> Chinese</label>
          <label><input type="radio" name="cuisine" value="Mexican"> Mexican</label>
          <label><input type="radio" name="cuisine" value="Asian"> Asian</label>
          <label><input type="radio" name="cuisine" value="Middle Eastern"> Middle Eastern</label>
          <label><input type="radio" name="cuisine" value="Continental"> Continental</label>
        </div>
      </div>

      <div class="category-block">
        <div class="category-title">Course</div>
        <div class="subcategory">
          <label><input type="radio" name="course" value="Breakfast"> Breakfast</label>
          <label><input type="radio" name="course" value="Lunch"> Lunch</label>
          <label><input type="radio" name="course" value="Dinner"> Dinner</label>
          <label><input type="radio" name="course" value="Snacks"> Snacks</label>
          <label><input type="radio" name="course" value="Desserts"> Desserts</label>
          <label><input type="radio" name="course" value="Drinks"> Drinks</label>
        </div>
      </div>

      <div class="category-block">
        <div class="category-title">Diet</div>
        <div class="subcategory">
          <label><input type="radio" name="diet" value="Gluten-Free"> Gluten-Free</label>
          <label><input type="radio" name="diet" value="Lactose-Free"> Lactose-Free</label>
          <label><input type="radio" name="diet" value="Sugar-Free"> Sugar-Free</label>
          <label><input type="radio" name="diet" value="High-Protein"> High-Protein</label>
          <label><input type="radio" name="diet" value="Low-Fat"> Low-Fat</label>
          <label><input type="radio" name="diet" value="Low-Carb"> Low-Carb</label>
        </div>
      </div>

      <label>Quick Recipe</label>
      <select name="quick_recipe">
        <option value="">-- Select Time --</option>
        <option value="Under 15 minutes">Under 15 minutes</option>
        <option value="Under 30 minutes">Under 30 minutes</option>
      </select>

      <label>Upload Image</label>
      <input type="file" name="image" accept="image/*" required>

      <button type="submit">Submit Recipe</button>
    </form>
  </div>

  <script>
    // Toggle subcategories
    document.querySelectorAll('.category-title').forEach(title => {
      title.addEventListener('click', function() {
        this.parentElement.classList.toggle('active');
      });
    });
  </script>
</body>
</html>
