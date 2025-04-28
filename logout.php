<?php
session_start(); // Start or resume the session

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Optionally, destroy the session cookie
if (ini_get("session.use_cookies")) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Redirect to login or homepage
header("Location: login.php"); // Change to your desired page
exit();
?>
