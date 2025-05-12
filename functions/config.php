<?php
/**
 * Configuration Settings
 * Controls environment-specific settings and database configuration
 */

// Environment settings
define('ENV_LOCAL', 'local');
define('ENV_PRODUCTION', 'production');

// Set current environment (change manually or detect based on hostname)
define('CURRENT_ENV', (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                      strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false) ? 
                      ENV_LOCAL : ENV_PRODUCTION);

// Determine which core we're running on (in production)
define('IS_CORE1_SERVER', CURRENT_ENV === ENV_PRODUCTION && 
                        strpos($_SERVER['HTTP_HOST'] ?? '', 'core1.moverstaxi.com') !== false);
define('IS_CORE2_SERVER', CURRENT_ENV === ENV_PRODUCTION && 
                        strpos($_SERVER['HTTP_HOST'] ?? '', 'core2.moverstaxi.com') !== false);

// Debug settings
// TEMPORARY: Set to true to enable debug in production - CHANGE BACK TO FALSE AFTER DEBUGGING!
define('DEBUG_MODE', true); 

// Apply error reporting settings based on debug mode
if (DEBUG_MODE) {
    // Show all errors in debug mode
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // Log errors to a file
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    // Hide errors in production but still log them
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    
    // Log errors to a file
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Database credentials based on server
if (CURRENT_ENV === ENV_PRODUCTION) {
    if (IS_CORE1_SERVER) {
        // Core1 Server - Update these with your actual credentials
        define('DB_USER_CORE1', 'core1_movers');
        define('DB_PASS_CORE1', '+B6q*gOuCLt^h^bb');
        define('DB_USER_CORE2', 'core1_movers2');  // Using same user for both DBs on core1
        define('DB_PASS_CORE2', '3LioPvbIwb70J@oJ');
    } else if (IS_CORE2_SERVER) {
        // Core2 Server - Update these with your actual credentials
        define('DB_USER_CORE1', 'core2_movers');
        define('DB_PASS_CORE1', 'hK@A+8g!NfLxv@Km');
        define('DB_USER_CORE2', 'core2_movers2');
        define('DB_PASS_CORE2', '3xKID-oO1h!z2wPw');
    }
}

// You can define other global settings here
// DEBUG_MODE is already defined at the top of this file 