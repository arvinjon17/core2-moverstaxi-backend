<?php
/**
 * API endpoint to delete a vehicle
 * Used by the delete functionality in fleet.php
 */

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

// Check if user has permission to manage vehicles
if (!hasPermission('manage_fleet')) {
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to manage vehicles'
    ]);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Check if ID is provided
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vehicle ID is required'
    ]);
    exit;
}

// Get vehicle ID
$vehicleId = intval($_POST['id']);

// Connect to database
$conn = connectToCore1DB();

if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // First, check if the vehicle exists
    $checkQuery = "SELECT vehicle_id, vehicle_image FROM vehicles WHERE vehicle_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('i', $vehicleId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle not found'
        ]);
        $checkStmt->close();
        exit;
    }
    
    // Get existing vehicle data for image deletion
    $vehicle = $result->fetch_assoc();
    $vehicleImage = $vehicle['vehicle_image'];
    
    // Close the check statement
    $checkStmt->close();
    
    // Check if the vehicle has associated records in dependent tables
    // 1. Check maintenance_records
    $maintenanceQuery = "SELECT COUNT(*) as count FROM maintenance_records WHERE vehicle_id = ?";
    $maintenanceStmt = $conn->prepare($maintenanceQuery);
    $maintenanceStmt->bind_param('i', $vehicleId);
    $maintenanceStmt->execute();
    $maintenanceResult = $maintenanceStmt->get_result();
    $maintenanceCount = $maintenanceResult->fetch_assoc()['count'];
    $maintenanceStmt->close();
    
    // 2. Check vehicle_performance
    $performanceQuery = "SELECT COUNT(*) as count FROM vehicle_performance WHERE vehicle_id = ?";
    $performanceStmt = $conn->prepare($performanceQuery);
    $performanceStmt->bind_param('i', $vehicleId);
    $performanceStmt->execute();
    $performanceResult = $performanceStmt->get_result();
    $performanceCount = $performanceResult->fetch_assoc()['count'];
    $performanceStmt->close();
    
    // 3. Check fuel_records
    $fuelQuery = "SELECT COUNT(*) as count FROM fuel_records WHERE vehicle_id = ?";
    $fuelStmt = $conn->prepare($fuelQuery);
    $fuelStmt->bind_param('i', $vehicleId);
    $fuelStmt->execute();
    $fuelResult = $fuelStmt->get_result();
    $fuelCount = $fuelResult->fetch_assoc()['count'];
    $fuelStmt->close();
    
    // 4. Check if vehicle is assigned to any driver
    $driverQuery = "SELECT COUNT(*) as count FROM drivers WHERE assigned_driver_id = ?";
    $driverStmt = $conn->prepare($driverQuery);
    $driverStmt->bind_param('i', $vehicleId);
    $driverStmt->execute();
    $driverResult = $driverStmt->get_result();
    $driverCount = $driverResult->fetch_assoc()['count'];
    $driverStmt->close();
    
    // Delete any associated records if they exist
    if ($maintenanceCount > 0) {
        $deleteMaintenanceQuery = "DELETE FROM maintenance_records WHERE vehicle_id = ?";
        $deleteMaintenanceStmt = $conn->prepare($deleteMaintenanceQuery);
        $deleteMaintenanceStmt->bind_param('i', $vehicleId);
        $deleteMaintenanceStmt->execute();
        $deleteMaintenanceStmt->close();
    }
    
    if ($performanceCount > 0) {
        $deletePerformanceQuery = "DELETE FROM vehicle_performance WHERE vehicle_id = ?";
        $deletePerformanceStmt = $conn->prepare($deletePerformanceQuery);
        $deletePerformanceStmt->bind_param('i', $vehicleId);
        $deletePerformanceStmt->execute();
        $deletePerformanceStmt->close();
    }
    
    if ($fuelCount > 0) {
        $deleteFuelQuery = "DELETE FROM fuel_records WHERE vehicle_id = ?";
        $deleteFuelStmt = $conn->prepare($deleteFuelQuery);
        $deleteFuelStmt->bind_param('i', $vehicleId);
        $deleteFuelStmt->execute();
        $deleteFuelStmt->close();
    }
    
    // Update any drivers assigned to this vehicle
    $updateDriverQuery = "UPDATE drivers SET assigned_driver_id = NULL WHERE assigned_driver_id = ?";
    $updateDriverStmt = $conn->prepare($updateDriverQuery);
    $updateDriverStmt->bind_param('i', $vehicleId);
    $updateDriverStmt->execute();
    $updateDriverStmt->close();
    
    // Now delete the vehicle
    $deleteQuery = "DELETE FROM vehicles WHERE vehicle_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param('i', $vehicleId);
    
    if ($deleteStmt->execute()) {
        // Commit the transaction
        $conn->commit();
        
        // Delete the vehicle image if it exists
        if ($vehicleImage && file_exists('../' . $vehicleImage)) {
            unlink('../' . $vehicleImage);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Vehicle deleted successfully',
            'vehicle_id' => $vehicleId,
            'affected_rows' => $deleteStmt->affected_rows
        ]);
    } else {
        // Rollback the transaction
        $conn->rollback();
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete vehicle: ' . $deleteStmt->error
        ]);
    }
    
    // Close the statement
    $deleteStmt->close();
} catch (Exception $e) {
    // Rollback the transaction on error
    if ($conn) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting vehicle: ' . $e->getMessage()
    ]);
} finally {
    // Close the database connection
    if ($conn) {
        $conn->close();
    }
} 