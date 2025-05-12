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

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST is allowed.'
    ]);
    exit;
}

// Required fields
$requiredFields = ['customer_id', 'pickup_location', 'dropoff_location', 'pickup_datetime'];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required field: ' . $field
        ]);
        exit;
    }
}

// Get values from POST data
$customerId = intval($_POST['customer_id']);
$pickupLocation = trim($_POST['pickup_location']);
$dropoffLocation = trim($_POST['dropoff_location']);
$pickupDatetime = trim($_POST['pickup_datetime']);
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$fare = isset($_POST['fare']) ? floatval($_POST['fare']) : 0.00;
$driverId = isset($_POST['driver_id']) && !empty($_POST['driver_id']) ? intval($_POST['driver_id']) : null;

// Validate customer id
if ($customerId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID'
    ]);
    exit;
}

// Validate pickup datetime
$pickupTimestamp = strtotime($pickupDatetime);
if ($pickupTimestamp === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid pickup datetime format'
    ]);
    exit;
}

// Make sure pickup time is in the future
if ($pickupTimestamp < time()) {
    echo json_encode([
        'success' => false,
        'message' => 'Pickup time must be in the future'
    ]);
    exit;
}

$formattedPickupDatetime = date('Y-m-d H:i:s', $pickupTimestamp);

// Use a database transaction to ensure data consistency
try {
    // Get database connections
    $db = getRidesDatabaseConnection();
    $dbCabs = getDatabaseConnection();
    $db->beginTransaction();

    // Check if customer exists
    $customerStmt = $dbCabs->prepare("SELECT id FROM customers WHERE id = ?");
    $customerStmt->execute([$customerId]);
    
    if (!$customerStmt->fetch()) {
        throw new Exception("Customer not found");
    }

    // Check if driver exists and is active (if driver is specified)
    if ($driverId !== null) {
        $driverStmt = $dbCabs->prepare("SELECT id, status FROM drivers WHERE id = ?");
        $driverStmt->execute([$driverId]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$driver) {
            throw new Exception("Driver not found");
        }
        
        if ($driver['status'] !== 'active') {
            throw new Exception("Selected driver is not active");
        }
        
        // Check if driver is already assigned to another active booking
        $activeBookingStmt = $db->prepare("
            SELECT id FROM bookings 
            WHERE driver_id = ? 
            AND status IN ('confirmed', 'in_progress')
        ");
        $activeBookingStmt->execute([$driverId]);
        
        if ($activeBookingStmt->fetch()) {
            throw new Exception("Driver is already assigned to another active booking");
        }
    }

    // Insert booking in database
    $insertStmt = $db->prepare("
        INSERT INTO bookings (
            customer_id,
            pickup_location,
            dropoff_location,
            pickup_datetime,
            notes,
            fare,
            driver_id,
            status,
            created_at,
            updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, 
            ?, NOW(), NOW()
        )
    ");

    // Default status is 'pending' if no driver is assigned, 'confirmed' if driver is assigned
    $status = $driverId ? 'confirmed' : 'pending';

    $insertStmt->execute([
        $customerId,
        $pickupLocation,
        $dropoffLocation,
        $formattedPickupDatetime,
        $notes,
        $fare,
        $driverId,
        $status
    ]);
    
    $bookingId = $db->lastInsertId();

    // Log the action
    logAction('Created new booking #' . $bookingId);
    
    // Commit transaction
    $db->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking_id' => $bookingId,
        'status' => $status
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log error
    error_log('Failed to create booking: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 