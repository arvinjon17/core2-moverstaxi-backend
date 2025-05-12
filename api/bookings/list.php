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

// Get filters from query parameters
$status = isset($_GET['status']) ? trim($_GET['status']) : null;
$customerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : null;
$driverId = isset($_GET['driver_id']) ? intval($_GET['driver_id']) : null;
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;
$vehicleType = isset($_GET['vehicle_type']) ? trim($_GET['vehicle_type']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20; // Default 20, max 100
$offset = ($page - 1) * $limit;

// Sorting parameters
$sortBy = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'created_at';
$sortOrder = isset($_GET['sort_order']) && strtolower($_GET['sort_order']) === 'asc' ? 'ASC' : 'DESC';

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

// Validate the sort column to prevent SQL injection
$allowedSortColumns = [
    'id', 'customer_id', 'driver_id', 'pickup_time', 'created_at', 
    'status', 'estimated_fare', 'vehicle_type'
];
if (!in_array($sortBy, $allowedSortColumns)) {
    $sortBy = 'created_at'; // Default to created_at if invalid
}

try {
    // Get database connections
    $mainDb = getDatabaseConnection();
    $ridesDb = getRidesDatabaseConnection();
    
    // Build the SQL query with filters
    $sql = "
        SELECT 
            b.id,
            b.customer_id,
            b.driver_id,
            b.pickup_location,
            b.dropoff_location,
            b.pickup_time,
            b.status,
            b.estimated_fare,
            b.estimated_distance,
            b.estimated_duration,
            b.vehicle_type,
            b.created_at,
            b.updated_at,
            c.name AS customer_name,
            c.phone AS customer_phone,
            CONCAT(u.name) AS driver_name
        FROM bookings b
        LEFT JOIN " . getDatabaseName() . ".customers c ON b.customer_id = c.id
        LEFT JOIN " . getDatabaseName() . ".drivers d ON b.driver_id = d.id
        LEFT JOIN " . getDatabaseName() . ".users u ON d.user_id = u.id
        WHERE 1
    ";
    
    $params = [];
    
    // Add filters to query
    if ($status !== null) {
        $sql .= " AND b.status = ?";
        $params[] = $status;
    }
    
    if ($customerId !== null && $customerId > 0) {
        $sql .= " AND b.customer_id = ?";
        $params[] = $customerId;
    }
    
    if ($driverId !== null && $driverId > 0) {
        $sql .= " AND b.driver_id = ?";
        $params[] = $driverId;
    }
    
    if ($startDate !== null) {
        $sql .= " AND b.created_at >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate !== null) {
        $sql .= " AND b.created_at <= ?";
        $params[] = $endDate;
    }
    
    if ($vehicleType !== null) {
        $sql .= " AND b.vehicle_type = ?";
        $params[] = $vehicleType;
    }
    
    if ($search !== null) {
        $sql .= " AND (
            b.pickup_location LIKE ? OR 
            b.dropoff_location LIKE ? OR 
            c.name LIKE ? OR 
            c.phone LIKE ? OR
            u.name LIKE ?
        )";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Count total results for pagination
    $countSql = "SELECT COUNT(*) as total FROM ({$sql}) as filtered_results";
    $countStmt = $ridesDb->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Add sorting and pagination
    $sql .= " ORDER BY b.{$sortBy} {$sortOrder}";
    $sql .= " LIMIT {$limit} OFFSET {$offset}";
    
    // Execute the main query
    $stmt = $ridesDb->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination metadata
    $totalPages = ceil($totalCount / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    // Return the results
    echo json_encode([
        'success' => true,
        'count' => count($bookings),
        'total' => (int) $totalCount,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage,
            'next_page' => $hasNextPage ? $page + 1 : null,
            'prev_page' => $hasPrevPage ? $page - 1 : null
        ],
        'bookings' => $bookings
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Failed to retrieve bookings: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 