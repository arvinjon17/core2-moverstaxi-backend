<?php
// API endpoint: Serve Edit Customer Modal Form (AJAX)
header('Content-Type: text/html; charset=UTF-8');
require_once '../../functions/db.php';
require_once '../../functions/auth.php';
require_once '../../functions/profile_images.php';

session_start();
if (!isset($_SESSION['user_id']) || !hasPermission('manage_customers')) {
    echo '<div class="alert alert-danger">Unauthorized access.</div>';
    exit;
}

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($userId <= 0) {
    echo '<div class="alert alert-danger">Invalid customer ID.</div>';
    exit;
}

// Fetch from core2_movers.users
$core2 = connectToCore2DB();
$core2Data = null;
if ($core2) {
    $stmt = $core2->prepare("SELECT firstname, lastname, email, phone, status, profile_picture FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $core2Data = $result->fetch_assoc();
    $stmt->close();
    $core2->close();
}

// Fetch from core1_movers.customers
$core1 = connectToCore1DB();
$core1Data = null;
if ($core1) {
    $stmt = $core1->prepare("SELECT address, city, state, zip, status FROM customers WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $core1Data = $result->fetch_assoc();
    $stmt->close();
    $core1->close();
}

// Auto-split legacy merged address if city, state, and zip are empty but address is filled
if ($core1Data && !empty($core1Data['address']) && empty($core1Data['city']) && empty($core1Data['state']) && empty($core1Data['zip'])) {
    // Try to split: e.g. "789 Pine St, Taguig, Metro Manila 1630"
    $parts = preg_split('/,\s*/', $core1Data['address']);
    if (count($parts) >= 3) {
        $core1Data['address'] = $parts[0];
        $core1Data['city'] = $parts[1];
        // Try to split state and zip
        $stateZip = preg_split('/\s+/', $parts[2], 2);
        $core1Data['state'] = $stateZip[0] ?? '';
        $core1Data['zip'] = $stateZip[1] ?? '';
    } elseif (count($parts) == 2) {
        $core1Data['address'] = $parts[0];
        $core1Data['city'] = $parts[1];
        $core1Data['state'] = '';
        $core1Data['zip'] = '';
    } else {
        // Fallback: put everything in address
        $core1Data['address'] = $core1Data['address'];
        $core1Data['city'] = '';
        $core1Data['state'] = '';
        $core1Data['zip'] = '';
    }
}

// Show warnings if missing
if (!$core2Data) {
    echo '<div class="alert alert-warning">Customer not found in core2_movers.users.</div>';
}
if (!$core1Data) {
    echo '<div class="alert alert-warning">Customer not found in core1_movers.customers.</div>';
}

// Render the form (fields: firstname, lastname, email, phone, address, city, state, zip, status)
?>
<form id="editCustomerNewForm" enctype="multipart/form-data">
    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userId); ?>">
    <div class="row">
        <!-- Profile Picture Display and Upload -->
        <div class="col-md-12 mb-3 text-center">
            <?php
            // Use the same logic as the view modal for profile image
            $firstname = $core2Data['firstname'] ?? '';
            $lastname = $core2Data['lastname'] ?? '';
            $profileImageUrl = getUserProfileImageUrl($userId, 'customer', $firstname, $lastname);
            ?>
            <img id="profilePicPreview" src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profile Picture" class="rounded-circle mb-2" width="80" height="80" style="object-fit: cover;">
            <div>
                <label for="profile_picture" class="form-label">Change Profile Picture</label>
                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                <div class="form-text">Max 2MB. JPG, PNG, GIF only.</div>
            </div>
        </div>
        <!-- End Profile Picture -->
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="firstname" class="form-label">First Name</label>
            <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo htmlspecialchars($core2Data['firstname'] ?? ''); ?>" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="lastname" class="form-label">Last Name</label>
            <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo htmlspecialchars($core2Data['lastname'] ?? ''); ?>" required>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($core2Data['email'] ?? ''); ?>" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="phone" class="form-label">Phone (PH format)</label>
            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($core2Data['phone'] ?? ''); ?>" pattern="^(\+639|09)\d{9}$" required>
            <div class="form-text">Format: +639XXXXXXXXX or 09XXXXXXXXX</div>
        </div>
    </div>
    <!-- Separate address fields -->
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="address" class="form-label">Address</label>
            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($core1Data['address'] ?? ''); ?>">
        </div>
        <div class="col-md-2 mb-3">
            <label for="city" class="form-label">City</label>
            <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($core1Data['city'] ?? ''); ?>">
        </div>
        <div class="col-md-2 mb-3">
            <label for="state" class="form-label">State</label>
            <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($core1Data['state'] ?? ''); ?>">
        </div>
        <div class="col-md-2 mb-3">
            <label for="zip" class="form-label">ZIP</label>
            <input type="text" class="form-control" id="zip" name="zip" value="<?php echo htmlspecialchars($core1Data['zip'] ?? ''); ?>">
        </div>
    </div>
    <div class="row">
        <!-- Customer App Status (core1_movers.customers.status) -->
        <div class="col-md-6 mb-3">
            <label for="current_status" class="form-label">Current Status (App)</label>
            <select class="form-select" id="current_status" name="current_status" required>
                <option value="online" <?php if (($core1Data['status'] ?? '') === 'online') echo 'selected'; ?>>Online</option>
                <option value="busy" <?php if (($core1Data['status'] ?? '') === 'busy') echo 'selected'; ?>>Busy</option>
                <option value="offline" <?php if (($core1Data['status'] ?? '') === 'offline' || !isset($core1Data['status'])) echo 'selected'; ?>>Offline</option>
            </select>
        </div>
        <!-- Account Status (core2_movers.users.status) -->
        <div class="col-md-6 mb-3">
            <label for="account_status" class="form-label">Account Status</label>
            <select class="form-select" id="account_status" name="account_status" required>
                <option value="active" <?php if (($core2Data['status'] ?? '') === 'active') echo 'selected'; ?>>Active</option>
                <option value="inactive" <?php if (($core2Data['status'] ?? '') === 'inactive') echo 'selected'; ?>>Inactive</option>
                <option value="suspended" <?php if (($core2Data['status'] ?? '') === 'suspended') echo 'selected'; ?>>Suspended</option>
            </select>
        </div>
    </div>
</form> 