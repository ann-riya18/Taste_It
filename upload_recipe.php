<?php
session_start();
if (!isset($_SESSION['user_email'])) {
    header("Location: user_login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email = $_SESSION['user_email'];
$result = $conn->query("SELECT id FROM users WHERE email = '$email'");
$user = $result->fetch_assoc();
$user_id = $user['id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $ingredients = $_POST['ingredients'];
    $steps = $_POST['steps'];
    
    // Convert arrays to comma-separated strings
    $cuisine = isset($_POST['cuisine']) ? implode(',', $_POST['cuisine']) : '';
    $course = isset($_POST['course']) ? implode(',', $_POST['course']) : '';
    $diet = isset($_POST['diet']) ? implode(',', $_POST['diet']) : '';
    $quick_recipe = isset($_POST['quick_recipe']) ? implode(',', $_POST['quick_recipe']) : '';

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
        echo "<script>alert('Recipe uploaded successfully!'); window.location.href='user_dashboard.php';</script>";
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Upload Recipe | Taste It</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: url('img/bg28.jpg') no-repeat center center fixed;
      background-size: cover;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    
    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.45);
      z-index: -1;
    }
    
    .form-wrapper {
      width: 100%;
      max-width: 700px;
    }
    
    .form-header {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .form-header h2 {
      color: #B0C364;
      font-size: 28px;
      font-weight: 600;
    }
    
    .form-row {
      margin-bottom: 20px;
      border-bottom: 1px solid #B0C364;
      padding-bottom: 15px;
    }
    
    .form-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    
    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: #333;
    }
    
    input[type="text"],
    textarea {
      width: 100%;
      padding: 10px;
      border: none;
      background: transparent;
      font-family: 'Poppins', sans-serif;
      font-size: 15px;
    }
    
    textarea {
      resize: vertical;
      min-height: 70px;
    }
    
    .category-row {
      margin-bottom: 20px;
      border-bottom: 1px solid #B0C364;
      padding-bottom: 15px;
    }
    
    .category-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
      cursor: pointer;
    }
    
    .category-header h3 {
      color: #333;
      font-weight: 500;
    }
    
    .category-header i {
      color: #B0C364;
    }
    
    .category-options {
      display: none;
      flex-wrap: wrap;
      gap: 8px;
    }
    
    .category-row.active .category-options {
      display: flex;
    }
    
    .category-option {
      position: relative;
    }
    
    .category-option input[type="checkbox"] {
      position: absolute;
      opacity: 0;
    }
    
    .category-option label {
      display: inline-block;
      padding: 6px 12px;
      background: #f5f5f5;
      border-radius: 20px;
      cursor: pointer;
      font-size: 14px;
    }
    
    .category-option input[type="checkbox"]:checked + label {
      background: #B0C364;
      color: white;
    }
    
    .file-upload {
      position: relative;
      display: block;
    }
    
    .file-upload input[type="file"] {
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
    }
    
    .file-upload-label {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 15px;
      border: 2px dashed #B0C364;
      border-radius: 8px;
      cursor: pointer;
    }
    
    .file-upload-label i {
      font-size: 24px;
      color: #B0C364;
      margin-bottom: 8px;
    }
    
    .submit-btn {
      width: 100%;
      padding: 12px;
      background: #B0C364;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 500;
      cursor: pointer;
      margin-top: 15px;
    }
  </style>
</head>
<body>
  <div class="overlay"></div>
  
  <div class="form-wrapper">
    <div class="form-header">
      <h2><i class="fas fa-utensils"></i> Upload Recipe</h2>
    </div>
    
    <form action="upload_recipe.php" method="POST" enctype="multipart/form-data">
      <div class="form-row">
        <label>Title</label>
        <input type="text" name="title" placeholder="Recipe name" required>
      </div>

      <div class="form-row">
        <label>Description</label>
        <input type="text" name="description" placeholder="Short description" required>
      </div>

      <div class="form-row">
        <label>Ingredients</label>
        <textarea name="ingredients" placeholder="List ingredients" required></textarea>
      </div>

      <div class="form-row">
        <label>Steps</label>
        <textarea name="steps" placeholder="Cooking instructions" required></textarea>
      </div>

      <div class="category-row">
        <div class="category-header">
          <h3>Cuisine</h3>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div class="category-options">
          <div class="category-option">
            <input type="checkbox" name="cuisine[]" value="Indian" id="indian">
            <label for="indian">Indian</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="cuisine[]" value="American" id="american">
            <label for="american">American</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="cuisine[]" value="Chinese" id="chinese">
            <label for="chinese">Chinese</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="cuisine[]" value="Mexican" id="mexican">
            <label for="mexican">Mexican</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="cuisine[]" value="Asian" id="asian">
            <label for="asian">Asian</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="cuisine[]" value="Italian" id="italian">
            <label for="italian">Italian</label>
          </div>
        </div>
      </div>

      <div class="category-row">
        <div class="category-header">
          <h3>Course</h3>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div class="category-options">
          <div class="category-option">
            <input type="checkbox" name="course[]" value="Breakfast" id="breakfast">
            <label for="breakfast">Breakfast</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="course[]" value="Lunch" id="lunch">
            <label for="lunch">Lunch</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="course[]" value="Dinner" id="dinner">
            <label for="dinner">Dinner</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="course[]" value="Snacks" id="snacks">
            <label for="snacks">Snacks</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="course[]" value="Desserts" id="desserts">
            <label for="desserts">Desserts</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="course[]" value="Drinks" id="drinks">
            <label for="drinks">Drinks</label>
          </div>
        </div>
      </div>

      <div class="category-row">
        <div class="category-header">
          <h3>Diet</h3>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div class="category-options">
          <div class="category-option">
            <input type="checkbox" name="diet[]" value="Gluten-Free" id="gluten-free">
            <label for="gluten-free">Gluten-Free</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="diet[]" value="Lactose-Free" id="lactose-free">
            <label for="lactose-free">Lactose-Free</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="diet[]" value="Sugar-Free" id="sugar-free">
            <label for="sugar-free">Sugar-Free</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="diet[]" value="High-Protein" id="high-protein">
            <label for="high-protein">High-Protein</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="diet[]" value="Low-Fat" id="low-fat">
            <label for="low-fat">Low-Fat</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="diet[]" value="Low-Carb" id="low-carb">
            <label for="low-carb">Low-Carb</label>
          </div>
        </div>
      </div>

      <div class="category-row">
        <div class="category-header">
          <h3>Quick Recipe</h3>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div class="category-options">
          <div class="category-option">
            <input type="checkbox" name="quick_recipe[]" value="Under 15 mins" id="under15">
            <label for="under15">Under 15 mins</label>
          </div>
          <div class="category-option">
            <input type="checkbox" name="quick_recipe[]" value="Under 30 mins" id="under30">
            <label for="under30">Under 30 mins</label>
          </div>
        </div>
      </div>

      <div class="form-row">
        <label>Upload Image</label>
        <div class="file-upload">
          <input type="file" name="image" accept="image/*" required>
          <div class="file-upload-label">
            <i class="fas fa-cloud-upload-alt"></i>
            <p>Click to upload image</p>
          </div>
        </div>
      </div>

      <button type="submit" class="submit-btn">Submit Recipe</button>
    </form>
  </div>

  <script>
    document.querySelectorAll('.category-header').forEach(header => {
      header.addEventListener('click', function() {
        this.parentElement.classList.toggle('active');
      });
    });
  </script>
</body>
</html>