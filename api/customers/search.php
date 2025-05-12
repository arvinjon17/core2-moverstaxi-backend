<?php
/**
 * API Endpoint: Search Customers
 * 
 * This endpoint searches for customers by name, phone, or email.
 * 
 * Required parameters:
 * - term: Search term to match against customer name, phone, or email
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Include required files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check if search term is provided
if (!isset($_GET['term']) || empty($_GET['term'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Search term is required'
    ]);
    exit;
}

$searchTerm = trim($_GET['term']);
error_log("Searching for customers with term: $searchTerm");

try {
    // Connect directly to core1_movers2 database
    $conn = new mysqli(DB_HOST, DB_USER_CORE2, DB_PASS_CORE2, 'core1_movers2');
    
    if ($conn->connect_error) {
        throw new Exception("Connection to core1_movers2 failed: " . $conn->connect_error);
    }
    
    error_log("Successfully connected to core1_movers2 database for search");
    
    // Search for customers by ID in core1_movers2
    $customerQuery = "
        SELECT 
            c.customer_id, c.user_id, c.address, c.city, c.state, c.zip, 
            c.notes, c.status, c.latitude, c.longitude, c.location_updated_at
        FROM 
            customers c
        WHERE 
            c.customer_id = ? OR c.user_id = ?";
    
    $customerStmt = $conn->prepare($customerQuery);
    $customerStmt->bind_param("ii", $searchId, $searchId);
    
    // Try to parse search term as an ID first
    $searchId = is_numeric($searchTerm) ? intval($searchTerm) : 0;
    $customerStmt->execute();
    $customerResult = $customerStmt->get_result();
    $customers = [];
    
    // If no results by ID, search by name, phone, email in users table
    if ($customerResult->num_rows == 0) {
        $customerStmt->close();
        
        // Search for users by name, phone, or email
        $userQuery = "
            SELECT 
                u.user_id, u.firstname, u.lastname, u.email, u.phone
            FROM 
                users u
            WHERE 
                (u.firstname LIKE ? OR 
                u.lastname LIKE ? OR 
                u.email LIKE ? OR 
                u.phone LIKE ?) AND
                u.role = 'customer' AND
                u.status = 'active'";
        
        $userStmt = $conn->prepare($userQuery);
        $searchPattern = "%$searchTerm%";
        $userStmt->bind_param("ssss", $searchPattern, $searchPattern, $searchPattern, $searchPattern);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        // Process the user results
        $userIds = [];
        $usersData = [];
        
        while ($user = $userResult->fetch_assoc()) {
            $userIds[] = $user['user_id'];
            $usersData[$user['user_id']] = $user;
        }
        
        $userStmt->close();
        error_log("Found " . count($userIds) . " matching users in core1_movers2");
        
        // If we have users, get their customer records from core1_movers2
        if (!empty($userIds)) {
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            
            $customerByUserQuery = "
                SELECT 
                    c.customer_id, c.user_id, c.address, c.city, c.state, c.zip, 
                    c.notes, c.status, c.latitude, c.longitude, c.location_updated_at
                FROM 
                    customers c
                WHERE 
                    c.user_id IN ($placeholders)";
            
            $customerByUserStmt = $conn->prepare($customerByUserQuery);
            
            // Create the bind parameters
            $bindTypes = str_repeat('i', count($userIds)); // All integers
            $params = array_merge([$bindTypes], $userIds);
            
            // Use reflection to pass parameters by reference
            $tmp = [];
            foreach ($params as $key => $value) {
                $tmp[$key] = &$params[$key];
            }
            
            call_user_func_array([$customerByUserStmt, 'bind_param'], $tmp);
            
            $customerByUserStmt->execute();
            $customerByUserResult = $customerByUserStmt->get_result();
            
            // Process the customer results
            while ($customer = $customerByUserResult->fetch_assoc()) {
                $userId = $customer['user_id'];
                
                // Combine customer and user data
                $customerData = $customer;
                $customerData['firstname'] = $usersData[$userId]['firstname'] ?? '';
                $customerData['lastname'] = $usersData[$userId]['lastname'] ?? '';
                $customerData['email'] = $usersData[$userId]['email'] ?? '';
                $customerData['phone'] = $usersData[$userId]['phone'] ?? '';
                
                $customers[] = $customerData;
            }
            
            $customerByUserStmt->close();
            error_log("Matched with " . count($customers) . " customer records");
        }
    } else {
        // Process direct customer ID matches
        while ($customer = $customerResult->fetch_assoc()) {
            // Get user data for this customer
            $userId = $customer['user_id'];
            
            $userQuery = "
                SELECT u.firstname, u.lastname, u.email, u.phone
                FROM users u
                WHERE u.user_id = ? AND u.role = 'customer' AND u.status = 'active'";
            
            $userStmt = $conn->prepare($userQuery);
            $userStmt->bind_param("i", $userId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            // Add user info to customer data
            if ($userResult && $userRow = $userResult->fetch_assoc()) {
                $customer['firstname'] = $userRow['firstname'];
                $customer['lastname'] = $userRow['lastname'];
                $customer['email'] = $userRow['email'];
                $customer['phone'] = $userRow['phone'];
                $customers[] = $customer;
            }
            
            $userStmt->close();
        }
        
        $customerStmt->close();
    }
    
    // Close database connection
    $conn->close();
    
    // Return results
    echo json_encode([
        'success' => true,
        'count' => count($customers),
        'search_term' => $searchTerm,
        'data' => $customers
    ]);
    
} catch (Exception $e) {
    error_log("Error in customer search: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error searching customers: ' . $e->getMessage()
    ]);
} 