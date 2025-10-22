<?php
session_start();

// Destroy all session data
$_SESSION = array();
session_unset();
session_destroy();

// Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login selection page
header("Location: login.html");
exit();
?>


