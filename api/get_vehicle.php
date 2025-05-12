<?php
/**
 * API endpoint to get vehicle data by ID
 * Used by the vehicle edit modal in fleet.php
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
    error_log("PHP Error in get_vehicle.php: $errstr in $errfile on line $errline");
    
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

    // Check if ID parameter is provided
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle ID is required'
        ]);
        exit;
    }

    // Get the vehicle ID
    $vehicleId = intval($_GET['id']);

    // Connect to database
    $conn = connectToCore1DB();

    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit;
    }

    // Get the vehicle data
    $query = "SELECT * FROM vehicles WHERE vehicle_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle not found'
        ]);
        exit;
    }

    // Fetch the vehicle data
    $vehicle = $result->fetch_assoc();

    // Log raw vehicle data for debugging
    error_log("Raw vehicle data for ID $vehicleId: " . json_encode($vehicle));

    // Make sure status is properly formatted
    if (!isset($vehicle['status']) || trim($vehicle['status']) === '') {
        // If status is empty, set to 'active' as default
        $vehicle['status'] = 'active';
        
        // Update the database with the default status
        $updateQuery = "UPDATE vehicles SET status = 'active', updated_at = NOW() WHERE vehicle_id = ? AND (status IS NULL OR status = '')";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('i', $vehicleId);
        $updateStmt->execute();
        $updateStmt->close();
        
        error_log("Empty status detected for vehicle ID $vehicleId. Set default to 'active'");
    }

    // Explicitly ensure status is a string (avoiding NULL or numeric conversion issues)
    $vehicle['status'] = (string)$vehicle['status'];

    // Debug status field type and value
    error_log("Vehicle status field: value = '" . $vehicle['status'] . "', type = " . gettype($vehicle['status']));

    // Get assigned driver information if assigned
    if ($vehicle['assigned_driver_id']) {
        // First, check if the drivers table has firstname/lastname columns directly
        $checkColumnsQuery = "SHOW COLUMNS FROM drivers LIKE 'firstname'";
        $columnResult = $conn->query($checkColumnsQuery);
        $hasNameColumns = ($columnResult && $columnResult->num_rows > 0);
        
        error_log("Checking drivers table structure, has direct name columns: " . ($hasNameColumns ? "yes" : "no"));
        
        if ($hasNameColumns) {
            // If drivers table has firstname/lastname, use that directly
            $driverQuery = "SELECT d.*, d.firstname, d.lastname
                          FROM drivers d 
                          WHERE d.driver_id = ?";
        } else {
            // If drivers table doesn't have name columns, just get basic driver info
            $driverQuery = "SELECT d.*
                          FROM drivers d 
                          WHERE d.driver_id = ?";
        }
        
        error_log("Executing driver query: " . $driverQuery);
        
        $stmt = $conn->prepare($driverQuery);
        if (!$stmt) {
            error_log("Failed to prepare driver query: " . $conn->error);
        } else {
            $stmt->bind_param('i', $vehicle['assigned_driver_id']);
            
            if (!$stmt->execute()) {
                error_log("Failed to execute driver query: " . $stmt->error);
            } else {
                $driverResult = $stmt->get_result();
                
                if ($driverResult->num_rows > 0) {
                    $driver = $driverResult->fetch_assoc();
                    $vehicle['driver_details'] = $driver;
                    
                    // Create driver_name based on available data
                    if ($hasNameColumns) {
                        $vehicle['driver_name'] = $driver['firstname'] . ' ' . $driver['lastname'];
                    } else {
                        $vehicle['driver_name'] = 'Driver #' . $driver['driver_id'];
                    }
                } else {
                    error_log("No driver found with ID: " . $vehicle['assigned_driver_id']);
                    $vehicle['driver_name'] = 'Driver #' . $vehicle['assigned_driver_id'];
                }
            }
        }
    }

    // Close the statement
    if (isset($stmt)) {
        $stmt->close();
    }
    
    // Close the database connection
    $conn->close();

    // Return the vehicle data
    echo json_encode([
        'success' => true,
        'vehicle' => $vehicle,
        'debug_info' => [
            'status_type' => gettype($vehicle['status']),
            'status_length' => strlen($vehicle['status']),
            'raw_status' => $vehicle['status']
        ]
    ]);
} catch (Exception $e) {
    // Log detailed error for debugging
    error_log("Exception in get_vehicle.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Set proper JSON content type
    header('Content-Type: application/json');
    
    // Return error in JSON format
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving vehicle data: ' . $e->getMessage(),
        'details' => 'Error occurred in: ' . basename($e->getFile()) . ' (Line: ' . $e->getLine() . ')'
    ]);
}
?> 