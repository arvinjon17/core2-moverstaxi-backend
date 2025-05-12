<?php
// API endpoint to get a specific driver's location
// This endpoint fetches location data for a single driver by ID

// Include required files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests if needed

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get the driver ID from the request
$driverId = isset($_GET['driver_id']) ? intval($_GET['driver_id']) : 0;

// Verify authentication (can be bypassed for public access if needed)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// If no driver ID provided, return error
if ($driverId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Driver ID is required'
    ]);
    exit;
}

try {
    // Get driver location data
    $locationQuery = "SELECT 
        d.driver_id,
        d.status,
        d.latitude,
        d.longitude,
        d.location_updated_at,
        d.user_id
    FROM 
        drivers d
    WHERE 
        d.driver_id = ?";
    
    // Connect to database
    $conn = connectToCore1DB();
    
    // Prepare statement
    $stmt = $conn->prepare($locationQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    // Bind parameters and execute
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    
    // Get result
    $result = $stmt->get_result();
    
    // Check if driver exists
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Driver not found with ID: ' . $driverId
        ]);
        exit;
    }
    
    // Get driver data
    $driverData = $result->fetch_assoc();
    
    // Get user data if user_id is available
    $userData = null;
    if (!empty($driverData['user_id'])) {
        $userQuery = "SELECT 
            user_id, firstname, lastname, email, phone
        FROM 
            users
        WHERE 
            user_id = ?";
        
        // Connect to core2 database
        $userConn = connectToCore2DB();
        $userStmt = $userConn->prepare($userQuery);
        
        if ($userStmt) {
            $userStmt->bind_param("i", $driverData['user_id']);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($userResult && $userResult->num_rows > 0) {
                $userData = $userResult->fetch_assoc();
            }
            
            $userStmt->close();
        }
        
        $userConn->close();
    }
    
    // Combine driver and user data
    $response = [
        'success' => true,
        'message' => 'Driver location retrieved successfully',
        'data' => [
            'driver_id' => $driverData['driver_id'],
            'status' => $driverData['status'],
            'status_text' => ucfirst($driverData['status']),
            'latitude' => $driverData['latitude'],
            'longitude' => $driverData['longitude'],
            'last_updated' => $driverData['location_updated_at'],
            'user_id' => $driverData['user_id'],
            'has_location' => (!empty($driverData['latitude']) && !empty($driverData['longitude']))
        ]
    ];
    
    // Add user data if available
    if ($userData) {
        $response['data']['firstname'] = $userData['firstname'];
        $response['data']['lastname'] = $userData['lastname'];
        $response['data']['email'] = $userData['email'];
        $response['data']['phone'] = $userData['phone'];
    }
    
    // Close connection
    $stmt->close();
    $conn->close();
    
    // Return response
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Error in get_driver_location.php: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve driver location: ' . $e->getMessage()
    ]);
} 