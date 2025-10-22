<?php
session_start();

// Unset and destroy all session data
$_SESSION = array();
session_unset();
session_destroy();

// Start a new session to pass logout message
session_start();
$_SESSION['logout_message'] = "You have been logged out successfully!";

// Disable browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page
header("Location: user_login.php");
exit();
?>
