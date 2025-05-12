<?php
/**
 * Booking Status Update API
 * Updates the status of a booking (pending, confirmed, in_progress, completed, cancelled)
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
        'message' => 'Unauthorized access. You do not have permission to update booking status.'
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

// Get input data (for compatibility with both form data and JSON)
$inputData = json_decode(file_get_contents('php://input'), true);
if (empty($inputData)) {
    $inputData = $_POST;
}

// Check for required fields
if (!isset($inputData['booking_id']) || !is_numeric($inputData['booking_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking ID.'
    ]);
    exit;
}

if (!isset($inputData['status']) || !in_array($inputData['status'], ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value. Status must be "pending", "confirmed", "in_progress", "completed", or "cancelled".'
    ]);
    exit;
}

// Get the booking ID and status
$bookingId = (int)$inputData['booking_id'];
$newStatus = $inputData['status'];
$cancellationReason = isset($inputData['cancellation_reason']) ? trim($inputData['cancellation_reason']) : '';

// Debugging
error_log("Processing booking status update for booking ID: {$bookingId}, new status: {$newStatus}");

// Connect to the database
$conn = connectToCore2DB();

if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to connect to database.'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Verify the booking exists
    $checkQuery = "SELECT booking_id, booking_status FROM bookings WHERE booking_id = ? LIMIT 1";
    $stmt = $conn->prepare($checkQuery);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        $stmt->close();
        throw new Exception("Booking not found.");
    }
    
    $bookingRow = $result->fetch_assoc();
    $currentStatus = $bookingRow['booking_status'];
    $stmt->close();
    
    // Validate the status transition
    $validTransitions = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [], // No further transitions allowed
        'cancelled' => [] // No further transitions allowed
    ];
    
    if (!in_array($newStatus, $validTransitions[$currentStatus]) && $newStatus !== $currentStatus) {
        throw new Exception("Invalid status transition from {$currentStatus} to {$newStatus}.");
    }
    
    // Update the booking status
    $updateQuery = "UPDATE bookings SET booking_status = ?";
    $params = [$newStatus];
    $types = "s";
    
    // Add cancellation reason if provided and status is 'cancelled'
    if ($newStatus === 'cancelled' && !empty($cancellationReason)) {
        $updateQuery .= ", cancellation_reason = ?";
        $params[] = $cancellationReason;
        $types .= "s";
    }
    
    // Add updated_at timestamp
    $updateQuery .= ", updated_at = NOW() WHERE booking_id = ?";
    $params[] = $bookingId;
    $types .= "i";
    
    $stmt = $conn->prepare($updateQuery);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare update query: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update booking status: " . $stmt->error);
    }
    
    // Check if any rows were affected
    if ($stmt->affected_rows === 0) {
        throw new Exception("No changes made to booking status.");
    }
    
    $stmt->close();
    
    // Record the status change in the system_logs table if it exists
    // This is optional but recommended for audit purposes
    $logQuery = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE action = VALUES(action), description = VALUES(description)";
                
    if ($stmt = $conn->prepare($logQuery)) {
        $adminUserId = $_SESSION['user_id'] ?? 0;
        $action = "booking_status_update";
        $description = "Booking ID: {$bookingId} status changed from {$currentStatus} to {$newStatus}";
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
        'message' => 'Booking status has been updated successfully to ' . ucfirst($newStatus) . '.'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }
    
    error_log("Error updating booking status: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error updating booking status: ' . $e->getMessage()
    ]);
} finally {
    // Close database connection
    if ($conn) {
        $conn->close();
    }
} 