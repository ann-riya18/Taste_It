<?php
session_start();

// Prevent caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_email'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "tasteit";

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admins WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin_email'] = $row['email'];
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error_message = "Incorrect password.";
        }
    } else {
        $error_message = "Admin not found.";
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Login | Taste It</title>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>

<style>
body {
    font-family: 'Poppins', sans-serif;
    background: url('img/bg4.jpg') no-repeat center center/cover;
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0;
}
.form-box {
    background: rgba(255, 255, 255, 0.25);
    padding: 40px 30px;
    border-radius: 20px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(6px);
}
.form-box h2 {
    font-weight: 700;
    margin-bottom: 30px;
    text-align: center;
    color:#b0c364;
}
.form-group { position: relative; margin-bottom: 20px; }
.form-group label { color: #b0c364; font-weight: 600; margin-bottom: 5px; display: block; }
.form-control { border: 1px solid #ccc; padding: 10px 40px 10px 12px; border-radius: 8px; font-size: 0.95rem; }
.form-group i { position: absolute; right: 15px; top: 38px; cursor: pointer; color: #666; }
.form-box button[type="submit"] { background-color: #b0c364; color: white; font-weight: 600; border: none; padding: 10px; width: 100%; border-radius: 0px; transition: background-color 0.3s ease; }
.form-box button[type="submit"]:hover { background-color: #9eb254; color: white; }
.form-box a { display: block; text-align: center; margin-top: 15px; font-size: 0.9rem; color: #333; text-decoration: none; transition: color 0.3s ease; }
.form-box a:hover { color: #b0c364; text-decoration: none; }
.error-message { color: red; font-weight: 600; text-align: center; margin-bottom: 15px; }
</style>
</head>
<body>

<div class="form-box">
    <h2>Admin Login</h2>
    <?php if (!empty($error_message)): ?>
      <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <form action="" method="POST">
      <div class="form-group">
        <label for="email">Admin Email</label>
        <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
        <i class="fas fa-eye" id="togglePassword"></i>
      </div>
      <button type="submit">Login</button>
      <a href="login.html">← Back to Login Selection</a>
    </form>
</div>

<script>
const togglePassword = document.getElementById('togglePassword');
const passwordField = document.getElementById('password');
togglePassword.addEventListener('click', () => {
    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordField.setAttribute('type', type);
});

// Prevent back to cached admin_dashboard after logout
window.addEventListener("pageshow", function(event) {
    if (event.persisted) {
        window.location.replace("index.php"); // homepage
    }
});
</script>
</body>
</html>
