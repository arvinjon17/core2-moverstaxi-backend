<?php
/**
 * API Endpoint: Get Available Drivers with Vehicles
 * 
 * This endpoint returns a list of available drivers who have vehicles assigned to them.
 * Useful for dispatch operations where drivers need to be assigned to bookings.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Include required files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';

try {
    // Connect to core1 database for drivers and vehicles
    $conn = connectToCore1DB();
    
    // Get drivers with vehicles in a single query within the same database
    $query = "
        SELECT 
            d.driver_id, d.user_id, d.license_number, d.license_expiry, 
            d.rating, d.status, d.latitude, d.longitude, d.location_updated_at,
            v.vehicle_id, v.model, v.plate_number, v.year, v.capacity, v.status as vehicle_status
        FROM 
            drivers d
        JOIN 
            vehicles v ON d.driver_id = v.assigned_driver_id
        WHERE 
            d.status != 'offline'
            AND v.status = 'active'
        ORDER BY 
            CASE 
                WHEN d.status = 'available' THEN 0 
                WHEN d.status = 'busy' THEN 1 
                ELSE 2 
            END,
            d.driver_id ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    // Initialize drivers array
    $drivers = [];
    
    // Process results
    while ($row = $result->fetch_assoc()) {
        $driverId = $row['driver_id'];
        
        // Get driver's user information from core2 database
        $userQuery = "SELECT firstname, lastname, phone, email FROM users WHERE user_id = ?";
        $userStmt = connectToCore2DB()->prepare($userQuery);
        $userStmt->bind_param("i", $row['user_id']);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        $userData = [];
        if ($userResult && $userRow = $userResult->fetch_assoc()) {
            $userData = $userRow;
        }
        $userStmt->close();
        
        // Calculate how recent the location data is
        $locationAge = null;
        if ($row['location_updated_at']) {
            $locationUpdated = new DateTime($row['location_updated_at']);
            $now = new DateTime();
            $locationAge = $now->getTimestamp() - $locationUpdated->getTimestamp();
        }
        
        // Create driver data array
        $driverData = [
            'driver_id' => $driverId,
            'user_id' => $row['user_id'],
            'license_number' => $row['license_number'],
            'license_expiry' => $row['license_expiry'],
            'rating' => $row['rating'],
            'status' => $row['status'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'location_updated_at' => $row['location_updated_at'],
            'location_age_seconds' => $locationAge,
            'firstname' => $userData['firstname'] ?? 'Unknown',
            'lastname' => $userData['lastname'] ?? 'Unknown',
            'phone' => $userData['phone'] ?? 'N/A',
            'email' => $userData['email'] ?? 'N/A',
            'vehicle_id' => $row['vehicle_id'],
            'vehicle_model' => $row['model'],
            'plate_number' => $row['plate_number'],
            'vehicle_year' => $row['year'],
            'capacity' => $row['capacity'],
            'vehicle_status' => $row['vehicle_status']
        ];
        
        // Add driver to array
        $drivers[] = $driverData;
    }
    
    // Return data as JSON
    echo json_encode([
        'success' => true,
        'count' => count($drivers),
        'data' => $drivers
    ]);
    
} catch (Exception $e) {
    // Return error as JSON
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching drivers with vehicles: ' . $e->getMessage()
    ]);
} 