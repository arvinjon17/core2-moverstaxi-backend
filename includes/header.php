<?php
// Get user information from session
$fullName = $_SESSION['user_full_name'] ?? $_SESSION['user_fullname'] ?? 'User';
$role = $_SESSION['user_role'] ?? 'Unknown';
$userId = $_SESSION['user_id'] ?? 0;
$firstname = $_SESSION['user_firstname'] ?? (isset($_SESSION['user_full_name']) ? explode(' ', $_SESSION['user_full_name'])[0] : '');
$lastname = $_SESSION['user_lastname'] ?? (isset($_SESSION['user_full_name']) ? (explode(' ', $_SESSION['user_full_name'])[1] ?? '') : '');

// Include role management functions if not already included
if (!function_exists('getRoleDisplayName')) {
    require_once 'functions/role_management.php';
}

// Include profile image functions
require_once 'functions/profile_images.php';

// Get readable role name
$roleDisplay = getRoleDisplayName($role);

// Base URL for assets - adjust if needed
$baseUrl = '';

// Create user data array for profile image function with correct structure matching users.php expectations
// This is critical - it must include user_id, firstname, lastname, role in this format
$userData = [
    'user_id' => $userId,
    'firstname' => $firstname,
    'lastname' => $lastname,
    'role' => $role,
    'profile_picture' => $_SESSION['profile_picture'] ?? '' // Get profile_picture from session if available
];

// Get the profile image URL
$profileImageUrl = '';
if (function_exists('getUserProfileImageUrl')) {
    $profileImageUrl = getUserProfileImageUrl($userId, $role, $firstname, $lastname);
    error_log("Profile URL found: $profileImageUrl");
}

// Ensure profile image URL exists and is valid
$imageExists = false;

// Default image doesn't need existence check
if ($profileImageUrl === 'assets/img/default_user.jpg') {
    $imageExists = true;
} 
// Check if the file exists directly
else if (!empty($profileImageUrl) && file_exists($profileImageUrl)) {
    $imageExists = true;
}
// Try with website root if available
else if (!empty($profileImageUrl) && function_exists('getWebsiteRoot')) {
    $websiteRoot = getWebsiteRoot();
    if (file_exists($websiteRoot . $profileImageUrl)) {
        $imageExists = true;
    } else {
        error_log("Profile image not found at website root path: {$websiteRoot}{$profileImageUrl}");
    }
}

if (!$imageExists && !empty($profileImageUrl)) {
    error_log("Profile image not found, falling back to initials display: $profileImageUrl");
}

// Expected folder path - for debugging only
$expectedFolderPath = 'uploads/' . $role . '_profiles/' . strtolower($firstname) . '_' . strtolower($lastname) . '_' . $userId . '/';
error_log("Expected folder path for user profile: $expectedFolderPath");
?>

<header class="navbar navbar-expand-md navbar-light bg-light shadow-sm">
    <div class="container-fluid">
        <!-- Mobile sidebar toggle button -->
        <button class="navbar-toggler me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Empty navbar brand to fill space when the sidebar is collapsed -->
        <span class="d-md-none"><!-- Mobile spacer --></span>
        
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav align-items-center">
                <li class="nav-item me-3">
                    <span id="current-datetime" class="text-secondary"></span>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <?php if ($imageExists): ?>
                                <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profile" class="rounded-circle me-2" width="35" height="35" style="object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                    <?php 
                                        if (!empty($firstname) && !empty($lastname)) {
                                            echo strtoupper(substr($firstname, 0, 1) . substr($lastname, 0, 1));
                                        } else {
                                            echo 'U';
                                        }
                                    ?>
                                </div>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($fullName); ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><span class="dropdown-item-text"><?php echo $roleDisplay; ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php?page=profile"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="index.php?page=system"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</header>

<script>
    // Update date and time
    function updateDateTime() {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: true,
            timeZone: 'Asia/Manila'
        };
        
        document.getElementById('current-datetime').textContent = now.toLocaleString('en-US', options);
    }
    
    // Update time initially and then every second
    updateDateTime();
    setInterval(updateDateTime, 1000);
</script> 