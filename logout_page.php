<?php
// logout_page.php - destroy admin session and redirect to login

session_start();

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login_page.php");
exit;
