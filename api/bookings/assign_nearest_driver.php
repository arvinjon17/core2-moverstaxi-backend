<?php
/**
 * API Endpoint: Assign Nearest Driver to Booking
 * 
 * This endpoint finds the nearest available driver to a booking's pickup location
 * and assigns them to the booking.
 * 
 * Required parameters:
 * - booking_id: ID of the booking to assign a driver to
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Include required files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Check for required parameters
if (!isset($_POST['booking_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameter: booking_id'
    ]);
    exit;
}

$bookingId = intval($_POST['booking_id']);

try {
    // Connect to database
    $conn2 = connectToCore2DB();
    
    // Step 1: Get booking details including pickup location
    $bookingQuery = "
        SELECT booking_id, customer_id, pickup_location, pickup_datetime, 
               pickup_lat, pickup_lng, dropoff_lat, dropoff_lng
        FROM bookings
        WHERE booking_id = ? AND booking_status IN ('pending', 'confirmed')";
    
    $bookingStmt = $conn2->prepare($bookingQuery);
    $bookingStmt->bind_param("i", $bookingId);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();
    
    if (!$bookingResult || $bookingResult->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Booking not found or not in pending/confirmed status'
        ]);
        exit;
    }
    
    $booking = $bookingResult->fetch_assoc();
    $bookingStmt->close();
    
    // If booking doesn't have coordinates for pickup, try to get it from the address
    if (empty($booking['pickup_lat']) || empty($booking['pickup_lng'])) {
        // Attempt to geocode the pickup address
        $pickupLocation = urlencode($booking['pickup_location']);
        $geocodeUrl = "https://maps.googleapis.com/maps/api/geocode/json?address={$pickupLocation}&key=YOUR_API_KEY";
        
        $geocodeResponse = file_get_contents($geocodeUrl);
        $geocodeData = json_decode($geocodeResponse, true);
        
        if ($geocodeData['status'] === 'OK' && !empty($geocodeData['results'][0]['geometry']['location'])) {
            $booking['pickup_lat'] = $geocodeData['results'][0]['geometry']['location']['lat'];
            $booking['pickup_lng'] = $geocodeData['results'][0]['geometry']['location']['lng'];
            
            // Update booking with coordinates
            $updateQuery = "UPDATE bookings SET pickup_lat = ?, pickup_lng = ? WHERE booking_id = ?";
            $updateStmt = $conn2->prepare($updateQuery);
            $updateStmt->bind_param("ddi", $booking['pickup_lat'], $booking['pickup_lng'], $bookingId);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Could not determine pickup location coordinates'
            ]);
            exit;
        }
    }
    
    // Step 2: Find the nearest available driver with an available vehicle
    // Use the API endpoint we already have for nearest drivers
    $nearestDriversUrl = "http://{$_SERVER['HTTP_HOST']}/api/drivers/get_nearest_drivers.php?latitude={$booking['pickup_lat']}&longitude={$booking['pickup_lng']}&limit=5&max_distance=50";
    
    $nearestDriversResponse = file_get_contents($nearestDriversUrl);
    $nearestDriversData = json_decode($nearestDriversResponse, true);
    
    if (!$nearestDriversData['success'] || empty($nearestDriversData['data'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No available drivers found nearby'
        ]);
        exit;
    }
    
    // Get the first (nearest) driver
    $nearestDriver = $nearestDriversData['data'][0];
    
    // Step 3: Assign driver to booking
    $conn2 = connectToCore2DB(); // Reconnect to be safe
    
    $updateBookingQuery = "
        UPDATE bookings 
        SET driver_id = ?, vehicle_id = ?, booking_status = 'confirmed', updated_at = NOW()
        WHERE booking_id = ?";
    
    $updateStmt = $conn2->prepare($updateBookingQuery);
    $updateStmt->bind_param("iii", $nearestDriver['driver_id'], $nearestDriver['vehicle_id'], $bookingId);
    $result = $updateStmt->execute();
    
    if (!$result) {
        throw new Exception("Failed to update booking: " . $conn2->error);
    }
    
    // Update driver status to 'busy'
    $conn1 = connectToCore1DB();
    $updateDriverQuery = "UPDATE drivers SET status = 'busy' WHERE driver_id = ?";
    $driverStmt = $conn1->prepare($updateDriverQuery);
    $driverStmt->bind_param("i", $nearestDriver['driver_id']);
    $driverStmt->execute();
    
    // Get driver name for response
    $driverName = $nearestDriver['firstname'] . ' ' . $nearestDriver['lastname'];
    
    // Calculate ETA based on distance (simple estimate)
    $etaMinutes = ceil($nearestDriver['distance_km'] * 2); // Simple calculation assuming 30 km/h
    
    // Prepare vehicle information
    $vehicleInfo = [
        'id' => $nearestDriver['vehicle_id'],
        'model' => $nearestDriver['vehicle_model'],
        'plate_number' => $nearestDriver['plate_number'],
        'year' => $nearestDriver['vehicle_year'],
        'capacity' => $nearestDriver['capacity']
    ];
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Nearest driver assigned to booking successfully',
        'booking_id' => $bookingId,
        'driver' => [
            'id' => $nearestDriver['driver_id'],
            'name' => $driverName,
            'phone' => $nearestDriver['phone'],
            'distance_km' => $nearestDriver['distance_km'],
            'eta_minutes' => $etaMinutes
        ],
        'vehicle' => $vehicleInfo
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error assigning nearest driver: ' . $e->getMessage()
    ]);
} 