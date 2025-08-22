<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db = "tasteit";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM admins WHERE email=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
  $row = $result->fetch_assoc();

  // Verify password
  if (password_verify($password, $row['password'])) {
    $_SESSION['admin_email'] = $row['email'];
    header("Location: admin_dashboard.php");
    exit();
  } else {
    echo "Incorrect password.";
  }
} else {
  echo "Admin not found.";
}

$conn->close();
?>
