<?php
// API endpoint to get all drivers regardless of location data
// This endpoint doesn't filter based on latitude/longitude availability

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
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// For debugging
error_log("get_all_drivers.php - Starting execution. Base dir: " . $baseDir);

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
if (!hasPermission('manage_bookings') && !hasPermission('view_driver_location')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Permission denied - you need either manage_bookings or view_driver_location permission'
    ]);
    exit;
}

try {
    // Get all active drivers regardless of location data
    $driversQuery = "SELECT 
        d.driver_id,
        d.user_id,
        d.status,
        d.license_number,
        d.license_expiry,
        d.rating,
        d.latitude,
        d.longitude,
        d.location_updated_at
    FROM 
        drivers d
    WHERE 
        d.status IN ('available', 'busy')";
    
    // Get results from core1 database
    $driversData = getRows($driversQuery, 'core1');
    
    // Log count of drivers retrieved
    error_log("Retrieved " . count($driversData) . " drivers from database");
    
    // Get user data for these drivers from core2 database
    $userIds = [];
    foreach ($driversData as $driver) {
        if (!empty($driver['user_id'])) {
            $userIds[] = $driver['user_id'];
        }
    }
    
    $userInfo = [];
    if (!empty($userIds)) {
        // Convert array to comma-separated string for SQL
        $userIdsString = implode(',', $userIds);
        
        $usersQuery = "SELECT 
            user_id, firstname, lastname, email, phone
        FROM 
            users
        WHERE 
            user_id IN ($userIdsString)";
        
        $usersData = getRows($usersQuery, 'core2');
        error_log("Retrieved " . count($usersData) . " user records for drivers");
        
        // Index users by ID for easy lookup
        foreach ($usersData as $user) {
            $userInfo[$user['user_id']] = $user;
        }
    }
    
    // Combine driver and user data
    $driversList = [];
    foreach ($driversData as $driver) {
        $userId = $driver['user_id'];
        
        // Ensure latitude and longitude are proper numeric values
        $latitude = $driver['latitude'];
        $longitude = $driver['longitude'];
        
        // Convert to float for consistency
        if ($latitude !== null) {
            $latitude = (float)$latitude;
        }
        
        if ($longitude !== null) {
            $longitude = (float)$longitude;
        }
        
        $driverData = [
            'driver_id' => $driver['driver_id'],
            'user_id' => $userId,
            'status' => $driver['status'],
            'license_number' => $driver['license_number'],
            'license_expiry' => $driver['license_expiry'],
            'rating' => $driver['rating'],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'last_updated' => $driver['location_updated_at']
        ];
        
        // Add user data if available
        if (isset($userInfo[$userId])) {
            $driverData['firstname'] = $userInfo[$userId]['firstname'];
            $driverData['lastname'] = $userInfo[$userId]['lastname'];
            $driverData['email'] = $userInfo[$userId]['email'];
            $driverData['phone'] = $userInfo[$userId]['phone'];
        } else {
            // Provide defaults if user data not found
            $driverData['firstname'] = 'Driver';
            $driverData['lastname'] = '#' . $driver['driver_id'];
            $driverData['email'] = 'unknown@example.com';
            $driverData['phone'] = 'Unknown';
        }
        
        // Log the location data for debugging
        error_log("Driver #{$driver['driver_id']} location: lat={$latitude}, lng={$longitude}");
        
        $driversList[] = $driverData;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Drivers retrieved successfully',
        'count' => count($driversList),
        'data' => $driversList
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Error in get_all_drivers.php: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve drivers: ' . $e->getMessage()
    ]);
} 