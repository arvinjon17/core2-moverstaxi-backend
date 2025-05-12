<?php
header('Content-Type: application/json');
require_once '../../functions/db.php';
require_once '../../functions/auth.php';

// Start session and check for auth
session_start();
if (!isset($_SESSION['user_id']) || !hasPermission('manage_customers')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Function to fix orphaned customers
function fixOrphanedCustomers() {
    try {
        // Connect to both databases
        $conn2 = connectToCore2DB();
        $conn1 = connectToCore1DB();
        
        if (!$conn2 || !$conn1) {
            throw new Exception("Could not connect to databases");
        }
        
        // Step 1: Get all users with role 'customer' from core2_movers.users
        $customerUsersQuery = "SELECT user_id, firstname, lastname, email FROM users WHERE role = 'customer'";
        $stmt = $conn2->prepare($customerUsersQuery);
        if (!$stmt) {
            throw new Exception("Failed to prepare customer users query: " . $conn2->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $customerUsers = [];
        
        while ($row = $result->fetch_assoc()) {
            $customerUsers[$row['user_id']] = $row;
        }
        
        $stmt->close();
        
        if (empty($customerUsers)) {
            return ['success' => true, 'message' => 'No customer users found in the system.', 'count' => 0];
        }
        
        // Step 2: Get all existing customer records from core1_movers.customers
        $existingCustomersQuery = "SELECT user_id FROM customers";
        $stmt = $conn1->prepare($existingCustomersQuery);
        if (!$stmt) {
            throw new Exception("Failed to prepare existing customers query: " . $conn1->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $existingCustomers = [];
        
        while ($row = $result->fetch_assoc()) {
            $existingCustomers[$row['user_id']] = true;
        }
        
        $stmt->close();
        
        // Step 3: Find users that are in customerUsers but not in existingCustomers
        $orphanedCustomers = [];
        foreach ($customerUsers as $userId => $userData) {
            if (!isset($existingCustomers[$userId])) {
                $orphanedCustomers[$userId] = $userData;
            }
        }
        
        // If no orphaned customers, return success
        if (empty($orphanedCustomers)) {
            return ['success' => true, 'message' => 'No orphaned customers found.', 'count' => 0];
        }
        
        // Add records to core1_movers.customers for each orphaned customer
        $insertCount = 0;
        $errors = [];
        
        foreach ($orphanedCustomers as $userId => $userData) {
            // Create a new customer record with default values
            $insertQuery = "INSERT INTO customers 
                          (user_id, status, created_at, updated_at) 
                          VALUES (?, 'offline', NOW(), NOW())";
            
            $insertStmt = $conn1->prepare($insertQuery);
            if (!$insertStmt) {
                $errors[] = "Failed to prepare insert for user ID {$userId}: " . $conn1->error;
                continue;
            }
            
            $insertStmt->bind_param('i', $userId);
            
            if (!$insertStmt->execute()) {
                $errors[] = "Failed to insert customer record for user ID {$userId}: " . $insertStmt->error;
            } else {
                $insertCount++;
            }
            
            $insertStmt->close();
        }
        
        // Close database connections
        $conn2->close();
        $conn1->close();
        
        // Return results
        return [
            'success' => true,
            'message' => "Fixed {$insertCount} orphaned customers.",
            'count' => $insertCount,
            'total' => count($orphanedCustomers),
            'errors' => $errors
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fixing orphaned customers: ' . $e->getMessage()];
    }
}

// Execute the fix and return JSON response
$result = fixOrphanedCustomers();
echo json_encode($result);

// If this was called from the browser, redirect back to customers.php
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'customers.php') !== false) {
    // Set a session message if you want to show a notification on the customers page
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    
    // Redirect back to customers page
    header('Location: ../../pages/customers.php');
    exit;
} 