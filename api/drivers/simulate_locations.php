<?php
// API endpoint to simulate driver locations for testing
// This will assign random locations around Metro Manila to drivers without location data

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files with a more reliable approach
$baseDir = dirname(dirname(dirname(__FILE__)));
require_once $baseDir . '/functions/db.php';
require_once $baseDir . '/functions/auth.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests if needed

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// For debugging purposes
error_log("simulate_locations.php - Starting execution. Base dir: " . $baseDir);

// Verify authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check permissions (comment out temporarily for debugging if needed)
if (!hasPermission('manage_bookings') && !hasPermission('simulate_driver_locations')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Permission denied - you need either manage_bookings or simulate_driver_locations permission'
    ]);
    exit;
}

// Get the number of drivers to update from query parameter, default to 5
$count = isset($_GET['count']) ? intval($_GET['count']) : 5;

// Enforce a reasonable limit
if ($count > 20) {
    $count = 20;
}

try {
    // Get database connection
    $conn = connectToCore1DB();
    
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    // Get drivers to update (including those with existing locations for testing purposes)
    $driversQuery = "SELECT 
        d.driver_id, d.user_id, d.status
    FROM 
        drivers d
    WHERE 
        d.status IN ('available', 'busy')
    LIMIT $count";
    
    $result = $conn->query($driversQuery);
    
    if (!$result) {
        throw new Exception("Failed to fetch drivers: " . $conn->error);
    }
    
    $driversData = [];
    while ($row = $result->fetch_assoc()) {
        $driversData[] = $row;
    }
    
    // Check if we found any drivers
    if (empty($driversData)) {
        echo json_encode([
            'success' => false,
            'message' => 'No drivers found to update'
        ]);
        exit;
    }
    
    // Metro Manila coordinates boundary (approximate)
    $minLat = 14.5201; // South boundary
    $maxLat = 14.7710; // North boundary
    $minLng = 120.9298; // West boundary
    $maxLng = 121.1013; // East boundary
    
    // Prepare statement for update
    $updateQuery = "UPDATE drivers SET 
        latitude = ?, 
        longitude = ?, 
        location_updated_at = NOW()
    WHERE driver_id = ?";
    
    $stmt = $conn->prepare($updateQuery);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare update statement: " . $conn->error);
    }
    
    $stmt->bind_param("ddi", $latitude, $longitude, $driverId);
    
    // Update each driver with random coordinates
    $updatedDrivers = [];
    foreach ($driversData as $driver) {
        // Generate random coordinates within Metro Manila boundaries
        $latitude = round($minLat + (mt_rand() / mt_getrandmax()) * ($maxLat - $minLat), 6);
        $longitude = round($minLng + (mt_rand() / mt_getrandmax()) * ($maxLng - $minLng), 6);
        
        // Ensure we're sending proper numeric values
        $latitude = (float)$latitude;
        $longitude = (float)$longitude;
        
        $driverId = $driver['driver_id'];
        
        // Execute update
        if (!$stmt->execute()) {
            throw new Exception("Failed to update driver $driverId: " . $stmt->error);
        }
        
        // Track successfully updated drivers
        $updatedDrivers[] = [
            'driver_id' => $driverId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => $driver['status']
        ];
    }
    
    // Close statement
    $stmt->close();
    
    // Return success response with details
    echo json_encode([
        'success' => true,
        'message' => count($updatedDrivers) . ' driver locations simulated successfully',
        'count' => count($updatedDrivers),
        'updated_drivers' => $updatedDrivers
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Error in simulate_locations.php: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to simulate driver locations: ' . $e->getMessage()
    ]);
} 