<?php
// OTP Diagnostics Page
// This page is accessible only to administrators for diagnosing OTP issues

// Make sure no output is sent before headers
ob_start();
session_start();
require_once 'functions/db.php';
require_once 'functions/auth.php';
require_once 'functions/otp.php';
require_once 'functions/email_config.php';

// Check if user has admin permissions
if (!isLoggedIn() || !hasRole(['super_admin', 'admin'])) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: index.php");
    exit;
}

$diagnosticsResults = [];
$testEmailResults = null;
$testOtpResults = null;

// Run diagnostics
$diagnosticsResults['php_version'] = [
    'name' => 'PHP Version',
    'status' => version_compare(PHP_VERSION, '7.2.0', '>='),
    'message' => 'PHP Version: ' . PHP_VERSION,
    'details' => version_compare(PHP_VERSION, '7.2.0', '>=') 
        ? 'Compatible with PHPMailer' 
        : 'PHPMailer requires PHP 7.2.0 or higher'
];

// Check for required PHP extensions
$requiredExtensions = ['openssl', 'mbstring', 'curl'];
foreach ($requiredExtensions as $ext) {
    $diagnosticsResults['ext_' . $ext] = [
        'name' => "PHP Extension: $ext",
        'status' => extension_loaded($ext),
        'message' => extension_loaded($ext) ? "$ext extension is loaded" : "$ext extension is not loaded",
        'details' => extension_loaded($ext) 
            ? "Required extension is available" 
            : "This extension is required for secure email communication"
    ];
}

// Check PHPMailer installation
$phpmailerCheck = checkPhpMailerInstallation();
$diagnosticsResults['phpmailer'] = [
    'name' => 'PHPMailer Installation',
    'status' => $phpmailerCheck['installed'],
    'message' => $phpmailerCheck['message'],
    'details' => implode('<br>', $phpmailerCheck['details'])
];

// Check database tables
$conn = connectToCore2DB();
$tables = [
    'user_otp' => 'Stores OTP codes',
    'user_otp_preferences' => 'Stores user OTP settings',
    'system_settings' => 'Contains global OTP settings'
];

foreach ($tables as $table => $description) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = $conn->query($query);
    $tableExists = ($result && $result->num_rows > 0);
    
    $diagnosticsResults['table_' . $table] = [
        'name' => "Database Table: $table",
        'status' => $tableExists,
        'message' => $tableExists ? "Table exists" : "Table does not exist",
        'details' => $description
    ];
}

// Check global OTP setting
$globalOtpEnabled = isOtpEnabledGlobally();
$diagnosticsResults['otp_global'] = [
    'name' => 'Global OTP Setting',
    'status' => true, // Not an error, just informational
    'message' => $globalOtpEnabled ? "OTP is enabled globally" : "OTP is disabled globally",
    'details' => "This setting can be changed in System Settings > Security"
];

// Handle test email form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'test_email' && isset($_POST['test_email'])) {
        $testEmail = $_POST['test_email'];
        $subject = 'Test Email from Movers Taxi System';
        $message = '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <h2 style="color: #2c3e50;">Test Email</h2>
                    <p>This is a test email sent from the Movers Taxi System.</p>
                    <p>If you received this email, the email functionality is working correctly.</p>
                    <p>Time sent: ' . date('Y-m-d H:i:s') . '</p>
                </div>
            </body>
            </html>
        ';
        
        $testEmailSuccess = sendEmail($testEmail, $subject, $message);
        $testEmailResults = [
            'success' => $testEmailSuccess,
            'message' => $testEmailSuccess ? 'Test email sent successfully!' : 'Failed to send test email.'
        ];
    } else if ($_POST['action'] === 'test_otp' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        
        // Check if the user exists
        $userQuery = "SELECT user_id, email, firstname, lastname FROM users WHERE user_id = $userId LIMIT 1";
        $userResult = $conn->query($userQuery);
        
        if ($userResult && $userResult->num_rows > 0) {
            $user = $userResult->fetch_assoc();
            $otpCode = generateOtp($userId);
            
            if ($otpCode) {
                $otpSendResult = sendOtp($userId, $otpCode);
                $testOtpResults = [
                    'success' => $otpSendResult,
                    'message' => $otpSendResult 
                        ? "OTP code $otpCode generated and sent to {$user['email']}" 
                        : "Failed to send OTP code $otpCode to {$user['email']}"
                ];
            } else {
                $testOtpResults = [
                    'success' => false,
                    'message' => "Failed to generate OTP code for user ID $userId"
                ];
            }
        } else {
            $testOtpResults = [
                'success' => false,
                'message' => "User with ID $userId not found"
            ];
        }
    }
}

// Get list of users for testing OTP
$usersForOtp = [];
$usersQuery = "SELECT user_id, email, firstname, lastname FROM users ORDER BY user_id LIMIT 10";
$usersResult = $conn->query($usersQuery);

if ($usersResult && $usersResult->num_rows > 0) {
    while ($user = $usersResult->fetch_assoc()) {
        $usersForOtp[] = $user;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Diagnostics - Movers Taxi System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <style>
        .status-ok {
            color: #198754;
        }
        .status-error {
            color: #dc3545;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            font-size: 0.875rem;
        }
        .card {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <h1 class="mb-4">
            <i class="fas fa-diagnoses me-2"></i>
            OTP Diagnostics
        </h1>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            This page provides diagnostic information and tools to troubleshoot OTP functionality.
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <!-- System Diagnostics -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-stethoscope me-2"></i> System Diagnostics</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Check</th>
                                    <th>Status</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($diagnosticsResults as $check): ?>
                                <tr>
                                    <td><?= htmlspecialchars($check['name']) ?></td>
                                    <td>
                                        <?php if ($check['status']): ?>
                                            <i class="fas fa-check-circle status-ok"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle status-error"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($check['message']) ?></strong>
                                        <div class="small text-muted"><?= $check['details'] ?></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Email Configuration -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i> Email Configuration</h5>
                    </div>
                    <div class="card-body">
                        <h6>Current SMTP Configuration:</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>SMTP Enabled</th>
                                <td><?= SMTP_ENABLED ? 'Yes' : 'No' ?></td>
                            </tr>
                            <tr>
                                <th>SMTP Host</th>
                                <td><?= SMTP_HOST ?></td>
                            </tr>
                            <tr>
                                <th>SMTP Port</th>
                                <td><?= SMTP_PORT ?></td>
                            </tr>
                            <tr>
                                <th>SMTP Security</th>
                                <td><?= SMTP_SECURE ?: 'None' ?></td>
                            </tr>
                            <tr>
                                <th>SMTP Authentication</th>
                                <td><?= SMTP_AUTH ? 'Yes' : 'No' ?></td>
                            </tr>
                            <tr>
                                <th>SMTP Username</th>
                                <td><?= SMTP_USERNAME ?></td>
                            </tr>
                            <tr>
                                <th>From Email</th>
                                <td><?= SMTP_FROM_EMAIL ?></td>
                            </tr>
                            <tr>
                                <th>From Name</th>
                                <td><?= SMTP_FROM_NAME ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Test Email -->
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i> Test Email</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($testEmailResults): ?>
                            <div class="alert <?= $testEmailResults['success'] ? 'alert-success' : 'alert-danger' ?>">
                                <?= htmlspecialchars($testEmailResults['message']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="test_email">
                            <div class="mb-3">
                                <label for="test_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="test_email" name="test_email" required>
                                <div class="form-text">Enter an email address to send a test email.</div>
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-paper-plane me-2"></i> Send Test Email
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Test OTP -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i> Test OTP</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($testOtpResults): ?>
                            <div class="alert <?= $testOtpResults['success'] ? 'alert-success' : 'alert-danger' ?>">
                                <?= htmlspecialchars($testOtpResults['message']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="test_otp">
                            <div class="mb-3">
                                <label for="user_id" class="form-label">Select User</label>
                                <select class="form-select" id="user_id" name="user_id" required>
                                    <option value="">-- Select a user --</option>
                                    <?php foreach ($usersForOtp as $user): ?>
                                        <option value="<?= $user['user_id'] ?>">
                                            <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?> 
                                            (<?= htmlspecialchars($user['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select a user to generate and send an OTP code.</div>
                            </div>
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-key me-2"></i> Generate & Send OTP
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Back to Dashboard -->
                <div class="mt-3">
                    <a href="index.php" class="btn btn-secondary w-100">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
</body>
</html> 