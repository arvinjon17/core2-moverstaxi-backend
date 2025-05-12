<?php
/**
 * Booking Details API
 * Retrieves detailed information about a specific booking
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
        'message' => 'Unauthorized access. You do not have permission to view booking details.'
    ]);
    exit;
}

// Check if a booking ID was provided
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking ID.'
    ]);
    exit;
}

$bookingId = (int)$_GET['booking_id'];

// Get the booking details - Modified to use prepared statements and correct database approach
try {
    // First, get the basic booking data
    $bookingQuery = "SELECT * FROM bookings WHERE booking_id = ?";
    $stmt = connectToCore2DB()->prepare($bookingQuery);
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookingData = $result->fetch_assoc();
    $stmt->close();
    
    if (!$bookingData) {
        echo json_encode([
            'success' => false,
            'message' => 'Booking not found.'
        ]);
        exit;
    }
    
    // If we have a customer ID, get customer data
    if (!empty($bookingData['customer_id'])) {
        $customerId = (int)$bookingData['customer_id'];
        $customerQuery = "SELECT address, city, state, zip, notes, status, user_id FROM customers WHERE customer_id = ?";
        $stmt = connectToCore1DB()->prepare($customerQuery);
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $customerData = $result->fetch_assoc();
        $stmt->close();
        
        if ($customerData) {
            // Add customer data to booking data
            foreach ($customerData as $key => $value) {
                $bookingData['customer_' . $key] = $value;
            }
            
            // If we have a user ID, get user data
            if (!empty($customerData['user_id'])) {
                $userId = (int)$customerData['user_id'];
                $userQuery = "SELECT firstname, lastname, email, phone FROM users WHERE user_id = ?";
                $stmt = connectToCore2DB()->prepare($userQuery);
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $userData = $result->fetch_assoc();
                $stmt->close();
                
                if ($userData) {
                    // Add user data to booking data
                    foreach ($userData as $key => $value) {
                        $bookingData['customer_' . $key] = $value;
                    }
                }
            }
        }
    }
    
    // If we have a driver ID, get driver data
    if (!empty($bookingData['driver_id'])) {
        $driverId = (int)$bookingData['driver_id'];
        $driverQuery = "SELECT license_number, license_expiry, rating, status, user_id FROM drivers WHERE driver_id = ?";
        $stmt = connectToCore1DB()->prepare($driverQuery);
        $stmt->bind_param("i", $driverId);
        $stmt->execute();
        $result = $stmt->get_result();
        $driverData = $result->fetch_assoc();
        $stmt->close();
        
        if ($driverData) {
            // Add driver data to booking data
            foreach ($driverData as $key => $value) {
                $bookingData['driver_' . $key] = $value;
            }
            
            // If we have a user ID, get driver user data
            if (!empty($driverData['user_id'])) {
                $driverUserId = (int)$driverData['user_id'];
                $driverUserQuery = "SELECT firstname, lastname, email, phone FROM users WHERE user_id = ?";
                $stmt = connectToCore2DB()->prepare($driverUserQuery);
                $stmt->bind_param("i", $driverUserId);
                $stmt->execute();
                $result = $stmt->get_result();
                $driverUserData = $result->fetch_assoc();
                $stmt->close();
                
                if ($driverUserData) {
                    // Add driver user data to booking data
                    foreach ($driverUserData as $key => $value) {
                        $bookingData['driver_' . $key] = $value;
                    }
                }
            }
        }
    }
    
    // If we have a vehicle ID, get vehicle data
    if (!empty($bookingData['vehicle_id'])) {
        $vehicleId = (int)$bookingData['vehicle_id'];
        $vehicleQuery = "SELECT model, plate_number, year, capacity FROM vehicles WHERE vehicle_id = ?";
        $stmt = connectToCore1DB()->prepare($vehicleQuery);
        $stmt->bind_param("i", $vehicleId);
        $stmt->execute();
        $result = $stmt->get_result();
        $vehicleData = $result->fetch_assoc();
        $stmt->close();
        
        if ($vehicleData) {
            // Add vehicle data to booking data
            foreach ($vehicleData as $key => $value) {
                $bookingData['vehicle_' . $key] = $value;
            }
        }
    }
    
    // Return the booking data as JSON
    echo json_encode([
        'success' => true,
        'data' => $bookingData
    ]);
    
} catch (Exception $e) {
    error_log("Error retrieving booking details: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving booking details: ' . $e->getMessage()
    ]);
} 