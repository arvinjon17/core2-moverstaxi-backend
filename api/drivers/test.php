<?php
// Test file for driver API functionality
// This should be removed after debugging is complete

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once '../../functions/db.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// For debugging purposes, mock a user session if not exists
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
}

// Set content type based on request mode
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'html';
if ($mode === 'json') {
    header('Content-Type: application/json');
}

// Function to test database connection
function testDatabaseConnection($dbName) {
    try {
        if ($dbName === 'core1') {
            $conn = connectToCore1DB();
        } else {
            $conn = connectToCore2DB();
        }
        
        if ($conn) {
            return [
                'success' => true,
                'message' => "Successfully connected to $dbName database"
            ];
        } else {
            return [
                'success' => false,
                'message' => "Failed to connect to $dbName database"
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Error connecting to $dbName database: " . $e->getMessage()
        ];
    }
}

// Function to test driver table structure
function testDriverTable() {
    try {
        $conn = connectToCore1DB();
        if (!$conn) {
            return [
                'success' => false,
                'message' => "Failed to connect to core1 database"
            ];
        }
        
        $query = "DESCRIBE drivers";
        $result = $conn->query($query);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => "Failed to describe drivers table: " . $conn->error
            ];
        }
        
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }
        
        return [
            'success' => true,
            'message' => "Retrieved drivers table structure",
            'columns' => $columns
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Error checking drivers table: " . $e->getMessage()
        ];
    }
}

// Function to test if any drivers exist
function testDriverData() {
    try {
        $conn = connectToCore1DB();
        if (!$conn) {
            return [
                'success' => false,
                'message' => "Failed to connect to core1 database"
            ];
        }
        
        // First test - count total drivers
        $query = "SELECT COUNT(*) as count FROM drivers";
        $result = $conn->query($query);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => "Failed to count drivers: " . $conn->error
            ];
        }
        
        $row = $result->fetch_assoc();
        $totalCount = $row['count'];
        
        // Second test - count drivers with location data
        $queryWithLocation = "SELECT COUNT(*) as count FROM drivers 
                             WHERE latitude IS NOT NULL 
                             AND longitude IS NOT NULL";
        $locationResult = $conn->query($queryWithLocation);
        $withLocationCount = 0;
        
        if ($locationResult) {
            $locationRow = $locationResult->fetch_assoc();
            $withLocationCount = $locationRow['count'];
        }
        
        // Third test - get sample driver data
        $sampleQuery = "SELECT driver_id, status, latitude, longitude, location_updated_at 
                       FROM drivers 
                       ORDER BY location_updated_at DESC 
                       LIMIT 1";
        $sampleResult = $conn->query($sampleQuery);
        $sampleDriver = null;
        
        if ($sampleResult && $sampleResult->num_rows > 0) {
            $sampleDriver = $sampleResult->fetch_assoc();
        }
        
        if ($totalCount > 0) {
            return [
                'success' => true,
                'message' => "Found $totalCount drivers in the database. $withLocationCount have location data.",
                'total_drivers' => $totalCount,
                'drivers_with_location' => $withLocationCount,
                'sample_driver' => $sampleDriver
            ];
        } else {
            return [
                'success' => false,
                'message' => "No drivers found in the database"
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Error checking driver data: " . $e->getMessage()
        ];
    }
}

// Run all tests
$results = [
    'core1_connection' => testDatabaseConnection('core1'),
    'core2_connection' => testDatabaseConnection('core2'),
    'driver_table' => testDriverTable(),
    'driver_data' => testDriverData()
];

// Output results
if ($mode === 'json') {
    echo json_encode($results);
} else {
    // HTML output
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Driver API Test</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; }
            .test { margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
            .success { background-color: #dff0d8; }
            .error { background-color: #f2dede; }
            .test h3 { margin-top: 0; }
            .code { background: #f8f8f8; padding: 10px; border-radius: 3px; font-family: monospace; overflow-x: auto; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <h1>Driver API Test Results</h1>';
    
    foreach ($results as $testName => $result) {
        $class = $result['success'] ? 'success' : 'error';
        echo "<div class='test $class'>";
        echo "<h3>" . ucwords(str_replace('_', ' ', $testName)) . "</h3>";
        echo "<p>" . $result['message'] . "</p>";
        
        if (isset($result['columns'])) {
            echo "<h4>Table Structure</h4>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            
            foreach ($result['columns'] as $column) {
                echo "<tr>";
                echo "<td>" . $column['Field'] . "</td>";
                echo "<td>" . $column['Type'] . "</td>";
                echo "<td>" . $column['Null'] . "</td>";
                echo "<td>" . $column['Key'] . "</td>";
                echo "<td>" . $column['Default'] . "</td>";
                echo "<td>" . $column['Extra'] . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
        
        echo "</div>";
    }
    
    // Add simulation test button
    echo '
    <div class="test">
        <h3>Test Simulate Driver Locations API</h3>
        <p>Click the button below to test the simulate driver locations API:</p>
        <button id="simulateBtn">Simulate Driver Locations</button>
        <div id="simulateResult" class="code" style="margin-top: 10px;"></div>
    </div>
    
    <script>
    document.getElementById("simulateBtn").addEventListener("click", function() {
        document.getElementById("simulateResult").innerHTML = "Loading...";
        
        fetch("simulate_locations.php?t=" + new Date().getTime())
            .then(response => response.text())
            .then(data => {
                let jsonData;
                try {
                    jsonData = JSON.parse(data);
                    document.getElementById("simulateResult").innerHTML = 
                        "<pre>" + JSON.stringify(jsonData, null, 2) + "</pre>";
                    
                    if (jsonData.success) {
                        document.getElementById("simulateResult").classList.add("success");
                        document.getElementById("simulateResult").classList.remove("error");
                    } else {
                        document.getElementById("simulateResult").classList.add("error");
                        document.getElementById("simulateResult").classList.remove("success");
                    }
                } catch (e) {
                    document.getElementById("simulateResult").innerHTML = 
                        "<div class=\'error\'>Error parsing JSON response: " + e.message + "</div>" +
                        "<pre>" + data + "</pre>";
                    document.getElementById("simulateResult").classList.add("error");
                }
            })
            .catch(error => {
                document.getElementById("simulateResult").innerHTML = 
                    "<div class=\'error\'>Error: " + error.message + "</div>";
                document.getElementById("simulateResult").classList.add("error");
            });
    });
    </script>
    </body>
    </html>';
} 