<?php
/**
 * Update Booking API
 * Updates an existing booking's details
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
if (!hasPermission('manage_bookings')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. You do not have permission to update bookings.'
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
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
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
$booking_status = isset($_POST['booking_status']) ? trim($_POST['booking_status']) : '';

// Validate required fields
if (empty($booking_id) || empty($customer_id) || empty($pickup_location) || 
    empty($dropoff_location) || empty($pickup_datetime) || empty($booking_status)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields. Please fill in all required fields.'
    ]);
    exit;
}

// Validate booking status
$valid_statuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
if (!in_array($booking_status, $valid_statuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking status. Status must be one of: ' . implode(', ', $valid_statuses)
    ]);
    exit;
}

// Connect to the database
$conn = connectToCore2DB();

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Check if booking exists and get current status
    $checkQuery = "SELECT booking_status FROM bookings WHERE booking_id = ? LIMIT 1";
    $stmt = $conn->prepare($checkQuery);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare check query: " . $conn->error);
    }
    
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception("Booking not found.");
    }
    
    $currentData = $result->fetch_assoc();
    $currentStatus = $currentData['booking_status'];
    $stmt->close();
    
    // Build the update query
    $sql = "UPDATE bookings SET 
            customer_id = ?, 
            pickup_location = ?, 
            dropoff_location = ?, 
            pickup_datetime = ?, 
            dropoff_datetime = ?, 
            vehicle_id = ?, 
            driver_id = ?,
            distance_km = ?, 
            duration_minutes = ?, 
            fare_amount = ?, 
            special_instructions = ?, 
            booking_status = ?,
            updated_at = NOW()
            WHERE booking_id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare update statement: " . $conn->error);
    }
    
    // Bind parameters
    $stmt->bind_param(
        "issssiiddssi",
        $customer_id,
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
        $booking_status,
        $booking_id
    );
    
    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Failed to update booking: " . $stmt->error);
    }
    
    // Check if any rows were affected
    if ($stmt->affected_rows === 0) {
        throw new Exception("No changes were made to the booking.");
    }
    
    $stmt->close();
    
    // Record the booking update in the system_logs table if it exists
    $logQuery = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE action = VALUES(action), description = VALUES(description)";
                
    if ($stmt = $conn->prepare($logQuery)) {
        $adminUserId = $_SESSION['user_id'] ?? 0;
        $action = "booking_updated";
        $description = "Updated booking ID: {$booking_id}";
        
        if ($currentStatus !== $booking_status) {
            $description .= " (Status changed from {$currentStatus} to {$booking_status})";
        }
        
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
        'message' => 'Booking updated successfully.'
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction on error
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }
    
    error_log("Error updating booking: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error updating booking: ' . $e->getMessage()
    ]);
} finally {
    // Close database connection
    if ($conn) {
        $conn->close();
    }
} 