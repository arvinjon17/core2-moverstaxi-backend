<?php
// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and helper functions
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check user is logged in and has appropriate permissions
if (!isUserLoggedIn() || !hasPermission('manage_bookings')) {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Check if it's a GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only GET is allowed.'
    ]);
    exit;
}

// Get booking ID if provided (optional)
$bookingId = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// Optional vehicle type filter
$vehicleType = isset($_GET['vehicle_type']) ? trim($_GET['vehicle_type']) : '';

// Optional pickup location coordinates
$pickupLat = isset($_GET['pickup_lat']) ? floatval($_GET['pickup_lat']) : null;
$pickupLng = isset($_GET['pickup_lng']) ? floatval($_GET['pickup_lng']) : null;

// Get database connections
try {
    $mainDb = getDatabaseConnection();
    $ridesDb = getRidesDatabaseConnection();
    
    // Base query to get active drivers
    $query = "
        SELECT 
            d.id,
            d.license_number,
            u.name AS driver_name,
            u.phone,
            d.vehicle_type,
            COALESCE(dl.latitude, 0) AS latitude,
            COALESCE(dl.longitude, 0) AS longitude,
            COALESCE(dl.last_updated, '0000-00-00 00:00:00') AS location_updated
        FROM drivers d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN driver_locations dl ON d.id = dl.driver_id
        WHERE d.status = 'active'
    ";
    
    $params = [];
    
    // Add vehicle type filter if provided
    if (!empty($vehicleType)) {
        $query .= " AND d.vehicle_type = ?";
        $params[] = $vehicleType;
    }
    
    // Check if driver is already assigned to any active booking
    $query .= " AND d.id NOT IN (
        SELECT DISTINCT driver_id 
        FROM bookings 
        WHERE driver_id IS NOT NULL 
        AND status IN ('confirmed', 'in_progress')
    )";
    
    // If booking ID is provided, check if it exists
    if ($bookingId > 0) {
        $bookingStmt = $ridesDb->prepare("
            SELECT id, vehicle_type, pickup_location 
            FROM bookings 
            WHERE id = ?
        ");
        $bookingStmt->execute([$bookingId]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking && !empty($booking['vehicle_type'])) {
            // Override vehicle type filter with the booking's vehicle type
            // Remove existing vehicle type condition if any
            $query = str_replace("AND d.vehicle_type = ?", "", $query);
            $params = array_filter($params, function($param) use ($vehicleType) {
                return $param !== $vehicleType;
            });
            
            // Add booking's vehicle type condition
            $query .= " AND d.vehicle_type = ?";
            $params[] = $booking['vehicle_type'];
        }
    }
    
    // Execute the query
    $stmt = $mainDb->prepare($query);
    $stmt->execute($params);
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate distance from pickup location if coordinates provided
    if ($pickupLat !== null && $pickupLng !== null) {
        foreach ($drivers as &$driver) {
            if (!empty($driver['latitude']) && !empty($driver['longitude'])) {
                // Calculate distance in kilometers
                $distance = calculateDistance(
                    $pickupLat,
                    $pickupLng,
                    $driver['latitude'],
                    $driver['longitude']
                );
                
                $driver['distance_km'] = round($distance, 2);
                $driver['estimated_arrival_mins'] = round($distance * 2); // Rough estimate: 2 mins per km
            } else {
                $driver['distance_km'] = null;
                $driver['estimated_arrival_mins'] = null;
            }
        }
        
        // Sort drivers by distance (closest first)
        usort($drivers, function($a, $b) {
            // If either distance is null, put them last
            if ($a['distance_km'] === null) return 1;
            if ($b['distance_km'] === null) return -1;
            
            return $a['distance_km'] <=> $b['distance_km'];
        });
    }
    
    // Return the available drivers
    echo json_encode([
        'success' => true,
        'count' => count($drivers),
        'drivers' => $drivers
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Failed to get available drivers: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Calculate the distance between two coordinates using the Haversine formula
 * 
 * @param float $lat1 First point latitude
 * @param float $lng1 First point longitude
 * @param float $lat2 Second point latitude
 * @param float $lng2 Second point longitude
 * @return float Distance in kilometers
 */
function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    // Convert degrees to radians
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);
    
    // Haversine formula
    $dlat = $lat2 - $lat1;
    $dlng = $lng2 - $lng1;
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlng/2) * sin($dlng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    // Earth's radius in kilometers
    $radius = 6371;
    
    // Distance in kilometers
    return $radius * $c;
}
?> 