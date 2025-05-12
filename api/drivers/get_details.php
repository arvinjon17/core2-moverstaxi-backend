<?php
// Suppress PHP errors and warnings from being output - we'll handle all errors via JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// API endpoint to get detailed information for a specific driver
// Includes current booking information if the driver is assigned to an active booking

// Include necessary files
require_once '../../functions/db.php';
require_once '../../functions/auth.php';
require_once '../../functions/role_management.php';

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug session info - comment out in production
error_log('Session data for get_details.php: ' . print_r($_SESSION, true));

// Check if user is authenticated
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check permissions - allow access for admin, super_admin, and dispatch roles
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['admin', 'super_admin', 'dispatch']) && 
    !hasPermission('manage_drivers') && 
    !hasPermission('view_drivers')) {
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to access this resource'
    ]);
    exit;
}

// Check if driver ID is provided
if (!isset($_GET['driver_id']) || empty($_GET['driver_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Driver ID is required'
    ]);
    exit;
}

try {
    // Get driver ID
    $driverId = intval($_GET['driver_id']);
    
    // Get driver details from the core1 database
    $driverQuery = "SELECT 
        d.driver_id, d.user_id, d.license_number, d.license_expiry, 
        d.rating, d.status, d.latitude, d.longitude, d.location_updated_at
    FROM 
        " . DB_NAME_CORE1 . ".drivers d
    WHERE 
        d.driver_id = ?";
    
    $conn = connectToCore1DB();
    $stmt = $conn->prepare($driverQuery);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Driver not found'
        ]);
        exit;
    }
    
    // Fetch driver data
    $driverData = $result->fetch_assoc();
    
    // Get user details from core2 database
    $userId = $driverData['user_id'];
    
    $userQuery = "SELECT 
        u.firstname, u.lastname, u.email, u.phone
    FROM 
        " . DB_NAME_CORE2 . ".users u
    WHERE 
        u.user_id = ?";
    
    $conn2 = connectToCore2DB();
    $stmt2 = $conn2->prepare($userQuery);
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    if ($result2->num_rows > 0) {
        // Merge user data with driver data
        $userData = $result2->fetch_assoc();
        $driverData = array_merge($driverData, $userData);
    }
    
    // Check if driver has a current booking
    $bookingQuery = "SELECT 
        b.booking_id, b.customer_id, b.user_id, b.pickup_location, b.dropoff_location,
        b.pickup_datetime, b.dropoff_datetime, b.booking_status, b.fare_amount,
        b.distance_km, b.duration_minutes, b.special_instructions, b.created_at
    FROM 
        " . DB_NAME_CORE2 . ".bookings b
    WHERE 
        b.driver_id = ? 
        AND b.booking_status IN ('confirmed', 'in_progress')
    ORDER BY 
        CASE 
            WHEN b.booking_status = 'in_progress' THEN 1
            WHEN b.booking_status = 'confirmed' THEN 2
        END,
        b.pickup_datetime ASC
    LIMIT 1";
    
    $stmt3 = $conn2->prepare($bookingQuery);
    $stmt3->bind_param("i", $driverId);
    $stmt3->execute();
    $result3 = $stmt3->get_result();
    
    $currentBooking = null;
    
    if ($result3->num_rows > 0) {
        // Get current booking data
        $bookingData = $result3->fetch_assoc();
        
        // Get customer details for this booking
        if ($bookingData['customer_id']) {
            $customerId = $bookingData['customer_id'];
            
            $customerQuery = "SELECT 
                u.firstname as customer_firstname, u.lastname as customer_lastname,
                u.email as customer_email, u.phone as customer_phone
            FROM 
                " . DB_NAME_CORE2 . ".users u
            JOIN 
                " . DB_NAME_CORE1 . ".customers c ON u.user_id = c.user_id
            WHERE 
                c.customer_id = ?";
            
            $stmt4 = $conn2->prepare($customerQuery);
            $stmt4->bind_param("i", $customerId);
            $stmt4->execute();
            $result4 = $stmt4->get_result();
            
            if ($result4->num_rows > 0) {
                $customerData = $result4->fetch_assoc();
                $bookingData = array_merge($bookingData, $customerData);
            }
        }
        
        $currentBooking = $bookingData;
    }
    
    // Return success response with driver details and current booking if available
    echo json_encode([
        'success' => true,
        'data' => $driverData,
        'current_booking' => $currentBooking
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Error in get_details.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving driver details: ' . $e->getMessage()
    ]);
}
?> 