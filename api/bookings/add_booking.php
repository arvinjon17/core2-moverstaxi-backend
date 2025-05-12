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

// Define required fields
$requiredFields = ['customer_id', 'pickup_location', 'dropoff_location', 'pickup_time'];
$missingFields = [];

// Check for missing required fields
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        $missingFields[] = $field;
    }
}

// If there are missing fields, return error
if (!empty($missingFields)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: ' . implode(', ', $missingFields)
    ]);
    exit;
}

// Get and sanitize input data
$customerId = intval($_POST['customer_id']);
$pickupLocation = trim($_POST['pickup_location']);
$dropoffLocation = trim($_POST['dropoff_location']);
$pickupTime = trim($_POST['pickup_time']);
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$vehicleType = isset($_POST['vehicle_type']) ? trim($_POST['vehicle_type']) : 'standard';
$estimatedFare = isset($_POST['estimated_fare']) ? floatval($_POST['estimated_fare']) : 0.00;
$estimatedDistance = isset($_POST['estimated_distance']) ? floatval($_POST['estimated_distance']) : 0.00;
$estimatedDuration = isset($_POST['estimated_duration']) ? intval($_POST['estimated_duration']) : 0;

// Validate pickup time format (expecting Y-m-d H:i:s format)
$pickupDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $pickupTime);
if (!$pickupDateTime) {
    $pickupDateTime = DateTime::createFromFormat('Y-m-d H:i', $pickupTime); // Try alternative format
    if (!$pickupDateTime) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid pickup time format. Expected format: YYYY-MM-DD HH:MM:SS'
        ]);
        exit;
    }
    $pickupTime = $pickupDateTime->format('Y-m-d H:i:s'); // Standardize format
}

// Validate the customer ID
if ($customerId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID'
    ]);
    exit;
}

// Use a database transaction to ensure data consistency
try {
    // Get database connections
    $mainDb = getDatabaseConnection();
    $ridesDb = getRidesDatabaseConnection();
    
    // Start transaction
    $ridesDb->beginTransaction();
    
    // Verify customer exists in main database
    $customerStmt = $mainDb->prepare("SELECT id, name, phone FROM customers WHERE id = ? AND is_active = 1");
    $customerStmt->execute([$customerId]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        throw new Exception("Customer not found or inactive");
    }
    
    // Insert new booking
    $insertStmt = $ridesDb->prepare("
        INSERT INTO bookings (
            customer_id, 
            pickup_location, 
            dropoff_location, 
            pickup_time, 
            estimated_fare, 
            estimated_distance,
            estimated_duration,
            vehicle_type,
            notes,
            status,
            created_at,
            updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW()
        )
    ");
    
    $insertStmt->execute([
        $customerId,
        $pickupLocation,
        $dropoffLocation,
        $pickupTime,
        $estimatedFare,
        $estimatedDistance,
        $estimatedDuration,
        $vehicleType,
        $notes
    ]);
    
    // Get the new booking ID
    $bookingId = $ridesDb->lastInsertId();
    
    // Log the booking creation
    logAction("Created new Booking #$bookingId for Customer #$customerId");
    
    // Commit the transaction
    $ridesDb->commit();
    
    // Return success response with the new booking ID
    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking_id' => $bookingId,
        'booking_details' => [
            'id' => $bookingId,
            'customer_id' => $customerId,
            'customer_name' => $customer['name'],
            'customer_phone' => $customer['phone'],
            'pickup_location' => $pickupLocation,
            'dropoff_location' => $dropoffLocation,
            'pickup_time' => $pickupTime,
            'status' => 'pending',
            'vehicle_type' => $vehicleType,
            'estimated_fare' => $estimatedFare,
            'estimated_distance' => $estimatedDistance,
            'estimated_duration' => $estimatedDuration,
            'notes' => $notes
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($ridesDb) && $ridesDb->inTransaction()) {
        $ridesDb->rollBack();
    }
    
    // Log error
    error_log('Failed to create new booking: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 