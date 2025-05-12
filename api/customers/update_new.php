<?php
header('Content-Type: application/json');
require_once '../../functions/db.php';
require_once '../../functions/auth.php';

session_start();
if (!isset($_SESSION['user_id']) || !hasPermission('manage_customers')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Helper: sanitize and validate
function getPost($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$firstname = getPost('firstname');
$lastname = getPost('lastname');
$email = getPost('email');
$phone = getPost('phone');
$account_status = getPost('account_status');
$address = getPost('address');
$city = getPost('city');
$state = getPost('state');
$zip = getPost('zip');
$current_status = getPost('current_status');

// Validate required fields
if ($userId <= 0 || !$firstname || !$lastname || !$email || !$phone || !$account_status || !$current_status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Validate PH phone
if (!preg_match('/^(\+639|09)\d{9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid PH phone number format.']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Validate statuses
$validAccountStatuses = ['active', 'inactive', 'suspended'];
$validCurrentStatuses = ['online', 'busy', 'offline'];
if (!in_array($account_status, $validAccountStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid account status.']);
    exit;
}
if (!in_array($current_status, $validCurrentStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid current status.']);
    exit;
}

// --- Profile Picture Upload Handling (Unified with Driver Logic) ---
$profilePicturePath = null;
if (isset($_FILES['profile_picture'])) {
    error_log('profile_picture detected, error code: ' . $_FILES['profile_picture']['error']);
} else {
    error_log('profile_picture NOT detected in $_FILES');
}
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
    // Use the same upload function as drivers
    require_once '../../functions/profile_images.php';
    $userData = [
        'firstname' => $firstname,
        'lastname' => $lastname
    ];
    // Pass false to updateDatabase parameter to prevent automatic database update
    $uploadResult = uploadProfileImage($_FILES['profile_picture'], 'customer', $userId, $userData, false);
    if ($uploadResult['success']) {
        $profilePicturePath = $uploadResult['filename'];
        error_log('Profile image uploaded: ' . $uploadResult['filename']);
    } else {
        error_log('Profile image upload failed: ' . $uploadResult['message']);
        echo json_encode(['success' => false, 'message' => 'Profile image upload failed: ' . $uploadResult['message']]);
        exit;
    }
}

// Update core2_movers.users
error_log('Updating user_id=' . $userId . ' with profile_picture=' . $profilePicturePath);
$core2 = connectToCore2DB();
$core2Success = false;
$core2Msg = '';
if ($core2) {
    // If new profile picture, update that field too
    if ($profilePicturePath) {
        $stmt = $core2->prepare('UPDATE users SET firstname=?, lastname=?, email=?, phone=?, status=?, profile_picture=? WHERE user_id=? AND role="customer"');
        $stmt->bind_param('ssssssi', $firstname, $lastname, $email, $phone, $account_status, $profilePicturePath, $userId);
    } else {
        $stmt = $core2->prepare('UPDATE users SET firstname=?, lastname=?, email=?, phone=?, status=? WHERE user_id=? AND role="customer"');
        $stmt->bind_param('sssssi', $firstname, $lastname, $email, $phone, $account_status, $userId);
    }
    if ($stmt) {
        $core2Success = $stmt->execute();
        if (!$core2Success) {
            error_log('SQL Error: ' . $stmt->error);
        } else {
            error_log('SQL Success: affected rows = ' . $stmt->affected_rows);
        }
        $stmt->close();
    } else {
        $core2Msg = $core2->error;
        error_log('SQL Prepare Error: ' . $core2Msg);
    }
    $core2->close();
} else {
    $core2Msg = 'Could not connect to core2_movers.';
    error_log($core2Msg);
}

// Update core1_movers.customers
$core1 = connectToCore1DB();
$core1Success = false;
$core1Msg = '';
if ($core1) {
    // First check if customer record exists
    $checkStmt = $core1->prepare('SELECT customer_id FROM customers WHERE user_id = ?');
    if (!$checkStmt) {
        $core1Msg = 'Failed to prepare check statement: ' . $core1->error;
        $core1->close();
    } else {
        $checkStmt->bind_param('i', $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $customerExists = ($checkResult && $checkResult->num_rows > 0);
        $checkStmt->close();
        
        if ($customerExists) {
            // Customer exists, perform UPDATE
            $stmt = $core1->prepare('UPDATE customers SET address=?, city=?, state=?, zip=?, status=? WHERE user_id=?');
            if ($stmt) {
                $stmt->bind_param('sssssi', $address, $city, $state, $zip, $current_status, $userId);
                $core1Success = $stmt->execute();
                if (!$core1Success) {
                    $core1Msg = 'Update failed: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $core1Msg = 'Failed to prepare update statement: ' . $core1->error;
            }
        } else {
            // Customer doesn't exist, perform INSERT
            $stmt = $core1->prepare('INSERT INTO customers (user_id, address, city, state, zip, status, created_at, updated_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            if ($stmt) {
                $stmt->bind_param('isssss', $userId, $address, $city, $state, $zip, $current_status);
                $core1Success = $stmt->execute();
                if (!$core1Success) {
                    $core1Msg = 'Insert failed: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $core1Msg = 'Failed to prepare insert statement: ' . $core1->error;
            }
        }
        $core1->close();
    }
} else {
    $core1Msg = 'Could not connect to core1_movers.';
}

if ($core2Success && $core1Success) {
    echo json_encode(['success' => true, 'message' => 'Customer updated successfully.']);
} else {
    $msg = 'Update failed:';
    if (!$core2Success) $msg .= ' [core2_movers.users] ' . $core2Msg;
    if (!$core1Success) $msg .= ' [core1_movers.customers] ' . $core1Msg;
    echo json_encode(['success' => false, 'message' => $msg]);
} 