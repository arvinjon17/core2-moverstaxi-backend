<?php
/**
 * Get Customer Details API
 * Returns detailed information about a customer for the modal view
 */

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once '../functions/db.php';
require_once '../functions/auth.php';
require_once '../functions/profile_images.php';

// Check if the user is logged in and has permission to manage customers
if (!hasPermission('manage_customers')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. You do not have permission to view customer details.'
    ]);
    exit;
}

// Check if customer ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID.'
    ]);
    exit;
}

$userId = (int)$_GET['id'];  // This is the user_id from core2_movers.users
error_log("Retrieving details for user_id: $userId");

// Connect to databases
$conn2 = connectToCore2DB();
$conn1 = connectToCore1DB();

if (!$conn2 || !$conn1) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to connect to database.'
    ]);
    exit;
}

try {
    // Get customer data from core2_movers (users table)
    $query2 = "SELECT 
        user_id, 
        firstname, 
        lastname, 
        email, 
        phone, 
        status,
        last_login,
        created_at
    FROM 
        users 
    WHERE 
        user_id = ? 
        AND role = 'customer'";
    
    $stmt2 = $conn2->prepare($query2);
    
    if (!$stmt2) {
        throw new Exception("Failed to prepare user query: " . $conn2->error);
    }
    
    $stmt2->bind_param('i', $userId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    if (!$result2 || $result2->num_rows === 0) {
        throw new Exception("Customer not found");
    }
    
    $userData = $result2->fetch_assoc();
    $stmt2->close();
    
    // Get additional customer data from core1_movers (customers table)
    $query1 = "SELECT 
        customer_id,
        address, 
        city, 
        state, 
        zip,
        status as activity_status,
        notes,
        created_at as customer_created_at,
        updated_at as customer_updated_at
    FROM 
        customers 
    WHERE 
        user_id = ?";
    
    $stmt1 = $conn1->prepare($query1);
    
    if (!$stmt1) {
        throw new Exception("Failed to prepare customer query: " . $conn1->error);
    }
    
    $stmt1->bind_param('i', $userId);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    
    $customerExtras = [];
    $customerId = null; // Initialize customer_id from core1_movers.customers
    
    if ($result1 && $result1->num_rows > 0) {
        $customerExtras = $result1->fetch_assoc();
        $customerId = $customerExtras['customer_id']; // Get the customer_id for booking queries
        error_log("Found customer_id: $customerId matching user_id: $userId");
    } else {
        error_log("No matching record found in core1_movers.customers for user_id: $userId");
    }
    $stmt1->close();
    
    // Get payment preferences if exists
    $paymentMethod = "Not specified";
    $userPreferencesQuery = "SELECT preference_value FROM user_preferences 
                            WHERE user_id = ? AND preference_key = 'payment_method' 
                            LIMIT 1";
    
    $prefStmt = $conn2->prepare($userPreferencesQuery);
    if ($prefStmt) {
        $prefStmt->bind_param('i', $userId);
        $prefStmt->execute();
        $prefResult = $prefStmt->get_result();
        
        if ($prefResult && $prefResult->num_rows > 0) {
            $prefRow = $prefResult->fetch_assoc();
            $paymentMethod = $prefRow['preference_value'];
        }
        $prefStmt->close();
    }
    
    // Get booking information and history
    $bookingCount = 0;
    $pendingBookings = 0;
    $completedBookings = 0;
    $cancelledBookings = 0;
    $totalSpending = 0;
    $bookingHistory = [];
    
    // First, try to get bookings using customer_id from core1_movers if it exists
    if ($customerId) {
        error_log("Trying to process booking data for customer_id: $customerId (from core1_movers.customers)");
        
        // Count total bookings and spending
        $bookingQuery = "SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN booking_status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN booking_status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
            SUM(CASE WHEN booking_status = 'completed' THEN fare_amount ELSE 0 END) AS total_value
        FROM 
            core1_movers2.bookings 
        WHERE 
            customer_id = ?";
        
        $bookingStmt = $conn2->prepare($bookingQuery);
        
        if ($bookingStmt) {
            $bookingStmt->bind_param('i', $customerId);
            $bookingStmt->execute();
            $bookingResult = $bookingStmt->get_result();
            
            if ($bookingResult && $bookingResult->num_rows > 0) {
                $bookingData = $bookingResult->fetch_assoc();
                $bookingCount = $bookingData['total'] ?? 0;
                $pendingBookings = $bookingData['pending'] ?? 0;
                $completedBookings = $bookingData['completed'] ?? 0;
                $cancelledBookings = $bookingData['cancelled'] ?? 0;
                $totalSpending = $bookingData['total_value'] ?? 0;
                error_log("Found booking data using customer_id: Total=$bookingCount, Completed=$completedBookings");
            } else {
                error_log("No booking statistics found for customer_id: $customerId - will try using user_id");
            }
            $bookingStmt->close();
        }
        
        // Get booking history using the customer_id from core1_movers
        $historyQuery = "SELECT 
            b.booking_id,
            b.pickup_datetime,
            b.pickup_location,
            b.dropoff_location,
            b.booking_status,
            b.fare_amount,
            b.distance_km,
            b.duration_minutes,
            b.rating,
            b.rating_comment,
            d.firstname as driver_firstname,
            d.lastname as driver_lastname,
            v.model as vehicle_model,
            v.plate_number as vehicle_plate,
            p.*
        FROM 
            core1_movers2.bookings b
        LEFT JOIN 
            core1_movers.drivers dr ON b.driver_id = dr.driver_id
        LEFT JOIN 
            core1_movers2.users d ON dr.user_id = d.user_id
        LEFT JOIN 
            core1_movers.vehicles v ON b.vehicle_id = v.vehicle_id
        LEFT JOIN 
            core1_movers2.payments p ON b.booking_id = p.booking_id
        WHERE 
            b.customer_id = ?
        ORDER BY 
            b.pickup_datetime DESC
        LIMIT 10";
        
        error_log("Executing booking history query for customer_id: $customerId");
        $historyStmt = $conn2->prepare($historyQuery);
        
        if ($historyStmt) {
            $historyStmt->bind_param('i', $customerId);
            $historyStmt->execute();
            $historyResult = $historyStmt->get_result();
            
            if ($historyResult) {
                $bookingsCount = 0;
                while ($booking = $historyResult->fetch_assoc()) {
                    $bookingsCount++;
                    error_log("Found booking: {$booking['booking_id']} using customer_id");
                    
                    // Determine payment status - check both 'status' and 'payment_status' fields
                    $paymentStatus = 'unknown';
                    if (isset($booking['status']) && !empty($booking['status'])) {
                        $paymentStatus = $booking['status'];
                    } else if (isset($booking['payment_status']) && !empty($booking['payment_status'])) {
                        $paymentStatus = $booking['payment_status'];
                    }
                    
                    error_log("Payment status for booking {$booking['booking_id']}: $paymentStatus");
                    
                    $bookingHistory[] = [
                        'booking_id' => $booking['booking_id'],
                        'date' => date('Y-m-d H:i', strtotime($booking['pickup_datetime'])),
                        'pickup' => $booking['pickup_location'],
                        'dropoff' => $booking['dropoff_location'],
                        'status' => $booking['booking_status'],
                        'payment_status' => $paymentStatus,
                        'transaction_id' => $booking['transaction_id'] ?? '',
                        'payment_date' => isset($booking['payment_date']) && $booking['payment_date'] ? 
                                        date('Y-m-d H:i', strtotime($booking['payment_date'])) : '',
                        'payment_method' => $booking['payment_method'] ?? 'unknown',
                        'fare' => $booking['fare_amount'],
                        'distance' => $booking['distance_km'],
                        'duration' => $booking['duration_minutes'],
                        'driver_name' => ($booking['driver_firstname'] && $booking['driver_lastname']) ? 
                                        $booking['driver_firstname'] . ' ' . $booking['driver_lastname'] : 'Not assigned',
                        'vehicle' => ($booking['vehicle_model'] && $booking['vehicle_plate']) ? 
                                    $booking['vehicle_model'] . ' (' . $booking['vehicle_plate'] . ')' : 'Not assigned',
                        'rating' => $booking['rating'],
                        'comment' => $booking['rating_comment']
                    ];
                }
                error_log("Processed $bookingsCount bookings for customer_id: $customerId");
            } else {
                error_log("No booking history results for customer_id: $customerId");
            }
            $historyStmt->close();
        } else {
            error_log("Failed to prepare booking history query using customer_id: " . $conn2->error);
        }
    }
    
    // If no bookings found using customer_id, try using user_id directly
    if (empty($bookingHistory)) {
        error_log("No bookings found using customer_id, trying with user_id: $userId directly");
        
        // Count total bookings and spending with user_id
        $bookingQueryWithUserId = "SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN booking_status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN booking_status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
            SUM(CASE WHEN booking_status = 'completed' THEN fare_amount ELSE 0 END) AS total_value
        FROM 
            core1_movers2.bookings 
        WHERE 
            user_id = ?";
        
        $bookingStmtWithUserId = $conn2->prepare($bookingQueryWithUserId);
        
        if ($bookingStmtWithUserId) {
            $bookingStmtWithUserId->bind_param('i', $userId);
            $bookingStmtWithUserId->execute();
            $bookingResultWithUserId = $bookingStmtWithUserId->get_result();
            
            if ($bookingResultWithUserId && $bookingResultWithUserId->num_rows > 0) {
                $bookingDataWithUserId = $bookingResultWithUserId->fetch_assoc();
                $bookingCount = $bookingDataWithUserId['total'] ?? 0;
                $pendingBookings = $bookingDataWithUserId['pending'] ?? 0;
                $completedBookings = $bookingDataWithUserId['completed'] ?? 0;
                $cancelledBookings = $bookingDataWithUserId['cancelled'] ?? 0;
                $totalSpending = $bookingDataWithUserId['total_value'] ?? 0;
                error_log("Found booking data using user_id: Total=$bookingCount, Completed=$completedBookings");
            } else {
                error_log("No booking statistics found for user_id: $userId");
            }
            $bookingStmtWithUserId->close();
        }
        
        // Get booking history using user_id
        $historyQueryWithUserId = "SELECT 
            b.booking_id,
            b.pickup_datetime,
            b.pickup_location,
            b.dropoff_location,
            b.booking_status,
            b.fare_amount,
            b.distance_km,
            b.duration_minutes,
            b.rating,
            b.rating_comment,
            d.firstname as driver_firstname,
            d.lastname as driver_lastname,
            v.model as vehicle_model,
            v.plate_number as vehicle_plate,
            p.status as payment_status,
            p.transaction_id,
            p.payment_date,
            p.payment_method
        FROM 
            core1_movers2.bookings b
        LEFT JOIN 
            core1_movers.drivers dr ON b.driver_id = dr.driver_id
        LEFT JOIN 
            core1_movers2.users d ON dr.user_id = d.user_id
        LEFT JOIN 
            core1_movers.vehicles v ON b.vehicle_id = v.vehicle_id
        LEFT JOIN 
            core1_movers2.payments p ON b.booking_id = p.booking_id
        WHERE 
            b.user_id = ?
        ORDER BY 
            b.pickup_datetime DESC
        LIMIT 10";
        
        error_log("Executing booking history query for user_id: $userId");
        $historyStmtWithUserId = $conn2->prepare($historyQueryWithUserId);
        
        if ($historyStmtWithUserId) {
            $historyStmtWithUserId->bind_param('i', $userId);
            $historyStmtWithUserId->execute();
            $historyResultWithUserId = $historyStmtWithUserId->get_result();
            
            if ($historyResultWithUserId) {
                $bookingsCount = 0;
                while ($booking = $historyResultWithUserId->fetch_assoc()) {
                    $bookingsCount++;
                    error_log("Found booking: {$booking['booking_id']} using user_id");
                    $bookingHistory[] = [
                        'booking_id' => $booking['booking_id'],
                        'date' => date('Y-m-d H:i', strtotime($booking['pickup_datetime'])),
                        'pickup' => $booking['pickup_location'],
                        'dropoff' => $booking['dropoff_location'],
                        'status' => $booking['booking_status'],
                        'payment_status' => $booking['payment_status'] ?? 'unknown',
                        'transaction_id' => $booking['transaction_id'] ?? '',
                        'payment_date' => $booking['payment_date'] ? date('Y-m-d H:i', strtotime($booking['payment_date'])) : '',
                        'payment_method' => $booking['payment_method'] ?? 'unknown',
                        'fare' => $booking['fare_amount'],
                        'distance' => $booking['distance_km'],
                        'duration' => $booking['duration_minutes'],
                        'driver_name' => ($booking['driver_firstname'] && $booking['driver_lastname']) ? 
                                        $booking['driver_firstname'] . ' ' . $booking['driver_lastname'] : 'Not assigned',
                        'vehicle' => ($booking['vehicle_model'] && $booking['vehicle_plate']) ? 
                                    $booking['vehicle_model'] . ' (' . $booking['vehicle_plate'] . ')' : 'Not assigned',
                        'rating' => $booking['rating'],
                        'comment' => $booking['rating_comment']
                    ];
                }
                error_log("Processed $bookingsCount bookings for user_id: $userId");
            } else {
                error_log("No booking history results for user_id: $userId");
            }
            $historyStmtWithUserId->close();
        } else {
            error_log("Failed to prepare booking history query using user_id: " . $conn2->error);
        }
    }
    
    // If still no bookings found, try a direct database query to understand how bookings are stored
    if (empty($bookingHistory)) {
        error_log("No bookings found using standard approaches. Attempting direct query analysis...");
        
        // First check the bookings table structure
        $tableStructureQuery = "DESCRIBE core1_movers2.bookings";
        $tableResult = $conn2->query($tableStructureQuery);
        
        if ($tableResult) {
            $fieldsFound = [];
            error_log("Bookings table structure:");
            while ($field = $tableResult->fetch_assoc()) {
                error_log("Field: {$field['Field']}, Type: {$field['Type']}");
                $fieldsFound[] = $field['Field'];
            }
            
            // Check if we have both customer_id and user_id fields or what identifier is used
            $hasCustomerId = in_array('customer_id', $fieldsFound);
            $hasUserId = in_array('user_id', $fieldsFound);
            $bookingIdField = in_array('booking_id', $fieldsFound) ? 'booking_id' : (in_array('id', $fieldsFound) ? 'id' : null);
            
            error_log("Booking table analysis - Has customer_id: " . ($hasCustomerId ? 'Yes' : 'No') . 
                     ", Has user_id: " . ($hasUserId ? 'Yes' : 'No') . 
                     ", Booking ID field: " . ($bookingIdField ?? 'Not found'));
            
            // If we found relevant fields, try one more approach - check for any bookings related to this user
            if ($bookingIdField) {
                $fallbackQuery = "SELECT * FROM core1_movers2.bookings WHERE 1=1";
                $limitClause = " LIMIT 10";
                $conditions = [];
                $params = [];
                $types = '';
                
                if ($hasCustomerId && $customerId) {
                    $conditions[] = "customer_id = ?";
                    $params[] = $customerId;
                    $types .= 'i';
                }
                
                if ($hasUserId) {
                    $conditions[] = "user_id = ?";
                    $params[] = $userId;
                    $types .= 'i';
                }
                
                // If we have conditions, add them to the query
                if (!empty($conditions)) {
                    $fallbackQuery .= " AND (" . implode(" OR ", $conditions) . ")";
                    $fallbackQuery .= $limitClause;
                    
                    error_log("Executing fallback query: $fallbackQuery");
                    $fallbackStmt = $conn2->prepare($fallbackQuery);
                    
                    if ($fallbackStmt) {
                        if (!empty($params)) {
                            $fallbackStmt->bind_param($types, ...$params);
                        }
                        $fallbackStmt->execute();
                        $fallbackResult = $fallbackStmt->get_result();
                        
                        if ($fallbackResult && $fallbackResult->num_rows > 0) {
                            $bookingsCount = 0;
                            error_log("Found " . $fallbackResult->num_rows . " bookings using fallback query");
                            
                            while ($booking = $fallbackResult->fetch_assoc()) {
                                $bookingsCount++;
                                error_log("Fallback query found booking ID: " . ($booking[$bookingIdField] ?? 'unknown'));
                                
                                // Extract available booking data
                                $processedBooking = [
                                    'booking_id' => $booking[$bookingIdField] ?? 'unknown',
                                    'date' => isset($booking['pickup_datetime']) ? date('Y-m-d H:i', strtotime($booking['pickup_datetime'])) : 'N/A',
                                    'pickup' => $booking['pickup_location'] ?? 'N/A',
                                    'dropoff' => $booking['dropoff_location'] ?? 'N/A',
                                    'status' => $booking['booking_status'] ?? $booking['status'] ?? 'unknown',
                                    'payment_status' => 'unknown', // To be populated separately
                                    'fare' => $booking['fare_amount'] ?? $booking['fare'] ?? '0.00',
                                    'distance' => $booking['distance_km'] ?? $booking['distance'] ?? 'N/A',
                                    'duration' => $booking['duration_minutes'] ?? $booking['duration'] ?? 'N/A',
                                    'driver_name' => 'Not available', // We don't have joined data here
                                    'vehicle' => 'Not available',    // We don't have joined data here
                                    'rating' => $booking['rating'] ?? 'N/A',
                                    'comment' => $booking['rating_comment'] ?? $booking['comment'] ?? 'N/A'
                                ];
                                
                                // Get payment details for this booking from the payments table
                                $paymentQuery = "SELECT * FROM core1_movers2.payments WHERE booking_id = ? LIMIT 1";
                                
                                $paymentStmt = $conn2->prepare($paymentQuery);
                                if ($paymentStmt) {
                                    $bookingId = $booking[$bookingIdField];
                                    $paymentStmt->bind_param('i', $bookingId);
                                    $paymentStmt->execute();
                                    $paymentResult = $paymentStmt->get_result();
                                    
                                    if ($paymentResult && $paymentResult->num_rows > 0) {
                                        $paymentData = $paymentResult->fetch_assoc();
                                        
                                        // Debug: Log the actual payment data to see field names
                                        error_log("Payment data for booking ID $bookingId: " . print_r($paymentData, true));
                                        
                                        // Check which field contains the payment status (could be 'status' or 'payment_status')
                                        if (isset($paymentData['status'])) {
                                            $processedBooking['payment_status'] = $paymentData['status'];
                                        } else if (isset($paymentData['payment_status'])) {
                                            $processedBooking['payment_status'] = $paymentData['payment_status'];
                                        } else {
                                            $processedBooking['payment_status'] = 'unknown';
                                        }
                                        
                                        $processedBooking['transaction_id'] = $paymentData['transaction_id'] ?? '';
                                        $processedBooking['payment_date'] = isset($paymentData['payment_date']) ? 
                                            date('Y-m-d H:i', strtotime($paymentData['payment_date'])) : '';
                                        $processedBooking['payment_method'] = $paymentData['payment_method'] ?? 'unknown';
                                        
                                        error_log("Assigned payment status: {$processedBooking['payment_status']}");
                                    } else {
                                        error_log("No payment info found for booking ID $bookingId");
                                    }
                                    $paymentStmt->close();
                                }
                                
                                $bookingHistory[] = $processedBooking;
                            }
                            
                            error_log("Processed $bookingsCount bookings from fallback query");
                            
                            // Update booking count statistics based on what we found
                            if ($bookingCount == 0) {
                                $bookingCount = $bookingsCount;
                                // Estimate other statistics since we don't have full info
                                $completedBookings = 0;
                                $pendingBookings = 0;
                                $cancelledBookings = 0;
                                
                                foreach ($bookingHistory as $booking) {
                                    if (strtolower($booking['status']) == 'completed') {
                                        $completedBookings++;
                                    } else if (strtolower($booking['status']) == 'cancelled') {
                                        $cancelledBookings++;
                                    } else {
                                        $pendingBookings++;
                                    }
                                }
                            }
                        } else {
                            error_log("No results from fallback query");
                        }
                        
                        $fallbackStmt->close();
                    } else {
                        error_log("Failed to prepare fallback query: " . $conn2->error);
                    }
                } else {
                    error_log("No suitable conditions for fallback query - no identifiers available");
                }
            }
            
            if (empty($bookingHistory)) {
                error_log("No real booking data found for this customer after all attempts. Returning empty booking history.");
            }
        } else {
            error_log("Failed to check bookings table structure: " . $conn2->error);
        }
    }
    
    // Get driver info from bookings if available
    $driverInfo = null;
    
    // Get customer documents if any
    $documents = [];
    $docsQuery = "SELECT document_id, document_type, document_name, uploaded_at 
                FROM user_documents WHERE user_id = ? ORDER BY uploaded_at DESC";
    
    if ($conn2->prepare($docsQuery)) {
        $docsStmt = $conn2->prepare($docsQuery);
        $docsStmt->bind_param('i', $userId);
        $docsStmt->execute();
        $docsResult = $docsStmt->get_result();
        
        if ($docsResult) {
            while ($doc = $docsResult->fetch_assoc()) {
                $documents[] = $doc;
            }
        }
        $docsStmt->close();
    }
    
    // Get driver feedback about customer
    $driverFeedback = [];
    $driverFeedbackQuery = "SELECT f.feedback_id, f.rating, f.comment, f.created_at,
                          u.firstname, u.lastname
                          FROM driver_feedback f
                          JOIN core1_movers.drivers d ON f.driver_id = d.driver_id
                          JOIN core1_movers2.users u ON d.user_id = u.user_id
                          WHERE f.customer_id = ?
                          ORDER BY f.created_at DESC";
    
    if ($conn2->prepare($driverFeedbackQuery)) {
        $feedbackStmt = $conn2->prepare($driverFeedbackQuery);
        $feedbackStmt->bind_param('i', $customerId);
        $feedbackStmt->execute();
        $feedbackResult = $feedbackStmt->get_result();
        
        if ($feedbackResult) {
            while ($feedback = $feedbackResult->fetch_assoc()) {
                $driverFeedback[] = [
                    'id' => $feedback['feedback_id'],
                    'rating' => $feedback['rating'],
                    'comment' => $feedback['comment'],
                    'date' => date('Y-m-d', strtotime($feedback['created_at'])),
                    'driver_name' => $feedback['firstname'] . ' ' . $feedback['lastname']
                ];
            }
        }
        $feedbackStmt->close();
    }
    
    // Get profile image URL
    $profileImageUrl = getUserProfileImageUrl(
        $userId, 
        'customer',
        $userData['firstname'] ?? '',
        $userData['lastname'] ?? ''
    );
    
    // Format address
    $address = '';
    if (!empty($customerExtras)) {
        $addressParts = [];
        if (!empty($customerExtras['address'])) $addressParts[] = $customerExtras['address'];
        if (!empty($customerExtras['city'])) $addressParts[] = $customerExtras['city'];
        if (!empty($customerExtras['state'])) $addressParts[] = $customerExtras['state'];
        if (!empty($customerExtras['zip'])) $addressParts[] = $customerExtras['zip'];
        
        $address = !empty($addressParts) ? implode(', ', $addressParts) : 'No address provided';
    }
    
    // Determine loyalty status based on booking count or spending
    $loyaltyStatus = 'Regular';
    if ($completedBookings >= 20 || $totalSpending >= 20000) {
        $loyaltyStatus = 'Gold';
    } elseif ($completedBookings >= 10 || $totalSpending >= 10000) {
        $loyaltyStatus = 'Silver';
    } elseif ($completedBookings >= 5 || $totalSpending >= 5000) {
        $loyaltyStatus = 'Bronze';
    }
    
    // Prepare response data
    $customer = [
        'id' => $userData['user_id'],
        'full_name' => $userData['firstname'] . ' ' . $userData['lastname'],
        'email' => $userData['email'],
        'phone' => $userData['phone'],
        'address' => $address,
        'account_status' => $userData['status'],
        'activity_status' => $customerExtras['activity_status'] ?? 'offline',
        'last_login' => $userData['last_login'] ? date('Y-m-d H:i:s', strtotime($userData['last_login'])) : 'Never',
        'registered_date' => $userData['created_at'] ? date('Y-m-d', strtotime($userData['created_at'])) : 'Unknown',
        'avatar' => $profileImageUrl,
        'customer_type' => 'Regular Customer',
        'notes' => $customerExtras['notes'] ?? '',
        'customer_id_core1' => $customerId, // Include the core1 customer_id for reference
        
        // Booking statistics
        'total_rides' => $bookingCount,
        'pending_rides' => $pendingBookings,
        'completed_rides' => $completedBookings,
        'cancelled_rides' => $cancelledBookings,
        'total_spending' => number_format($totalSpending, 2),
        'loyalty_status' => $loyaltyStatus,
        
        // Payment information
        'preferred_payment_method' => $paymentMethod,
        
        // Booking history
        'booking_history' => $bookingHistory,
        
        // Customer documents
        'documents' => $documents,
        
        // Driver feedback
        'driver_feedback' => $driverFeedback
    ];
    
    // Debug info
    error_log("Returning customer data with " . count($bookingHistory) . " bookings");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'customer' => $customer
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error fetching customer details: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Close database connections
    if ($conn1) {
        $conn1->close();
    }
    if ($conn2) {
        $conn2->close();
    }
} 