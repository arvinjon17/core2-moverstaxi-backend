<?php

/**
 * API endpoint to add or update vehicle data
 * Used by the add/edit vehicle modal in fleet.php
 */

// Ensure no output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', '../error.log'); // Set error log path

// Additional diagnostic logging
error_log("Starting save_vehicle.php script");
error_log("POST data: " . json_encode($_POST));
error_log("FILES data: " . json_encode($_FILES));

// Function to handle errors and convert to JSON
function handleError($errno, $errstr, $errfile, $errline) {
    $error = array(
        'success' => false,
        'message' => 'PHP Error: ' . $errstr,
        'details' => "File: $errfile, Line: $errline"
    );
    
    // Log the error to a file for debugging
    error_log("PHP Error in save_vehicle.php: $errstr in $errfile on line $errline");
    
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
    
    // Check if action is set
    if (!isset($_POST['action'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Action is required'
        ]);
        exit;
    }
    
    // Get the action
    $action = $_POST['action'];
    
    // Additional logging for debugging status updates
    error_log("API Request: " . $action . " with POST data: " . json_encode($_POST));
    
    // Connect to database
    $conn = connectToCore1DB();
    
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit;
    }
    
    // Handle the action
    if ($action === 'add') {
        // Add a new vehicle
        // Required fields
        $requiredFields = ['plate_number', 'vin', 'model', 'year', 'capacity', 'fuel_type', 'status'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            echo json_encode([
                'success' => false,
                'message' => "Required fields missing: " . implode(', ', $missingFields),
                'debug_data' => [
                    'missing_fields' => $missingFields,
                    'post_data' => $_POST
                ]
            ]);
            exit;
        }
        
        // Get data from POST
        $plateNumber = $_POST['plate_number'];
        $vin = $_POST['vin'];
        $model = $_POST['model'];
        $year = intval($_POST['year']);
        $capacity = intval($_POST['capacity']);
        $fuelType = $_POST['fuel_type'];
        
        // Debug: Log raw status from POST
        error_log("Raw status from POST: " . (isset($_POST['status']) ? $_POST['status'] : 'NULL'));
        
        // Get and validate status - default to 'active' if missing or invalid
        $status = isset($_POST['status']) && trim($_POST['status']) !== '' ? trim($_POST['status']) : 'active';
        
        // Debug: Log status after initial processing
        error_log("Status after initial processing: " . $status);
        
        // Make sure status is one of the valid ENUM values (case-insensitive)
        $validStatuses = ['active', 'maintenance', 'inactive'];
        $status = strtolower($status); // Convert to lowercase for comparison
        
        if (!in_array($status, $validStatuses)) {
            error_log("Invalid status detected: " . $status . ". Defaulting to 'active'");
            $status = 'active'; // Default to 'active' if invalid
        }
        
        // Log the validated status
        error_log("Vehicle status validated: " . $status);
        
        $assignedDriverId = isset($_POST['assigned_driver_id']) && !empty($_POST['assigned_driver_id']) ? 
                            intval($_POST['assigned_driver_id']) : null;
        
        // Handle image upload if provided
        $vehicleImage = null;
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
                // Process the uploaded image
                $targetDir = "../uploads/vehicles/";
                
                // Create directory if it doesn't exist
                if (!file_exists($targetDir)) {
                    error_log("Upload directory doesn't exist, trying to create: " . $targetDir);
                    if (!mkdir($targetDir, 0777, true)) {
                        error_log("Failed to create upload directory: " . $targetDir . " - Error: " . error_get_last()['message']);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Failed to create upload directory: ' . error_get_last()['message']
                        ]);
                        exit;
                    } else {
                        // Ensure directory is writable
                        chmod($targetDir, 0777);
                        error_log("Successfully created upload directory: " . $targetDir);
                    }
                }
                
                // Check if directory is writable
                if (!is_writable($targetDir)) {
                    error_log("Upload directory is not writable: " . $targetDir);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Upload directory is not writable. Please check permissions.'
                    ]);
                    exit;
                }
                
                // Generate a unique filename
                $filename = uniqid() . '_' . basename($_FILES["vehicle_image"]["name"]);
                $targetFile = $targetDir . $filename;
                
                // Check file type and size
                $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                
                // Validate file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($fileType, $allowedTypes)) {
                    error_log("Invalid file type: " . $fileType);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Only JPG, JPEG, PNG & GIF files are allowed'
                    ]);
                    exit;
                }
                
                // Validate file size
                if ($_FILES["vehicle_image"]["size"] > $maxFileSize) {
                    error_log("File too large: " . $_FILES["vehicle_image"]["size"]);
                    echo json_encode([
                        'success' => false,
                        'message' => 'File is too large. Maximum file size is 5MB'
                    ]);
                    exit;
                }
                
                // Move the uploaded file
                if (move_uploaded_file($_FILES["vehicle_image"]["tmp_name"], $targetFile)) {
                    $vehicleImage = 'uploads/vehicles/' . $filename; // Store the relative path
                    error_log("New image uploaded: " . $vehicleImage);
                } else {
                    error_log("Failed to move uploaded file from tmp to target: " . $_FILES["vehicle_image"]["tmp_name"] . " -> " . $targetFile . " - Error: " . error_get_last()['message']);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to upload image. Please try again.'
                    ]);
                    exit;
                }
            } else {
                // Log file upload error
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                    UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                ];
                
                $errorMessage = isset($uploadErrors[$_FILES['vehicle_image']['error']]) ? 
                                $uploadErrors[$_FILES['vehicle_image']['error']] : 
                                'Unknown upload error';
                
                error_log("File upload error: " . $errorMessage);
                echo json_encode([
                    'success' => false,
                    'message' => 'File upload error: ' . $errorMessage
                ]);
                exit;
            }
        }
        
        // Insert the vehicle
        // Handle null assigned_driver_id properly
        if ($assignedDriverId === null) {
            // Use a different approach for NULL values - this was causing errors
            $query = "INSERT INTO vehicles (plate_number, vin, model, year, capacity, fuel_type, status, assigned_driver_id, vehicle_image, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, NOW(), NOW())";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssssisss', $plateNumber, $vin, $model, $year, $capacity, $fuelType, $status, $vehicleImage);
        } else {
            $query = "INSERT INTO vehicles (plate_number, vin, model, year, capacity, fuel_type, status, assigned_driver_id, vehicle_image, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sssisisis', $plateNumber, $vin, $model, $year, $capacity, $fuelType, $status, $assignedDriverId, $vehicleImage);
        }
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Vehicle added successfully',
                'vehicle_id' => $conn->insert_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to add vehicle: ' . $stmt->error
            ]);
        }
    } elseif ($action === 'update') {
        // Special case: Status-only direct update
        if (isset($_POST['status_only_update']) && $_POST['status_only_update'] === '1') {
            error_log("Status-only update detected");
            
            // Check if vehicle_id is set
            if (!isset($_POST['vehicle_id']) || empty($_POST['vehicle_id'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Vehicle ID is required for status updates'
                ]);
                exit;
            }
            
            // Get and validate vehicle ID
            $vehicleId = intval($_POST['vehicle_id']);
            
            // Get and validate status
            if (!isset($_POST['status']) || trim($_POST['status']) === '') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Status is required for status updates'
                ]);
                exit;
            }
            
            // Get and clean status value
            $status = trim($_POST['status']);
            error_log("Status value received: " . $status);
            
            // Normalize to lowercase for consistency with ENUM values
            $status = strtolower($status);
            
            // Validate against allowed values
            $validStatuses = ['active', 'maintenance', 'inactive'];
            if (!in_array($status, $validStatuses)) {
                error_log("Invalid status detected: " . $status . ". Defaulting to 'active'");
                $status = 'active';
            }
            
            // Check if the record exists before updating
            $checkQuery = "SELECT vehicle_id, status FROM vehicles WHERE vehicle_id = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param('i', $vehicleId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows === 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Vehicle not found with ID: ' . $vehicleId
                ]);
                $checkStmt->close();
                exit;
            }
            
            $currentData = $checkResult->fetch_assoc();
            error_log("Current vehicle status in DB: " . $currentData['status'] . " for vehicle ID: " . $vehicleId);
            $checkStmt->close();
            
            // Prepare direct SQL statement
            $directQuery = "UPDATE vehicles SET status = ?, updated_at = NOW() WHERE vehicle_id = ?";
            error_log("Direct status update query: " . $directQuery . " with values: " . $status . ", " . $vehicleId);
            
            $stmt = $conn->prepare($directQuery);
            
            if (!$stmt) {
                error_log("Prepare failed: " . $conn->error);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to prepare status update statement: ' . $conn->error
                ]);
                exit;
            }
            
            // Bind parameters
            $stmt->bind_param('si', $status, $vehicleId);
            
            // Execute the statement
            if ($stmt->execute()) {
                error_log("Status update successful. Affected rows: " . $stmt->affected_rows);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Vehicle status updated successfully',
                    'vehicle_id' => $vehicleId,
                    'new_status' => $status,
                    'affected_rows' => $stmt->affected_rows,
                    'debug_info' => [
                        'previous_status' => $currentData['status'],
                        'new_status' => $status,
                        'query' => 'UPDATE vehicles SET status = "' . $status . '" WHERE vehicle_id = ' . $vehicleId
                    ]
                ]);
            } else {
                error_log("Execute failed: " . $stmt->error);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update vehicle status: ' . $stmt->error
                ]);
            }
            
            // Close the statement and exit
            $stmt->close();
            exit;
        }
        
        // Regular update continues below
        // Check if vehicle_id is set
        if (!isset($_POST['vehicle_id']) || empty($_POST['vehicle_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Vehicle ID is required for updates'
            ]);
            exit;
        }
        
        // Get vehicle ID
        $vehicleId = intval($_POST['vehicle_id']);
        
        // Check if the vehicle exists
        $checkQuery = "SELECT * FROM vehicles WHERE vehicle_id = ?";
        $stmt = $conn->prepare($checkQuery);
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
        
        // Get the existing vehicle data
        $existingVehicle = $result->fetch_assoc();
        
        // Required fields
        $requiredFields = ['plate_number', 'vin', 'model', 'year', 'capacity', 'fuel_type', 'status'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            echo json_encode([
                'success' => false,
                'message' => "Required fields missing: " . implode(', ', $missingFields),
                'debug_data' => [
                    'missing_fields' => $missingFields,
                    'post_data' => $_POST
                ]
            ]);
            exit;
        }
        
        // Get data from POST
        $plateNumber = $_POST['plate_number'];
        $vin = $_POST['vin'];
        $model = $_POST['model'];
        $year = intval($_POST['year']);
        $capacity = intval($_POST['capacity']);
        $fuelType = $_POST['fuel_type'];
        
        // Debug: Log raw status from POST
        error_log("Raw status from POST: " . (isset($_POST['status']) ? $_POST['status'] : 'NULL'));
        
        // Get and validate status - default to 'active' if missing or invalid
        $status = isset($_POST['status']) && trim($_POST['status']) !== '' ? trim($_POST['status']) : 'active';
        
        // Debug: Log status after initial processing
        error_log("Status after initial processing: " . $status);
        
        // Make sure status is one of the valid ENUM values (case-insensitive)
        $validStatuses = ['active', 'maintenance', 'inactive'];
        $status = strtolower($status); // Convert to lowercase for comparison
        
        if (!in_array($status, $validStatuses)) {
            error_log("Invalid status detected: " . $status . ". Defaulting to 'active'");
            $status = 'active'; // Default to 'active' if invalid
        }
        
        // Log the validated status
        error_log("Vehicle status validated: " . $status);
        
        // Handle assigned_driver_id field properly
        if (isset($_POST['assigned_driver_id']) && $_POST['assigned_driver_id'] !== '') {
            $assignedDriverId = intval($_POST['assigned_driver_id']);
        } else {
            $assignedDriverId = null;
        }
        
        // Handle image upload if provided
        $vehicleImage = $existingVehicle['vehicle_image']; // Keep existing image by default
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
                // Process the uploaded image
                $targetDir = "../uploads/vehicles/";
                
                // Create directory if it doesn't exist
                if (!file_exists($targetDir)) {
                    error_log("Upload directory doesn't exist, trying to create: " . $targetDir);
                    if (!mkdir($targetDir, 0777, true)) {
                        error_log("Failed to create upload directory: " . $targetDir . " - Error: " . error_get_last()['message']);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Failed to create upload directory: ' . error_get_last()['message']
                        ]);
                        exit;
                    } else {
                        // Ensure directory is writable
                        chmod($targetDir, 0777);
                        error_log("Successfully created upload directory: " . $targetDir);
                    }
                }
                
                // Check if directory is writable
                if (!is_writable($targetDir)) {
                    error_log("Upload directory is not writable: " . $targetDir);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Upload directory is not writable. Please check permissions.'
                    ]);
                    exit;
                }
                
                // Generate a unique filename
                $filename = uniqid() . '_' . basename($_FILES["vehicle_image"]["name"]);
                $targetFile = $targetDir . $filename;
                
                // Check file type and size
                $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                
                // Validate file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($fileType, $allowedTypes)) {
                    error_log("Invalid file type: " . $fileType);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Only JPG, JPEG, PNG & GIF files are allowed'
                    ]);
                    exit;
                }
                
                // Validate file size
                if ($_FILES["vehicle_image"]["size"] > $maxFileSize) {
                    error_log("File too large: " . $_FILES["vehicle_image"]["size"]);
                    echo json_encode([
                        'success' => false,
                        'message' => 'File is too large. Maximum file size is 5MB'
                    ]);
                    exit;
                }
                
                // Move the uploaded file
                if (move_uploaded_file($_FILES["vehicle_image"]["tmp_name"], $targetFile)) {
                    // Delete the old image if it exists
                    if ($existingVehicle['vehicle_image'] && file_exists('../' . $existingVehicle['vehicle_image'])) {
                        // Check if we can delete the file and log if we can't
                        if (!unlink('../' . $existingVehicle['vehicle_image'])) {
                            error_log("Warning: Failed to delete old image file: " . '../' . $existingVehicle['vehicle_image']);
                            // Continue anyway, this is not critical
                        } else {
                            error_log("Successfully deleted old image: " . $existingVehicle['vehicle_image']);
                        }
                    }
                    
                    $vehicleImage = 'uploads/vehicles/' . $filename; // Store the relative path
                    error_log("New image uploaded: " . $vehicleImage);
                } else {
                    error_log("Failed to move uploaded file from tmp to target: " . $_FILES["vehicle_image"]["tmp_name"] . " -> " . $targetFile . " - Error: " . error_get_last()['message']);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to upload new image. Please try again.'
                    ]);
                    exit;
                }
            } else {
                // Log file upload error
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                    UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                ];
                
                $errorMessage = isset($uploadErrors[$_FILES['vehicle_image']['error']]) ? 
                                $uploadErrors[$_FILES['vehicle_image']['error']] : 
                                'Unknown upload error';
                
                error_log("File upload error: " . $errorMessage);
                echo json_encode([
                    'success' => false,
                    'message' => 'File upload error: ' . $errorMessage
                ]);
                exit;
            }
        }
        
        // Begin transaction to ensure data consistency
        $conn->begin_transaction();
        
        try {
            // Now update the database record
            // First, create the SQL query based on whether assigned_driver_id is null or not
            if ($assignedDriverId === null) {
                // For null assigned_driver_id, explicitly set it to NULL in the query
                $query = "UPDATE vehicles 
                     SET plate_number = ?, 
                         vin = ?, 
                         model = ?, 
                         year = ?, 
                         capacity = ?, 
                         fuel_type = ?, 
                         status = ?, 
                         assigned_driver_id = NULL, 
                         vehicle_image = ?,
                         updated_at = NOW() 
                     WHERE vehicle_id = ?";
                
                // When vehicle_image is NULL or empty, keep the existing image
                if (empty($vehicleImage)) {
                    $vehicleImage = $existingVehicle['vehicle_image'];
                }
                
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement: " . $conn->error);
                }
                
                // The bind_param format string should match the number of ? placeholders
                // For the first query with NULL assigned_driver_id (9 parameters)
                $result = $stmt->bind_param('ssssisssi', $plateNumber, $vin, $model, $year, $capacity, $fuelType, $status, $vehicleImage, $vehicleId);
                
                if (!$result) {
                    throw new Exception("Bind parameters failed: " . $stmt->error);
                }
            } else {
                // For non-null assigned_driver_id
                $query = "UPDATE vehicles 
                     SET plate_number = ?, 
                         vin = ?, 
                         model = ?, 
                         year = ?, 
                         capacity = ?, 
                         fuel_type = ?, 
                         status = ?, 
                         assigned_driver_id = ?, 
                         vehicle_image = ?,
                         updated_at = NOW() 
                     WHERE vehicle_id = ?";
                
                // When vehicle_image is NULL or empty, keep the existing image
                if (empty($vehicleImage)) {
                    $vehicleImage = $existingVehicle['vehicle_image'];
                }
                
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement: " . $conn->error);
                }
                
                // The bind_param format string should match the number of ? placeholders
                // For the second query with assigned_driver_id (10 parameters)
                $result = $stmt->bind_param('sssisisisi', $plateNumber, $vin, $model, $year, $capacity, $fuelType, $status, $assignedDriverId, $vehicleImage, $vehicleId);
                
                if (!$result) {
                    throw new Exception("Bind parameters failed: " . $stmt->error);
                }
            }
            
            // Execute the statement
            if ($stmt->execute()) {
                // Commit the transaction
                $conn->commit();
                
                // Send success response
                echo json_encode([
                    'success' => true,
                    'message' => 'Vehicle updated successfully',
                    'vehicle_id' => $vehicleId,
                    'status' => $status,
                    'affected_rows' => $stmt->affected_rows
                ]);
            } else {
                // Rollback on error
                $conn->rollback();
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update vehicle: ' . $stmt->error
                ]);
            }
            
            // Close the statement
            $stmt->close();
        } catch (Exception $e) {
            // Rollback on exception
            $conn->rollback();
            
            // Log detailed error for debugging
            error_log("Exception in save_vehicle.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            
            echo json_encode([
                'success' => false,
                'message' => 'Error updating vehicle: ' . $e->getMessage(),
                'details' => 'Error occurred in: ' . basename($e->getFile()) . ' (Line: ' . $e->getLine() . ')'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action: ' . $action
        ]);
    }
    
    // Close the database connection
    $conn->close();
} catch (Exception $e) {
    // Handle unexpected exceptions
    error_log("Unexpected exception in save_vehicle.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage(),
        'details' => 'Error occurred in: ' . basename($e->getFile()) . ' (Line: ' . $e->getLine() . ')'
    ]);
}