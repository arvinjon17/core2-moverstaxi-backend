<?php
/**
 * User Actions AJAX Handler
 * 
 * This file handles AJAX requests related to user management including:
 * - Fetching user details
 * - Validating user data
 * - Other user-related actions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once '../functions/db.php';
require_once '../functions/auth.php';
require_once '../functions/profile_images.php';

// Only allow authenticated users
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if action parameter exists
if (!isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing action parameter']);
    exit;
}

// Get the action
$action = $_POST['action'];

// Connect to database
$conn = connectToCore2DB();

switch ($action) {
    case 'get_user_data':
        // Handle getting user data for editing
        handleGetUserData($conn);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// Close the database connection
$conn->close();

/**
 * Handle fetching user data for the edit form
 */
function handleGetUserData($conn) {
    // Check if user_id is provided
    if (!isset($_POST['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing user_id parameter']);
        return;
    }
    
    // Get and sanitize the user_id
    $userId = (int)$_POST['user_id'];
    
    // Check if user has permission to view user data
    if (!hasPermission('view_users') && !hasPermission('edit_user')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to view user data']);
        return;
    }
    
    // Fetch user data from database
    $query = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    // Get user data
    $user = $result->fetch_assoc();
    
    // Get profile image URL
    $profileImageUrl = getUserProfileImageUrl($user);
    
    // Prepare the response
    $response = [
        'success' => true,
        'user' => [
            'user_id' => $user['user_id'],
            'email' => $user['email'],
            'firstname' => $user['firstname'],
            'lastname' => $user['lastname'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'status' => $user['status'],
            'profile_image_url' => $profileImageUrl
        ]
    ];
    
    // Send the response
    echo json_encode($response);
}
?> 