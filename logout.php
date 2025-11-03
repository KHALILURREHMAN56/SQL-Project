<?php
require_once 'config.php';

// Check if user was actually logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Store username for the message
$username = $_SESSION['username'];

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie securely
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Destroy the session
session_destroy();

// Regenerate session ID for security
session_start();
session_regenerate_id(true);

// Set flash message with user's name
$_SESSION['flash_message'] = [
    'type' => 'success',
    'message' => 'Goodbye, ' . htmlspecialchars($username) . '! You have been successfully logged out.',
    'timestamp' => time()
];

// Clear any remember-me cookies if they exist
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Clear any other application-specific cookies
setcookie('last_visit', '', time() - 3600, '/');
setcookie('user_preferences', '', time() - 3600, '/');

// Prevent browser caching of sensitive pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Redirect to login page
header('Location: login.php');
exit();
?> 