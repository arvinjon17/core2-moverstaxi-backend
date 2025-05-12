<?php
/**
 * Get Bookings List API
 * Retrieves a list of bookings, optionally filtered by status
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
        'message' => 'Unauthorized access. You do not have permission to view bookings.'
    ]);
    exit;
}

// Get status filter if provided
$status = isset($_GET['status']) ? trim($_GET['status']) : null;

// Validate status if provided
$valid_statuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
if ($status !== null && !in_array($status, $valid_statuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status filter. Status must be one of: ' . implode(', ', $valid_statuses)
    ]);
    exit;
}

// Build the query with cross-database joins
$query = "SELECT 
    b.booking_id, b.customer_id, b.user_id, b.pickup_location, b.dropoff_location, 
    b.pickup_datetime, b.dropoff_datetime, b.vehicle_id, b.driver_id, b.booking_status, 
    b.fare_amount, b.distance_km, b.duration_minutes, b.special_instructions, 
    b.cancellation_reason, b.created_at, b.updated_at,
    c.address as customer_address, c.city as customer_city, c.state as customer_state, 
    c.zip as customer_zip, c.notes as customer_notes, c.status as customer_status,
    u.firstname as customer_firstname, u.lastname as customer_lastname, 
    u.email as customer_email, u.phone as customer_phone,
    d.license_number, d.license_expiry, d.rating as driver_rating, d.status as driver_status,
    du.firstname as driver_firstname, du.lastname as driver_lastname,
    du.email as driver_email, du.phone as driver_phone,
    v.model as vehicle_model, v.plate_number, v.year as vehicle_year, v.capacity as vehicle_capacity
FROM 
    core2_movers.bookings b
LEFT JOIN 
    core1_movers.customers c ON b.customer_id = c.customer_id
LEFT JOIN 
    core2_movers.users u ON c.user_id = u.user_id
LEFT JOIN 
    core1_movers.drivers d ON b.driver_id = d.driver_id
LEFT JOIN 
    core2_movers.users du ON d.user_id = du.user_id
LEFT JOIN 
    core1_movers.vehicles v ON b.vehicle_id = v.vehicle_id";

// Add status filter if provided
if ($status !== null) {
    $query .= " WHERE b.booking_status = '" . $status . "'";
}

// Add ordering
$query .= " ORDER BY 
    CASE 
        WHEN b.booking_status = 'pending' THEN 1
        WHEN b.booking_status = 'confirmed' THEN 2
        WHEN b.booking_status = 'in_progress' THEN 3
        WHEN b.booking_status = 'completed' THEN 4
        WHEN b.booking_status = 'cancelled' THEN 5
    END, 
    b.pickup_datetime DESC";

try {
    // Execute the cross-database query
    $bookings = getRows($query, 'core2');
    
    // Return the bookings as JSON
    echo json_encode([
        'success' => true,
        'count' => count($bookings),
        'data' => $bookings
    ]);
    
} catch (Exception $e) {
    error_log("Error retrieving bookings list: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving bookings: ' . $e->getMessage()
    ]);
} 