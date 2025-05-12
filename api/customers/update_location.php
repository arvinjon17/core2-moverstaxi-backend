<?php
/**
 * API Endpoint: Update Customer Location
 * 
 * This endpoint updates a customer's location coordinates.
 * 
 * Required parameters:
 * - customer_id: ID of the customer to update
 * - latitude: New latitude value
 * - longitude: New longitude value
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Include required files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check if user has permission to manage bookings
if (!hasPermission('manage_bookings')) {
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to update customer locations'
    ]);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['customer_id']) || !isset($_POST['latitude']) || !isset($_POST['longitude'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

// Get and validate parameters
$customerId = intval($_POST['customer_id']);
$latitude = floatval($_POST['latitude']);
$longitude = floatval($_POST['longitude']);

// Validate customer ID
if ($customerId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID'
    ]);
    exit;
}

// Validate coordinates (basic check for valid range)
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid coordinates'
    ]);
    exit;
}

try {
    // Connect directly to core1_movers2 database
    $conn = new mysqli(DB_HOST, DB_USER_CORE2, DB_PASS_CORE2, 'core1_movers2');
    
    if ($conn->connect_error) {
        throw new Exception("Connection to core1_movers2 failed: " . $conn->connect_error);
    }
    
    error_log("Updating location for customer ID: $customerId to lat: $latitude, lng: $longitude");
    
    // Update customer location
    $updateQuery = "
        UPDATE customers
        SET 
            latitude = ?,
            longitude = ?,
            location_updated_at = NOW()
        WHERE 
            customer_id = ?";
    
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ddi", $latitude, $longitude, $customerId);
    
    if ($stmt->execute()) {
        // Check if any rows were affected
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Location updated successfully',
                'customer_id' => $customerId,
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);
        } else {
            // No rows updated could mean customer doesn't exist
            echo json_encode([
                'success' => false,
                'message' => 'Customer not found or no changes made'
            ]);
        }
    } else {
        throw new Exception("Error executing update: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("Error updating customer location: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error updating location: ' . $e->getMessage()
    ]);
}
?> 