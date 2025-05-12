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
$requiredFields = ['booking_id', 'pickup_location', 'dropoff_location', 'pickup_time'];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required field: ' . $field
        ]);
        exit;
    }
}

// Get values from POST data
$bookingId = intval($_POST['booking_id']);
$pickupLocation = trim($_POST['pickup_location']);
$dropoffLocation = trim($_POST['dropoff_location']);
$pickupTime = trim($_POST['pickup_time']);
$price = isset($_POST['price']) ? floatval($_POST['price']) : null;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$customerId = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : null;

// Validate booking ID
if ($bookingId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking ID'
    ]);
    exit;
}

// Validate pickup time format (YYYY-MM-DD HH:MM:SS)
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $pickupTime)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid pickup time format. Use YYYY-MM-DD HH:MM:SS'
    ]);
    exit;
}

// Validate customer ID if provided
if ($customerId !== null && $customerId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID'
    ]);
    exit;
}

// Use a database transaction to ensure data consistency
try {
    // Get database connections
    $ridesDb = getRidesDatabaseConnection();
    $mainDb = getMainDatabaseConnection();
    $ridesDb->beginTransaction();
    
    // Check if booking exists and can be updated
    $bookingStmt = $ridesDb->prepare("
        SELECT id, status, customer_id FROM bookings 
        WHERE id = ?
    ");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    // Check if booking is in a state that can be updated
    $nonUpdatableStatuses = ['completed', 'cancelled'];
    if (in_array($booking['status'], $nonUpdatableStatuses)) {
        throw new Exception("Cannot update a booking with status: " . $booking['status']);
    }
    
    // If changing customer, verify customer exists
    if ($customerId !== null && $customerId !== (int)$booking['customer_id']) {
        // Check if customer exists
        $customerStmt = $mainDb->prepare("
            SELECT id FROM customers 
            WHERE id = ? AND status = 'active'
        ");
        $customerStmt->execute([$customerId]);
        
        if (!$customerStmt->fetch()) {
            throw new Exception("Customer not found or not active");
        }
    } else {
        // Keep existing customer
        $customerId = $booking['customer_id'];
    }
    
    // Build the update query
    $updateFields = [
        "pickup_location = ?",
        "dropoff_location = ?",
        "pickup_time = ?",
        "notes = ?",
        "updated_at = NOW()"
    ];
    
    $updateParams = [
        $pickupLocation,
        $dropoffLocation,
        $pickupTime,
        $notes
    ];
    
    // Add price if provided
    if ($price !== null) {
        $updateFields[] = "price = ?";
        $updateParams[] = $price;
    }
    
    // Add customer_id if provided
    if ($customerId !== null) {
        $updateFields[] = "customer_id = ?";
        $updateParams[] = $customerId;
    }
    
    // Add booking_id at the end of parameters
    $updateParams[] = $bookingId;
    
    // Update booking
    $updateQuery = "UPDATE bookings SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $updateStmt = $ridesDb->prepare($updateQuery);
    $updateStmt->execute($updateParams);
    
    // Log changes
    $changes = [];
    if ($pickupLocation) $changes[] = "pickup_location";
    if ($dropoffLocation) $changes[] = "dropoff_location"; 
    if ($pickupTime) $changes[] = "pickup_time";
    if ($price !== null) $changes[] = "price";
    if ($customerId !== null && $customerId !== (int)$booking['customer_id']) $changes[] = "customer_id";
    if ($notes) $changes[] = "notes";
    
    $changesStr = implode(', ', $changes);
    logAction("Updated Booking #$bookingId: Changed $changesStr");
    
    // Get updated booking
    $updatedBookingStmt = $ridesDb->prepare("
        SELECT b.*, c.name as customer_name 
        FROM bookings b
        LEFT JOIN {$_ENV['DB_NAME']}.customers c ON b.customer_id = c.id
        WHERE b.id = ?
    ");
    $updatedBookingStmt->execute([$bookingId]);
    $updatedBooking = $updatedBookingStmt->fetch(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $ridesDb->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Booking updated successfully',
        'data' => $updatedBooking
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($ridesDb) && $ridesDb->inTransaction()) {
        $ridesDb->rollBack();
    }
    
    // Log error
    error_log('Failed to update booking: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 