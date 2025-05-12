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

// Required fields validation
if (!isset($_POST['booking_id']) || !trim($_POST['booking_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID is required'
    ]);
    exit;
}

// Get cancellation reason if provided
$cancellationReason = isset($_POST['cancellation_reason']) ? trim($_POST['cancellation_reason']) : 'Cancelled by administrator';

// Get and validate booking ID
$bookingId = intval($_POST['booking_id']);
if ($bookingId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking ID'
    ]);
    exit;
}

// Use a database transaction to ensure data consistency
try {
    // Get database connection
    $ridesDb = getRidesDatabaseConnection();
    $ridesDb->beginTransaction();
    
    // Check if booking exists and can be cancelled
    $bookingStmt = $ridesDb->prepare("
        SELECT id, status, driver_id FROM bookings 
        WHERE id = ?
    ");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    // Don't allow cancelling already completed or cancelled bookings
    if ($booking['status'] === 'completed') {
        throw new Exception("Cannot cancel a completed booking");
    }
    
    if ($booking['status'] === 'cancelled') {
        throw new Exception("This booking is already cancelled");
    }
    
    // Update booking status to cancelled
    $updateStmt = $ridesDb->prepare("
        UPDATE bookings 
        SET 
            status = 'cancelled',
            cancellation_reason = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$cancellationReason, $bookingId]);
    
    // If driver was assigned, update their status in the system
    if ($booking['driver_id']) {
        // Log driver release - this would be a good place to trigger a notification
        logAction("Driver #" . $booking['driver_id'] . " released from Booking #$bookingId due to cancellation");
    }
    
    // Log the cancellation action
    logAction("Cancelled Booking #$bookingId. Reason: $cancellationReason");
    
    // Commit transaction
    $ridesDb->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Booking cancelled successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($ridesDb) && $ridesDb->inTransaction()) {
        $ridesDb->rollBack();
    }
    
    // Log error
    error_log('Failed to cancel booking: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 