<?php
/**
 * Create New Booking API
 * Creates a new booking in the system
 */

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';

// Check if the user is logged in and has permission to manage bookings
if (!hasPermission('manage_bookings') && !hasPermission('create_booking')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. You do not have permission to create bookings.'
    ]);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST is supported.'
    ]);
    exit;
}

// Get input data
$customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
$pickup_location = isset($_POST['pickup_location']) ? trim($_POST['pickup_location']) : '';
$dropoff_location = isset($_POST['dropoff_location']) ? trim($_POST['dropoff_location']) : '';
$pickup_datetime = isset($_POST['pickup_datetime']) ? trim($_POST['pickup_datetime']) : '';
$dropoff_datetime = isset($_POST['dropoff_datetime']) ? trim($_POST['dropoff_datetime']) : null;
$vehicle_id = isset($_POST['vehicle_id']) && !empty($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : null;
$driver_id = isset($_POST['driver_id']) && !empty($_POST['driver_id']) ? intval($_POST['driver_id']) : null;
$distance_km = isset($_POST['distance_km']) && !empty($_POST['distance_km']) ? floatval($_POST['distance_km']) : null;
$duration_minutes = isset($_POST['duration_minutes']) && !empty($_POST['duration_minutes']) ? intval($_POST['duration_minutes']) : null;
$fare_amount = isset($_POST['fare_amount']) && !empty($_POST['fare_amount']) ? floatval($_POST['fare_amount']) : null;
$special_instructions = isset($_POST['special_instructions']) ? trim($_POST['special_instructions']) : '';
$booking_status = 'pending'; // Default status for new bookings
$user_id = $_SESSION['user_id']; // Current user creating the booking

// Validate required fields
if (empty($customer_id) || empty($pickup_location) || empty($dropoff_location) || empty($pickup_datetime)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields. Please fill in all required fields.'
    ]);
    exit;
}

// Connect to the database
$conn = connectToCore2DB();

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Prepare SQL statement
    $sql = "INSERT INTO bookings (
                customer_id, user_id, pickup_location, dropoff_location, 
                pickup_datetime, dropoff_datetime, vehicle_id, driver_id,
                distance_km, duration_minutes, fare_amount, 
                special_instructions, booking_status, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, 
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, NOW(), NOW()
            )";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    // Bind parameters
    $stmt->bind_param(
        "iissssiiddss",
        $customer_id,
        $user_id,
        $pickup_location,
        $dropoff_location,
        $pickup_datetime,
        $dropoff_datetime,
        $vehicle_id,
        $driver_id,
        $distance_km,
        $duration_minutes,
        $fare_amount,
        $special_instructions,
        $booking_status
    );
    
    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Failed to create booking: " . $stmt->error);
    }
    
    // Get the ID of the newly created booking
    $booking_id = $conn->insert_id;
    
    // Close the statement
    $stmt->close();
    
    // Record the booking creation in the system_logs table if it exists
    $logQuery = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE action = VALUES(action), description = VALUES(description)";
                
    if ($stmt = $conn->prepare($logQuery)) {
        $adminUserId = $_SESSION['user_id'] ?? 0;
        $action = "booking_created";
        $description = "Created new booking ID: {$booking_id}";
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $stmt->bind_param('isss', $adminUserId, $action, $description, $ipAddress);
        $stmt->execute();
        $stmt->close();
    }
    
    // Commit the transaction
    $conn->commit();
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully.',
        'booking_id' => $booking_id
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction on error
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }
    
    error_log("Error creating booking: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error creating booking: ' . $e->getMessage()
    ]);
} finally {
    // Close database connection
    if ($conn) {
        $conn->close();
    }
} 