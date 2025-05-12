<?php
/**
 * API Endpoint: Get Nearby Customers
 * 
 * This endpoint returns a list of customers sorted by their distance from a specific location (lat/lng).
 * 
 * Required parameters:
 * - latitude: Decimal latitude of the reference location
 * - longitude: Decimal longitude of the reference location
 * - limit (optional): Maximum number of customers to return (default: 10)
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
    // Connect to database (core1 for customers table)
    $conn = connectToCore1DB();
    
    // Using Haversine formula to calculate distance in kilometers
    // 6371 is the Earth's radius in kilometers
    $query = "
        SELECT 
            c.customer_id, c.user_id, c.address, c.city, c.state, c.zip, 
            c.notes, c.status, c.latitude, c.longitude, c.location_updated_at,
            (6371 * acos(
                cos(radians(?)) * cos(radians(c.latitude)) * cos(radians(c.longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(c.latitude))
            )) AS distance
        FROM 
            customers c
        WHERE 
            c.latitude IS NOT NULL 
            AND c.longitude IS NOT NULL
            AND c.latitude != 0
            AND c.longitude != 0
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
    
    // Build array of customers
    $customers = [];
    while ($customer = $result->fetch_assoc()) {
        // Calculate how recent the location data is
        $locationAge = null;
        if ($customer['location_updated_at']) {
            $locationUpdated = new DateTime($customer['location_updated_at']);
            $now = new DateTime();
            $locationAge = $now->getTimestamp() - $locationUpdated->getTimestamp();
        }
        
        // Get customer's user information from core2 database
        $userData = null;
        
        // We need to get user data using a separate query since we can't join across databases
        if ($customer['user_id']) {
            $userQuery = "SELECT firstname, lastname, phone, email FROM users WHERE user_id = ?";
            $userStmt = connectToCore2DB()->prepare($userQuery);
            $userStmt->bind_param("i", $customer['user_id']);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($userResult && $userRow = $userResult->fetch_assoc()) {
                $userData = $userRow;
            }
            $userStmt->close();
        }
        
        // Combine all data
        $customerData = [
            'customer_id' => $customer['customer_id'],
            'user_id' => $customer['user_id'],
            'address' => $customer['address'],
            'city' => $customer['city'],
            'state' => $customer['state'],
            'zip' => $customer['zip'],
            'status' => $customer['status'],
            'latitude' => $customer['latitude'],
            'longitude' => $customer['longitude'],
            'location_updated_at' => $customer['location_updated_at'],
            'location_age_seconds' => $locationAge,
            'distance_km' => round($customer['distance'], 2),
        ];
        
        // Add user data if available
        if ($userData) {
            $customerData['firstname'] = $userData['firstname'];
            $customerData['lastname'] = $userData['lastname'];
            $customerData['phone'] = $userData['phone'];
            $customerData['email'] = $userData['email'];
        }
        
        // Check if customer has any pending bookings
        $bookingsQuery = "
            SELECT booking_id, pickup_location, dropoff_location, pickup_datetime, booking_status
            FROM bookings
            WHERE customer_id = ? AND booking_status IN ('pending', 'confirmed')
            ORDER BY pickup_datetime ASC
            LIMIT 1";
        
        $bookingStmt = connectToCore2DB()->prepare($bookingsQuery);
        $bookingStmt->bind_param("i", $customer['customer_id']);
        $bookingStmt->execute();
        $bookingResult = $bookingStmt->get_result();
        
        if ($bookingResult && $bookingRow = $bookingResult->fetch_assoc()) {
            $customerData['pending_booking'] = $bookingRow;
        }
        $bookingStmt->close();
        
        $customers[] = $customerData;
    }
    
    // Close statement and connection
    $stmt->close();
    $conn->close();
    
    // Return results as JSON
    echo json_encode([
        'success' => true,
        'count' => count($customers),
        'reference_location' => [
            'latitude' => $latitude,
            'longitude' => $longitude
        ],
        'data' => $customers
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching nearby customers: ' . $e->getMessage()
    ]);
} 