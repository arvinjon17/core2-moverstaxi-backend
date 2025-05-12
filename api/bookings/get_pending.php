<?php
// API endpoint to get pending bookings for assignment to drivers

// Include necessary files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';
require_once '../../functions/role_management.php';

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check permissions - allow access for admin, super_admin, and dispatch roles
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['admin', 'super_admin', 'dispatch']) && 
    !hasPermission('manage_bookings')) {
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to access this resource'
    ]);
    exit;
}

try {
    // Create connection to core2 database
    $conn = connectToCore2DB();
    
    // Get pending and confirmed bookings without assigned drivers
    $query = "SELECT 
        b.booking_id, 
        b.customer_id, 
        b.pickup_location, 
        b.dropoff_location, 
        b.pickup_datetime, 
        b.booking_status,
        b.fare_amount,
        c.firstname as customer_firstname,
        c.lastname as customer_lastname,
        c.phone as customer_phone
    FROM 
        " . DB_NAME_CORE2 . ".bookings b
    LEFT JOIN 
        " . DB_NAME_CORE2 . ".users c ON b.user_id = c.user_id
    WHERE 
        (b.booking_status = 'pending' OR b.booking_status = 'confirmed')
        AND (b.driver_id IS NULL OR b.driver_id = 0)
    ORDER BY 
        b.pickup_datetime ASC";
    
    $result = $conn->query($query);
    
    if ($result === false) {
        throw new Exception($conn->error);
    }
    
    $bookings = [];
    
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    // Return success response with pending bookings
    echo json_encode([
        'success' => true,
        'data' => $bookings,
        'count' => count($bookings)
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Error in get_pending.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving pending bookings: ' . $e->getMessage()
    ]);
}
?> 