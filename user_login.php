<?php
session_start();

// Prevent caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: user_dashboard.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$error_message = "";

// Handle login POST request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_email'] = $email;
                $_SESSION['user_id'] = $user['id'];
                header("Location: user_dashboard.php");
                exit();
            } else {
                $error_message = "Incorrect password.";
            }
        } else {
            $error_message = "No user found with that email.";
        }
        $stmt->close();
    }
}

$conn->close();

// --- Logout message via session ---
$logout_message = "";
if (isset($_SESSION['logout_message'])) {
    $logout_message = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']); // Remove after showing
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>User Login | Taste It</title>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>

<style>
body {
  font-family: 'Poppins', sans-serif;
  background: url('img/bg5.jpg') no-repeat center center/cover;
  height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 0;
  flex-direction: column;
}

.success-message {
  color: #B0C364;
  font-weight: 600;
  margin-bottom: 15px;
  text-align: center;
  font-size: 1rem;
}

.error-message {
  color: red;
  text-align: center;
  margin-bottom: 15px;
  font-weight: 500;
}

.form-box {
  background: rgba(255, 255, 255, 0.78);
  padding: 35px 30px;
  width: 100%;
  max-width: 360px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.2);
  border-radius: 15px;
}

.form-box h2 {
  font-weight: 700;
  margin-bottom: 25px;
  text-align: center;
  color: #B0C364;
  font-size: 1.8rem;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.form-box input {
  margin-bottom: 15px;
  padding: 12px 15px;
  border-radius: 4px;
  border: 1px solid #ccc;
  font-size: 1rem;
}

.form-box button {
  background-color: #B0C364;
  color: white;
  font-weight: 600;
  padding: 10px;
  width: 100%;
  font-size: 1rem;
  border: none;
  transition: 0.3s ease;
}

.form-box button:hover {
  background-color: #98aa4f;
}

.form-group {
  position: relative;
}

.form-group i {
  position: absolute;
  top: 50%;
  right: 15px;
  transform: translateY(-50%);
  cursor: pointer;
  color: #666;
}

.register-text {
  text-align: center;
  margin-top: 12px;
  font-size: 0.9rem;
}

.register-text span { color: #000; }
.register-text a {
  color: #B0C364;
  font-weight: 600;
  text-decoration: none;
}
.register-text a:hover { color: #98aa4f; text-decoration: none; }
</style>
</head>
<body>

<?php if(!empty($logout_message)): ?>
  <div class="success-message"><?php echo htmlspecialchars($logout_message); ?></div>
<?php endif; ?>

<div class="form-box">
  <h2>Registered User Login</h2>
  <?php if(!empty($error_message)): ?>
    <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <input type="email" name="email" class="form-control" placeholder="Email" required>
    </div>
    <div class="form-group">
      <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
      <i class="fas fa-eye" id="togglePassword"></i>
    </div>
    <button type="submit">Login</button>
    <div class="register-text">
      <span>Don't have an account? </span><a href="register.html">Register</a>
    </div>
  </form>
</div>

<script>
const togglePassword = document.getElementById('togglePassword');
const passwordField = document.getElementById('password');
togglePassword.addEventListener('click', () => {
  const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
  passwordField.setAttribute('type', type);
});
</script>
</body>
</html>
