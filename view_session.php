<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple function to render arrays nicely
function displayArray($array, $level = 0) {
    $indent = str_repeat("  ", $level);
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            echo "$indent$key => <br>";
            displayArray($value, $level + 1);
        } else {
            $displayValue = is_string($value) ? htmlspecialchars($value) : var_export($value, true);
            echo "$indent$key => $displayValue<br>";
        }
    }
}

// Set page title
echo "<html><head><title>Session Data Viewer</title>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.5; }";
echo "h1, h2 { color: #333; }";
echo "pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }";
echo ".key { color: #0066cc; font-weight: bold; }";
echo ".value { color: #333; }";
echo ".back-link { margin-top: 20px; }";
echo "</style></head><body>";

echo "<h1>Session Data Viewer</h1>";

// Check if session exists
if (empty($_SESSION)) {
    echo "<p>No session data available. Session may not be started or is empty.</p>";
} else {
    echo "<h2>Session ID: " . session_id() . "</h2>";
    
    // Display session variables
    echo "<h2>Session Variables:</h2>";
    echo "<pre>";
    displayArray($_SESSION);
    echo "</pre>";
    
    // Display current session settings
    echo "<h2>Session Configuration:</h2>";
    echo "<pre>";
    echo "session.save_path: " . ini_get('session.save_path') . "<br>";
    echo "session.name: " . ini_get('session.name') . "<br>";
    echo "session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "<br>";
    echo "session.cookie_path: " . ini_get('session.cookie_path') . "<br>";
    echo "session.cookie_domain: " . ini_get('session.cookie_domain') . "<br>";
    echo "session.cookie_secure: " . ini_get('session.cookie_secure') . "<br>";
    echo "session.cookie_httponly: " . ini_get('session.cookie_httponly') . "<br>";
    echo "session.use_cookies: " . ini_get('session.use_cookies') . "<br>";
    echo "session.use_only_cookies: " . ini_get('session.use_only_cookies') . "<br>";
    echo "session.gc_maxlifetime: " . ini_get('session.gc_maxlifetime') . "<br>";
    echo "</pre>";
}

// Show environment variables
echo "<h2>Server Environment:</h2>";
echo "<pre>";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'Not set') . "<br>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "<br>";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'Not set') . "<br>";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Not set') . "<br>";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'Not set') . "<br>";
echo "HTTP_USER_AGENT: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set') . "<br>";
echo "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'Not set') . "<br>";
echo "</pre>";

// Add link to return to the dashboard
echo "<div class='back-link'><a href='index.php'>Return to Dashboard</a></div>";
echo "</body></html>";
?> 