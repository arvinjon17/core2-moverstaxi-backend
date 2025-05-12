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
$requiredFields = ['booking_id', 'cancel_reason'];
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
$cancelReason = trim($_POST['cancel_reason']);
$cancelledBy = isset($_POST['cancelled_by']) ? trim($_POST['cancelled_by']) : 'admin';

// Validate data
if ($bookingId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking ID'
    ]);
    exit;
}

if (strlen($cancelReason) < 5) {
    echo json_encode([
        'success' => false,
        'message' => 'Cancellation reason must be at least 5 characters'
    ]);
    exit;
}

// Valid cancellation sources
$validCancelledBy = ['admin', 'driver', 'customer', 'system'];
if (!in_array($cancelledBy, $validCancelledBy)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid cancellation source'
    ]);
    exit;
}

// Use a database transaction to ensure data consistency
try {
    // Get database connection
    $db = getRidesDatabaseConnection();
    $db->beginTransaction();

    // Check if booking exists and is in a cancellable state
    $bookingStmt = $db->prepare("
        SELECT id, status, driver_id, customer_id FROM bookings 
        WHERE id = ?
    ");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    // Check if booking can be cancelled (not already completed or cancelled)
    $nonCancellableStatuses = ['completed', 'cancelled'];
    if (in_array($booking['status'], $nonCancellableStatuses)) {
        throw new Exception("Cannot cancel a booking that is already {$booking['status']}");
    }

    // Record cancellation
    $cancelStmt = $db->prepare("
        INSERT INTO booking_cancellations (
            booking_id, 
            cancelled_by, 
            reason, 
            cancelled_at
        )
        VALUES (?, ?, ?, NOW())
    ");
    $cancelStmt->execute([
        $bookingId,
        $cancelledBy,
        $cancelReason
    ]);
    
    // Update booking status
    $updateStmt = $db->prepare("
        UPDATE bookings 
        SET status = 'cancelled',
            cancellation_reason = ?,
            cancellation_time = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $updateStmt->execute([$cancelReason, $bookingId]);

    // If a driver was assigned, free them up
    if (!empty($booking['driver_id'])) {
        // Log that driver has been unassigned
        logAction('Driver #' . $booking['driver_id'] . ' unassigned from booking #' . $bookingId . ' due to cancellation');
    }

    // Log the action
    logAction('Cancelled booking #' . $bookingId . ' - Reason: ' . $cancelReason);
    
    // Commit transaction
    $db->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Booking cancelled successfully',
        'data' => [
            'booking_id' => $bookingId,
            'status' => 'cancelled',
            'cancel_reason' => $cancelReason
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
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