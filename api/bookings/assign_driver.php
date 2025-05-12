<?php
// Suppress PHP errors and warnings from being output - we'll handle all errors via JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// API endpoint to assign a driver to a booking

// Include necessary files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';
require_once '../../functions/role_management.php';

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check permissions - allow access for admin, super_admin, and dispatch roles
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['admin', 'super_admin', 'dispatch']) && 
    !hasPermission('manage_bookings')) {
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to access this resource'
    ]);
    exit;
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'POST method required'
    ]);
    exit;
}

// Check if booking ID and driver ID are provided
if (!isset($_POST['booking_id']) || empty($_POST['booking_id']) || 
    !isset($_POST['driver_id']) || empty($_POST['driver_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID and driver ID are required'
    ]);
    exit;
}

try {
    // Get booking ID and driver ID
    $bookingId = intval($_POST['booking_id']);
    $driverId = intval($_POST['driver_id']);
    
    // Connect to core2 database
    $conn = connectToCore2DB();
    
    // Check if the booking exists and is in a valid state for assignment
    $checkBookingQuery = "SELECT 
        booking_id, booking_status, driver_id 
    FROM 
        " . DB_NAME_CORE2 . ".bookings 
    WHERE 
        booking_id = ?";
    
    $stmtCheck = $conn->prepare($checkBookingQuery);
    $stmtCheck->bind_param("i", $bookingId);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    
    if ($resultCheck->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Booking not found'
        ]);
        exit;
    }
    
    $bookingData = $resultCheck->fetch_assoc();
    
    // Check if booking can be assigned
    if (!in_array($bookingData['booking_status'], ['pending', 'confirmed'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Booking cannot be assigned in its current status: ' . $bookingData['booking_status']
        ]);
        exit;
    }
    
    // Check if driver is already assigned
    if (!empty($bookingData['driver_id']) && $bookingData['driver_id'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'A driver is already assigned to this booking'
        ]);
        exit;
    }
    
    // Connect to core1 database
    $conn1 = connectToCore1DB();
    
    // Check if the driver exists and is available
    $checkDriverQuery = "SELECT 
        d.driver_id, d.status, v.vehicle_id, v.plate_number
    FROM 
        " . DB_NAME_CORE1 . ".drivers d
    LEFT JOIN
        " . DB_NAME_CORE1 . ".vehicles v ON d.driver_id = v.assigned_driver_id
    WHERE 
        d.driver_id = ?";
    
    $stmtDriver = $conn1->prepare($checkDriverQuery);
    $stmtDriver->bind_param("i", $driverId);
    $stmtDriver->execute();
    $resultDriver = $stmtDriver->get_result();
    
    if ($resultDriver->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Driver not found'
        ]);
        exit;
    }
    
    $driverData = $resultDriver->fetch_assoc();
    
    // Check if driver is available
    if ($driverData['status'] !== 'available') {
        echo json_encode([
            'success' => false,
            'message' => 'Driver is not available for assignment'
        ]);
        exit;
    }
    
    // Check if driver has an assigned vehicle
    if (empty($driverData['vehicle_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Driver does not have an assigned vehicle. Please assign a vehicle to this driver first.'
        ]);
        exit;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    $conn1->begin_transaction();
    
    try {
        // Assign driver to booking and include vehicle_id
        $assignQuery = "UPDATE " . DB_NAME_CORE2 . ".bookings 
            SET driver_id = ?, vehicle_id = ?, updated_at = NOW() 
            WHERE booking_id = ?";
        
        $stmtAssign = $conn->prepare($assignQuery);
        $stmtAssign->bind_param("iii", $driverId, $driverData['vehicle_id'], $bookingId);
        $resultAssign = $stmtAssign->execute();
        
        if (!$resultAssign) {
            throw new Exception("Failed to assign driver to booking: " . $conn->error);
        }
        
        // Update driver status to busy
        $updateDriverQuery = "UPDATE " . DB_NAME_CORE1 . ".drivers 
            SET status = 'busy' 
            WHERE driver_id = ?";
        
        $stmtUpdateDriver = $conn1->prepare($updateDriverQuery);
        $stmtUpdateDriver->bind_param("i", $driverId);
        $resultUpdateDriver = $stmtUpdateDriver->execute();
        
        if (!$resultUpdateDriver) {
            throw new Exception("Failed to update driver status: " . $conn1->error);
        }
        
        // If everything is OK, commit both transactions
        $conn->commit();
        $conn1->commit();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Driver successfully assigned to booking with vehicle ' . $driverData['plate_number'],
            'booking_id' => $bookingId,
            'driver_id' => $driverId,
            'vehicle_id' => $driverData['vehicle_id'],
            'vehicle_plate' => $driverData['plate_number']
        ]);
        
    } catch (Exception $e) {
        // If there is an error, roll back both transactions
        $conn->rollback();
        $conn1->rollback();
        
        throw $e;
    }
    
} catch (Exception $e) {
    // Log error
    error_log("Error in assign_driver.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error assigning driver: ' . $e->getMessage()
    ]);
}
?> 