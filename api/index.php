<?php
/**
 * API Endpoint
 * Handles cross-server database queries between core1 and core2
 */

// Set headers for CORS and JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-API-KEY');
header('Content-Type: application/json');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include the necessary files
require_once '../functions/config.php';
require_once '../functions/db.php';

// Basic API authentication
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (CURRENT_ENV === ENV_PRODUCTION && $api_key !== API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Handle API requests
$action = $_GET['action'] ?? '';
$query = $_POST['query'] ?? '';
$db = $_POST['db'] ?? (IS_CORE1_SERVER ? 'core1' : 'core2');

// Add debugging if enabled
if (DEBUG_MODE) {
    error_log("API Request: Action=$action, DB=$db, Query=$query");
}

// Process based on the requested action
switch ($action) {
    case 'execute_query':
        if (empty($query)) {
            echo json_encode(['error' => 'No query provided']);
            exit;
        }
        
        // Execute the query directly (we're on the correct server)
        $conn = ($db === 'core1') ? connectToCore1DB() : connectToCore2DB();
        
        if (!$conn) {
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
        
        $result = $conn->query($query);
        
        if ($result === false) {
            echo json_encode(['error' => 'Query failed: ' . $conn->error]);
        } elseif ($result === true) {
            // For INSERT, UPDATE, DELETE queries that don't return a result set
            echo json_encode([
                'success' => true, 
                'affected_rows' => $conn->affected_rows,
                'insert_id' => $conn->insert_id
            ]);
        } else {
            // For SELECT queries that return a result set
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            echo json_encode(['data' => $rows]);
        }
        break;
        
    case 'ping':
        // Simple heartbeat to check if API is responding
        echo json_encode([
            'success' => true,
            'server' => IS_CORE1_SERVER ? 'core1' : 'core2',
            'time' => date('Y-m-d H:i:s')
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}