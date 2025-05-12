<?php
// API endpoint to update driver location
// This will update the driver's location in the core1_movers database

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once dirname(__FILE__) . '/../../functions/db.php';
require_once dirname(__FILE__) . '/../../functions/auth.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Unknown error occurred'
];

// Check if user is logged in
if (!isLoggedIn()) {
    $response['message'] = 'Authentication required';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// Get and validate input parameters
$driverId = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

// Validate input
if ($driverId <= 0) {
    $response['message'] = 'Invalid driver ID';
    echo json_encode($response);
    exit;
} else if ($latitude === null || $longitude === null) {
    $response['message'] = 'Latitude and longitude are required';
    echo json_encode($response);
    exit;
} else if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    $response['message'] = 'Invalid coordinates';
    echo json_encode($response);
    exit;
} else {
    try {
        // Update driver location in core1_movers database
        $updateQuery = "UPDATE drivers 
                       SET latitude = ?, 
                           longitude = ?, 
                           location_updated_at = NOW() 
                       WHERE driver_id = ?";
        
        // Connect to database
        $conn = connectToCore1DB();
        
        // Prepare and execute statement
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ddi", $latitude, $longitude, $driverId);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Success
                $response['success'] = true;
                $response['message'] = 'Driver location updated successfully';
                
                // Also log the location to history table if it exists
                $historyCheck = "SHOW TABLES LIKE 'driver_location_history'";
                $result = $conn->query($historyCheck);
                $tableExists = ($result && $result->num_rows > 0);
                
                if ($tableExists) {
                    $historyQuery = "INSERT INTO driver_location_history 
                                    (driver_id, latitude, longitude) 
                                    VALUES (?, ?, ?)";
                    
                    $historyStmt = $conn->prepare($historyQuery);
                    $historyStmt->bind_param("idd", $driverId, $latitude, $longitude);
                    $historyStmt->execute();
                    $historyStmt->close();
                }
            } else {
                $response['message'] = 'No driver found with ID: ' . $driverId;
            }
        } else {
            $response['message'] = 'Database error: ' . $stmt->error;
        }
        
        // Close statement and connection
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

// Return response
echo json_encode($response);
?> 