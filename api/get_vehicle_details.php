<?php
/**
 * API endpoint to get detailed vehicle information
 * Used by the vehicle details modal in fleet.php
 */

// Set error handling to prevent HTML errors from breaking JSON output
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', '../error.log'); // Set error log path

// Start the session to access session variables
session_start();

// Include necessary files
require_once '../functions/auth.php';
require_once '../functions/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Log request for debugging
error_log("get_vehicle_details.php called with ID: " . ($_GET['id'] ?? 'none'));

try {
    // Check if user is authenticated
    if (!isLoggedIn()) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }
    
    // Check if vehicle ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle ID is required'
        ]);
        exit;
    }
    
    // Sanitize the vehicle ID
    $vehicleId = intval($_GET['id']);
    
    // Connect to database
    $conn = connectToCore1DB();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // First, check if the drivers table has firstname/lastname columns directly
    $checkColumnsQuery = "SHOW COLUMNS FROM drivers LIKE 'firstname'";
    $columnResult = $conn->query($checkColumnsQuery);
    $hasNameColumns = ($columnResult && $columnResult->num_rows > 0);
    
    error_log("Checking drivers table structure, has direct name columns: " . ($hasNameColumns ? "yes" : "no"));
    
    // Check if driver_vehicle_history table exists
    $checkHistoryTable = "SHOW TABLES LIKE 'driver_vehicle_history'";
    $historyTableExists = $conn->query($checkHistoryTable)->num_rows > 0;
    error_log("Checking driver_vehicle_history table existence: " . ($historyTableExists ? "exists" : "doesn't exist"));
    
    // Modified query to avoid cross-database access
    if ($hasNameColumns) {
        // If drivers table has firstname/lastname columns, use them directly
        if ($historyTableExists) {
            $vehicleQuery = "SELECT v.*, 
                                d.license_number, 
                                CONCAT(d.firstname, ' ', d.lastname) as driver_name,
                                dvh.assigned_date
                            FROM vehicles v 
                            LEFT JOIN drivers d ON v.assigned_driver_id = d.driver_id
                            LEFT JOIN driver_vehicle_history dvh ON dvh.vehicle_id = v.vehicle_id 
                                AND dvh.driver_id = v.assigned_driver_id
                                AND dvh.unassigned_date IS NULL
                            WHERE v.vehicle_id = ?";
        } else {
            $vehicleQuery = "SELECT v.*, 
                                d.license_number, 
                                CONCAT(d.firstname, ' ', d.lastname) as driver_name
                            FROM vehicles v 
                            LEFT JOIN drivers d ON v.assigned_driver_id = d.driver_id
                            WHERE v.vehicle_id = ?";
        }
    } else {
        // If drivers table doesn't have name fields, just get basic info
        if ($historyTableExists) {
            $vehicleQuery = "SELECT v.*, 
                                d.license_number,
                                dvh.assigned_date
                            FROM vehicles v 
                            LEFT JOIN drivers d ON v.assigned_driver_id = d.driver_id
                            LEFT JOIN driver_vehicle_history dvh ON dvh.vehicle_id = v.vehicle_id 
                                AND dvh.driver_id = v.assigned_driver_id
                                AND dvh.unassigned_date IS NULL
                            WHERE v.vehicle_id = ?";
        } else {
            $vehicleQuery = "SELECT v.*, 
                                d.license_number
                            FROM vehicles v 
                            LEFT JOIN drivers d ON v.assigned_driver_id = d.driver_id
                            WHERE v.vehicle_id = ?";
        }
    }
    
    error_log("Executing vehicle query: " . $vehicleQuery);
    
    $stmt = $conn->prepare($vehicleQuery);
    if (!$stmt) {
        throw new Exception('Failed to prepare vehicle query: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $vehicleId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute vehicle query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle not found'
        ]);
        exit;
    }
    
    $vehicle = $result->fetch_assoc();
    
    // If we don't have driver_name but have assigned_driver_id, add a placeholder
    if (!isset($vehicle['driver_name']) && !empty($vehicle['assigned_driver_id'])) {
        $vehicle['driver_name'] = 'Driver #' . $vehicle['assigned_driver_id'];
    }
    
    // Check if maintenance_records table exists before querying
    $checkMaintenanceTable = "SHOW TABLES LIKE 'maintenance_records'";
    $maintenanceTableExists = $conn->query($checkMaintenanceTable)->num_rows > 0;
    
    $maintenanceRecords = [];
    
    if ($maintenanceTableExists) {
        // Get maintenance records if table exists
        $maintenanceQuery = "SELECT * FROM maintenance_records 
                            WHERE vehicle_id = ? 
                            ORDER BY service_date DESC LIMIT 5";
        
        $stmt = $conn->prepare($maintenanceQuery);
        if ($stmt) {
            $stmt->bind_param('i', $vehicleId);
            $stmt->execute();
            $maintResult = $stmt->get_result();
            
            while ($row = $maintResult->fetch_assoc()) {
                $maintenanceRecords[] = $row;
            }
        }
    } else {
        error_log("Maintenance_records table doesn't exist, skipping query");
    }
    
    // Check if vehicle_performance table exists before querying
    $checkPerformanceTable = "SHOW TABLES LIKE 'vehicle_performance'";
    $performanceTableExists = $conn->query($checkPerformanceTable)->num_rows > 0;
    
    $performance = null;
    
    if ($performanceTableExists) {
        // Get performance metrics if table exists
        $performanceQuery = "SELECT * FROM vehicle_performance 
                            WHERE vehicle_id = ? 
                            ORDER BY report_date DESC LIMIT 1";
        
        $stmt = $conn->prepare($performanceQuery);
        if ($stmt) {
            $stmt->bind_param('i', $vehicleId);
            $stmt->execute();
            $perfResult = $stmt->get_result();
            
            if ($perfResult->num_rows > 0) {
                $performance = $perfResult->fetch_assoc();
            }
        }
    } else {
        error_log("Vehicle_performance table doesn't exist, skipping query");
    }
    
    // Return the data
    echo json_encode([
        'success' => true,
        'vehicle' => $vehicle,
        'maintenance_records' => $maintenanceRecords,
        'performance' => $performance
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Exception in get_vehicle_details.php: " . $e->getMessage());
    
    // Return a proper JSON error response
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving vehicle details: ' . $e->getMessage()
    ]);
} finally {
    // Close database connection if it exists
    if (isset($conn) && $conn) {
        $conn->close();
    }
} 