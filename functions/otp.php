<?php
/**
 * OTP Functions
 * Handles generation, validation, and management of One-Time Passwords
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/email_config.php';

/**
 * Check if OTP system is enabled globally
 * 
 * @return bool Whether OTP is enabled globally
 */
function isOtpEnabledGlobally() {
    $conn = connectToCore2DB();
    $query = "SELECT enable_otp FROM system_settings LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (bool)$row['enable_otp'];
    }
    
    return false;
}

/**
 * Check if OTP is enabled for a specific user
 * 
 * @param int $userId The user ID to check
 * @return bool Whether OTP is enabled for this user
 */
function isOtpEnabledForUser($userId) {
    // If OTP is not enabled globally, return false
    if (!isOtpEnabledGlobally()) {
        return false;
    }
    
    $conn = connectToCore2DB();
    $userId = (int)$userId;
    
    // Check user preference
    $query = "SELECT enable_otp FROM user_otp_preferences WHERE user_id = $userId LIMIT 1";
    $result = $conn->query($query);
    
    // If user has a preference set, return it
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (bool)$row['enable_otp'];
    }
    
    // If no preference set yet, create default (enabled)
    $insertQuery = "INSERT INTO user_otp_preferences (user_id, enable_otp, otp_method) VALUES ($userId, 1, 'email')";
    $conn->query($insertQuery);
    
    return true;
}

/**
 * Get OTP delivery method for a user
 * 
 * @param int $userId The user ID
 * @return string The OTP delivery method ('email' or 'sms')
 */
function getUserOtpMethod($userId) {
    $conn = connectToCore2DB();
    $userId = (int)$userId;
    
    $query = "SELECT otp_method FROM user_otp_preferences WHERE user_id = $userId LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['otp_method'];
    }
    
    // Default to email
    return 'email';
}

/**
 * Update OTP settings for a user
 * 
 * @param int $userId The user ID
 * @param bool $enableOtp Whether to enable OTP for the user
 * @param string $otpMethod The OTP delivery method ('email' or 'sms')
 * @return array Status array with success flag and message
 */
function updateUserOtpSettings($userId, $enableOtp, $otpMethod = 'email') {
    $conn = connectToCore2DB();
    $userId = (int)$userId;
    $enableOtp = (int)(bool)$enableOtp;
    $otpMethod = $conn->real_escape_string($otpMethod);
    
    $query = "INSERT INTO user_otp_preferences (user_id, enable_otp, otp_method) 
              VALUES ($userId, $enableOtp, '$otpMethod')
              ON DUPLICATE KEY UPDATE enable_otp = $enableOtp, otp_method = '$otpMethod'";
    
    if ($conn->query($query)) {
        return ['success' => true, 'message' => 'OTP settings updated successfully.'];
    } else {
        return ['success' => false, 'message' => 'Failed to update OTP settings: ' . $conn->error];
    }
}

/**
 * Generate a new OTP for a user
 * 
 * @param int $userId The user ID
 * @param int $expiryMinutes Number of minutes until the OTP expires
 * @return string|false The generated OTP code, or false on failure
 */
function generateOtp($userId, $expiryMinutes = 10) {
    try {
        $conn = connectToCore2DB();
        $userId = (int)$userId;
        
        // Generate 6-digit OTP
        $otpCode = sprintf("%06d", mt_rand(100000, 999999));
        
        // Get current date and time
        $currentTime = new DateTime('now');
        
        // Add expiry minutes
        $expiryTime = clone $currentTime;
        $expiryTime->modify("+{$expiryMinutes} minutes");
        
        // Format for database
        $currentTimeFormatted = $currentTime->format('Y-m-d H:i:s');
        $expiryTimeFormatted = $expiryTime->format('Y-m-d H:i:s');
        
        // Log the times for debugging
        error_log("OTP Generated - Current time: $currentTimeFormatted, Expiry time: $expiryTimeFormatted");
        
        // Get IP and user agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Store OTP in database
        $query = "INSERT INTO user_otp (user_id, otp_code, created_at, expires_at, ip_address, user_agent) 
                VALUES ($userId, '$otpCode', '$currentTimeFormatted', '$expiryTimeFormatted', '$ipAddress', '$userAgent')";
        
        if ($conn->query($query)) {
            return $otpCode;
        } else {
            error_log("Failed to generate OTP: " . $conn->error);
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception in generateOtp: " . $e->getMessage());
        return false;
    }
}

/**
 * Send OTP to the user via email
 * 
 * @param int $userId The user ID
 * @param string $otpCode The OTP code to send
 * @return bool Whether the OTP was sent successfully
 */
function sendOtpByEmail($userId, $otpCode) {
    try {
        $conn = connectToCore2DB();
        $userId = (int)$userId;
        
        // Get user email and name
        $query = "SELECT email, firstname, lastname FROM users WHERE user_id = $userId LIMIT 1";
        $result = $conn->query($query);
        
        if (!$result) {
            error_log("Error querying user for OTP email: " . $conn->error);
            return false;
        }
        
        if ($result->num_rows === 0) {
            error_log("User not found for OTP email: $userId");
            return false;
        }
        
        $user = $result->fetch_assoc();
        $userEmail = $user['email'];
        $userName = $user['firstname'] . ' ' . $user['lastname'];
        
        error_log("Sending OTP to user $userId ($userEmail)");
        
        // Get company name from system settings
        $settingsQuery = "SELECT company_name FROM system_settings LIMIT 1";
        $settingsResult = $conn->query($settingsQuery);
        $companyName = 'Movers Taxi System';
        
        if ($settingsResult && $settingsResult->num_rows > 0) {
            $settings = $settingsResult->fetch_assoc();
            $companyName = $settings['company_name'];
        }
        
        // Update SMTP_FROM_NAME with company name
        if (defined('SMTP_FROM_NAME')) {
            // We can't redefine a constant, but we can use the company name directly in the email
            $fromName = $companyName;
        } else {
            define('SMTP_FROM_NAME', $companyName);
            $fromName = SMTP_FROM_NAME;
        }
        
        // Prepare email content
        $subject = "Your OTP Code for $companyName";
        
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                <h2 style='color: #2c3e50; margin-bottom: 20px;'>Authentication Code</h2>
                <p>Hello $userName,</p>
                <p>Your one-time password (OTP) for $companyName login is:</p>
                <div style='background: #f8f9fa; padding: 12px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 4px;'>
                    $otpCode
                </div>
                <p>This code will expire in 10 minutes.</p>
                <p>If you did not request this code, please ignore this email or contact support if you have concerns.</p>
                <p style='margin-top: 30px; font-size: 12px; color: #6c757d;'>This is an automated message, please do not reply.</p>
            </div>
        </body>
        </html>
        ";
        
        // Send email using the configured email function
        $result = sendEmail($userEmail, $subject, $message, [], $fromName);
        if ($result) {
            error_log("OTP email sent successfully to $userEmail");
            return true;
        } else {
            error_log("Failed to send OTP email to $userEmail");
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception in sendOtpByEmail: " . $e->getMessage());
        return false;
    }
}

/**
 * Send OTP via SMS (placeholder function)
 * 
 * @param int $userId The user ID
 * @param string $otpCode The OTP code to send
 * @return bool Whether the OTP was sent successfully
 */
function sendOtpBySms($userId, $otpCode) {
    // This is a placeholder function
    // In a real implementation, this would integrate with an SMS gateway
    
    // For now just log that we would send an SMS
    error_log("SMS OTP would be sent to user $userId with code $otpCode");
    
    // For testing purposes, always return true
    return true;
}

/**
 * Send OTP to user using their preferred method
 * 
 * @param int $userId The user ID
 * @param string $otpCode The OTP code to send
 * @return bool Whether the OTP was sent successfully
 */
function sendOtp($userId, $otpCode) {
    try {
        $otpMethod = getUserOtpMethod($userId);
        
        if ($otpMethod === 'sms') {
            return sendOtpBySms($userId, $otpCode);
        } else {
            $result = sendOtpByEmail($userId, $otpCode);
            if (!$result) {
                error_log("Failed to send OTP email to user $userId");
            }
            return $result;
        }
    } catch (Exception $e) {
        error_log("Exception in sendOtp: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify an OTP code for a user
 * 
 * @param int $userId The user ID
 * @param string $otpCode The OTP code to verify
 * @return bool Whether the OTP is valid
 */
function verifyOtp($userId, $otpCode) {
    try {
        $conn = connectToCore2DB();
        $userId = (int)$userId;
        $otpCode = $conn->real_escape_string($otpCode);
        
        // Current time for debugging
        $now = date('Y-m-d H:i:s');
        error_log("OTP verification attempt - User ID: $userId, Code: $otpCode, Current time: $now");
        
        // Find the most recent valid OTP
        $query = "SELECT id, created_at, expires_at FROM user_otp 
                WHERE user_id = $userId 
                AND otp_code = '$otpCode' 
                AND is_verified = 0 
                ORDER BY created_at DESC LIMIT 1";
        
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $otpId = $row['id'];
            $createdAt = $row['created_at'];
            $expiresAt = $row['expires_at'];
            
            error_log("OTP found - ID: $otpId, Created: $createdAt, Expires: $expiresAt");
            
            // Check if it has expired
            $currentTime = new DateTime('now');
            $expiryTime = new DateTime($expiresAt);
            
            if ($currentTime > $expiryTime) {
                error_log("OTP has expired - Current: " . $currentTime->format('Y-m-d H:i:s') . ", Expiry: " . $expiryTime->format('Y-m-d H:i:s'));
                return false;
            }
            
            // Mark OTP as verified
            $updateQuery = "UPDATE user_otp 
                            SET is_verified = 1, verification_time = NOW() 
                            WHERE id = $otpId";
            
            if ($conn->query($updateQuery)) {
                error_log("OTP verified successfully - ID: $otpId");
                return true;
            } else {
                error_log("Failed to update OTP verification status: " . $conn->error);
                return false;
            }
        } else {
            error_log("No valid OTP found for user $userId with code $otpCode");
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception in verifyOtp: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's OTP history
 * 
 * @param int $userId The user ID
 * @param int $limit Maximum number of records to return
 * @return array Array of OTP records
 */
function getUserOtpHistory($userId, $limit = 10) {
    $conn = connectToCore2DB();
    $userId = (int)$userId;
    $limit = (int)$limit;
    
    $query = "SELECT id, otp_code, created_at, expires_at, is_verified, 
              verification_time, ip_address, user_agent 
              FROM user_otp 
              WHERE user_id = $userId 
              ORDER BY created_at DESC 
              LIMIT $limit";
    
    $result = $conn->query($query);
    $history = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Mask OTP code for security
            $row['otp_code'] = '******';
            $history[] = $row;
        }
    }
    
    return $history;
}

/**
 * Toggle global OTP setting
 * 
 * @param bool $enable Whether to enable OTP globally
 * @return array Status array with success flag and message
 */
function toggleGlobalOtp($enable) {
    $conn = connectToCore2DB();
    $enable = (int)(bool)$enable;
    
    $query = "UPDATE system_settings SET enable_otp = $enable";
    
    if ($conn->query($query)) {
        return ['success' => true, 'message' => 'Global OTP setting updated successfully.'];
    } else {
        return ['success' => false, 'message' => 'Failed to update global OTP setting: ' . $conn->error];
    }
}
?> 