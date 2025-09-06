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
    $main_category = $_POST['main_category'];
    $sub_category = $_POST['sub_category'];

    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir);
        $image_path = $targetDir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $image_path);
    }

    $stmt = $conn->prepare("INSERT INTO recipes (user_id, title, description, ingredients, steps, category, image_path) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $user_id, $title, $description, $ingredients, $steps, $sub_category, $image_path);

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
      <select name="main_category" id="main_category" required onchange="showSubOptions()">
        <option value="">-- Select Category --</option>
        <option value="Cuisine">By Cuisine</option>
        <option value="Course">By Course</option>
        <option value="Diet">By Diet Preference</option>
        <option value="Quick">By Quick Recipe</option>
      </select>

      <!-- Sub Category (dynamic) -->
      <div id="subOptions" style="display:none;">
        <label id="subLabel"></label>
        <select name="sub_category" id="sub_category" required></select>
      </div>

      <!-- Upload Image always visible -->
      <label>Upload Image</label>
      <input type="file" name="image" accept="image/*" required>

      <button type="submit">Submit Recipe</button>
    </form>
  </div>

  <script>
    function showSubOptions() {
      let mainCat = document.getElementById("main_category").value;
      let subOptions = document.getElementById("subOptions");
      let subLabel = document.getElementById("subLabel");
      let subSelect = document.getElementById("sub_category");

      subSelect.innerHTML = ""; // clear old options

      if (!mainCat) {
        subOptions.style.display = "none";
        return;
      }

      let options = [];
      if (mainCat === "Cuisine") {
        subLabel.innerText = "Cuisine";
        options = ["Indian", "Italian", "Chinese", "Mexican", "Continental"];
      } else if (mainCat === "Course") {
        subLabel.innerText = "Course";
        options = ["Breakfast", "Lunch", "Dinner", "Snacks", "Desserts", "Drinks"];
      } else if (mainCat === "Diet") {
        subLabel.innerText = "Diet Preference";
        options = ["Gluten-Free", "Lactose-Free", "Sugar-Free", "High-Protein", "Low-Fat", "Low-Carb"];
      } else if (mainCat === "Quick") {
        subLabel.innerText = "Quick Recipe";
        options = ["Under 15 minutes", "Under 30 minutes"];
      }

      // Add placeholder
      let placeholder = document.createElement("option");
      placeholder.value = "";
      placeholder.text = "-- Select " + subLabel.innerText + " --";
      subSelect.appendChild(placeholder);

      // Add real options
      options.forEach(function(opt) {
        let option = document.createElement("option");
        option.value = opt;
        option.text = opt;
        subSelect.appendChild(option);
      });

      subOptions.style.display = "block";
    }
  </script>

</body>
</html>
