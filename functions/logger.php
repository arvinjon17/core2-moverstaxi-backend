<?php
/**
 * Logger Functions
 * Functions for logging system messages to the database
 */

// Include database connection if not already included
if (!function_exists('connectToCore2DB')) {
    require_once __DIR__ . '/db.php';
}

/**
 * Log a message to the system_logs table
 * 
 * @param string $message The message to log
 * @param string $logType The type of log (error, warning, info, debug, api)
 * @param int|null $userId The user ID associated with the log (null for system)
 * @return bool True on success, false on failure
 */
function logSystemMessage($message, $logType = 'info', $userId = null) {
    // Ensure valid log type
    $validTypes = ['error', 'warning', 'info', 'debug', 'api'];
    if (!in_array($logType, $validTypes)) {
        $logType = 'info'; // Default to info if invalid type
    }
    
    try {
        $conn = connectToCore2DB();
        
        // Prepare SQL statement
        $sql = "INSERT INTO system_logs (log_type, message, user_id, ip_address, user_agent, request_url, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Logger prepare error: " . $conn->error);
            return false;
        }
        
        // Get client IP address
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Get user agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Get request URL
        $requestUrl = $_SERVER['REQUEST_URI'] ?? null;
        
        // Bind parameters and execute
        $stmt->bind_param("ssssss", $logType, $message, $userId, $ipAddress, $userAgent, $requestUrl);
        
        $result = $stmt->execute();
        if (!$result) {
            error_log("Logger execute error: " . $stmt->error);
        }
        
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Logger exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Log an error message
 * 
 * @param string $message The error message
 * @param int|null $userId The user ID associated with the error
 * @return bool True on success, false on failure
 */
function logError($message, $userId = null) {
    return logSystemMessage($message, 'error', $userId);
}

/**
 * Log a warning message
 * 
 * @param string $message The warning message
 * @param int|null $userId The user ID associated with the warning
 * @return bool True on success, false on failure
 */
function logWarning($message, $userId = null) {
    return logSystemMessage($message, 'warning', $userId);
}

/**
 * Log an info message
 * 
 * @param string $message The info message
 * @param int|null $userId The user ID associated with the info
 * @return bool True on success, false on failure
 */
function logInfo($message, $userId = null) {
    return logSystemMessage($message, 'info', $userId);
}

/**
 * Log a debug message
 * 
 * @param string $message The debug message
 * @param int|null $userId The user ID associated with the debug message
 * @return bool True on success, false on failure
 */
function logDebug($message, $userId = null) {
    return logSystemMessage($message, 'debug', $userId);
}

/**
 * Log an API request message
 * 
 * @param string $message The API request message
 * @param int|null $userId The user ID associated with the API request
 * @return bool True on success, false on failure
 */
function logApi($message, $userId = null) {
    return logSystemMessage($message, 'api', $userId);
}

/**
 * Get API settings from the database
 * 
 * @return array|null The API settings or null on failure
 */
function getApiSettings() {
    try {
        $conn = connectToCore2DB();
        
        $result = $conn->query("SELECT * FROM api_settings LIMIT 1");
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        // If no settings found, create default settings
        $defaultSettings = [
            'debug_mode' => 0,
            'rate_limit' => 100,
            'api_version' => '1.0',
            'auth_token' => ''
        ];
        
        $sql = "INSERT INTO api_settings (debug_mode, rate_limit, api_version, auth_token) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("API settings prepare error: " . $conn->error);
            return $defaultSettings;
        }
        
        $stmt->bind_param("iiss", 
            $defaultSettings['debug_mode'], 
            $defaultSettings['rate_limit'], 
            $defaultSettings['api_version'], 
            $defaultSettings['auth_token']
        );
        
        $stmt->execute();
        $stmt->close();
        
        return $defaultSettings;
    } catch (Exception $e) {
        error_log("API settings exception: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if the API rate limit has been exceeded
 * 
 * @param string $clientIp The client IP address
 * @return bool True if rate limit exceeded, false otherwise
 */
function isApiRateLimitExceeded($clientIp) {
    try {
        $conn = connectToCore2DB();
        $settings = getApiSettings();
        
        if (!$settings) {
            return false; // Allow if settings can't be retrieved
        }
        
        $rateLimit = (int)$settings['rate_limit'];
        
        // Count requests in the last minute
        $sql = "SELECT COUNT(*) as request_count FROM system_logs 
                WHERE log_type = 'api' 
                AND ip_address = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Rate limit check prepare error: " . $conn->error);
            return false; // Allow if check fails
        }
        
        $stmt->bind_param("s", $clientIp);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $requestCount = (int)$row['request_count'];
        
        // Log if rate limit exceeded
        if ($requestCount >= $rateLimit) {
            logWarning("API rate limit exceeded for IP: " . $clientIp);
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Rate limit check exception: " . $e->getMessage());
        return false; // Allow if check fails
    }
}

/**
 * Validate API token
 * 
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateApiToken($token) {
    try {
        $settings = getApiSettings();
        
        if (!$settings) {
            return false;
        }
        
        // If no token set in settings, API authentication is disabled
        if (empty($settings['auth_token'])) {
            return true;
        }
        
        return $token === $settings['auth_token'];
    } catch (Exception $e) {
        error_log("API token validation exception: " . $e->getMessage());
        return false;
    }
} 