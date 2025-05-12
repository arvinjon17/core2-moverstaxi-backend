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

// Get reason for unassigning if provided
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Unassigned by administrator';

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
    
    // Check if booking exists and has a driver assigned
    $bookingStmt = $ridesDb->prepare("
        SELECT id, status, driver_id FROM bookings 
        WHERE id = ?
    ");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    // Check if there's a driver to unassign
    if (empty($booking['driver_id'])) {
        throw new Exception("No driver is assigned to this booking");
    }
    
    // Store the driver ID before unassigning for logging purposes
    $driverId = $booking['driver_id'];
    
    // Don't allow unassigning from completed bookings
    if ($booking['status'] === 'completed') {
        throw new Exception("Cannot unassign driver from a completed booking");
    }
    
    // Don't allow unassigning from cancelled bookings
    if ($booking['status'] === 'cancelled') {
        throw new Exception("Cannot unassign driver from a cancelled booking");
    }
    
    // Update booking to remove driver and set status back to pending
    $updateStmt = $ridesDb->prepare("
        UPDATE bookings 
        SET 
            driver_id = NULL,
            status = 'pending',
            updated_at = NOW(),
            driver_unassign_reason = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$reason, $bookingId]);
    
    // Log the action
    logAction("Unassigned Driver #$driverId from Booking #$bookingId. Reason: $reason");
    
    // Commit transaction
    $ridesDb->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Driver unassigned successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($ridesDb) && $ridesDb->inTransaction()) {
        $ridesDb->rollBack();
    }
    
    // Log error
    error_log('Failed to unassign driver: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 