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
    $category = $_POST['category'];
    $cuisine = $_POST['cuisine'];
    $course = $_POST['course'];
    $diet = $_POST['diet'];
    $quick_recipe = $_POST['quick_recipe'];

    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir);
        $image_path = $targetDir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $image_path);
    }

    $stmt = $conn->prepare("INSERT INTO recipes (user_id, title, description, ingredients, steps, category, cuisine, course, diet, quick_recipe, image_path) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssssss", $user_id, $title, $description, $ingredients, $steps, $category, $cuisine, $course, $diet, $quick_recipe, $image_path);

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
      max-width: 700px;
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
    textarea,
    select {
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

      <!-- Main Category -->
      <label>Main Category</label>
      <select name="category" id="category" required onchange="showSubOptions()">
        <option value="">-- Select Category --</option>
        <option value="Veg">Veg</option>
        <option value="Non-Veg">Non-Veg</option>
        <option value="Dessert">Dessert</option>
        <option value="Snacks">Snacks</option>
      </select>

      <!-- Hidden fields (appear after selecting Main Category) -->
      <div id="subOptions" style="display:none;">
        <label>Cuisine</label>
        <select name="cuisine" required>
          <option value="">-- Select Cuisine --</option>
          <option value="Indian">Indian</option>
          <option value="Italian">Italian</option>
          <option value="Chinese">Chinese</option>
          <option value="Mexican">Mexican</option>
          <option value="Continental">Continental</option>
        </select>

        <label>Course</label>
        <select name="course" required>
          <option value="">-- Select Course --</option>
          <option value="Breakfast">Breakfast</option>
          <option value="Lunch">Lunch</option>
          <option value="Dinner">Dinner</option>
          <option value="Snacks">Snacks</option>
          <option value="Desserts">Desserts</option>
          <option value="Drinks">Drinks</option>
        </select>

        <label>Diet Preference</label>
        <select name="diet" required>
          <option value="">-- Select Diet --</option>
          <option value="Gluten-Free">Gluten-Free</option>
          <option value="Lactose-Free">Lactose-Free</option>
          <option value="Sugar-Free">Sugar-Free</option>
          <option value="High-Protein">High-Protein</option>
          <option value="Low-Fat">Low-Fat</option>
          <option value="Low-Carb">Low-Carb</option>
        </select>

        <label>Quick Recipe</label>
        <select name="quick_recipe" required>
          <option value="">-- Select Time --</option>
          <option value="Under 15 minutes">Under 15 minutes</option>
          <option value="Under 30 minutes">Under 30 minutes</option>
        </select>
      </div>

      <!-- Upload Image always visible -->
      <label>Upload Image</label>
      <input type="file" name="image" accept="image/*" required>

      <button type="submit">Submit Recipe</button>
    </form>
  </div>

  <script>
    function showSubOptions() {
      let category = document.getElementById("category").value;
      let subOptions = document.getElementById("subOptions");

      if (category) {
        subOptions.style.display = "block";
      } else {
        subOptions.style.display = "none";
      }
    }
  </script>

</body>
</html>
