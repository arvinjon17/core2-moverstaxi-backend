<?php
/**
 * Debug Information Page
 * 
 * This file displays important debugging information.
 * SECURITY WARNING: Remove this file when debugging is complete!
 */

// Ensure this file can only be accessed with a special token for security
$debug_token = 'debug_movers_123'; // Change this to a secure random token in production

// Validate debug token
if (!isset($_GET['token']) || $_GET['token'] !== $debug_token) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>403 Forbidden</h1>';
    exit;
}

// Set error reporting to maximum
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check for specific test to run
$test = $_GET['test'] ?? '';

// Header
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Information</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.5; }
        h1, h2 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; }
        .error { color: red; }
        .success { color: green; }
        .section { margin-bottom: 30px; border-bottom: 1px solid #ccc; padding-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Debug Information</h1>
    <p class="error"><strong>SECURITY WARNING: Remove this file when debugging is complete!</strong></p>';

// Run specific tests if requested
if ($test === 'db_core1') {
    test_db_connection('core1');
    exit;
} elseif ($test === 'db_core2') {
    test_db_connection('core2');
    exit;
} elseif ($test === 'phpinfo') {
    phpinfo();
    exit;
} elseif ($test === 'error_log') {
    display_error_log();
    exit;
}

// Basic server information
echo '<div class="section">
    <h2>Server Information</h2>
    <table>
        <tr><th>Item</th><th>Value</th></tr>
        <tr><td>Server Software</td><td>' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . '</td></tr>
        <tr><td>PHP Version</td><td>' . PHP_VERSION . '</td></tr>
        <tr><td>Server Name</td><td>' . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . '</td></tr>
        <tr><td>Server Address</td><td>' . ($_SERVER['SERVER_ADDR'] ?? 'Unknown') . '</td></tr>
        <tr><td>Document Root</td><td>' . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . '</td></tr>
        <tr><td>Request Time</td><td>' . date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time()) . '</td></tr>
    </table>
</div>';

// PHP extensions and configuration
echo '<div class="section">
    <h2>PHP Configuration</h2>
    <table>
        <tr><th>Item</th><th>Value</th></tr>
        <tr><td>display_errors</td><td>' . ini_get('display_errors') . '</td></tr>
        <tr><td>error_reporting</td><td>' . error_reporting() . '</td></tr>
        <tr><td>max_execution_time</td><td>' . ini_get('max_execution_time') . '</td></tr>
        <tr><td>memory_limit</td><td>' . ini_get('memory_limit') . '</td></tr>
        <tr><td>post_max_size</td><td>' . ini_get('post_max_size') . '</td></tr>
        <tr><td>upload_max_filesize</td><td>' . ini_get('upload_max_filesize') . '</td></tr>
        <tr><td>date.timezone</td><td>' . ini_get('date.timezone') . '</td></tr>
    </table>
    
    <h3>Loaded Extensions</h3>
    <pre>' . implode(', ', get_loaded_extensions()) . '</pre>
</div>';

// Test includes and required files
echo '<div class="section">
    <h2>Include Path & Required Files</h2>
    <p>Include Path: ' . get_include_path() . '</p>';

// Test including essential files
echo '<h3>Testing File Includes</h3>';

try {
    require_once 'functions/config.php';
    echo '<p class="success">✓ config.php included successfully</p>';
} catch (Exception $e) {
    echo '<p class="error">✗ Error including config.php: ' . $e->getMessage() . '</p>';
}

try {
    require_once 'functions/db.php';
    echo '<p class="success">✓ db.php included successfully</p>';
} catch (Exception $e) {
    echo '<p class="error">✗ Error including db.php: ' . $e->getMessage() . '</p>';
}

try {
    require_once 'functions/auth.php';
    echo '<p class="success">✓ auth.php included successfully</p>';
} catch (Exception $e) {
    echo '<p class="error">✗ Error including auth.php: ' . $e->getMessage() . '</p>';
}

echo '</div>';

// Database connection test links
echo '<div class="section">
    <h2>Database Tests</h2>
    <p><a href="?token=' . htmlspecialchars($debug_token) . '&test=db_core1">Test Core1 Database Connection</a></p>
    <p><a href="?token=' . htmlspecialchars($debug_token) . '&test=db_core2">Test Core2 Database Connection</a></p>
</div>';

// Additional tests
echo '<div class="section">
    <h2>Additional Tests</h2>
    <p><a href="?token=' . htmlspecialchars($debug_token) . '&test=phpinfo">View Full PHP Info</a></p>
    <p><a href="?token=' . htmlspecialchars($debug_token) . '&test=error_log">View Error Log</a></p>
</div>';

// Footer
echo '</body>
</html>';

/**
 * Test database connection function
 */
function test_db_connection($db) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Test</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.5; }
            h1, h2 { color: #333; }
            pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; }
            .error { color: red; }
            .success { color: green; }
        </style>
    </head>
    <body>
        <h1>Testing ' . strtoupper($db) . ' Database Connection</h1>';
    
    if ($db === 'core1') {
        try {
            require_once 'functions/db.php';
            $conn = connectToCore1DB();
            
            if ($conn === null) {
                echo '<p class="error">Connection is null. This could mean you\'re in production and on the wrong server.</p>';
                echo '<p>IS_CORE1_SERVER: ' . (IS_CORE1_SERVER ? 'true' : 'false') . '</p>';
                echo '<p>IS_CORE2_SERVER: ' . (IS_CORE2_SERVER ? 'true' : 'false') . '</p>';
                echo '<p>CURRENT_ENV: ' . CURRENT_ENV . '</p>';
            } elseif ($conn->connect_error) {
                echo '<p class="error">Connection failed: ' . $conn->connect_error . '</p>';
            } else {
                echo '<p class="success">Core1 Database connection successful!</p>';
                
                // Test query
                $result = $conn->query("SHOW TABLES");
                if ($result) {
                    echo '<h2>Tables in database:</h2>';
                    echo '<ul>';
                    while ($row = $result->fetch_row()) {
                        echo '<li>' . $row[0] . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="error">Error listing tables: ' . $conn->error . '</p>';
                }
                
                // Show connection info
                echo '<h2>Connection Information:</h2>';
                echo '<p>Host info: ' . $conn->host_info . '</p>';
                echo '<p>Protocol version: ' . $conn->protocol_version . '</p>';
                echo '<p>Character set: ' . $conn->character_set_name() . '</p>';
                
                $conn->close();
            }
        } catch (Exception $e) {
            echo '<p class="error">Exception: ' . $e->getMessage() . '</p>';
        }
    } else if ($db === 'core2') {
        try {
            require_once 'functions/db.php';
            $conn = connectToCore2DB();
            
            if ($conn === null) {
                echo '<p class="error">Connection is null. This could mean you\'re in production and on the wrong server.</p>';
                echo '<p>IS_CORE1_SERVER: ' . (IS_CORE1_SERVER ? 'true' : 'false') . '</p>';
                echo '<p>IS_CORE2_SERVER: ' . (IS_CORE2_SERVER ? 'true' : 'false') . '</p>';
                echo '<p>CURRENT_ENV: ' . CURRENT_ENV . '</p>';
            } elseif ($conn->connect_error) {
                echo '<p class="error">Connection failed: ' . $conn->connect_error . '</p>';
            } else {
                echo '<p class="success">Core2 Database connection successful!</p>';
                
                // Test query
                $result = $conn->query("SHOW TABLES");
                if ($result) {
                    echo '<h2>Tables in database:</h2>';
                    echo '<ul>';
                    while ($row = $result->fetch_row()) {
                        echo '<li>' . $row[0] . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="error">Error listing tables: ' . $conn->error . '</p>';
                }
                
                // Show connection info
                echo '<h2>Connection Information:</h2>';
                echo '<p>Host info: ' . $conn->host_info . '</p>';
                echo '<p>Protocol version: ' . $conn->protocol_version . '</p>';
                echo '<p>Character set: ' . $conn->character_set_name() . '</p>';
                
                $conn->close();
            }
        } catch (Exception $e) {
            echo '<p class="error">Exception: ' . $e->getMessage() . '</p>';
        }
    }
    
    echo '<p><a href="debug_info.php?token=' . htmlspecialchars($_GET['token']) . '">Back to Debug Info</a></p>';
    echo '</body></html>';
}

/**
 * Display error log
 */
function display_error_log() {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error Log</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.5; }
            h1, h2 { color: #333; }
            pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; }
        </style>
    </head>
    <body>
        <h1>PHP Error Log</h1>';
    
    require_once 'functions/config.php';
    
    $log_file = ini_get('error_log');
    if (file_exists($log_file) && is_readable($log_file)) {
        $log_contents = file_get_contents($log_file);
        echo '<pre>' . htmlspecialchars($log_contents) . '</pre>';
    } else {
        echo '<p>Error log file not found or not readable: ' . htmlspecialchars($log_file) . '</p>';
        
        // Check for alternate log locations
        $possible_logs = [
            __DIR__ . '/../logs/php_errors.log',
            __DIR__ . '/logs/php_errors.log',
            '/var/log/apache2/error.log',
            '/var/log/httpd/error_log',
            'C:/xampp/apache/logs/error.log',
            'C:/wamp64/logs/apache_error.log'
        ];
        
        foreach ($possible_logs as $log) {
            if (file_exists($log) && is_readable($log)) {
                echo '<h2>Found log file at: ' . htmlspecialchars($log) . '</h2>';
                $log_contents = file_get_contents($log);
                echo '<pre>' . htmlspecialchars($log_contents) . '</pre>';
                break;
            }
        }
    }
    
    echo '<p><a href="debug_info.php?token=' . htmlspecialchars($_GET['token']) . '">Back to Debug Info</a></p>';
    echo '</body></html>';
} 