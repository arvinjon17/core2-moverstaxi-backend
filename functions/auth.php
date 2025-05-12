<?php
/**
 * Authentication Functions
 * Handles login, logout, and session management
 */

// Include db.php for database connections
require_once __DIR__ . '/db.php';

// The database connection settings are now in db.php
// No need to redefine the database constants here

/**
 * Validate remember me token from cookie
 * 
 * @return int|false User ID if token is valid, false otherwise
 */
function validateRememberToken() {
    // Check if remember cookies exist
    if (!isset($_COOKIE['remember_token']) || !isset($_COOKIE['remember_user'])) {
        return false;
    }
    
    $token = $_COOKIE['remember_token'];
    $userId = (int)$_COOKIE['remember_user'];
    
    // Validate from database
    $conn = connectToCore2DB();
    
    // Find valid token for this user that hasn't expired
    $query = "SELECT token_hash FROM auth_tokens 
              WHERE user_id = $userId 
              AND expires_at > NOW()
              ORDER BY expires_at DESC";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Verify token against stored hash
            if (password_verify($token, $row['token_hash'])) {
                // Token is valid, set up session
                $userQuery = "SELECT email, role, firstname, lastname FROM users 
                             WHERE user_id = $userId 
                             AND status = 'active' 
                             LIMIT 1";
                $userResult = $conn->query($userQuery);
                
                if ($userResult && $userResult->num_rows > 0) {
                    $user = $userResult->fetch_assoc();
                    
                    // Set session variables
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_full_name'] = $user['firstname'] . ' ' . $user['lastname'];
                    $_SESSION['user_firstname'] = $user['firstname'];
                    $_SESSION['user_lastname'] = $user['lastname'];
                    
                    // Log auto-login
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $logQuery = "INSERT INTO auth_logs (user_id, email, action, ip_address, user_agent) 
                                VALUES ($userId, '{$user['email']}', 'auto_login', '$ip', '$userAgent')";
                    $conn->query($logQuery);
                    
                    // Extend token validity (optional)
                    $newExpires = time() + (86400 * 30); // 30 days
                    $newExpiresDate = date('Y-m-d H:i:s', $newExpires);
                    setcookie('remember_token', $token, $newExpires, '/', '', false, true);
                    setcookie('remember_user', $userId, $newExpires, '/', '', false, true);
                    
                    $conn->close();
                    return $userId;
                }
            }
        }
    }
    
    $conn->close();
    return false;
}

/**
 * Check if the user is logged in
 */
function isLoggedIn() {
    // First check if user is logged in via session
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }
    
    // If not, check for remember me token
    $userId = validateRememberToken();
    if ($userId !== false) {
        // User was logged in via remember me token
        return true;
    }
    
    return false;
}

/**
 * Authenticate user login
 * Fixed to match the core2_movers.users table structure
 */
function authenticateUser($email, $password) {
    $conn = connectToCore2DB();
    
    // Sanitize inputs - using sanitizeInput function which works even when connection is null
    $email = sanitizeInput($email, 'core2');
    
    // Query user data - matches the actual database structure
    $query = "SELECT user_id, email, password, role, firstname, lastname, status 
              FROM users 
              WHERE email = '$email'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            $conn->close();
            return ['success' => false, 'message' => 'Your account is not active. Please contact an administrator.'];
        }
        
        // Verify password - uses the standard bcrypt hash from the database
        if (password_verify($password, $user['password'])) {
            // Update last login timestamp
            $userId = $user['user_id'];
            $updateQuery = "UPDATE users SET last_login = NOW() WHERE user_id = $userId";
            $conn->query($updateQuery);
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email']; 
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_full_name'] = $user['firstname'] . ' ' . $user['lastname']; // Updated to user_full_name
            // Add individual firstname and lastname
            $_SESSION['user_firstname'] = $user['firstname'];
            $_SESSION['user_lastname'] = $user['lastname'];
            
            // Add an entry to auth_logs
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $logQuery = "INSERT INTO auth_logs (user_id, email, action, ip_address, user_agent) 
                        VALUES ($userId, '$email', 'login', '$ip', '$userAgent')";
            $conn->query($logQuery);
            
            $conn->close();
            return ['success' => true, 'role' => $user['role']];
        }
    }
    
    // Log failed login attempt
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $logQuery = "INSERT INTO auth_logs (user_id, email, action, ip_address, user_agent) 
                VALUES (NULL, '$email', 'failed_login', '$ip', '$userAgent')";
    $conn->query($logQuery);
    
    $conn->close();
    return ['success' => false, 'message' => 'Invalid email or password.'];
}

/**
 * Authenticate user credentials without creating a full session
 * Used for OTP flow where we need to verify credentials first
 */
function authenticate($email, $password) {
    $conn = connectToCore2DB();
    
    // Sanitize inputs
    $email = sanitizeInput($email, 'core2');
    
    // Query user data
    $query = "SELECT user_id, email, password, status 
              FROM users 
              WHERE email = '$email'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            $conn->close();
            resetCore2Connection();
            return ['success' => false, 'message' => 'Your account is not active. Please contact an administrator.'];
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Log the successful authentication attempt
            $userId = $user['user_id'];
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $logQuery = "INSERT INTO auth_logs (user_id, email, action, ip_address, user_agent) 
                        VALUES ($userId, '$email', 'authentication', '$ip', '$userAgent')";
            $conn->query($logQuery);
            
            // Don't close the connection here as it will be needed later
            return ['success' => true, 'message' => 'Authentication successful'];
        }
    }
    
    // Log failed login attempt
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $logQuery = "INSERT INTO auth_logs (user_id, email, action, ip_address, user_agent) 
                VALUES (NULL, '$email', 'failed_authentication', '$ip', '$userAgent')";
    $conn->query($logQuery);
    
    $conn->close();
    resetCore2Connection();
    return ['success' => false, 'message' => 'Invalid email or password.'];
}

/**
 * Log out user
 */
function logoutUser() {
    // Log the logout
    if (isset($_SESSION['user_id'])) {
        $conn = connectToCore2DB();
        $userId = $_SESSION['user_id'];
        $email = $_SESSION['user_email'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $logQuery = "INSERT INTO auth_logs (user_id, email, action, ip_address, user_agent) 
                    VALUES ($userId, '$email', 'logout', '$ip', '$userAgent')";
        $conn->query($logQuery);
        
        // Remove remember me token from database (if exists)
        if (isset($_COOKIE['remember_token'])) {
            $query = "DELETE FROM auth_tokens WHERE user_id = $userId";
            $conn->query($query);
            
            // Expire the cookies
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
            setcookie('remember_user', '', time() - 3600, '/', '', false, true);
        }
        
        $conn->close();
    }
    
    // Unset all session variables
    $_SESSION = [];
    
    // If it's desired to kill the session, also delete the session cookie.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Finally, destroy the session.
    session_destroy();
    
    return true;
}

/**
 * Check if user has access to a specific page
 * Updated to match the roles in the database
 */
function hasPageAccess($role, $page) {
    $roleAccess = [
        'super_admin' => ['dashboard', 'fleet', 'drivers', 'dispatch', 'customers', 'fuel', 'storeroom', 'booking', 'gps', 'payment', 'users', 'analytics', 'inventory_management', 'system'],
        'admin' => ['dashboard', 'fleet', 'drivers', 'dispatch', 'customers', 'fuel', 'storeroom', 'booking', 'gps', 'payment', 'users', 'analytics', 'inventory_management', 'system'],
        'finance' => ['dashboard', 'payment', 'analytics'],
        'dispatch' => ['dashboard', 'fleet', 'drivers', 'dispatch', 'customers', 'gps'],
        'driver' => ['dashboard', 'profile'],
        'customer' => ['dashboard', 'profile', 'booking']
    ];
    
    // If role doesn't exist or page not in role access array, deny access
    if (!isset($roleAccess[$role]) || !in_array($page, $roleAccess[$role])) {
        return false;
    }
    
    return true;
}

/**
 * Check if the current user has a specific permission
 * 
 * This function verifies if a user has permission to access a specific feature or page.
 * It checks if the user is logged in, retrieves their role, and compares against defined permissions.
 * Super admin users are granted all permissions automatically.
 * 
 * @param string $permission The permission to check
 * @return bool True if the user has permission, false otherwise
 */
function hasPermission($permission) {
    // Check if the user is logged in
    if (!isLoggedIn()) {
        return false;
    }
    
    // Include role management functions if not already included
    if (!function_exists('defineRolePermissions')) {
        require_once 'role_management.php';
    }
    
    // Get permission definitions
    $permissionsByRole = defineRolePermissions();
    
    // Get user role from session
    $userRole = $_SESSION['user_role'] ?? '';
    
    // Super admin has all permissions
    if ($userRole === 'super_admin') {
        return true;
    }
    
    // Check if the role exists in the permissions array
    if (!isset($permissionsByRole[$userRole])) {
        // Role not found in permissions
        return false;
    }
    
    // Check if the permission exists for this role
    return in_array($permission, $permissionsByRole[$userRole]);
}

/**
 * Create JWT token for API authentication
 */
function createJWTToken($userId, $role) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $userId,
        'role' => $role,
        'exp' => time() + 3600 // 1 hour expiration
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Verify JWT token for API authentication
 */
function verifyJWTToken($token) {
    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) {
        return false;
    }
    
    $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0]));
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
    $signatureProvided = $tokenParts[2];
    
    // Check token expiration
    $payloadObj = json_decode($payload);
    if ($payloadObj->exp < time()) {
        return false;
    }
    
    // Verify signature
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return ($base64UrlSignature === $signatureProvided);
}

/**
 * Check if user has a specific role
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // If multiple roles are passed as an array, check if user has any of them
    if (is_array($role)) {
        return in_array($_SESSION['user_role'] ?? '', $role);
    }
    
    // Check for a single role
    return ($_SESSION['user_role'] ?? '') === $role;
}

/**
 * For debugging permission issues - log the permission check details
 * 
 * @param string $permission The permission to check
 * @return bool Returns the result of hasPermission, but logs the details
 */
function debugPermission($permission) {
    // Check if the user is logged in
    $isLoggedIn = isLoggedIn();
    
    // Log login status
    error_log("Debug Permission: User logged in: " . ($isLoggedIn ? "YES" : "NO"));
    
    if (!$isLoggedIn) {
        error_log("Debug Permission: {$permission} check failed - User not logged in");
        return false;
    }
    
    // Include role management functions if not already included
    if (!function_exists('defineRolePermissions')) {
        require_once __DIR__ . '/role_management.php';
    }
    
    // Get permission definitions
    $permissionsByRole = defineRolePermissions();
    
    // Get user role from session
    $userRole = $_SESSION['user_role'] ?? '';
    error_log("Debug Permission: User role: {$userRole}");
    
    // Super admin has all permissions
    if ($userRole === 'super_admin') {
        error_log("Debug Permission: {$permission} check passed - User is super_admin");
        return true;
    }
    
    // Check if the role exists in the permissions array
    if (!isset($permissionsByRole[$userRole])) {
        error_log("Debug Permission: {$permission} check failed - Role {$userRole} not found in permissions");
        return false;
    }
    
    // Check if the permission exists for this role
    $hasPermission = in_array($permission, $permissionsByRole[$userRole]);
    error_log("Debug Permission: {$permission} check " . ($hasPermission ? "passed" : "failed") . " for role {$userRole}");
    
    return $hasPermission;
}
