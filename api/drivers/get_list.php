<?php
/**
 * Driver List API
 * Retrieves a list of available drivers for select dropdowns
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
        'message' => 'Unauthorized access. You do not have permission to view driver list.'
    ]);
    exit;
}

// Query to get all active drivers with user details
$query = "SELECT 
    d.driver_id, u.firstname, u.lastname, u.email, u.phone, 
    d.license_number, d.license_expiry, d.status, d.rating
FROM 
    core1_movers.drivers d
JOIN 
    core1_movers2.users u ON d.user_id = u.user_id
WHERE 
    d.status IN ('available', 'active') AND u.status = 'active'
ORDER BY 
    u.lastname, u.firstname";

try {
    // Execute the cross-database query
    $drivers = getRows($query, 'core1');
    
    // Return the drivers as JSON
    echo json_encode([
        'success' => true,
        'count' => count($drivers),
        'data' => $drivers
    ]);
    
} catch (Exception $e) {
    error_log("Error retrieving driver list: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving drivers: ' . $e->getMessage()
    ]);
} 