<?php
// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and helper functions
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check user is logged in and has appropriate permissions
if (!isUserLoggedIn() || !hasPermission('manage_bookings')) {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Check if it's a GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only GET is allowed.'
    ]);
    exit;
}

// Optional date range filters
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;

// Validate date formats if provided
if ($startDate !== null) {
    $startDateObj = DateTime::createFromFormat('Y-m-d', $startDate);
    if (!$startDateObj) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid start date format. Expected format: YYYY-MM-DD'
        ]);
        exit;
    }
    $startDate = $startDateObj->format('Y-m-d 00:00:00');
}

if ($endDate !== null) {
    $endDateObj = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!$endDateObj) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid end date format. Expected format: YYYY-MM-DD'
        ]);
        exit;
    }
    $endDate = $endDateObj->format('Y-m-d 23:59:59');
}

// If no date range provided, default to current month
if ($startDate === null && $endDate === null) {
    $startDate = date('Y-m-01 00:00:00'); // First day of current month
    $endDate = date('Y-m-t 23:59:59');    // Last day of current month
}

try {
    // Get rides database connection
    $ridesDb = getRidesDatabaseConnection();
    
    // Prepare base WHERE clause for date filtering
    $dateWhere = "";
    $params = [];
    
    if ($startDate !== null && $endDate !== null) {
        $dateWhere = " WHERE created_at BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
    } elseif ($startDate !== null) {
        $dateWhere = " WHERE created_at >= ?";
        $params = [$startDate];
    } elseif ($endDate !== null) {
        $dateWhere = " WHERE created_at <= ?";
        $params = [$endDate];
    }
    
    // Get total bookings count
    $totalStmt = $ridesDb->prepare("
        SELECT COUNT(*) as total FROM bookings" . $dateWhere
    );
    $totalStmt->execute($params);
    $totalBookings = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get bookings by status
    $statusStmt = $ridesDb->prepare("
        SELECT 
            status, 
            COUNT(*) as count 
        FROM bookings" . 
        $dateWhere . 
        ($dateWhere ? " AND " : " WHERE ") . "status IS NOT NULL 
        GROUP BY status
    ");
    $statusStmt->execute($params);
    $bookingsByStatus = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get bookings by day for the selected period
    $dailyStmt = $ridesDb->prepare("
        SELECT 
            DATE(created_at) as date, 
            COUNT(*) as count 
        FROM bookings" . 
        $dateWhere . 
        " GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ");
    $dailyStmt->execute($params);
    $bookingsByDay = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get average fare by vehicle type
    $fareStmt = $ridesDb->prepare("
        SELECT 
            vehicle_type, 
            AVG(estimated_fare) as average_fare,
            COUNT(*) as count
        FROM bookings" . 
        $dateWhere . 
        ($dateWhere ? " AND " : " WHERE ") . "vehicle_type IS NOT NULL AND estimated_fare > 0
        GROUP BY vehicle_type
    ");
    $fareStmt->execute($params);
    $faresByVehicleType = $fareStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get count of bookings by hour of day (for determining peak hours)
    $hourlyStmt = $ridesDb->prepare("
        SELECT 
            HOUR(created_at) as hour, 
            COUNT(*) as count 
        FROM bookings" . 
        $dateWhere . 
        " GROUP BY HOUR(created_at) 
        ORDER BY hour ASC
    ");
    $hourlyStmt->execute($params);
    $bookingsByHour = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent booking activity (last 10 bookings)
    $recentStmt = $ridesDb->prepare("
        SELECT 
            id, 
            customer_id, 
            status, 
            created_at,
            pickup_time,
            vehicle_type
        FROM bookings
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recentStmt->execute();
    $recentBookings = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format status counts into a more usable structure
    $statusCounts = [
        'pending' => 0,
        'confirmed' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    
    foreach ($bookingsByStatus as $status) {
        $statusCounts[$status['status']] = (int) $status['count'];
    }
    
    // Calculate revenue statistics
    $revenueStmt = $ridesDb->prepare("
        SELECT 
            SUM(estimated_fare) as total_revenue,
            COUNT(*) as completed_rides
        FROM bookings" . 
        $dateWhere . 
        ($dateWhere ? " AND " : " WHERE ") . "status = 'completed'
    ");
    $revenueStmt->execute($params);
    $revenue = $revenueStmt->fetch(PDO::FETCH_ASSOC);
    
    // Return all statistics
    echo json_encode([
        'success' => true,
        'date_range' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'total_bookings' => (int) $totalBookings,
        'bookings_by_status' => $statusCounts,
        'revenue_data' => [
            'total_revenue' => $revenue['total_revenue'] ? (float) $revenue['total_revenue'] : 0,
            'completed_rides' => (int) $revenue['completed_rides'],
            'average_fare' => $revenue['completed_rides'] > 0 ? round(($revenue['total_revenue'] / $revenue['completed_rides']), 2) : 0
        ],
        'daily_bookings' => $bookingsByDay,
        'hourly_distribution' => $bookingsByHour,
        'vehicle_type_stats' => $faresByVehicleType,
        'recent_bookings' => $recentBookings
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Failed to get booking statistics: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 