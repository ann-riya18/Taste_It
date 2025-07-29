<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// DB connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle login
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        die("❗ Please fill in all fields.");
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // ✅ Store email in session for dashboard
            $_SESSION['user_email'] = $user['email'];

            // ✅ Redirect to user dashboard
            header("Location: user_dashboard.php");
            exit();
        } else {
            echo "<script>alert('❌ Incorrect password!'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('❌ No user found with that email!'); window.history.back();</script>";
    }

    $stmt->close();
}

$conn->close();
?>
