<?php
/**
 * Driver Debug Tool
 * This file is used for debugging driver-related API calls and database connections
 */

require_once '../functions/auth.php';
require_once '../functions/db.php';

// Disable this in production by uncommenting the following
// die("Debugging disabled in production environment");

// Connect to databases
$conn1 = connectToCore1DB();
$conn2 = connectToCore2DB();

// Test connection to core1_movers
echo "<h2>Testing connection to core1_movers database:</h2>";
if ($conn1->connect_error) {
    echo "<p style='color:red'>Connection to core1_movers failed: " . $conn1->connect_error . "</p>";
} else {
    echo "<p style='color:green'>Successfully connected to core1_movers database.</p>";

    // Test query to drivers table
    $driversQuery = "SELECT COUNT(*) as driver_count FROM drivers";
    $result = $conn1->query($driversQuery);
    
    if ($result) {
        $count = $result->fetch_assoc()['driver_count'];
        echo "<p>Found $count drivers in the database.</p>";
    } else {
        echo "<p style='color:red'>Error querying drivers table: " . $conn1->error . "</p>";
    }
}

// Test connection to core2_movers
echo "<h2>Testing connection to core2_movers database:</h2>";
if ($conn2->connect_error) {
    echo "<p style='color:red'>Connection to core2_movers failed: " . $conn2->connect_error . "</p>";
} else {
    echo "<p style='color:green'>Successfully connected to core2_movers database.</p>";

    // Test query to users table
    $usersQuery = "SELECT COUNT(*) as user_count FROM users WHERE role='driver'";
    $result = $conn2->query($usersQuery);
    
    if ($result) {
        $count = $result->fetch_assoc()['user_count'];
        echo "<p>Found $count users with driver role in the database.</p>";
    } else {
        echo "<p style='color:red'>Error querying users table: " . $conn2->error . "</p>";
    }
}

// Test driver detail function
echo "<h2>Testing Driver Details API:</h2>";

// Get a test driver ID
$testDriverId = 0;
$query = "SELECT driver_id FROM drivers LIMIT 1";
$result = $conn1->query($query);
if ($result && $result->num_rows > 0) {
    $testDriverId = $result->fetch_assoc()['driver_id'];
    echo "<p>Found a driver to test with ID: $testDriverId</p>";
} else {
    echo "<p style='color:red'>No drivers found in the database to test with.</p>";
}

// If we have a test driver ID, simulate the API call
if ($testDriverId > 0) {
    echo "<h3>Simulating API call for driver_id = $testDriverId:</h3>";
    
    try {
        // First, get driver data from core1_movers.drivers
        $query = "
            SELECT d.*, 
                v.vehicle_id, v.plate_number, v.model, v.year, v.status AS vehicle_status,
                v.fuel_type, v.capacity, v.vehicle_image
            FROM drivers d
            LEFT JOIN vehicles v ON d.driver_id = v.assigned_driver_id
            WHERE d.driver_id = $testDriverId
        ";
        
        $result = $conn1->query($query);
        if (!$result) {
            throw new Exception("Driver query error: " . $conn1->error);
        }
        
        if ($result->num_rows === 0) {
            throw new Exception("Driver not found with ID: $testDriverId");
        }
        
        $driver = $result->fetch_assoc();
        echo "<p style='color:green'>Successfully retrieved driver details.</p>";
        
        // Get user data from core2_movers.users
        $userId = $driver['user_id'];
        $query = "
            SELECT u.user_id, u.firstname, u.lastname, u.email, u.phone, 
                u.profile_picture, u.status, u.last_login, u.created_at
            FROM users u
            WHERE u.user_id = $userId
        ";
        
        $result = $conn2->query($query);
        if (!$result) {
            throw new Exception("User query error: " . $conn2->error);
        }
        
        if ($result->num_rows === 0) {
            throw new Exception("User record not found for driver with ID: $userId");
        }
        
        $user = $result->fetch_assoc();
        echo "<p style='color:green'>Successfully retrieved user details.</p>";
        
        // Combine the data
        $driverDetails = array_merge($driver, $user);
        
        // Display the data
        echo "<h4>Driver Details:</h4>";
        echo "<pre>" . print_r($driverDetails, true) . "</pre>";
        
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
}

// Close connections
if ($conn1) $conn1->close();
if ($conn2) $conn2->close();

echo "<p><small>Debug completed at " . date('Y-m-d H:i:s') . "</small></p>";
?> 