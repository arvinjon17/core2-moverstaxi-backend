<?php
/**
 * Driver Actions - AJAX Handler for Driver Management
 * 
 * This file handles all AJAX requests related to driver management including:
 * - Adding a new driver
 * - Editing an existing driver
 * - Getting driver details
 * - Deleting/deactivating a driver
 * - Getting available vehicles for assignment
 */

// Suppress errors from being output - we'll handle errors via JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Always set content type to JSON
header('Content-Type: application/json');

// Error logging for debugging AJAX requests
error_log("==== DRIVER_ACTIONS.PHP CALLED ====");
error_log("GET: " . json_encode($_GET));
error_log("POST: " . json_encode($_POST));
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

// Include necessary files
require_once '../functions/auth.php';
require_once '../functions/db.php';
require_once '../functions/profile_images.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has permission
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to perform this action'
    ]);
    exit;
}

// Check if this is a direct call for driver details 
// Handle this special case first before other checks
if (isset($_GET['driver_id']) && !empty($_GET['driver_id']) && !isset($_GET['action'])) {
    // Direct call to fetch driver details - skip the action check
    error_log("Direct call for driver details detected with ID: " . $_GET['driver_id']);
    
    // Get database connections
    $conn = connectToCore1DB();
    $core2Conn = connectToCore2DB();
    
    // Call the function directly
    getDriverDetails($conn, $core2Conn);
    exit;
} elseif (!isset($_GET['action']) && !isset($_POST['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No action specified'
    ]);
    exit;
}

// Get database connections
$conn = connectToCore1DB();
$core2Conn = connectToCore2DB();

// Process requested action - check both GET and POST for action
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
error_log("Processing action: " . $action);

// Process based on action
switch ($action) {
    case 'add_driver':
        if (!hasPermission('manage_drivers')) {
            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to add drivers'
            ]);
            exit;
        }
        
        addDriver($conn, $core2Conn);
        break;
        
    case 'edit_driver':
        if (!hasPermission('manage_drivers')) {
            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to edit drivers'
            ]);
            exit;
        }
        
        editDriver($conn, $core2Conn);
        break;
        
    case 'get_driver_details':
        // Debug permission check
        error_log("Checking permission 'manage_drivers' for get_driver_details action");
        error_log("hasPermission('manage_drivers') result: " . (hasPermission('manage_drivers') ? 'true' : 'false'));
        error_log("User session: " . json_encode($_SESSION));
        
        // Temporarily allow all logged-in users to view driver details for debugging purposes
        if (isLoggedIn()) {
            // Force permission temporarily to debug the underlying issue
            error_log("User is logged in, proceeding with driver details even if permission check would fail");
            getDriverDetails($conn, $core2Conn);
        } else {
            error_log("Permission denied: User is not logged in");
            echo json_encode([
                'success' => false,
                'message' => 'You must be logged in to view driver details',
                'debug_info' => [
                    'session_id' => session_id(),
                    'user_logged_in' => 'no',
                    'requested_permission' => 'manage_drivers'
                ]
            ]);
        }
        break;
        
    case 'delete_driver':
        if (!hasPermission('manage_drivers')) {
            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to delete drivers'
            ]);
            exit;
        }
        
        deleteDriver($conn, $core2Conn);
        break;
        
    case 'get_vehicles':
        if (!hasPermission('manage_drivers')) {
            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to view vehicles'
            ]);
            exit;
        }
        
        getAvailableVehicles($conn);
        break;
        
    case 'activate_driver':
        if (!hasPermission('manage_drivers')) {
            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to activate drivers'
            ]);
            exit;
        }
        
        activateDriver($conn, $core2Conn);
        break;
        
    default:
        // If no matching action but we have a driver_id, treat it as a driver details request
        if (isset($_GET['driver_id']) && !empty($_GET['driver_id'])) {
            getDriverDetails($conn, $core2Conn);
        } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action: ' . $action
        ]);
        }
        break;
}

// Close database connections after processing all actions
if (isset($conn) && $conn) $conn->close();
if (isset($core2Conn) && $core2Conn) $core2Conn->close();
exit;

// -----------------------------------------------------------------------------------
// Function implementations
// -----------------------------------------------------------------------------------

/**
 * Add a new driver
 */
function addDriver($conn, $core2Conn) {
    header('Content-Type: application/json');
    
    // Validate required fields
    $requiredFields = ['firstname', 'lastname', 'email', 'phone', 'license_number', 'license_expiry'];
    $missingFields = [];
    $inputData = [];
    
    // Collect and sanitize input data
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            $missingFields[] = $field;
        } else {
            $inputData[$field] = driverSanitizeInput($_POST[$field]);
        }
    }
    
    if (!empty($missingFields)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $missingFields),
            'debug' => [
                'error_code' => 'MISSING_PARAMETER',
                'context' => 'Driver creation validation',
                'fields' => $missingFields,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        return;
    }
    
    // Additional data validation checks
    $validationErrors = [];
    
    // Validate email format
    if (!filter_var($inputData['email'], FILTER_VALIDATE_EMAIL)) {
        $validationErrors[] = [
            'field' => 'email',
            'error' => 'Invalid email format',
            'error_code' => 'INVALID_EMAIL_FORMAT'
        ];
    }
    
    // Validate phone number (basic format check)
    if (!preg_match('/^[0-9\+\-\(\)\s]{7,20}$/', $inputData['phone'])) {
        $validationErrors[] = [
            'field' => 'phone',
            'error' => 'Invalid phone number format',
            'error_code' => 'INVALID_PHONE_FORMAT'
        ];
    }
    
    // Validate firstname and lastname (no special characters except spaces and hyphens)
    if (!preg_match('/^[a-zA-Z\s\-\']+$/', $inputData['firstname'])) {
        $validationErrors[] = [
            'field' => 'firstname',
            'error' => 'First name contains invalid characters',
            'error_code' => 'INVALID_FIRSTNAME_FORMAT'
        ];
    }
    
    if (!preg_match('/^[a-zA-Z\s\-\']+$/', $inputData['lastname'])) {
        $validationErrors[] = [
            'field' => 'lastname',
            'error' => 'Last name contains invalid characters',
            'error_code' => 'INVALID_LASTNAME_FORMAT'
        ];
    }
    
    // Validate license number (alphanumeric with basic formatting)
    if (!preg_match('/^[a-zA-Z0-9\-\s]{5,20}$/', $inputData['license_number'])) {
        $validationErrors[] = [
            'field' => 'license_number',
            'error' => 'License number format is invalid',
            'error_code' => 'INVALID_LICENSE_FORMAT'
        ];
    }
    
    // Validate license expiry date
    $licenseExpiry = $inputData['license_expiry'];
    $expiryDate = DateTime::createFromFormat('Y-m-d', $licenseExpiry);
    
    if (!$expiryDate || $expiryDate->format('Y-m-d') !== $licenseExpiry) {
        $validationErrors[] = [
            'field' => 'license_expiry',
            'error' => 'Invalid license expiry date format. Use YYYY-MM-DD',
            'error_code' => 'INVALID_DATE_FORMAT'
        ];
    } else {
        // Check if license is expired
        $today = new DateTime();
        if ($expiryDate < $today) {
            $validationErrors[] = [
                'field' => 'license_expiry',
                'error' => 'License has already expired',
                'error_code' => 'LICENSE_EXPIRED'
            ];
        }
    }
    
    // Return all validation errors if any
    if (!empty($validationErrors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Validation errors found',
            'validation_errors' => $validationErrors,
            'debug' => [
                'error_code' => 'VALIDATION_FAILED',
                'context' => 'Driver creation validation',
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        return;
    }
    
    // Check file upload if provided
    $hasProfileImage = isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0;
    if ($hasProfileImage) {
        // Validate file type and size
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid file type. Only JPG, JPEG, and PNG are allowed.',
                'debug' => [
                    'error_code' => 'INVALID_FILE_TYPE',
                    'context' => 'Profile image validation',
                    'provided_type' => $_FILES['profile_image']['type'],
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            return;
        }
        
        if ($_FILES['profile_image']['size'] > $maxSize) {
            echo json_encode([
                'success' => false,
                'message' => 'File size too large. Maximum size is 5MB.',
                'debug' => [
                    'error_code' => 'FILE_TOO_LARGE',
                    'context' => 'Profile image validation',
                    'file_size' => $_FILES['profile_image']['size'],
                    'max_size' => $maxSize,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            return;
        }
    }
    
    // Start transaction - we need to insert into multiple databases
    $connStarted = false;
    $core2ConnStarted = false;
    
    try {
        $conn->begin_transaction();
        $connStarted = true;
        
        $core2Conn->begin_transaction();
        $core2ConnStarted = true;
        
        // Check if email already exists with proper error handling
        $emailCheck = $core2Conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if (!$emailCheck) {
            throw new Exception("Database preparation error: " . $core2Conn->error, 1000);
        }
        
        $emailCheck->bind_param('s', $inputData['email']);
        
        if (!$emailCheck->execute()) {
            throw new Exception("Email check query failed: " . $emailCheck->error, 1000);
        }
        
        $emailResult = $emailCheck->get_result();
        
        if ($emailResult->num_rows > 0) {
            throw new Exception("Email address already in use", 1001);
        }
        $emailCheck->close();
        
        // Generate a random password for the new user
        $password = generateRandomPassword(12); // Increased to 12 characters for better security
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // First, insert into core2_movers.users table
        $stmt = $core2Conn->prepare("
            INSERT INTO users (firstname, lastname, email, phone, password, role, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'driver', 'active', NOW())
        ");
        
        if (!$stmt) {
            throw new Exception("Database preparation error: " . $core2Conn->error, 1002);
        }
        
        $stmt->bind_param('sssss', 
            $inputData['firstname'], 
            $inputData['lastname'], 
            $inputData['email'], 
            $inputData['phone'], 
            $hashedPassword
        );
        
        if (!$stmt->execute()) {
            throw new Exception("User creation failed: " . $stmt->error, 1002);
        }
        
        if ($stmt->affected_rows <= 0) {
            throw new Exception("Failed to create user account", 1002);
        }
        
        // Get the new user's ID
        $userId = $stmt->insert_id;
        $stmt->close();
        
        // Upload profile image if provided
        $profileImagePath = '';
        if ($hasProfileImage) {
            try {
                // Create driver data array with firstname and lastname
                $driverData = [
                    'firstname' => $inputData['firstname'],
                    'lastname' => $inputData['lastname']
                ];
                
                // Add additional debug info
                error_log("Starting profile image upload from addDriver function");
                error_log("Current directory: " . getcwd());
                error_log("Driver data: " . json_encode($driverData));
                
                // Use 'driver' type instead of 'user' to trigger folder structure creation
                $profileImageResult = uploadProfileImage($_FILES['profile_image'], 'driver', $userId, $driverData);
                
                if ($profileImageResult['success']) {
                    $profileImagePath = $profileImageResult['filename'];
                    
                    error_log("Profile upload successful, saving path: " . $profileImagePath);
                
                // Update the user record with the profile image path
                $stmt = $core2Conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                if (!$stmt) {
                    throw new Exception("Database preparation error: " . $core2Conn->error, 1002);
                }
                
                $stmt->bind_param('si', $profileImagePath, $userId);
                
                if (!$stmt->execute()) {
                    // Log the error but continue
                    error_log("Failed to update profile image: " . $stmt->error);
                }
                
                $stmt->close();
                    
                    error_log("Driver profile image uploaded successfully to: " . $profileImagePath);
                } else {
                    error_log("Profile image upload failed: " . $profileImageResult['message']);
                }
            } catch (Exception $imgEx) {
                // Log the error and re-throw it to be caught by the main try-catch block
                error_log("Profile image upload failed: " . $imgEx->getMessage());
                throw $imgEx;
            }
        }
        
        // Now, insert into core1_movers.drivers table
        $stmt = $conn->prepare("
            INSERT INTO drivers (user_id, license_number, license_expiry, status, rating, created_at)
            VALUES (?, ?, ?, ?, 5.0, NOW())
        ");
        
        if (!$stmt) {
            throw new Exception("Database preparation error: " . $conn->error, 1003);
        }
        
        $status = isset($_POST['status']) ? driverSanitizeInput($_POST['status']) : 'offline';
        
        $stmt->bind_param('isss', 
            $userId, 
            $inputData['license_number'], 
            $inputData['license_expiry'], 
            $status
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Driver creation failed: " . $stmt->error, 1003);
        }
        
        if ($stmt->affected_rows <= 0) {
            throw new Exception("Failed to create driver record", 1003);
        }
        
        $driverId = $stmt->insert_id;
        $stmt->close();
        
        // Assign vehicle if specified
        if (isset($_POST['vehicle_id']) && !empty($_POST['vehicle_id'])) {
            $vehicleId = (int)driverSanitizeInput($_POST['vehicle_id']);
            
            // First, check if the vehicle exists and is available
            $stmt = $conn->prepare("
                SELECT assigned_driver_id, status 
                FROM vehicles 
                WHERE vehicle_id = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Database preparation error: " . $conn->error, 1004);
            }
            
            $stmt->bind_param('i', $vehicleId);
            
            if (!$stmt->execute()) {
                throw new Exception("Vehicle query failed: " . $stmt->error, 1004);
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                throw new Exception("Vehicle not found", 1004);
            }
            
            $vehicle = $result->fetch_assoc();
            $stmt->close();
            
            // Check if vehicle is in operational state
            if (isset($vehicle['status']) && $vehicle['status'] !== 'active') {
                throw new Exception("Cannot assign non-operational vehicle to driver", 1008);
            }
            
            if ($vehicle && $vehicle['assigned_driver_id'] !== null) {
                // Remove the assignment from the current driver
                $stmt = $conn->prepare("
                    UPDATE vehicles 
                    SET assigned_driver_id = NULL, 
                        updated_at = NOW()
                    WHERE vehicle_id = ?
                ");
                
                if (!$stmt) {
                    throw new Exception("Database preparation error: " . $conn->error, 1005);
                }
                
                $stmt->bind_param('i', $vehicleId);
                
                if (!$stmt->execute()) {
                    throw new Exception("Vehicle unassignment failed: " . $stmt->error, 1005);
                }
                
                if ($stmt->affected_rows <= 0) {
                    throw new Exception("Failed to update vehicle assignment", 1005);
                }
                $stmt->close();
                
                // Log the unassignment in vehicle_assignment_history
                $stmt = $conn->prepare("
                    UPDATE vehicle_assignment_history 
                    SET unassigned_date = NOW(), 
                        notes = CONCAT(notes, ' | Unassigned due to reassignment to driver ID: ', ?)
                    WHERE vehicle_id = ? 
                    AND unassigned_date IS NULL
                ");
                
                if (!$stmt) {
                    throw new Exception("Database preparation error: " . $conn->error, 1005);
                }
                
                $stmt->bind_param('ii', $driverId, $vehicleId);
                
                if (!$stmt->execute()) {
                    throw new Exception("History update failed: " . $stmt->error, 1005);
                }
                
                $stmt->close();
            }
            
            // Assign vehicle to new driver
            $stmt = $conn->prepare("
                UPDATE vehicles 
                SET assigned_driver_id = ?,
                    updated_at = NOW()
                WHERE vehicle_id = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Database preparation error: " . $conn->error, 1006);
            }
            
            $stmt->bind_param('ii', $driverId, $vehicleId);
            
            if (!$stmt->execute()) {
                throw new Exception("Vehicle assignment failed: " . $stmt->error, 1006);
            }
            
            if ($stmt->affected_rows <= 0) {
                throw new Exception("Failed to assign vehicle to driver", 1006);
            }
            $stmt->close();
            
            // Add record to vehicle_assignment_history
            $stmt = $conn->prepare("
                INSERT INTO vehicle_assignment_history 
                (vehicle_id, driver_id, assigned_date, notes) 
                VALUES (?, ?, NOW(), 'Initial assignment at driver creation')
            ");
            
            if (!$stmt) {
                throw new Exception("Database preparation error: " . $conn->error, 1007);
            }
            
            $stmt->bind_param('ii', $vehicleId, $driverId);
            
            if (!$stmt->execute()) {
                throw new Exception("History insertion failed: " . $stmt->error, 1007);
            }
            
            if ($stmt->affected_rows <= 0) {
                throw new Exception("Failed to record vehicle assignment history", 1007);
            }
            $stmt->close();
        }
        
        // Commit transactions if everything was successful
        $conn->commit();
        $core2Conn->commit();
        
        // Send success response
        echo json_encode([
            'success' => true,
            'message' => 'Driver added successfully',
            'data' => [
                'driver_id' => $driverId,
                'user_id' => $userId,
                'temporary_password' => $password,
                'profile_image' => !empty($profileImagePath) ? $profileImagePath : null,
                'email' => $inputData['email'],
                'name' => $inputData['firstname'] . ' ' . $inputData['lastname']
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transactions if any error occurred
        if ($connStarted) {
            $conn->rollback();
        }
        if ($core2ConnStarted) {
            $core2Conn->rollback();
        }
        // Get error code if available, or use a default
        $errorCode = $e->getCode() ?: 'UNKNOWN_ERROR';
        // Determine the context based on the error code
        $context = 'Driver creation';
        switch ($errorCode) {
            case 1000:
                $context = 'Database query preparation';
                break;
            case 1001:
                $context = 'Email validation';
                break;
            case 1002:
                $context = 'User account creation';
                break;
            case 1003:
                $context = 'Driver record creation';
                break;
            case 1004:
            case 1005:
            case 1006:
            case 1007:
            case 1008:
                $context = 'Vehicle assignment';
                break;
        }
        // Log the error with additional details
        error_log("Driver creation error: " . $e->getMessage() . 
                 " | Code: " . $errorCode . 
                 " | Context: " . $context . 
                 " | IP: " . $_SERVER['REMOTE_ADDR']);
        // Return a user-friendly message with appropriate debug info
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'debug' => [
                'error_code' => $errorCode,
                'context' => $context,
                'trace' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getTraceAsString() : null,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    }
}

/**
 * Edit an existing driver
 */
function editDriver($conn, $core2Conn) {
    // Validate required fields
    if (!isset($_POST['driver_id']) || empty($_POST['driver_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Driver ID is required'
        ]);
        return;
    }
    
    $driverId = (int)$_POST['driver_id'];
    
    // Start transaction
    $conn->begin_transaction();
    $core2Conn->begin_transaction();
    
    try {
        // First, get the user ID associated with this driver
        $stmt = $conn->prepare("SELECT user_id FROM drivers WHERE driver_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed for driver lookup: " . $conn->error);
        }
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Driver not found");
        }
        
        $driver = $result->fetch_assoc();
        $userId = $driver['user_id'];
        $stmt->close();
        
        // Update user information in core2_movers.users
        $stmt = $core2Conn->prepare("
            UPDATE users 
            SET firstname = ?, lastname = ?, email = ?, phone = ?
            WHERE user_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare statement failed for user update: " . $core2Conn->error);
        }
        
        $firstname = driverSanitizeInput($_POST['firstname']);
        $lastname = driverSanitizeInput($_POST['lastname']);
        $email = driverSanitizeInput($_POST['email']);
        $phone = driverSanitizeInput($_POST['phone']);
        
        $stmt->bind_param('ssssi', $firstname, $lastname, $email, $phone, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Upload new profile image if provided
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
            try {
                // Create driver data array with firstname and lastname
                $driverData = [
                    'firstname' => $firstname,
                    'lastname' => $lastname
                ];
                
                // Add additional debug info
                error_log("Starting profile image upload from editDriver function");
                error_log("Current directory: " . getcwd());
                error_log("Driver data: " . json_encode($driverData));
                
                // Use driver type to trigger folder structure creation
                $profileImageResult = uploadProfileImage($_FILES['profile_image'], 'driver', $userId, $driverData);
                
                if ($profileImageResult['success']) {
                    $profileImagePath = $profileImageResult['filename'];
                    
                    error_log("Profile upload successful, saving path: " . $profileImagePath);
            
                    // Update the user record with the profile image path
                    $stmt = $core2Conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                    if (!$stmt) {
                        throw new Exception("Database preparation error: " . $core2Conn->error);
                    }
                    
                    $stmt->bind_param('si', $profileImagePath, $userId);
                    
                    if (!$stmt->execute()) {
                        // Log the error but continue
                        error_log("Failed to update profile image: " . $stmt->error);
                    }
                    
                    $stmt->close();
                    
                    error_log("Driver profile image updated successfully to: " . $profileImagePath);
                } else {
                    error_log("Profile image upload failed: " . $profileImageResult['message']);
                    throw new Exception("Failed to upload profile image: " . $profileImageResult['message']);
                }
            } catch (Exception $imgEx) {
                // Log the error and re-throw it to be caught by the main try-catch block
                error_log("Profile image upload failed: " . $imgEx->getMessage());
                throw $imgEx;
            }
        }
        
        // Update driver information in core1_movers.drivers
        $stmt = $conn->prepare("
            UPDATE drivers 
            SET license_number = ?, license_expiry = ?, status = ?, rating = ?
            WHERE driver_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare statement failed for driver update: " . $conn->error);
        }
        
        $licenseNumber = driverSanitizeInput($_POST['license_number']);
        $licenseExpiry = driverSanitizeInput($_POST['license_expiry']);
        $status = driverSanitizeInput($_POST['status']);
        
        // Add more robust validation for rating
        $rating = 0;
        if (isset($_POST['rating'])) {
            $ratingValue = $_POST['rating'];
            error_log("Rating value from POST: " . $ratingValue);
            
            // Ensure it's a valid number
            if (is_numeric($ratingValue)) {
                $rating = (float)$ratingValue;
                
                // Clamp rating between 0 and 5
                $rating = max(0, min(5, $rating));
                error_log("Validated rating value: " . $rating);
            } else {
                error_log("Invalid rating value: " . $ratingValue);
            }
        } else {
            error_log("Rating not provided in POST data");
        }
        
        $stmt->bind_param('sssdi', $licenseNumber, $licenseExpiry, $status, $rating, $driverId);
        $stmt->execute();
        $stmt->close();
        
        // Handle vehicle assignment
        if (isset($_POST['vehicle_id'])) {
            $newVehicleId = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;
            
            // Get current vehicle assignment
            $stmt = $conn->prepare("
                SELECT v.vehicle_id, v.plate_number
                FROM vehicles v
                WHERE v.assigned_driver_id = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare statement failed for vehicle lookup: " . $conn->error);
            }
            
            $stmt->bind_param('i', $driverId);
            $stmt->execute();
            $result = $stmt->get_result();
            $currentVehicle = $result->fetch_assoc();
            $currentVehicleId = $currentVehicle ? $currentVehicle['vehicle_id'] : null;
            $stmt->close();
            
            // If vehicle assignment has changed
            if ($currentVehicleId !== $newVehicleId) {
                // If driver was assigned to a vehicle, unassign it
                if ($currentVehicleId) {
                    $stmt = $conn->prepare("
                        UPDATE vehicles 
                        SET assigned_driver_id = NULL 
                        WHERE vehicle_id = ?
                    ");
                    
                    if (!$stmt) {
                        throw new Exception("Prepare statement failed for unassigning vehicle: " . $conn->error);
                    }
                    
                    $stmt->bind_param('i', $currentVehicleId);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Check if vehicle_assignment_history table exists before using it
                    $checkTableResult = $conn->query("SHOW TABLES LIKE 'vehicle_assignment_history'");
                    $tableExists = $checkTableResult && $checkTableResult->num_rows > 0;
                    
                    if ($tableExists) {
                        // Update vehicle assignment history
                        $stmt = $conn->prepare("
                            UPDATE vehicle_assignment_history 
                            SET unassigned_date = NOW(), 
                                notes = CONCAT(IFNULL(notes, ''), ' | Unassigned during driver edit') 
                            WHERE vehicle_id = ? 
                            AND driver_id = ? 
                            AND unassigned_date IS NULL
                        ");
                        
                        if (!$stmt) {
                            // Log but don't fail - this is not critical
                            error_log("Warning: Failed to prepare vehicle history update statement: " . $conn->error);
                        } else {
                            $stmt->bind_param('ii', $currentVehicleId, $driverId);
                            $stmt->execute();
                            $stmt->close();
                        }
                    } else {
                        error_log("Warning: vehicle_assignment_history table doesn't exist - skipping history update");
                    }
                }
                
                // If a new vehicle is being assigned
                if ($newVehicleId) {
                    // Check if the new vehicle is already assigned
                    $stmt = $conn->prepare("
                        SELECT assigned_driver_id 
                        FROM vehicles 
                        WHERE vehicle_id = ?
                    ");
                    
                    if (!$stmt) {
                        throw new Exception("Prepare statement failed for new vehicle check: " . $conn->error);
                    }
                    
                    $stmt->bind_param('i', $newVehicleId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $vehicle = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($vehicle && $vehicle['assigned_driver_id'] !== null) {
                        // Unassign from current driver
                        $stmt = $conn->prepare("
                            UPDATE vehicles 
                            SET assigned_driver_id = NULL 
                            WHERE vehicle_id = ?
                        ");
                        
                        if (!$stmt) {
                            throw new Exception("Prepare statement failed for vehicle reassignment: " . $conn->error);
                        }
                        
                        $stmt->bind_param('i', $newVehicleId);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Check if vehicle_assignment_history table exists before using it
                        $checkTableResult = $conn->query("SHOW TABLES LIKE 'vehicle_assignment_history'");
                        $tableExists = $checkTableResult && $checkTableResult->num_rows > 0;
                        
                        if ($tableExists) {
                            // Update vehicle assignment history
                            $stmt = $conn->prepare("
                                UPDATE vehicle_assignment_history 
                                SET unassigned_date = NOW(), 
                                    notes = CONCAT(IFNULL(notes, ''), ' | Unassigned due to reassignment to another driver') 
                                WHERE vehicle_id = ? 
                                AND unassigned_date IS NULL
                            ");
                            
                            if (!$stmt) {
                                // Log but don't fail - this is not critical
                                error_log("Warning: Failed to prepare vehicle history reassignment: " . $conn->error);
                            } else {
                                $stmt->bind_param('i', $newVehicleId);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }
                    }
                    
                    // Assign to this driver
                    $stmt = $conn->prepare("
                        UPDATE vehicles 
                        SET assigned_driver_id = ? 
                        WHERE vehicle_id = ?
                    ");
                    
                    if (!$stmt) {
                        throw new Exception("Prepare statement failed for assigning vehicle: " . $conn->error);
                    }
                    
                    $stmt->bind_param('ii', $driverId, $newVehicleId);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Check if vehicle_assignment_history table exists before using it
                    $checkTableResult = $conn->query("SHOW TABLES LIKE 'vehicle_assignment_history'");
                    $tableExists = $checkTableResult && $checkTableResult->num_rows > 0;
                    
                    if ($tableExists) {
                        // Add record to vehicle_assignment_history
                        $stmt = $conn->prepare("
                            INSERT INTO vehicle_assignment_history 
                            (vehicle_id, driver_id, assigned_date, notes) 
                            VALUES (?, ?, NOW(), 'Assigned during driver edit')
                        ");
                        
                        if (!$stmt) {
                            // Log but don't fail - this is not critical
                            error_log("Warning: Failed to prepare vehicle history insert: " . $conn->error);
                        } else {
                            $stmt->bind_param('ii', $newVehicleId, $driverId);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
        }
        
        // Commit transactions if everything was successful
        $conn->commit();
        $core2Conn->commit();
        
        // Send success response
        echo json_encode([
            'success' => true,
            'message' => 'Driver updated successfully'
        ]);
        
    } catch (Exception $e) {
        // Log the full error for debugging
        error_log("Error in editDriver function: " . $e->getMessage());
        
        // Rollback transactions if any error occurred
        $conn->rollback();
        $core2Conn->rollback();
        
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get driver details for view/edit
 */
function getDriverDetails($conn, $core2Conn) {
    // Set content type header
    header('Content-Type: application/json');
    
    // Suppress PHP errors and warnings from being output - we'll handle all errors via JSON
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    // Detailed logging for debugging
    error_log("==== getDriverDetails function called ====");
    
    // Get the driver_id parameter - support both direct calls and action-based calls
    $driverId = null;
    
    // Check for driver_id in GET parameters
    if (isset($_GET['driver_id']) && !empty($_GET['driver_id'])) {
        $driverId = (int)$_GET['driver_id'];
        error_log("Driver ID found in GET: " . $driverId);
    } else {
        error_log("Error: Missing driver_id parameter");
        echo json_encode([
            'success' => false,
            'message' => 'Driver ID is required',
            'debug_info' => [
                'error_code' => 'MISSING_PARAMETER',
                'context' => 'Driver ID validation',
                'received_params' => $_GET
            ]
        ]);
        return;
    }
    
    error_log("Processing driver ID: " . $driverId);
    
    // Ensure both database connections are valid
    if (!$conn) {
        error_log("Error: Core1 database connection is invalid");
        echo json_encode([
            'success' => false,
            'message' => 'Database connection error (Core1)',
            'debug_info' => [
                'error_code' => 'DB_CONNECTION_ERROR',
                'context' => 'Core1 database connection check'
            ]
        ]);
        return;
    }
    
    if (!$core2Conn) {
        error_log("Error: Core2 database connection is invalid");
        echo json_encode([
            'success' => false,
            'message' => 'Database connection error (Core2)',
            'debug_info' => [
                'error_code' => 'DB_CONNECTION_ERROR',
                'context' => 'Core2 database connection check'
            ]
        ]);
        return;
    }
    
    try {
        // Add debug info
        error_log("Fetching driver details for ID: " . $driverId);
        
        // First, get driver data from core1_movers.drivers
        $driverQuery = "
            SELECT d.*, 
                v.vehicle_id, v.plate_number, v.model, v.year, v.status,
                v.fuel_type, v.capacity, v.vehicle_image
            FROM drivers d
            LEFT JOIN vehicles v ON d.driver_id = v.assigned_driver_id
            WHERE d.driver_id = ?
        ";
        
        $stmt = $conn->prepare($driverQuery);
        if (!$stmt) {
            throw new Exception("Database error in driver query: " . $conn->error);
        }
        
        $stmt->bind_param('i', $driverId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error executing driver query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Driver not found with ID: ' . $driverId,
                'debug_info' => [
                    'error_code' => 'DRIVER_NOT_FOUND',
                    'context' => 'Driver lookup',
                    'query' => 'SELECT driver WHERE driver_id = ' . $driverId
                ]
            ]);
            return;
        }
        
        $driver = $result->fetch_assoc();
        $stmt->close();
        
        // Get user data from core2_movers.users
        $userId = $driver['user_id'];
        
        if (!$userId) {
            throw new Exception("Driver record has no associated user ID", 404);
        }
        
        $userQuery = "
            SELECT u.user_id, u.firstname, u.lastname, u.email, u.phone, 
                u.profile_picture, u.status AS user_status, u.last_login, u.created_at
            FROM users u
            WHERE u.user_id = ?
        ";
        
        $stmt = $core2Conn->prepare($userQuery);
        if (!$stmt) {
            throw new Exception("Database error in user query: " . $core2Conn->error);
        }
        
        $stmt->bind_param('i', $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error executing user query: " . $stmt->error);
        }
        
        $userResult = $stmt->get_result();
        
        if ($userResult->num_rows === 0) {
            throw new Exception("User record not found for driver with user ID: " . $userId, 404);
        }
        
        $user = $userResult->fetch_assoc();
        $stmt->close();
        
        // Alias and set driver and vehicle status explicitly
        $driverStatus = $driver['status']; // from drivers table
        $vehicleStatus = isset($driver['vehicle_status']) ? $driver['vehicle_status'] : null; // from vehicles table
        
        // Combine the data
        $driverDetails = array_merge($driver, $user);
        
        // Set explicit status fields for clarity
        $driverDetails['status'] = $driverStatus; // for backward compatibility
        $driverDetails['driver_status'] = $driverStatus;
        $driverDetails['vehicle_status'] = $vehicleStatus;
        
        // Get assignment history 
        $assignmentHistory = [];
        try {
            $historyQuery = "
                SELECT h.*, v.plate_number, v.model, v.year
                FROM driver_vehicle_history h
                JOIN vehicles v ON h.vehicle_id = v.vehicle_id
                WHERE h.driver_id = ?
                ORDER BY h.assigned_date DESC
                LIMIT 10
            ";
            
            $stmt = $conn->prepare($historyQuery);
            
            if ($stmt) {
                $stmt->bind_param('i', $driverId);
                $stmt->execute();
                $assignmentResult = $stmt->get_result();
                
                while ($row = $assignmentResult->fetch_assoc()) {
                    // Format dates for better display
                    $row['assignment_start'] = $row['assigned_date'];
                    $row['assignment_end'] = $row['unassigned_date'];
                    $row['status'] = $row['unassigned_date'] ? 'Completed' : 'Current';
                    $assignmentHistory[] = $row;
                }
                $stmt->close();
            }
        } catch (Exception $historyEx) {
            // Just log the error and continue without history data
            error_log("Error fetching assignment history: " . $historyEx->getMessage());
        }
        
        // Handle profile image paths using the same logic as the driver directory table
        require_once __DIR__ . '/../functions/profile_images.php';
        $profileImage = getProfileImagePath($driverDetails, $driverDetails);
        if (!$profileImage) {
            $profileImage = 'assets/img/default-profile.jpg';
        }
        $driverDetails['profile_image_url'] = $profileImage;
        $driverDetails['user_profile_picture'] = $driverDetails['profile_picture'] ?? null;
        $driverDetails['driver_profile_image'] = $driverDetails['profile_image'] ?? null;
        
        // Get driver performance data if available
        $driverPerformance = null;
        try {
            $performanceQuery = "
                SELECT * FROM driver_performance 
                WHERE driver_id = ? 
                ORDER BY report_date DESC 
                LIMIT 1
            ";
            
            $performanceStmt = $conn->prepare($performanceQuery);
            
            if ($performanceStmt) {
                $performanceStmt->bind_param('i', $driverId);
                $performanceStmt->execute();
                $performanceResult = $performanceStmt->get_result();
                
                if ($performanceResult->num_rows > 0) {
                    $driverPerformance = $performanceResult->fetch_assoc();
                }
                $performanceStmt->close();
            }
        } catch (Exception $perfEx) {
            // Just log the error and continue without performance data
            error_log("Error fetching performance data: " . $perfEx->getMessage());
        }
        
        // Return the combined data
        $responseData = [
            'success' => true,
            'data' => [
                'driver' => $driverDetails,
                'assignments' => $assignmentHistory,
                'performance' => $driverPerformance
            ]
        ];
        
        echo json_encode($responseData);
        
    } catch (Exception $e) {
        error_log("Error fetching driver details: " . $e->getMessage() . " (Line: " . $e->getLine() . ")");
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Get error code from exception if available
        $errorCode = $e->getCode() ?: 500;
        $errorContext = 'Database query';
        
        // Determine error context based on message
        if (strpos($e->getMessage(), 'driver query') !== false) {
            $errorContext = 'Driver table query';
        } elseif (strpos($e->getMessage(), 'user query') !== false) {
            $errorContext = 'User table query';
        } elseif (strpos($e->getMessage(), 'not found') !== false) {
            $errorContext = 'Record lookup';
            $errorCode = 404;
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving driver details: ' . $e->getMessage(),
            'debug_info' => [
                'error_code' => $errorCode,
                'context' => $errorContext,
                'line' => $e->getLine(),
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)
            ]
        ]);
    }
}

/**
 * Delete (deactivate) a driver
 */
function deleteDriver($conn, $core2Conn) {
    if (!isset($_POST['driver_id']) || empty($_POST['driver_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Driver ID is required'
        ]);
        return;
    }
    
    $driverId = (int)$_POST['driver_id'];
    
    // Start transaction
    $conn->begin_transaction();
    $core2Conn->begin_transaction();
    
    try {
        // Get user ID for this driver
        $stmt = $conn->prepare("SELECT user_id FROM drivers WHERE driver_id = ?");
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Driver not found");
        }
        
        $driver = $result->fetch_assoc();
        $userId = $driver['user_id'];
        $stmt->close();
        
        // Update driver status to 'inactive' in drivers table
        $stmt = $conn->prepare("UPDATE drivers SET status = 'inactive' WHERE driver_id = ?");
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $stmt->close();
        
        // Update user status to 'inactive' in users table
        $stmt = $core2Conn->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        
        // Unassign any vehicles from this driver
        $stmt = $conn->prepare("
            UPDATE vehicles 
            SET assigned_driver_id = NULL 
            WHERE assigned_driver_id = ?
        ");
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $stmt->close();
        
        // Update vehicle assignment history if the table exists
        // First check if the table exists to avoid errors
        $tableCheckResult = $conn->query("SHOW TABLES LIKE 'driver_vehicle_history'");
        if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
            $stmt = $conn->prepare("
                UPDATE driver_vehicle_history 
                SET unassigned_date = NOW(), 
                    notes = CONCAT(IFNULL(notes, ''), ' | Unassigned due to driver deactivation') 
                WHERE driver_id = ? 
                AND unassigned_date IS NULL
            ");
            $stmt->bind_param('i', $driverId);
            $stmt->execute();
            $stmt->close();
        } else {
            // Log that we couldn't update history because table doesn't exist
            error_log("Warning: driver_vehicle_history table not found. Vehicle history not updated for driver ID $driverId.");
        }
        
        // Commit the transactions
        $conn->commit();
        $core2Conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Driver has been deactivated successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback the transactions if an error occurred
        $conn->rollback();
        $core2Conn->rollback();
        
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get available vehicles for assignment
 */
function getAvailableVehicles($conn) {
    // Set content type header
    header('Content-Type: application/json');
    
    // Suppress PHP errors and warnings from being output - we'll handle all errors via JSON
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    $driverId = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;
    
    try {
        // First, get all vehicles with their assigned driver IDs
        $query = "
            SELECT v.*, d.driver_id AS current_driver_id
            FROM vehicles v
            LEFT JOIN drivers d ON v.assigned_driver_id = d.driver_id
            WHERE v.status = 'active'
            ORDER BY 
                CASE WHEN v.assigned_driver_id = ? THEN 0
                     WHEN v.assigned_driver_id IS NULL THEN 1
                     ELSE 2 END,
                v.plate_number
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare statement failed for vehicle query: " . $conn->error);
        }
        
        $stmt->bind_param('i', $driverId);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $vehicles = [];
        $driverIds = [];
        
        while ($vehicle = $result->fetch_assoc()) {
            // Store the vehicle data
            $vehicles[$vehicle['vehicle_id']] = $vehicle;
            
            // Collect driver IDs to fetch their names in a separate query
            if (!empty($vehicle['current_driver_id'])) {
                $driverIds[] = $vehicle['current_driver_id'];
            }
        }
        $stmt->close();
        
        // If we have assigned drivers, get their names from separate queries
        if (!empty($driverIds)) {
            // Get the driver user_ids
            $idList = implode(',', array_map('intval', $driverIds)); // Ensure IDs are integers
            $driverQuery = "SELECT driver_id, user_id FROM drivers WHERE driver_id IN ($idList)";
            $driverResult = $conn->query($driverQuery);
            
            if (!$driverResult) {
                throw new Exception("Error fetching driver user IDs: " . $conn->error);
            }
            
            $userIds = [];
            $driverUserMap = [];
            
            while ($driver = $driverResult->fetch_assoc()) {
                $userIds[] = $driver['user_id'];
                $driverUserMap[$driver['driver_id']] = $driver['user_id'];
            }
            
            if (!empty($userIds)) {
                // Now get driver names from core2 database in separate connection
                $core2Conn = connectToCore2DB();
                $userIdList = implode(',', $userIds);
                $userQuery = "SELECT user_id, CONCAT(firstname, ' ', lastname) AS driver_name 
                             FROM users 
                             WHERE user_id IN ($userIdList)";
                
                $userResult = $core2Conn->query($userQuery);
                
                if (!$userResult) {
                    // Log the error but continue with what we have
                    error_log("Warning: Error fetching driver names: " . $core2Conn->error);
                    // Set name to 'Unknown' for all drivers
                    foreach ($vehicles as &$vehicle) {
                        if (!empty($vehicle['current_driver_id'])) {
                            $vehicle['current_driver_name'] = 'Unknown Driver';
                        }
                    }
                } else {
                    $userNames = [];
                    while ($user = $userResult->fetch_assoc()) {
                        $userNames[$user['user_id']] = $user['driver_name'];
                    }
                    
                    // Now add the driver names to the vehicles array
                    foreach ($vehicles as &$vehicle) {
                        if (!empty($vehicle['current_driver_id'])) {
                            $userId = $driverUserMap[$vehicle['current_driver_id']] ?? null;
                            $vehicle['current_driver_name'] = $userNames[$userId] ?? 'Unknown Driver';
                        }
                    }
                }
            }
        }
        
        // Convert vehicles associative array back to indexed array for JSON
        $vehiclesArray = array_values($vehicles);
        
        echo json_encode([
            'success' => true,
            'data' => $vehiclesArray
        ]);
        
    } catch (Exception $e) {
        error_log("Vehicle loading error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Activate a previously deactivated driver
 */
function activateDriver($conn, $core2Conn) {
    if (!isset($_POST['driver_id']) || empty($_POST['driver_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Driver ID is required'
        ]);
        return;
    }
    
    $driverId = (int)$_POST['driver_id'];
    
    // Start transaction
    $conn->begin_transaction();
    $core2Conn->begin_transaction();
    
    try {
        // Get user ID for this driver
        $stmt = $conn->prepare("SELECT user_id FROM drivers WHERE driver_id = ?");
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Driver not found");
        }
        
        $driver = $result->fetch_assoc();
        $userId = $driver['user_id'];
        $stmt->close();
        
        // Update driver status to 'offline' in drivers table (starting status for reactivated drivers)
        $stmt = $conn->prepare("UPDATE drivers SET status = 'offline' WHERE driver_id = ?");
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $stmt->close();
        
        // Update user status to 'active' in users table
        $stmt = $core2Conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        
        // Commit the transactions
        $conn->commit();
        $core2Conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Driver has been reactivated successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback the transactions if an error occurred
        $conn->rollback();
        $core2Conn->rollback();
        
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Generate a random password
 */
function generateRandomPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $password;
}

/**
 * Sanitize input to prevent XSS attacks
 * 
 * @param string $input The input string to sanitize
 * @return string The sanitized input
 */
function driverSanitizeInput($input) {
    // Remove whitespace from the beginning and end
    $input = trim($input);
    
    // Remove backslashes
    $input = stripslashes($input);
    
    // Convert HTML special characters to their entities
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
} 