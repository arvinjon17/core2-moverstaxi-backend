<?php
/**
 * API Test Script
 * Used for testing API endpoints directly
 */

// Set content type for the response
header('Content-Type: text/html; charset=UTF-8');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Include necessary files
require_once 'functions/db.php';
require_once 'functions/auth.php';

// Authentication check - Require admin or super_admin role for security
$isAuthenticated = isset($_SESSION['user_id']) && (
    $_SESSION['user_role'] === 'admin' || 
    $_SESSION['user_role'] === 'super_admin'
);

if (!$isAuthenticated) {
    echo '<div style="padding: 20px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px; color: #721c24;">
        <h3>Authentication Required</h3>
        <p>You must be logged in as an administrator to access this test page.</p>
        <p><a href="index.php" style="color: #721c24; text-decoration: underline;">Return to login</a></p>
    </div>';
    exit;
}

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test Tool</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">API Test Tool</h1>
                <div class="alert alert-warning">
                    <strong>Warning:</strong> This page is for development and testing purposes only.
                </div>
            </div>
        </div>';

// Check if an API endpoint is specified
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

echo '<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Select API Endpoint to Test</h5>
            </div>
            <div class="card-body">
                <form action="" method="get">
                    <div class="mb-3">
                        <label for="endpoint" class="form-label">API Endpoint</label>
                        <select name="endpoint" id="endpoint" class="form-select">
                            <option value="">Select an endpoint</option>
                            <option value="bookings/get_details.php" ' . ($endpoint === 'bookings/get_details.php' ? 'selected' : '') . '>Get Booking Details</option>
                            <option value="bookings/list.php" ' . ($endpoint === 'bookings/list.php' ? 'selected' : '') . '>List Bookings</option>
                        </select>
                    </div>
                    <div class="mb-3" id="idField" style="display: ' . ($endpoint === 'bookings/get_details.php' ? 'block' : 'none') . ';">
                        <label for="id" class="form-label">ID Parameter</label>
                        <input type="number" class="form-control" id="id" name="id" value="' . $id . '" min="1" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Test API</button>
                </form>
            </div>
        </div>
    </div>
</div>';

// If an endpoint is selected, test it
if (!empty($endpoint)) {
    echo '<div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">API Test Results</h5>
                </div>
                <div class="card-body">
                    <h6 class="mb-3">Testing: ' . htmlspecialchars($endpoint) . '</h6>';
    
    // Determine the URL based on the endpoint
    $url = 'api/' . $endpoint;
    if ($endpoint === 'bookings/get_details.php' && $id > 0) {
        $url .= '?booking_id=' . $id;
    }
    
    echo '<p class="small text-muted">URL: ' . htmlspecialchars($url) . '</p>';
    
    // Capture start time
    $startTime = microtime(true);
    
    // Make the API request
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_COOKIE, session_name() . '=' . session_id()); // Pass session cookie
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    // Calculate execution time
    $executionTime = microtime(true) - $startTime;
    
    echo '<div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge bg-' . ($httpCode === 200 ? 'success' : 'danger') . '">HTTP Code: ' . $httpCode . '</span>
            <span class="text-muted small">Execution Time: ' . number_format($executionTime * 1000, 2) . ' ms</span>
        </div>';
    
    // Check if the response is valid JSON
    $isJson = false;
    $jsonData = null;
    
    try {
        $jsonData = json_decode($response, true);
        $isJson = json_last_error() === JSON_ERROR_NONE;
    } catch (Exception $e) {
        // Not JSON
    }
    
    if ($isJson) {
        // Pretty print JSON response
        echo '<h6>Response (JSON):</h6>
            <pre class="bg-light p-3 rounded" style="max-height: 500px; overflow-y: auto;">' . 
                htmlspecialchars(json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . 
            '</pre>';
    } else {
        // Show raw response
        echo '<h6>Response (Raw):</h6>
            <div class="alert alert-warning">Response is not valid JSON</div>
            <pre class="bg-light p-3 rounded" style="max-height: 500px; overflow-y: auto;">' . 
                htmlspecialchars($response) . 
            '</pre>';
    }
    
    echo '</div>
                </div>
            </div>
        </div>
    </div>';
}

echo '
    </div>
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $("#endpoint").change(function() {
                if ($(this).val() === "bookings/get_details.php") {
                    $("#idField").show();
                } else {
                    $("#idField").hide();
                }
            });
        });
    </script>
</body>
</html>';