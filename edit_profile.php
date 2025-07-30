<?php
session_start();
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_email'])) {
    header("Location: user_login.html");
    exit();
}

$email = $_SESSION['user_email'];

// Fetch user info
$query = $conn->prepare("SELECT username, profile_pic FROM users WHERE email = ?");
$query->bind_param("s", $email);
$query->execute();
$query->bind_result($username, $profile_pic);
$query->fetch();
$query->close();

$success = "";
$error = "";

// Handle update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_username = $_POST['username'] ?? $username;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $profile_filename = $profile_pic;

    // Handle profile picture
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "uploads/";
        $fileName = basename($_FILES['profile_pic']['name']);
        $targetFile = $targetDir . uniqid() . "_" . $fileName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imageFileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetFile)) {
                $profile_filename = $targetFile;
            } else {
                $error = "Error uploading file.";
            }
        } else {
            $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }

    // Update password only if it's entered
    if (!empty($new_password)) {
        if ($new_password === $confirm_password) {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, password=?, profile_pic=? WHERE email=?");
            $stmt->bind_param("ssss", $new_username, $hashedPassword, $profile_filename, $email);
        } else {
            $error = "Passwords do not match!";
        }
    } else {
        // Update username and profile picture only
        $stmt = $conn->prepare("UPDATE users SET username=?, profile_pic=? WHERE email=?");
        $stmt->bind_param("sss", $new_username, $profile_filename, $email);
    }

    if (empty($error) && $stmt->execute()) {
        $success = "Profile updated successfully!";
        $username = $new_username;
        $profile_pic = $profile_filename;
    } elseif (!$error) {
        $error = "Update failed: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Profile</title>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #fff8f0;
      margin: 0;
      padding: 40px;
    }
    .container {
      max-width: 600px;
      background: #fff;
      padding: 30px;
      margin: auto;
      border-radius: 12px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #B0C364;
    }
    label {
      font-weight: bold;
      display: block;
      margin: 12px 0 6px;
    }
    input[type="text"],
    input[type="password"],
    input[type="file"] {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    button {
      margin-top: 20px;
      padding: 10px 20px;
      background-color: #B0C364;
      border: none;
      color: white;
      font-weight: 600;
      cursor: pointer;
      border-radius: 6px;
    }
    button:hover {
      background-color: #9aae54;
    }
    .profile-pic {
      width: 100px;
      border-radius: 50%;
      margin-top: 10px;
    }
    .msg {
      margin-top: 15px;
      color: green;
      font-weight: bold;
    }
    .error {
      margin-top: 15px;
      color: red;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Edit Profile</h2>

    <?php if ($success) echo "<p class='msg'>$success</p>"; ?>
    <?php if ($error) echo "<p class='error'>$error</p>"; ?>

    <form method="POST" enctype="multipart/form-data">
      <label>Username</label>
      <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>

      <label>New Password</label>
      <input type="password" name="new_password" placeholder="Leave blank to keep current password">

      <label>Confirm Password</label>
      <input type="password" name="confirm_password">

      <label>Profile Picture</label>
      <input type="file" name="profile_pic">
      <br>
      <img src="<?php echo $profile_pic ? $profile_pic : 'uploads/default.jpg'; ?>" alt="Current Profile" class="profile-pic">

      <button type="submit">Update Profile</button>
    </form>
  </div>
</body>
</html>
