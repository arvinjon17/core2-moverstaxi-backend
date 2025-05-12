<?php
/**
 * Customer Status Update API
 * Handles changing a customer's status (active/inactive)
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

// Check if the user is logged in and has permission to manage customers
if (!hasPermission('manage_customers')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. You do not have permission to update customer status.'
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

// Get input data (for compatibility with both form data and JSON)
$inputData = json_decode(file_get_contents('php://input'), true);
if (empty($inputData)) {
    $inputData = $_POST;
}

// Check for required fields
if (!isset($inputData['user_id']) || !is_numeric($inputData['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID.'
    ]);
    exit;
}

if (!isset($inputData['status']) || !in_array($inputData['status'], ['active', 'inactive'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value. Status must be "active" or "inactive".'
    ]);
    exit;
}

// Get the customer ID and status
$customerId = (int)$inputData['user_id'];
$status = $inputData['status'];

// Debugging
error_log("Processing customer status update for customer ID: {$customerId}, new status: {$status}");

// Connect to the database
$conn = connectToCore2DB();

if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to connect to database.'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Verify the user exists and is a customer
    $checkQuery = "SELECT user_id FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1";
    $stmt = $conn->prepare($checkQuery);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        $stmt->close();
        throw new Exception("Customer not found or is not a customer account.");
    }
    
    $stmt->close();
    
    // Update the customer status
    $updateQuery = "UPDATE users SET status = ? WHERE user_id = ? AND role = 'customer'";
    $stmt = $conn->prepare($updateQuery);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare update query: " . $conn->error);
    }
    
    $stmt->bind_param('si', $status, $customerId);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update customer status: " . $stmt->error);
    }
    
    // Check if any rows were affected
    if ($stmt->affected_rows === 0) {
        throw new Exception("No changes made to customer status.");
    }
    
    $stmt->close();
    
    // Now update or create the corresponding record in core1_movers.customers
    $conn1 = connectToCore1DB();
    if (!$conn1) {
        throw new Exception("Failed to connect to core1_movers database.");
    }
    
    // First check if the customer record exists
    $checkCustomerQuery = "SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1";
    $checkStmt = $conn1->prepare($checkCustomerQuery);
    
    if (!$checkStmt) {
        throw new Exception("Failed to prepare customer check query: " . $conn1->error);
    }
    
    $checkStmt->bind_param('i', $customerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $customerExists = ($checkResult && $checkResult->num_rows > 0);
    $checkStmt->close();
    
    // Map core2_movers.users.status to core1_movers.customers.status
    $appStatus = ($status === 'active') ? 'online' : 'offline';
    
    if ($customerExists) {
        // Update existing customer record
        $updateCustomerQuery = "UPDATE customers SET status = ?, updated_at = NOW() WHERE user_id = ?";
        $updateStmt = $conn1->prepare($updateCustomerQuery);
        
        if (!$updateStmt) {
            throw new Exception("Failed to prepare customer update query: " . $conn1->error);
        }
        
        $updateStmt->bind_param('si', $appStatus, $customerId);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update customer app status: " . $updateStmt->error);
        }
        
        $updateStmt->close();
    } else {
        // Create new customer record
        $insertCustomerQuery = "INSERT INTO customers (user_id, status, created_at, updated_at) 
                              VALUES (?, ?, NOW(), NOW())";
        $insertStmt = $conn1->prepare($insertCustomerQuery);
        
        if (!$insertStmt) {
            throw new Exception("Failed to prepare customer insert query: " . $conn1->error);
        }
        
        $insertStmt->bind_param('is', $customerId, $appStatus);
        
        if (!$insertStmt->execute()) {
            throw new Exception("Failed to create customer record: " . $insertStmt->error);
        }
        
        $insertStmt->close();
    }
    
    $conn1->close();
    
    // Record the status change in the system_logs table if it exists
    // This is optional but recommended for audit purposes
    $logQuery = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE action = VALUES(action), description = VALUES(description)";
                
    if ($stmt = $conn->prepare($logQuery)) {
        $adminUserId = $_SESSION['user_id'] ?? 0;
        $action = "customer_status_update";
        $description = "Customer ID: {$customerId} status changed to {$status}";
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $stmt->bind_param('isss', $adminUserId, $action, $description, $ipAddress);
        $stmt->execute();
        $stmt->close();
    }
    
    // Commit the transaction
    $conn->commit();
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Customer status has been updated successfully to ' . ucfirst($status) . '.'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }
    
    error_log("Error updating customer status: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error updating customer status: ' . $e->getMessage()
    ]);
} finally {
    // Close database connection
    if ($conn) {
        $conn->close();
    }
} 