<?php
/**
 * Customer List API
 * Retrieves a list of active customers for select dropdowns
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

// Check if the user is logged in and has permission to manage bookings
if (!hasPermission('manage_bookings')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. You do not have permission to view customer list.'
    ]);
    exit;
}

try {
    // Connect directly to core1_movers2 database
    $conn = new mysqli(DB_HOST, DB_USER_CORE2, DB_PASS_CORE2, 'core1_movers2');
    
    if ($conn->connect_error) {
        throw new Exception("Connection to core1_movers2 failed: " . $conn->connect_error);
    }
    
    // Log the successful connection
    error_log("Successfully connected to core1_movers2 database");
    
    // Step 1: Fetch customers from core1_movers2 database
    $customersQuery = "SELECT 
        customer_id, user_id, address, city, state, zip, 
        latitude, longitude, status, location_updated_at
    FROM 
        customers
    ORDER BY 
        customer_id DESC";
    
    $result = $conn->query($customersQuery);
    
    if ($result === false) {
        throw new Exception("Error fetching customers: " . $conn->error);
    }
    
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    error_log("Fetched " . count($customers) . " customers from core1_movers2");
    
    // Step 2: Extract all user_ids to fetch user details
    $userIds = [];
    foreach ($customers as $customer) {
        if (!empty($customer['user_id'])) {
            $userIds[] = $customer['user_id'];
        }
    }
    
    // Step 3: Fetch user details from core1_movers2
    $userDetails = [];
    if (!empty($userIds)) {
        // Convert array to comma-separated string for the IN clause
        $userIdsStr = implode(',', $userIds);
        
        $usersQuery = "SELECT 
            user_id, firstname, lastname, email, phone, status
        FROM 
            users
        WHERE 
            user_id IN ($userIdsStr) AND 
            role = 'customer' AND
            status = 'active'";
        
        $result = $conn->query($usersQuery);
        
        if ($result === false) {
            throw new Exception("Error fetching users: " . $conn->error);
        }
        
        // Build associative array of user details keyed by user_id
        while ($user = $result->fetch_assoc()) {
            $userDetails[$user['user_id']] = $user;
        }
        
        error_log("Fetched " . count($userDetails) . " users from core1_movers2");
    }
    
    // Step 4: Combine customer and user data
    $combinedCustomers = [];
    foreach ($customers as $customer) {
        $userId = $customer['user_id'];
        
        // Only include if we have matching user details and user is active
        if (isset($userDetails[$userId])) {
            $customerData = array_merge($customer, [
                'firstname' => $userDetails[$userId]['firstname'],
                'lastname' => $userDetails[$userId]['lastname'],
                'email' => $userDetails[$userId]['email'],
                'phone' => $userDetails[$userId]['phone'],
                'user_status' => $userDetails[$userId]['status']
            ]);
            
            $combinedCustomers[] = $customerData;
        }
    }
    
    error_log("Combined " . count($combinedCustomers) . " customers with user data");
    
    // Close the database connection
    $conn->close();
    
    // Return the customers as JSON
    echo json_encode([
        'success' => true,
        'count' => count($combinedCustomers),
        'data' => $combinedCustomers
    ]);
    
} catch (Exception $e) {
    error_log("Error retrieving customer list: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving customers: ' . $e->getMessage()
    ]);
} 