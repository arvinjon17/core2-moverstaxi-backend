<?php
/**
 * Database Functions
 * Handles database connections and related operations in both local and production environments
 */

// Include configuration
require_once __DIR__ . '/config.php';

// Global variables for connection management
$core2_connection_reset = false;

// Database connection settings
define('DB_HOST', 'localhost');

// Local environment database credentials
if (CURRENT_ENV === ENV_LOCAL) {
    // Core1 database credentials
    if (!defined('DB_USER_CORE1')) {
        define('DB_USER_CORE1', 'core1_movers');
        define('DB_PASS_CORE1', '+B6q*gOuCLt^h^bb');
    }
    define('DB_NAME_CORE1', 'core1_movers');

    // Core2 database credentials
    if (!defined('DB_USER_CORE2')) {
        define('DB_USER_CORE2', 'core1_movers2');
        define('DB_PASS_CORE2', '3LioPvbIwb70J@oJ');
    }
    define('DB_NAME_CORE2', 'core2_movers');
}

// Special case: Database names in production environment
if (CURRENT_ENV === ENV_PRODUCTION) {
    if (IS_CORE1_SERVER) {
        if (!defined('DB_NAME_CORE1')) {
            define('DB_NAME_CORE1', 'core1_movers');  // Core1 DB on Core1 Server
        }
        if (!defined('DB_NAME_CORE2')) {
            define('DB_NAME_CORE2', 'core1_movers2'); // Core2 DB on Core1 Server
        }
    } else if (IS_CORE2_SERVER) {
        if (!defined('DB_NAME_CORE1')) {
            define('DB_NAME_CORE1', 'core2_movers');  // Core1 DB on Core2 Server 
        }
        if (!defined('DB_NAME_CORE2')) {
            define('DB_NAME_CORE2', 'core2_movers2');  // Core2 DB on Core2 Server
        }
    }
}

/**
 * Reset the static connection for Core2DB
 * Used after a connection is closed to ensure new connections work properly
 */
function resetCore2Connection() {
    global $core2_connection_reset;
    $core2_connection_reset = true;
}

/**
 * Connect to the core1_movers database
 * 
 * @return mysqli The database connection
 */
function connectToCore1DB() {
    static $conn = null;
    
    if ($conn === null) {
        error_log("Creating new connection to " . DB_NAME_CORE1 . " (Host: " . DB_HOST . ", User: " . DB_USER_CORE1 . ")");
        try {
            // Add timeout parameters to prevent hanging connections
            $conn = new mysqli(DB_HOST, DB_USER_CORE1, DB_PASS_CORE1, DB_NAME_CORE1);
            
            if ($conn->connect_error) {
                error_log("Connection to " . DB_NAME_CORE1 . " failed: " . $conn->connect_error);
                // Log additional details for troubleshooting
                error_log("Connection details: Host=" . DB_HOST . ", User=" . DB_USER_CORE1 . ", DB=" . DB_NAME_CORE1);
                throw new Exception("Connection to " . DB_NAME_CORE1 . " failed: " . $conn->connect_error);
            }
            
            // Test connection with a simple query
            $testResult = $conn->query("SELECT 1");
            if ($testResult === false) {
                error_log("Connection test query failed: " . $conn->error);
                throw new Exception("Connection test query failed: " . $conn->error);
            }
            $testResult->free();
            
            // Verify vehicles table exists
            $checkTableResult = $conn->query("SHOW TABLES LIKE 'vehicles'");
            if ($checkTableResult->num_rows === 0) {
                error_log("WARNING: 'vehicles' table does not exist in " . DB_NAME_CORE1);
            } else {
                error_log("Successfully verified 'vehicles' table exists in " . DB_NAME_CORE1);
            }
            $checkTableResult->free();
            
            $conn->set_charset("utf8mb4");
            error_log("Successfully connected to " . DB_NAME_CORE1);
        } catch (Exception $e) {
            error_log("Error in connectToCore1DB: " . $e->getMessage());
            throw $e;
        }
    }
    
    return $conn;
}

/**
 * Connect to the core2_movers database
 * 
 * @return mysqli The database connection
 */
function connectToCore2DB() {
    static $conn = null;
    global $core2_connection_reset;
    
    // Reset connection if needed
    if ($core2_connection_reset === true) {
        $conn = null;
        $core2_connection_reset = false;
    }
    
    if ($conn === null) {
        error_log("Creating new connection to " . DB_NAME_CORE2);
        try {
            $conn = new mysqli(DB_HOST, DB_USER_CORE2, DB_PASS_CORE2, DB_NAME_CORE2);
            
            if ($conn->connect_error) {
                error_log("Connection to " . DB_NAME_CORE2 . " failed: " . $conn->connect_error);
                throw new Exception("Connection to " . DB_NAME_CORE2 . " failed: " . $conn->connect_error);
            }
            
            // Test connection with a simple query
            $testResult = $conn->query("SELECT 1");
            if ($testResult === false) {
                error_log("Connection test query failed: " . $conn->error);
                throw new Exception("Connection test query failed: " . $conn->error);
            }
            $testResult->free();
            
            $conn->set_charset("utf8mb4");
            error_log("Successfully connected to " . DB_NAME_CORE2);
        } catch (Exception $e) {
            error_log("Error in connectToCore2DB: " . $e->getMessage());
            throw $e;
        }
    }
    
    return $conn;
}

/**
 * Execute a query and return the result
 * 
 * @param string $query The SQL query to execute
 * @param string $db The database to use (core1 or core2)
 * @return mysqli_result|bool The result of the query
 */
function executeQuery($query, $db = 'core2') {
    try {
        error_log("executeQuery called with db=$db, query: " . substr($query, 0, 100) . "...");
        $conn = ($db === 'core1') ? connectToCore1DB() : connectToCore2DB();
        
        if (!$conn) {
            error_log("Database connection failed for $db");
            throw new Exception("Database connection failed for $db");
        }
        
        $result = $conn->query($query);
        
        if ($result === false) {
            error_log("Query execution failed: " . $conn->error);
            throw new Exception("Query execution failed: " . $conn->error);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("executeQuery exception: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get a single row from a query
 * 
 * @param string $query The SQL query to execute
 * @param string $db The database to use (core1 or core2)
 * @return array|null The first row of the result or null
 */
function getRow($query, $db = 'core2') {
    $result = executeQuery($query, $db);
    
    if ($result && $result instanceof mysqli_result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get all rows from a query
 * 
 * @param string $query The SQL query to execute
 * @param string $db The database to use (core1 or core2)
 * @return array An array of rows or an empty array
 */
function getRows($query, $db = 'core2') {
    try {
        error_log("getRows called with db=$db, query: " . substr($query, 0, 100) . "...");
        $result = executeQuery($query, $db);
        $rows = [];
        
        if ($result && $result instanceof mysqli_result) {
            $rowCount = $result->num_rows;
            error_log("Query returned $rowCount rows");
            
            if ($rowCount > 0) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            } else {
                // If no rows returned, log the query for troubleshooting
                error_log("No rows returned. Full query: " . $query);
            }
            
            // Free result set
            $result->free();
        } else {
            error_log("Result is not a valid mysqli_result instance");
        }
        
        return $rows;
    } catch (Exception $e) {
        error_log("getRows exception: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Sanitize input to prevent SQL injection
 * 
 * @param string $input The input to sanitize
 * @param string $db The database to use (core1 or core2)
 * @return string The sanitized input
 */
function sanitizeInput($input, $db = 'core2') {
    // Get the connection
    $conn = ($db === 'core1') ? connectToCore1DB() : connectToCore2DB();
    
    // Check if connection is valid and not closed
    if ($conn && !$conn->connect_error && $conn->ping()) {
        return $conn->real_escape_string($input);
    } else {
        // If connection is closed, create a fresh one
        error_log("Connection was closed, creating a new one for sanitizeInput");
        
        try {
            // Create a new temporary connection for this operation
            $tempConn = new mysqli(DB_HOST, ($db === 'core1' ? DB_USER_CORE1 : DB_USER_CORE2), 
                                 ($db === 'core1' ? DB_PASS_CORE1 : DB_PASS_CORE2), 
                                 ($db === 'core1' ? DB_NAME_CORE1 : DB_NAME_CORE2));
                                 
            if ($tempConn->connect_error) {
                error_log("Failed to create new connection in sanitizeInput: " . $tempConn->connect_error);
                return addslashes($input); // Fallback if we can't connect
            }
            
            $sanitized = $tempConn->real_escape_string($input);
            $tempConn->close();
            return $sanitized;
        } catch (Exception $e) {
            error_log("Exception in sanitizeInput: " . $e->getMessage());
            return addslashes($input); // Fallback to simple escaping
        }
    }
}
