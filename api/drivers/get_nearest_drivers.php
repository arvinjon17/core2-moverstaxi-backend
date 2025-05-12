<?php
/**
 * API Endpoint: Get Nearest Drivers to a Location
 * 
 * This endpoint returns a list of available drivers sorted by their distance
 * from a specific location (latitude/longitude).
 * 
 * Required parameters:
 * - latitude: Decimal latitude of the reference location
 * - longitude: Decimal longitude of the reference location
 * - limit (optional): Maximum number of drivers to return (default: 10)
 * - max_distance (optional): Maximum distance in kilometers to filter by (default: 50)
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Include required files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';

// Check for required parameters
if (!isset($_GET['latitude']) || !isset($_GET['longitude'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: latitude and longitude'
    ]);
    exit;
}

// Validate parameters
$latitude = floatval($_GET['latitude']);
$longitude = floatval($_GET['longitude']);
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$maxDistance = isset($_GET['max_distance']) ? floatval($_GET['max_distance']) : 50;

// Validate coordinates are in valid range
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid coordinates. Latitude must be between -90 and 90, longitude between -180 and 180'
    ]);
    exit;
}

try {
    // Connect to database (core1 for drivers table)
    $conn = connectToCore1DB();
    
    // Using Haversine formula to calculate distance in kilometers
    // 6371 is the Earth's radius in kilometers
    $query = "
        SELECT 
            d.driver_id, d.user_id, d.license_number, d.license_expiry, 
            d.rating, d.status, d.latitude, d.longitude, d.location_updated_at,
            (6371 * acos(
                cos(radians(?)) * cos(radians(d.latitude)) * cos(radians(d.longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(d.latitude))
            )) AS distance
        FROM 
            drivers d
        WHERE 
            d.latitude IS NOT NULL 
            AND d.longitude IS NOT NULL
            AND d.latitude != 0
            AND d.longitude != 0
            AND d.status = 'available'
        HAVING 
            distance <= ?
        ORDER BY 
            distance ASC
        LIMIT ?";
    
    // Prepare and execute statement
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ddddi", $latitude, $longitude, $latitude, $maxDistance, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    // Build array of drivers
    $drivers = [];
    while ($driver = $result->fetch_assoc()) {
        // Calculate how recent the location data is
        $locationAge = null;
        if ($driver['location_updated_at']) {
            $locationUpdated = new DateTime($driver['location_updated_at']);
            $now = new DateTime();
            $locationAge = $now->getTimestamp() - $locationUpdated->getTimestamp();
        }
        
        // Get driver's user information from core2 database
        $userData = null;
        
        // We need to get user data using a separate query since we can't join across databases
        if ($driver['user_id']) {
            $userQuery = "SELECT firstname, lastname, phone, email FROM users WHERE user_id = ?";
            $userStmt = connectToCore2DB()->prepare($userQuery);
            $userStmt->bind_param("i", $driver['user_id']);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($userResult && $userRow = $userResult->fetch_assoc()) {
                $userData = $userRow;
            }
            $userStmt->close();
        }
        
        // Get vehicle information for this driver
        $vehicleData = null;
        $vehicleQuery = "
            SELECT v.vehicle_id, v.model, v.plate_number, v.year, v.capacity, v.status
            FROM vehicles v
            WHERE v.assigned_driver_id = ?
            LIMIT 1";
        
        $vehicleStmt = $conn->prepare($vehicleQuery);
        $vehicleStmt->bind_param("i", $driver['driver_id']);
        $vehicleStmt->execute();
        $vehicleResult = $vehicleStmt->get_result();
        
        if ($vehicleResult && $vehicleRow = $vehicleResult->fetch_assoc()) {
            $vehicleData = $vehicleRow;
        }
        $vehicleStmt->close();
        
        // Combine all data
        $driverData = [
            'driver_id' => $driver['driver_id'],
            'user_id' => $driver['user_id'],
            'license_number' => $driver['license_number'],
            'license_expiry' => $driver['license_expiry'],
            'rating' => $driver['rating'],
            'status' => $driver['status'],
            'latitude' => $driver['latitude'],
            'longitude' => $driver['longitude'],
            'location_updated_at' => $driver['location_updated_at'],
            'location_age_seconds' => $locationAge,
            'distance_km' => round($driver['distance'], 2),
            'eta_minutes' => round($driver['distance'] * 2), // Rough estimate: 30 km/h average speed
        ];
        
        // Add user data if available
        if ($userData) {
            $driverData['firstname'] = $userData['firstname'];
            $driverData['lastname'] = $userData['lastname'];
            $driverData['phone'] = $userData['phone'];
            $driverData['email'] = $userData['email'];
        }
        
        // Add vehicle data if available
        if ($vehicleData) {
            $driverData['vehicle_id'] = $vehicleData['vehicle_id'];
            $driverData['vehicle_model'] = $vehicleData['model'];
            $driverData['plate_number'] = $vehicleData['plate_number'];
            $driverData['vehicle_year'] = $vehicleData['year'];
            $driverData['capacity'] = $vehicleData['capacity'];
            $driverData['vehicle_status'] = $vehicleData['status'];
        }
        
        $drivers[] = $driverData;
    }
    
    // Close statement and connection
    $stmt->close();
    $conn->close();
    
    // Return results as JSON
    echo json_encode([
        'success' => true,
        'count' => count($drivers),
        'reference_location' => [
            'latitude' => $latitude,
            'longitude' => $longitude
        ],
        'data' => $drivers
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching nearest drivers: ' . $e->getMessage()
    ]);
} 