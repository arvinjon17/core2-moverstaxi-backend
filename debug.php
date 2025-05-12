<?php
// Basic debugging file

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Output PHP info and environment
echo "<h1>PHP Debug Information</h1>";
echo "<h2>PHP Version: " . phpversion() . "</h2>";
echo "<h2>Server Information</h2>";
echo "<pre>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "Current Directory: " . getcwd() . "\n";
echo "</pre>";

// Check for specific files existence
echo "<h2>File Existence Check</h2>";
echo "<pre>";
$files_to_check = [
    'functions/auth.php',
    'functions/role_management.php',
    'functions/db.php',
    'pages/storeroom.php'
];

foreach ($files_to_check as $file) {
    echo $file . ": " . (file_exists($file) ? "Exists" : "Missing") . "\n";
    
    // For more details about the file
    if (file_exists($file)) {
        echo "  Size: " . filesize($file) . " bytes\n";
        echo "  Permissions: " . substr(sprintf('%o', fileperms($file)), -4) . "\n";
        echo "  Last modified: " . date("Y-m-d H:i:s", filemtime($file)) . "\n";
    }
    echo "\n";
}
echo "</pre>";

// Check if we can include files without error
echo "<h2>Include Files Test</h2>";
echo "<pre>";
try {
    echo "Testing include of functions/auth.php... ";
    include_once 'functions/auth.php';
    echo "Success\n";
    
    echo "Testing include of functions/role_management.php... ";
    include_once 'functions/role_management.php';
    echo "Success\n";
    
    echo "Testing include of functions/db.php... ";
    include_once 'functions/db.php';
    echo "Success\n";
    
    echo "Testing database connection... ";
    $conn = connectToCore2DB();
    echo "Success - Connected to " . DB_NAME_CORE2 . "\n";
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "</pre>";

// Display session information
echo "<h2>Session Information</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . " (1=Disabled, 2=Enabled but no session, 3=Enabled with session)\n";
echo "Session Variables:\n";
print_r($_SESSION);
echo "</pre>";

// Display any PHP errors or warnings
echo "<h2>Error Log</h2>";
echo "<pre>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    echo "Last 20 lines of error log ($error_log):\n";
    $log_content = file($error_log);
    $last_lines = array_slice($log_content, -20);
    foreach ($last_lines as $line) {
        echo htmlspecialchars($line);
    }
} else {
    echo "Error log not found or not readable\n";
}
echo "</pre>";
?> 