<?php
/**
 * Command-line script for simulating driver locations
 * This can be run from the terminal to test the driver location simulation without going through the web interface
 * Usage: php run_simulation.php [count]
 * e.g., php run_simulation.php 5
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CLI mode
define('CLI_MODE', true);

// Include required files
require_once '../../functions/db.php';

// Mock session for testing
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

// Function to simulate driver locations
function simulateDriverLocations($count = 5) {
    // Get count from command line if provided
    global $argv;
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $count = intval($argv[1]);
    }
    
    if ($count < 1) $count = 5;
    
    echo "Starting driver location simulation for $count drivers...\n";
    
    try {
        // Test database connection first
        $conn = connectToCore1DB();
        if (!$conn) {
            throw new Exception("Failed to connect to core1 database");
        }
        
        echo "Connected to core1 database successfully.\n";
        
        // Get drivers to update
        $driversQuery = "SELECT 
            d.driver_id, d.user_id, d.status, 
            CASE WHEN d.latitude IS NULL THEN 'No location' ELSE 'Has location' END AS location_status
        FROM 
            drivers d
        WHERE 
            d.status IN ('available', 'busy')
        LIMIT $count";
        
        // Execute the query
        $stmt = $conn->prepare($driversQuery);
        if (!$stmt) {
            throw new Exception("Failed to prepare query: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $driversData = [];
        
        while ($row = $result->fetch_assoc()) {
            $driversData[] = $row;
        }
        
        $stmt->close();
        
        // If no drivers found, return message
        if (empty($driversData)) {
            echo "No drivers found to update locations.\n";
            return false;
        }
        
        echo "Found " . count($driversData) . " drivers to update.\n";
        
        // Count how many have no location
        $noLocationCount = 0;
        foreach ($driversData as $driver) {
            if (isset($driver['location_status']) && $driver['location_status'] === 'No location') {
                $noLocationCount++;
            }
        }
        echo "$noLocationCount drivers have no location data yet.\n";
        
        // Metro Manila coordinates for simulation
        $metroManila = [
            'center' => ['lat' => 14.5995, 'lng' => 120.9842],
            'radius' => 0.05 // Approximately 5 km
        ];
        
        $updatedDrivers = [];
        
        // Update each driver with random location around Metro Manila
        foreach ($driversData as $driver) {
            // Generate random point within circle
            $radius = $metroManila['radius'] * sqrt(mt_rand(0, 1000) / 1000);
            $angle = mt_rand(0, 360) * (M_PI / 180);
            
            $latitude = $metroManila['center']['lat'] + $radius * cos($angle);
            $longitude = $metroManila['center']['lng'] + $radius * sin($angle);
            
            // Format with 6 decimal places
            $latitude = number_format($latitude, 6, '.', '');
            $longitude = number_format($longitude, 6, '.', '');
            
            // Update driver location
            $updateQuery = "UPDATE drivers SET 
                latitude = ?, 
                longitude = ?, 
                location_updated_at = NOW()
            WHERE driver_id = ?";
            
            $updateStmt = $conn->prepare($updateQuery);
            if (!$updateStmt) {
                echo "Failed to prepare update statement for driver {$driver['driver_id']}: " . $conn->error . "\n";
                continue; // Skip this driver but try others
            }
            
            $updateStmt->bind_param('ddi', $latitude, $longitude, $driver['driver_id']);
            
            $success = $updateStmt->execute();
            if (!$success) {
                echo "Failed to update driver {$driver['driver_id']}: " . $updateStmt->error . "\n";
            } else {
                echo "Updated driver {$driver['driver_id']} with lat: $latitude, lng: $longitude\n";
                
                $updatedDrivers[] = [
                    'driver_id' => $driver['driver_id'],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }
            
            $updateStmt->close();
        }
        
        // Close database connection
        $conn->close();
        
        echo "Successfully updated " . count($updatedDrivers) . " driver locations.\n";
        return count($updatedDrivers) > 0;
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Run the simulation
$result = simulateDriverLocations();
echo "Simulation " . ($result ? "completed successfully!" : "failed!") . "\n";
exit($result ? 0 : 1); // Return appropriate exit code for scripting 