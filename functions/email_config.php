<?php
/**
 * Email Configuration
 * Contains settings for email sending including SMTP credentials
 */

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// SMTP Configuration
define('SMTP_ENABLED', true); // Set to false to use PHP mail() instead
define('SMTP_HOST', 'smtp.gmail.com'); // SMTP server address
define('SMTP_PORT', 587); // SMTP port (typically 587 for TLS, 465 for SSL)
define('SMTP_SECURE', 'tls'); // 'ssl', 'tls', or empty string for no encryption
define('SMTP_AUTH', true); // Whether to use SMTP authentication
define('SMTP_USERNAME', '-----'); // SMTP username (your email)
define('SMTP_PASSWORD', '----'); // SMTP password or app password
define('SMTP_FROM_EMAIL', 'your_email@gmail.com'); // From email address
define('SMTP_FROM_NAME', 'Movers Taxi System'); // From name

/**
 * Send email using configured method (SMTP or mail())
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param array $attachments Optional array of attachments
 * @param string $fromName Optional custom from name
 * @return bool Whether the email was sent successfully
 */
function sendEmail($to, $subject, $message, $attachments = [], $fromName = null) {
    try {
        error_log("Sending email to: $to with subject: $subject");
        
        // If SMTP is enabled, use PHPMailer
        if (SMTP_ENABLED) {
            return sendEmailSmtp($to, $subject, $message, $attachments, $fromName);
        } else {
            // Use default PHP mail() function
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $displayName = $fromName ?? SMTP_FROM_NAME;
            $headers .= "From: " . $displayName . " <" . SMTP_FROM_EMAIL . ">" . "\r\n";
            
            $result = mail($to, $subject, $message, $headers);
            if (!$result) {
                error_log("PHP mail() failed to send email to $to");
            }
            return $result;
        }
    } catch (Exception $e) {
        error_log("Exception in sendEmail: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email using SMTP with PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param array $attachments Optional array of attachments
 * @param string $fromName Optional custom from name
 * @return bool Whether the email was sent successfully
 */
function sendEmailSmtp($to, $subject, $message, $attachments = [], $fromName = null) {
    try {
        // First, run diagnostics to check PHPMailer installation
        $phpMailerCheck = checkPhpMailerInstallation();
        
        if (!$phpMailerCheck['installed']) {
            error_log("PHPMailer not installed, falling back to basic mail() function");
            
            // Fall back to mail() function
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $displayName = $fromName ?? SMTP_FROM_NAME;
            $headers .= "From: " . $displayName . " <" . SMTP_FROM_EMAIL . ">" . "\r\n";
            
            return mail($to, $subject, $message, $headers);
        }
        
        // Path to PHPMailer autoloader
        $phpmailerPath = __DIR__ . '/../vendor/autoload.php';
        
        // Include PHPMailer (we already verified it exists in the check)
        require_once $phpmailerPath;
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings
        if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
        } else {
            $mail->SMTPDebug = 0; // Disable debug output
        }
        
        $mail->isSMTP(); // Send using SMTP
        $mail->Host = SMTP_HOST; // SMTP server
        $mail->Port = SMTP_PORT; // TCP port to connect to
        
        // Security settings
        if (SMTP_SECURE) {
            $mail->SMTPSecure = SMTP_SECURE; // Enable TLS/SSL encryption
        }
        
        // Authentication
        if (SMTP_AUTH) {
            $mail->SMTPAuth = true; // Enable SMTP authentication
            $mail->Username = SMTP_USERNAME; // SMTP username
            $mail->Password = SMTP_PASSWORD; // SMTP password
        } else {
            $mail->SMTPAuth = false;
        }
        
        // Recipients
        $displayName = $fromName ?? SMTP_FROM_NAME;
        $mail->setFrom(SMTP_FROM_EMAIL, $displayName);
        $mail->addAddress($to); // Add a recipient
        
        // Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->CharSet = 'UTF-8'; // Ensure proper character encoding
        
        // Add attachments if any
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment['path'])) {
                    $mail->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                }
            }
        }
        
        // Log SMTP configuration for debugging
        error_log("SMTP Configuration: Host=$mail->Host, Port=$mail->Port, Username=$mail->Username, Auth=" . ($mail->SMTPAuth ? 'Yes' : 'No'));
        
        // Send the email
        $mail->send();
        error_log("Email sent successfully to $to via SMTP");
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: " . (isset($mail) ? $mail->ErrorInfo : 'PHPMailer not initialized'));
        error_log("Exception details: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Check if PHPMailer is properly installed and configured
 * 
 * @return array Associative array with status and message
 */
function checkPhpMailerInstallation() {
    $phpmailerPath = __DIR__ . '/../vendor/autoload.php';
    $vendorDir = __DIR__ . '/../vendor';
    $result = [
        'installed' => false,
        'message' => '',
        'details' => []
    ];
    
    // Check if vendor directory exists
    if (!is_dir($vendorDir)) {
        $result['message'] = 'Vendor directory not found. PHPMailer is not installed.';
        $result['details'][] = 'Expected vendor directory: ' . $vendorDir;
        $result['details'][] = 'Please install PHPMailer using Composer: composer require phpmailer/phpmailer';
        return $result;
    }
    
    // Check if autoload.php exists
    if (!file_exists($phpmailerPath)) {
        $result['message'] = 'Composer autoload file not found.';
        $result['details'][] = 'Expected autoload file: ' . $phpmailerPath;
        $result['details'][] = 'Please run: composer install';
        return $result;
    }
    
    // Check if PHPMailer class exists
    try {
        require_once $phpmailerPath;
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $result['message'] = 'PHPMailer class not found.';
            $result['details'][] = 'Autoload file exists but PHPMailer class is not available.';
            $result['details'][] = 'Please run: composer require phpmailer/phpmailer';
            return $result;
        }
        
        // All checks passed
        $result['installed'] = true;
        $result['message'] = 'PHPMailer is properly installed.';
        
        // Add SMTP configuration details
        $result['details'][] = 'SMTP Host: ' . SMTP_HOST;
        $result['details'][] = 'SMTP Port: ' . SMTP_PORT;
        $result['details'][] = 'SMTP Secure: ' . SMTP_SECURE;
        $result['details'][] = 'SMTP Auth: ' . (SMTP_AUTH ? 'Yes' : 'No');
        $result['details'][] = 'SMTP Username: ' . SMTP_USERNAME;
        $result['details'][] = 'SMTP From Email: ' . SMTP_FROM_EMAIL;
        
        return $result;
    } catch (Exception $e) {
        $result['message'] = 'Error checking PHPMailer installation: ' . $e->getMessage();
        return $result;
    }
}
?> 