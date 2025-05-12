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
$requiredFields = ['booking_id', 'fare_amount'];
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
$fareAmount = floatval($_POST['fare_amount']);
$paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cash';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Validate data
if ($bookingId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking ID'
    ]);
    exit;
}

if ($fareAmount <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Fare amount must be greater than zero'
    ]);
    exit;
}

// Valid payment methods
$validPaymentMethods = ['cash', 'credit_card', 'debit_card', 'mobile_payment', 'prepaid'];
if (!in_array($paymentMethod, $validPaymentMethods)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment method'
    ]);
    exit;
}

// Use a database transaction to ensure data consistency
try {
    // Get database connection
    $db = getRidesDatabaseConnection();
    $db->beginTransaction();

    // Check if booking exists and is in progress
    $bookingStmt = $db->prepare("
        SELECT id, status, driver_id FROM bookings 
        WHERE id = ?
    ");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    if ($booking['status'] !== 'in_progress') {
        throw new Exception("Only in-progress bookings can be completed");
    }

    // Create payment record
    $paymentStmt = $db->prepare("
        INSERT INTO payments (
            booking_id, 
            amount, 
            payment_method, 
            status, 
            payment_date
        )
        VALUES (?, ?, ?, 'completed', NOW())
    ");
    $paymentStmt->execute([
        $bookingId,
        $fareAmount,
        $paymentMethod
    ]);
    
    $paymentId = $db->lastInsertId();

    // Update booking status
    $updateStmt = $db->prepare("
        UPDATE bookings 
        SET status = 'completed',
            actual_fare = ?,
            completion_time = NOW(),
            notes = CONCAT(IFNULL(notes, ''), ?),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $notePrefix = !empty($notes) ? "\n----------\nCompletion Notes: " . $notes : '';
    $updateStmt->execute([$fareAmount, $notePrefix, $bookingId]);

    // Log the action
    logAction('Completed booking #' . $bookingId . ' with fare amount ' . $fareAmount);
    
    // Commit transaction
    $db->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Booking completed successfully',
        'data' => [
            'booking_id' => $bookingId,
            'payment_id' => $paymentId,
            'amount' => $fareAmount,
            'payment_method' => $paymentMethod
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log error
    error_log('Failed to complete booking: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 