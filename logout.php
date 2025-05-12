<?php
/**
 * Logout Script
 * Clears user session and redirects to login page
 */
session_start();
require_once 'functions/auth.php';

// Get user_id before logging out
$userId = $_SESSION['user_id'] ?? 0;

// Log the logout action (if needed)
if (isset($_SESSION['user_id'])) {
    // You can add logout logging here if required
    $username = $_SESSION['username'] ?? 'Unknown';
    
    // Log the logout action in a file or database
    $logMessage = date('Y-m-d H:i:s') . " - User ID: $userId ($username) logged out\n";
    error_log($logMessage, 3, 'logs/auth.log');
}

// Log the user out
logoutUser();

// Remove remember me cookies
if (isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user'])) {
    // Delete the token from database if user_id is available
    if ($userId > 0) {
        $conn = connectToCore2DB();
        $token = $_COOKIE['remember_token'];
        
        // Delete all tokens for this user
        $query = "DELETE FROM auth_tokens WHERE user_id = $userId";
        $conn->query($query);
        $conn->close();
    }
    
    // Expire the cookies
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    setcookie('remember_user', '', time() - 3600, '/', '', false, true);
}

// Redirect to login page
header('Location: login.php');
exit;
?> 