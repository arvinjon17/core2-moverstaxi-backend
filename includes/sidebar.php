<?php
// Include role management functions if not already included
if (!function_exists('getNavigationItems')) {
    require_once 'functions/role_management.php';
}

// Get current user role and navigation items
$userRole = $_SESSION['user_role'] ?? 'guest';
$currentPage = $_GET['page'] ?? 'dashboard';
$navigationItems = getNavigationItems($userRole);
?>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky">
        <!-- Company Logo -->
        <div class="text-center py-2 bg-white border-bottom">
            <a href="index.php">
                <img src="assets/img/logo.png" alt="MOVERS" class="img-fluid" style="max-width: 85%; max-height: 60px;">
            </a>
        </div>
        
        <div class="pt-2 px-2">
            <ul class="nav flex-column">
                <?php foreach ($navigationItems as $item): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage == $item['id']) ? 'active' : ''; ?>" 
                           href="<?php echo $item['url']; ?>" 
                           aria-current="<?php echo ($currentPage == $item['id']) ? 'page' : 'false'; ?>">
                            <i class="<?php echo $item['icon']; ?> me-2"></i>
                            <?php echo $item['title']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <?php if ($userRole == 'super_admin' || $userRole == 'admin'): ?>
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span>Administration</span>
            </h6>
            <ul class="nav flex-column mb-2">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'logs') ? 'active' : ''; ?>" href="index.php?page=logs">
                        <i class="fas fa-history me-2"></i>
                        System Logs
                    </a>
                </li>
                <?php if ($userRole === 'super_admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'activity_logs') ? 'active' : ''; ?>" href="index.php?page=activity_logs">
                        <i class="fas fa-user-clock me-2"></i>
                        Activity Logs
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('view_otp_logs')): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'otp_diagnostics') ? 'active' : ''; ?>" href="otp_diagnostics.php">
                        <i class="fas fa-key me-2"></i>
                        OTP Diagnostics
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>
            
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span>Account</span>
            </h6>
            <ul class="nav flex-column mb-2">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'profile') ? 'active' : ''; ?>" href="index.php?page=profile">
                        <i class="fas fa-user-circle me-2"></i>
                        My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        Logout
                    </a>
                </li>
            </ul>
            <ul class="nav flex-column mb-2">
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fas fa-user-circle me-2"></i>
                        Version 0.36320.2 Booking&Payment Implments
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
/* Custom sidebar styling */
.sidebar {
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
    z-index: 100;
}

.sidebar .nav-link {
    font-weight: 500;
    color: #333;
    padding: .5rem 1rem;
    border-radius: .25rem;
    margin: 2px 0;
}

.sidebar .nav-link:hover {
    background-color: rgba(13, 110, 253, 0.1);
}

.sidebar .nav-link.active {
    color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.1);
}

/* For mobile views */
@media (max-width: 767.98px) {
    .sidebar {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        width: 240px;
        max-width: 100%;
        overflow-y: auto;
        height: 100vh;
        transition: transform .3s ease-in-out;
    }
    
    .sidebar.collapse:not(.show) {
        transform: translateX(-100%);
    }
}
</style> 