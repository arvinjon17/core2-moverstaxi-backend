<?php
/**
 * Vehicle List API
 * Retrieves a list of active vehicles for select dropdowns
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
        'message' => 'Unauthorized access. You do not have permission to view vehicle list.'
    ]);
    exit;
}

// Query to get all active vehicles
$query = "SELECT 
    vehicle_id, model, plate_number, year, capacity, status
FROM 
    core1_movers.vehicles
WHERE 
    status = 'active'
ORDER BY 
    model, year DESC";

try {
    // Execute the query
    $vehicles = getRows($query, 'core1');
    
    // Return the vehicles as JSON
    echo json_encode([
        'success' => true,
        'count' => count($vehicles),
        'data' => $vehicles
    ]);
    
} catch (Exception $e) {
    error_log("Error retrieving vehicle list: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving vehicles: ' . $e->getMessage()
    ]);
} 