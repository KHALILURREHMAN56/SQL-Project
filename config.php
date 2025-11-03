<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ice_cream_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Get database connection
 * @return PDO Database connection
 * @throws PDOException if connection fails
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Log the error and throw it again
        error_log("Database Connection Error: " . $e->getMessage());
        throw new PDOException("Failed to connect to database. Please try again later.");
    }
}

/**
 * HTML escape function for safe output
 * @param string $str String to escape
 * @return string Escaped string
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize user input
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format price in PKR
 * @param float $price Price to format
 * @return string Formatted price
 */
function formatPrice($price) {
    return 'Rs. ' . number_format($price, 2);
}

/**
 * Check if user is logged in
 * @return bool True if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 * @return bool True if user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is manager
 * @return bool True if user is manager
 */
function isManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}

/**
 * Redirect to another page
 * @param string $page Page to redirect to
 */
function redirect($page) {
    header("Location: $page");
    exit();
}

/**
 * Get current date and time in MySQL format
 * @return string Current date and time
 */
function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

/**
 * Generate random string
 * @param int $length Length of string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Get flash message from session
 * @return array|null Message array or null if no message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Set flash message in session
 * @param string $message Message to set
 * @param string $type Message type (success, error, info, warning)
 */
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Set default timezone
date_default_timezone_set('Asia/Karachi');

// Common status messages
define('MSG_RECORD_ADDED', 'Record added successfully!');
define('MSG_RECORD_UPDATED', 'Record updated successfully!');
define('MSG_RECORD_DELETED', 'Record deleted successfully!');
define('MSG_ERROR', 'An error occurred. Please try again.');
define('MSG_ACCESS_DENIED', 'Access denied. Please log in first.');
define('MSG_INVALID_REQUEST', 'Invalid request.');

// Require admin role
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: unauthorized.php');
        exit();
    }
}

// Require manager role or higher
function requireManager() {
    if (!isAdmin() && !isManager()) {
        header('Location: unauthorized.php');
        exit();
    }
}

// Create an admin user if none exists
function createInitialAdmin() {
    try {
        $pdo = getDBConnection();
        
        // Check if any admin exists
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        if ($stmt->fetchColumn() == 0) {
            // Create default admin user
            $username = 'admin';
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, is_active) 
                                  VALUES (?, ?, 'System Admin', 'admin@example.com', 'admin', 1)");
            $stmt->execute([$username, $password]);
            
            error_log("Initial admin user created");
        }
    } catch (PDOException $e) {
        error_log("Error creating initial admin: " . $e->getMessage());
    }
}

// Create initial admin user
createInitialAdmin();

/**
 * Get the next daily order number
 * @param PDO $pdo Database connection
 * @return int Next daily order number
 */
function getNextDailyOrderNumber($pdo) {
    $today = date('Y-m-d');
    
    // Get the maximum daily order number for today
    $stmt = $pdo->prepare("
        SELECT MAX(daily_order_number) 
        FROM orders 
        WHERE DATE(order_date) = ?
    ");
    $stmt->execute([$today]);
    $maxNumber = $stmt->fetchColumn();
    
    // If no orders today, start from 1, otherwise increment
    return $maxNumber ? $maxNumber + 1 : 1;
} 