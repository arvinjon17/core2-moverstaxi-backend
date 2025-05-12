<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once dirname(__FILE__) . '/../../functions/db.php';
require_once dirname(__FILE__) . '/../../functions/auth.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Handle the request
try {
    $conn = connectToCore1DB();
    
    // Get driver locations
    $driverLocations = [];
    
    // Check if driver_locations table exists
    $checkDriverTableQuery = "SHOW TABLES LIKE 'driver_locations'";
    $driverTableExists = false;
    
    if ($result = $conn->query($checkDriverTableQuery)) {
        $driverTableExists = ($result->num_rows > 0);
    }
    
    if ($driverTableExists) {
        // Get all driver locations
        $sql = "SELECT dl.*, d.status, u.firstname, u.lastname, u.phone 
                FROM driver_locations dl
                JOIN drivers d ON dl.driver_id = d.driver_id
                JOIN " . DB_NAME_CORE2 . ".users u ON d.user_id = u.user_id
                WHERE d.status IN ('available', 'busy') 
                ORDER BY dl.last_updated DESC 
                LIMIT 20";
                
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $driverLocations[] = [
                    'id' => $row['driver_id'],
                    'name' => $row['firstname'] . ' ' . $row['lastname'],
                    'phone' => $row['phone'],
                    'status' => $row['status'],
                    'lat' => (float)$row['latitude'],
                    'lng' => (float)$row['longitude'],
                    'heading' => (float)$row['heading'],
                    'speed' => (float)$row['speed'],
                    'lastUpdated' => $row['last_updated']
                ];
            }
        }
    } else {
        // Try to get driver data directly from drivers table
        $driversQuery = "SELECT 
                d.driver_id, d.user_id, d.status as driver_status, d.latitude, d.longitude,
                u.firstname, u.lastname, u.phone
            FROM 
                drivers d
            JOIN 
                " . DB_NAME_CORE2 . ".users u ON d.user_id = u.user_id
            WHERE 
                d.status IN ('available', 'busy')
            LIMIT 10";
        
        $drivers = [];
        $result = $conn->query($driversQuery);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $drivers[] = $row;
            }
        }
        
        // If no drivers from DB, create sample ones
        if (empty($drivers)) {
            $sampleDrivers = [
                ['driver_id' => 1, 'firstname' => 'Juan', 'lastname' => 'Cruz', 'phone' => '09123456789', 'driver_status' => 'available', 'latitude' => null, 'longitude' => null],
                ['driver_id' => 2, 'firstname' => 'Maria', 'lastname' => 'Santos', 'phone' => '09123456790', 'driver_status' => 'busy', 'latitude' => null, 'longitude' => null],
                ['driver_id' => 3, 'firstname' => 'Pedro', 'lastname' => 'Reyes', 'phone' => '09123456791', 'driver_status' => 'available', 'latitude' => null, 'longitude' => null],
                ['driver_id' => 4, 'firstname' => 'Jose', 'lastname' => 'Garcia', 'phone' => '09123456792', 'driver_status' => 'available', 'latitude' => null, 'longitude' => null],
                ['driver_id' => 5, 'firstname' => 'Manuel', 'lastname' => 'Lim', 'phone' => '09123456793', 'driver_status' => 'busy', 'latitude' => null, 'longitude' => null],
                ['driver_id' => 6, 'firstname' => 'Antonio', 'lastname' => 'Tan', 'phone' => '09123456794', 'driver_status' => 'available', 'latitude' => null, 'longitude' => null],
                ['driver_id' => 7, 'firstname' => 'Ricardo', 'lastname' => 'Mendoza', 'phone' => '09123456795', 'driver_status' => 'available', 'latitude' => null, 'longitude' => null],
                ['driver_id' => 8, 'firstname' => 'Eduardo', 'lastname' => 'Ramos', 'phone' => '09123456796', 'driver_status' => 'busy', 'latitude' => null, 'longitude' => null],
                ['driver_id' => 9, 'firstname' => 'Carlos', 'lastname' => 'Morales', 'phone' => '09123456797', 'driver_status' => 'available', 'latitude' => null, 'longitude' => null],
                ['driver_id' => 10, 'firstname' => 'Roberto', 'lastname' => 'Gonzales', 'phone' => '09123456798', 'driver_status' => 'available', 'latitude' => null, 'longitude' => null]
            ];
            $drivers = $sampleDrivers;
        }
        
        foreach ($drivers as $driver) {
            // Check if driver has location data, generate random coordinates if not
            $lat = (!empty($driver['latitude'])) ? (float)$driver['latitude'] : 14.58 + (mt_rand(-100, 100) / 1000);
            $lng = (!empty($driver['longitude'])) ? (float)$driver['longitude'] : 120.98 + (mt_rand(-100, 100) / 1000);
            
            $heading = mt_rand(0, 359);
            $speed = mt_rand(0, 60);
            
            $driverLocations[] = [
                'id' => $driver['driver_id'],
                'name' => $driver['firstname'] . ' ' . $driver['lastname'],
                'phone' => $driver['phone'],
                'status' => $driver['driver_status'],
                'lat' => $lat,
                'lng' => $lng,
                'heading' => $heading,
                'speed' => $speed,
                'lastUpdated' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // Get customer locations
    $customerLocations = [];
    
    // Check if customer_locations table exists
    $checkCustomerTableQuery = "SHOW TABLES LIKE 'customer_locations'";
    $customerTableExists = false;
    
    if ($result = $conn->query($checkCustomerTableQuery)) {
        $customerTableExists = ($result->num_rows > 0);
    }
    
    if ($customerTableExists) {
        // Get all customer locations
        $sql = "SELECT cl.*, c.status, u.firstname, u.lastname, u.phone 
                FROM customer_locations cl
                JOIN customers c ON cl.customer_id = c.customer_id
                JOIN " . DB_NAME_CORE2 . ".users u ON c.user_id = u.user_id
                WHERE c.status = 'active' 
                ORDER BY cl.last_updated DESC 
                LIMIT 20";
                
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $customerLocations[] = [
                    'id' => $row['customer_id'],
                    'name' => $row['firstname'] . ' ' . $row['lastname'],
                    'phone' => $row['phone'],
                    'status' => $row['status'],
                    'lat' => (float)$row['latitude'],
                    'lng' => (float)$row['longitude'],
                    'lastUpdated' => $row['last_updated']
                ];
            }
        }
    } else {
        // Try to get customer data directly from customers table with possible latitude/longitude
        $customersQuery = "SELECT 
                c.customer_id, c.status as customer_status, c.latitude, c.longitude,
                u.firstname, u.lastname, u.phone
            FROM 
                customers c
            JOIN 
                " . DB_NAME_CORE2 . ".users u ON c.user_id = u.user_id
            WHERE 
                c.status = 'active'
            LIMIT 10";
        
        $customers = [];
        $result = $conn->query($customersQuery);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $customers[] = $row;
            }
        }
        
        // If no customers from DB, create sample ones
        if (empty($customers)) {
            $sampleCustomers = [
                ['customer_id' => 1, 'firstname' => 'Anna', 'lastname' => 'Rodriguez', 'phone' => '09123456799', 'customer_status' => 'active', 'latitude' => null, 'longitude' => null],
                ['customer_id' => 2, 'firstname' => 'Sofia', 'lastname' => 'Fernandez', 'phone' => '09123456800', 'customer_status' => 'active', 'latitude' => null, 'longitude' => null],
                ['customer_id' => 3, 'firstname' => 'Isabella', 'lastname' => 'Villanueva', 'phone' => '09123456801', 'customer_status' => 'active', 'latitude' => null, 'longitude' => null],
                ['customer_id' => 4, 'firstname' => 'Emma', 'lastname' => 'De Castro', 'phone' => '09123456802', 'customer_status' => 'active', 'latitude' => null, 'longitude' => null],
                ['customer_id' => 5, 'firstname' => 'Olivia', 'lastname' => 'Del Rosario', 'phone' => '09123456803', 'customer_status' => 'active', 'latitude' => null, 'longitude' => null],
                ['customer_id' => 6, 'firstname' => 'Sophia', 'lastname' => 'Tolentino', 'phone' => '09123456804', 'customer_status' => 'active', 'latitude' => null, 'longitude' => null],
                ['customer_id' => 7, 'firstname' => 'Miguel', 'lastname' => 'Torres', 'phone' => '09123456805', 'customer_status' => 'active', 'latitude' => null, 'longitude' => null],
                ['customer_id' => 8, 'firstname' => 'Gabriel', 'lastname' => 'Castillo', 'phone' => '09123456806', 'customer_status' => 'active', 'latitude' => null, 'longitude' => null],
                ['customer_id' => 9, 'firstname' => 'Rafael', 'lastname' => 'Pascual', 'phone' => '09123456807', 'customer_status' => 'active', 'latitude' => null, 'longitude' => null],
                ['customer_id' => 10, 'firstname' => 'Samuel', 'lastname' => 'Navarro', 'phone' => '09123456808', 'customer_status' => 'active', 'latitude' => null, 'longitude' => null]
            ];
            $customers = $sampleCustomers;
        }
        
        foreach ($customers as $customer) {
            // Check if customer has location data, generate random coordinates if not
            $lat = (!empty($customer['latitude'])) ? (float)$customer['latitude'] : 14.58 + (mt_rand(-100, 100) / 1000);
            $lng = (!empty($customer['longitude'])) ? (float)$customer['longitude'] : 120.98 + (mt_rand(-100, 100) / 1000);
            
            $customerLocations[] = [
                'id' => $customer['customer_id'],
                'name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'phone' => $customer['phone'],
                'status' => $customer['customer_status'],
                'lat' => $lat,
                'lng' => $lng,
                'lastUpdated' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // Return location data
    echo json_encode([
        'success' => true,
        'drivers' => $driverLocations,
        'customers' => $customerLocations,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 