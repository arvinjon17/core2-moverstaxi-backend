<?php
/**
 * Customer Update API
 * Handles updates to customer data including profile picture
 */

// Set the content type header to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';
require_once '../../functions/profile_images.php';

// Check if the user is logged in and has permission to manage customers
if (!hasPermission('manage_customers')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. You do not have permission to update customer data.'
    ]);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST is supported.'
    ]);
    exit;
}

// Check for required fields
if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID.'
    ]);
    exit;
}

// Get the customer ID
$customerId = (int)$_POST['user_id'];

// Debugging
error_log("Processing customer update for customer ID: {$customerId}");

// Default response
$response = [
    'success' => false,
    'message' => 'Failed to update customer data.',
    'errors' => []
];

// Connect to the database
$conn2 = connectToCore2DB();
$conn1 = connectToCore1DB();

if (!$conn2 || !$conn1) {
    $response['message'] = 'Failed to connect to database.';
    echo json_encode($response);
    exit;
}

try {
    // Start transaction for both databases
    $conn2->begin_transaction();
    $conn1->begin_transaction();
    
    // Prepare the data for core2_movers.users
    $firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
    $lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $account_status = isset($_POST['account_status']) ? trim($_POST['account_status']) : 'active';
    $current_status = isset($_POST['current_status']) ? trim($_POST['current_status']) : 'offline';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // Validate data
    if (empty($firstname) || empty($lastname) || empty($email)) {
        $response['message'] = 'First name, last name, and email are required.';
        echo json_encode($response);
        exit;
    }
    
    // Validate account_status
    $validAccountStatuses = ['active', 'inactive', 'suspended'];
    if (!in_array($account_status, $validAccountStatuses)) {
        $account_status = 'active'; // Default to active if invalid
    }
    
    // Validate current_status
    $validCurrentStatuses = ['online', 'busy', 'offline'];
    if (!in_array($current_status, $validCurrentStatuses)) {
        $current_status = 'offline'; // Default to offline if invalid
    }
    
    // Check if email is already in use by a different user
    $emailCheckQuery = "SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1";
    $stmt = $conn2->prepare($emailCheckQuery);
    
    if ($stmt) {
        $stmt->bind_param('si', $email, $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $response['message'] = 'Email address is already in use by another user.';
            $response['errors'][] = 'duplicate_email';
            echo json_encode($response);
            $stmt->close();
            exit;
        }
        
        $stmt->close();
    }
    
    // Handle profile picture upload first if provided
    $profilePicturePath = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        // Create an array with customer data for the upload function
        $customerData = [
            'role' => 'customer',
            'firstname' => $firstname,
            'lastname' => $lastname
        ];
        
        // Upload the profile picture, passing customer ID and role explicitly
        // Set updateDatabase to false since we'll handle database updates ourselves
        $uploadResult = uploadProfileImage('profile_picture', 'customer', $customerId, $customerData, false);
        
        if (!$uploadResult['success']) {
            $response['errors'][] = 'profile_picture_upload_failed';
            $response['profilePictureError'] = $uploadResult['message'];
            error_log("Failed to upload profile picture: " . $uploadResult['message']);
        } else {
            $profilePicturePath = $uploadResult['filename'];
            error_log("Successfully uploaded profile picture: " . $profilePicturePath);
        }
    }
    
    // Update user data in core2_movers.users
    $updateUserQuery = "UPDATE users SET 
                       firstname = ?,
                       lastname = ?,
                       email = ?,
                       phone = ?,
                       status = ?";
    
    // Add profile picture update if available
    if ($profilePicturePath) {
        $updateUserQuery .= ", profile_picture = ?";
    }
    
    // Add password update if provided
    if (!empty($password)) {
        $updateUserQuery .= ", password = ?";
    }
    
    $updateUserQuery .= " WHERE user_id = ? AND role = 'customer'";
    
    $stmt = $conn2->prepare($updateUserQuery);
    
    if ($stmt) {
        if ($profilePicturePath && !empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param('ssssssi', $firstname, $lastname, $email, $phone, $account_status, $profilePicturePath, $hashedPassword, $customerId);
        } else if ($profilePicturePath) {
            $stmt->bind_param('sssssi', $firstname, $lastname, $email, $phone, $account_status, $profilePicturePath, $customerId);
        } else if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param('sssssi', $firstname, $lastname, $email, $phone, $account_status, $hashedPassword, $customerId);
        } else {
            $stmt->bind_param('sssssi', $firstname, $lastname, $email, $phone, $account_status, $customerId);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update customer information: " . $stmt->error);
        }
        
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare user update statement: " . $conn2->error);
    }
    
    // Prepare the data for core1_movers.customers
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $state = isset($_POST['state']) ? trim($_POST['state']) : '';
    $zip = isset($_POST['zip']) ? trim($_POST['zip']) : '';
    
    // Check if customer exists in core1_movers.customers
    $checkCustomerQuery = "SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1";
    $stmt = $conn1->prepare($checkCustomerQuery);
    
    if ($stmt) {
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $customerExists = ($result && $result->num_rows > 0);
        $stmt->close();
        
        if ($customerExists) {
            // Update existing customer in core1_movers.customers
            $updateCustomerQuery = "UPDATE customers SET 
                               address = ?,
                               city = ?,
                               state = ?,
                               zip = ?,
                               status = ?,
                               updated_at = NOW()";
            
            // Add profile picture update if available               
            if ($profilePicturePath) {
                $updateCustomerQuery .= ", profile_picture = ?";
            }
            
            $updateCustomerQuery .= " WHERE user_id = ?";
            
            $stmt = $conn1->prepare($updateCustomerQuery);
            
            if ($stmt) {
                if ($profilePicturePath) {
                    $stmt->bind_param('ssssssi', $address, $city, $state, $zip, $current_status, $profilePicturePath, $customerId);
                } else {
                    $stmt->bind_param('sssssi', $address, $city, $state, $zip, $current_status, $customerId);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update customer details: " . $stmt->error);
                }
                
                $stmt->close();
            } else {
                throw new Exception("Failed to prepare customer update statement: " . $conn1->error);
            }
        } else {
            // Insert new customer in core1_movers.customers
            $insertCustomerQuery = "INSERT INTO customers
                               (user_id, address, city, state, zip, status";
            
            // Add profile picture field if available
            if ($profilePicturePath) {
                $insertCustomerQuery .= ", profile_picture";
            }
            
            $insertCustomerQuery .= ", created_at, updated_at) 
                               VALUES (?, ?, ?, ?, ?, ?";
                               
            // Add profile picture value placeholder if available
            if ($profilePicturePath) {
                $insertCustomerQuery .= ", ?";
            }
            
            $insertCustomerQuery .= ", NOW(), NOW())";
            
            $stmt = $conn1->prepare($insertCustomerQuery);
            
            if ($stmt) {
                if ($profilePicturePath) {
                    $stmt->bind_param('issssss', $customerId, $address, $city, $state, $zip, $current_status, $profilePicturePath);
                } else {
                    $stmt->bind_param('isssss', $customerId, $address, $city, $state, $zip, $current_status);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create customer details: " . $stmt->error);
                }
                
                $stmt->close();
            } else {
                throw new Exception("Failed to prepare customer insert statement: " . $conn1->error);
            }
        }
    } else {
        throw new Exception("Failed to check if customer exists: " . $conn1->error);
    }
    
    // Check if database connections are still active before committing
    if ($conn2 && $conn2->ping()) {
        $conn2->commit();
    } else {
        // Reconnect if connection was lost
        $conn2 = connectToCore2DB();
        if ($conn2) {
            $conn2->commit();
        }
    }
    
    if ($conn1 && $conn1->ping()) {
        $conn1->commit();
    } else {
        // Reconnect if connection was lost
        $conn1 = connectToCore1DB();
        if ($conn1) {
            $conn1->commit();
        }
    }
    
    // Success response
    $response = [
        'success' => true,
        'message' => 'Customer information has been updated successfully.'
    ];
    
} catch (Exception $e) {
    // Rollback transactions on error
    if ($conn2 && $conn2->ping()) {
        $conn2->rollback();
    }
    
    if ($conn1 && $conn1->ping()) {
        $conn1->rollback();
    }
    
    error_log("Error updating customer data: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'message' => 'Error updating customer data: ' . $e->getMessage()
    ];
} finally {
    // Close database connections only if they're still active
    if ($conn2 && $conn2->ping()) {
        $conn2->close();
    }
    
    if ($conn1 && $conn1->ping()) {
        $conn1->close();
    }
}

// Return the response as JSON
echo json_encode($response); 