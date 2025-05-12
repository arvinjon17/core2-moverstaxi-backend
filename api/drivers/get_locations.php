<?php
// API endpoint to get driver locations
// This is a safer implementation that only accesses the core1_movers database

// Include required files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';

// Set headers
header('Content-Type: application/json');

// Verify authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check permissions
if (!hasPermission('manage_bookings')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Permission denied'
    ]);
    exit;
}

try {
    // After the database schema changes, this will access the latitude/longitude directly from drivers table
    // For now, we'll implement a workaround until the schema changes are applied
    
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
        d.status IN ('available', 'busy')
        AND d.latitude IS NOT NULL 
        AND d.longitude IS NOT NULL";
    
    // Get results from core1 database only
    $driversLocationData = getRows($locationQuery, 'core1');
    
    // Format data for the frontend
    $driverLocations = [];
    foreach ($driversLocationData as $driver) {
        // Get driver name from cache if implemented, or use a placeholder
        // In a production environment, you should implement a proper caching mechanism
        // or a secure way to access user data without direct cross-database access
        
        // For now, we'll just use driver ID as the identifier
        $driverLocations[] = [
            'driver_id' => $driver['driver_id'],
            'user_id' => $driver['user_id'],
            'status' => $driver['status'],
            'latitude' => $driver['latitude'],
            'longitude' => $driver['longitude'],
            'updated_at' => $driver['location_updated_at'],
            'driver_name' => 'Driver #' . $driver['driver_id'] // Placeholder until we have a proper solution
        ];
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Driver locations retrieved successfully',
        'count' => count($driverLocations),
        'data' => $driverLocations
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Error in get_locations.php: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve driver locations: ' . $e->getMessage()
    ]);
} 