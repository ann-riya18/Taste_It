<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// DB connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo "<p style='color: red; text-align: center;'>Please fill in all fields.</p>";
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
                echo "<p style='color: red; text-align: center;'>Incorrect password.</p>";
            }
        } else {
            echo "<p style='color: red; text-align: center;'>No user found with that email.</p>";
        }

        $stmt->close();
    }
}

$conn->close();

$message = "";
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $message = "<p class='success'>You have been logged out successfully!</p>";
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
      flex-direction: column; /* so message can appear below form */
    }

    .form-box {
      background: rgba(255, 255, 255, 0.78);
      padding: 35px 30px;
      width: 100%;
      max-width: 360px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
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
      background-color:#98aa4f;
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

    .register-text span {
      color: #000;
    }

    .register-text a {
      color: #b0c364;
      font-weight: 600;
      text-decoration: none;
    }

    .register-text a:hover {
      color: #b0c364;
      text-decoration: none;
    }

    .success {
      color: #88a121ff;
      text-align: center;
      margin-top: 15px;
      font-weight: 500;
    }
  </style>
</head>
<body>

  <div class="form-box">
    <h2>Registered User Login</h2>
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

  <!-- ✅ logout success message placed OUTSIDE form-box -->
  <?php if (!empty($message)) echo $message; ?>

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
