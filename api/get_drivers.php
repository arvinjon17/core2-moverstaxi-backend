<?php
/**
 * API endpoint to get available drivers for vehicle assignment
 * Used by the add/edit vehicle modal in fleet.php
 */

// Ensure no output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', '../error.log'); // Set error log path

// Function to handle errors and convert to JSON
function handleError($errno, $errstr, $errfile, $errline) {
    $error = array(
        'success' => false,
        'message' => 'PHP Error: ' . $errstr,
        'details' => "File: $errfile, Line: $errline"
    );
    
    // Log the error to a file for debugging
    error_log("PHP Error in get_drivers.php: $errstr in $errfile on line $errline");
    
    // Set proper JSON content type
    header('Content-Type: application/json');
    
    // Return JSON error
    echo json_encode($error);
    exit;
}

// Set the error handler
set_error_handler('handleError');

try {
    // Start the session to access session variables
    session_start();

    // Include necessary files
    require_once '../functions/auth.php';
    require_once '../functions/db.php';

    // Set content type to JSON
    header('Content-Type: application/json');

    // Check if user is authenticated
    if (!isLoggedIn()) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }

    // Connect to databases
    $conn = connectToCore1DB();
    $core2Conn = connectToCore2DB();

    if (!$conn || !$core2Conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit;
    }

    // Get the current vehicle ID (if any)
    $vehicleId = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

    // First, check if the drivers table has firstname/lastname columns
    $checkColumnsQuery = "SHOW COLUMNS FROM drivers LIKE 'firstname'";
    $columnResult = $conn->query($checkColumnsQuery);
    $hasNameColumns = ($columnResult && $columnResult->num_rows > 0);
    
    error_log("Checking drivers table structure, has direct name columns: " . ($hasNameColumns ? "yes" : "no"));

    // Modified query to avoid cross-database access
    // This query uses only the drivers and vehicles tables without joining to core1_movers2.users
    if ($hasNameColumns) {
        // If drivers table has firstname/lastname columns, use them directly
        $query = "SELECT d.driver_id, d.license_number, d.firstname, d.lastname, d.status, 
                    v.vehicle_id as current_vehicle_id, v.plate_number as current_vehicle
              FROM drivers d
              LEFT JOIN vehicles v ON d.driver_id = v.assigned_driver_id
              WHERE d.status != 'inactive'
              ORDER BY 
                  CASE WHEN v.vehicle_id IS NULL THEN 0 ELSE 1 END, 
                  CASE WHEN v.vehicle_id = ? THEN 0 ELSE 1 END,
                  d.firstname, d.lastname";
    } else {
        // If drivers table doesn't have name columns, select without them
        // We'll add placeholder names based on driver_id
        $query = "SELECT d.driver_id, d.license_number, d.status, 
                    v.vehicle_id as current_vehicle_id, v.plate_number as current_vehicle
              FROM drivers d
              LEFT JOIN vehicles v ON d.driver_id = v.assigned_driver_id
              WHERE d.status != 'inactive'
              ORDER BY 
                  CASE WHEN v.vehicle_id IS NULL THEN 0 ELSE 1 END, 
                  CASE WHEN v.vehicle_id = ? THEN 0 ELSE 1 END,
                  d.driver_id";
    }
    
    error_log("Executing modified driver query: " . $query);
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param('i', $vehicleId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $drivers = [];
    while ($row = $result->fetch_assoc()) {
        // If the driver table doesn't have name columns, add placeholder names
        if (!$hasNameColumns) {
            $row['firstname'] = 'Driver';
            $row['lastname'] = '#' . $row['driver_id'] . ' (' . $row['license_number'] . ')';
        }
        $drivers[] = $row;
    }
    
    error_log("Successfully fetched " . count($drivers) . " drivers");
    
    // Close statements and connections
    $stmt->close();
    $conn->close();
    
    // We no longer need the core2Conn since we're not doing cross-database queries
    if (isset($core2Conn) && $core2Conn) {
        $core2Conn->close();
    }
    
    // Return the results
    echo json_encode([
        'success' => true,
        'drivers' => $drivers
    ]);
} catch (Exception $e) {
    // Log the error
    error_log("Exception in get_drivers.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Ensure content type is set to JSON
    header('Content-Type: application/json');
    
    // Return error in JSON format
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving drivers: ' . $e->getMessage(),
        'details' => 'Error occurred in: ' . basename($e->getFile()) . ' (Line: ' . $e->getLine() . ')'
    ]);
    
    // Try to close any open connections
    if (isset($stmt) && $stmt) $stmt->close();
    if (isset($conn) && $conn) $conn->close();
    if (isset($core2Conn) && $core2Conn) $core2Conn->close();
} 