<?php
/**
 * Role Management Functions
 * Handles role-based access control for the application
 */

require_once 'db.php';

/**
 * Get all available roles
 */
function getAllRoles() {
    $conn = connectToCore2DB();
    
    $roles = [];
    $query = "SELECT DISTINCT role FROM role_permissions ORDER BY role";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row['role'];
        }
    }
    
    $conn->close();
    return $roles;
}

/**
 * Get the display name for a role
 * 
 * @param string $role The role slug
 * @return string The display name for the role
 */
function getRoleDisplayName($role) {
    $roleMap = [
        'super_admin' => 'Super Admin',
        'admin' => 'Administrator',
        'dispatch' => 'Dispatch Officer',
        'finance' => 'Finance Officer',
        'driver' => 'Driver',
        'customer' => 'Customer'
    ];
    
    return $roleMap[$role] ?? 'Unknown Role';
}

/**
 * Get all permissions from the system
 */
function getAllPermissions() {
    $conn = connectToCore2DB();
    
    $permissions = [];
    $query = "SELECT permission_id, name, description FROM permissions ORDER BY name";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row;
        }
    }
    
    $conn->close();
    return $permissions;
}

/**
 * Get permissions assigned to a specific role
 */
function getRolePermissions($role) {
    $conn = connectToCore2DB();
    
    // Sanitize input
    $role = $conn->real_escape_string($role);
    
    $permissions = [];
    $query = "SELECT p.name, p.description 
              FROM permissions p 
              JOIN role_permissions rp ON p.permission_id = rp.permission_id 
              WHERE rp.role = '$role' 
              ORDER BY p.name";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row;
        }
    }
    
    $conn->close();
    return $permissions;
}

/**
 * Assign a permission to a role
 */
function assignPermissionToRole($role, $permissionId) {
    $conn = connectToCore2DB();
    
    // Sanitize inputs
    $role = $conn->real_escape_string($role);
    $permissionId = (int)$permissionId;
    
    // Check if the assignment already exists
    $checkQuery = "SELECT id FROM role_permissions WHERE role = '$role' AND permission_id = $permissionId";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $conn->close();
        return ['success' => false, 'message' => 'This permission is already assigned to the role.'];
    }
    
    // Insert the new assignment
    $query = "INSERT INTO role_permissions (role, permission_id) VALUES ('$role', $permissionId)";
    if ($conn->query($query)) {
        $conn->close();
        return ['success' => true, 'message' => 'Permission assigned successfully.'];
    } else {
        $error = $conn->error;
        $conn->close();
        return ['success' => false, 'message' => 'Failed to assign permission: ' . $error];
    }
}

/**
 * Remove a permission from a role
 */
function removePermissionFromRole($role, $permissionId) {
    $conn = connectToCore2DB();
    
    // Sanitize inputs
    $role = $conn->real_escape_string($role);
    $permissionId = (int)$permissionId;
    
    // Delete the assignment
    $query = "DELETE FROM role_permissions WHERE role = '$role' AND permission_id = $permissionId";
    if ($conn->query($query)) {
        $conn->close();
        return ['success' => true, 'message' => 'Permission removed successfully.'];
    } else {
        $error = $conn->error;
        $conn->close();
        return ['success' => false, 'message' => 'Failed to remove permission: ' . $error];
    }
}

// Only define hasPermission if it doesn't already exist
// This prevents conflicts with the auth.php version
if (!function_exists('hasPermission')) {
    /**
     * Check if user has permission for a specific action
     * This is a fallback function and should not be used if auth.php is included
     */
    function hasPermission($permission) {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            return false;
        }
        
        $role = $_SESSION['user_role'] ?? '';
        
        $permissions = [
            'super_admin' => [
                'access_dashboard', 'access_fleet', 'access_drivers', 'access_dispatch', 'access_customers', 
                'access_fuel', 'access_storeroom', 'access_booking', 'access_gps', 'access_payment', 
                'access_users', 'access_analytics', 'inventory_management',
                'create_user', 'edit_user', 'delete_user',
                'create_vehicle', 'edit_vehicle', 'delete_vehicle',
                'create_driver', 'edit_driver', 'delete_driver',
                'create_dispatch', 'edit_dispatch', 'delete_dispatch',
                'view_reports', 'export_data'
            ],
            'admin' => [
                'access_dashboard', 'access_fleet', 'access_drivers', 'access_dispatch', 'access_customers', 
                'access_fuel', 'access_storeroom', 'access_booking', 'access_gps', 'access_payment', 
                'access_users', 'access_analytics', 'inventory_management',
                'create_user', 'edit_user',
                'create_vehicle', 'edit_vehicle',
                'create_driver', 'edit_driver',
                'create_dispatch', 'edit_dispatch',
                'view_reports', 'export_data'
            ],
            'dispatch' => [
                'access_dashboard', 'access_fleet', 'access_drivers', 'access_dispatch', 'access_customers', 'access_gps',
                'create_dispatch', 'edit_dispatch',
                'view_driver_location', 'assign_driver',
                'manage_bookings', 'manage_drivers', 'manage_customers', 'manage_dispatch',
                'access_gps', 'access_booking', 'edit_booking', 'view_bookings',
                'driver', 'test_driver_locations'
            ],
            'finance' => [
                'access_dashboard', 'access_payment', 'access_analytics',
                'create_payment', 'edit_payment',
                'view_reports', 'export_data'
            ],
            'driver' => [
                'access_dashboard', 'access_profile',
                'view_own_assignments', 'update_status'
            ],
            'customer' => [
                'access_dashboard', 'access_profile', 'access_booking',
                'create_booking', 'view_own_bookings'
            ]
        ];
        
        // If role doesn't exist or permission not in role permissions array, deny access
        if (!isset($permissions[$role]) || !in_array($permission, $permissions[$role])) {
            return false;
        }
        
        return true;
    }
}

/**
 * Get list of all users with their roles
 */
function getAllUsers() {
    $conn = connectToCore2DB();
    
    $query = "SELECT user_id, username, email, role, first_name, last_name, status, last_login 
              FROM users 
              ORDER BY role, last_name, first_name";
    
    $result = $conn->query($query);
    $users = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    
    $conn->close();
    return $users;
}

/**
 * Update user role
 */
function updateUserRole($userId, $newRole) {
    $conn = connectToCore2DB();
    
    // Sanitize inputs
    $userId = (int)$userId;
    $newRole = $conn->real_escape_string($newRole);
    
    // Verify the role exists
    $roles = getAllRoles();
    if (!array_key_exists($newRole, $roles)) {
        return false;
    }
    
    // Update the user's role
    $query = "UPDATE users SET role = '$newRole' WHERE user_id = $userId";
    $result = $conn->query($query);
    
    $conn->close();
    return $result;
}

/**
 * Get navigation menu items based on user role
 */
function getNavigationItems($role) {
    $items = [];
    
    // Default navigation items for all authenticated users
    $items[] = [
        'id' => 'dashboard',
        'title' => 'Dashboard',
        'url' => 'index.php?page=dashboard',
        'icon' => 'fas fa-tachometer-alt'
    ];
    
    // Add role-specific menu items
    switch ($role) {
        case 'super_admin':
            $items[] = [
                'id' => 'bookings',
                'title' => 'Booking Management',
                'url' => 'index.php?page=bookings',
                'icon' => 'fas fa-calendar-check'
            ];
            $items[] = [
                'id' => 'customers',
                'title' => 'Customer Management',
                'url' => 'index.php?page=customers',
                'icon' => 'fas fa-users'
            ];
            $items[] = [
                'id' => 'fleet',
                'title' => 'Fleet Management',
                'url' => 'index.php?page=fleet',
                'icon' => 'fas fa-truck'
            ];
            $items[] = [
                'id' => 'drivers',
                'title' => 'Driver Management',
                'url' => 'index.php?page=drivers',
                'icon' => 'fas fa-id-card'
            ];
            $items[] = [
                'id' => 'storeroom',
                'title' => 'Storeroom Management',
                'url' => 'index.php?page=storeroom',
                'icon' => 'fas fa-warehouse'
            ];
            $items[] = [
                'id' => 'users',
                'title' => 'User Management',
                'url' => 'index.php?page=users',
                'icon' => 'fas fa-user-cog'
            ];
            $items[] = [
                'id' => 'system',
                'title' => 'System Settings',
                'url' => 'index.php?page=system',
                'icon' => 'fas fa-cogs'
            ];
            break;
            
        case 'admin':
            // Admin dashboard for super_admin and admin roles
            $items[] = [
                'id' => 'admin',
                'title' => 'Admin Dashboard',
                'icon' => 'fas fa-user-shield',
                'url' => 'index.php?page=admin'
            ];
            break;
            
        case 'dispatch':
            // Dispatcher needs access to these core tools
            $items[] = [
                'id' => 'dispatch_dashboard',
                'title' => 'Dispatcher Dashboard',
                'icon' => 'fas fa-map-marked-alt',
                'url' => 'index.php?page=dashboard_dispatcher'
            ];
            
            $items[] = [
                'id' => 'bookings',
                'title' => 'Booking Management',
                'url' => 'index.php?page=bookings',
                'icon' => 'fas fa-calendar-check'
            ];
            
            $items[] = [
                'id' => 'drivers',
                'title' => 'Driver Management',
                'url' => 'index.php?page=drivers',
                'icon' => 'fas fa-id-card'
            ];
            
            $items[] = [
                'id' => 'customers',
                'title' => 'Customer Management',
                'url' => 'index.php?page=customers',
                'icon' => 'fas fa-users'
            ];
            
            $items[] = [
                'id' => 'gps',
                'title' => 'GPS Tracking',
                'icon' => 'fas fa-map-marker-alt',
                'url' => 'index.php?page=gps'
            ];
            
            $items[] = [
                'id' => 'driver_locations',
                'title' => 'Driver Locations',
                'icon' => 'fas fa-location-arrow',
                'url' => 'index.php?page=test_driver_locations'
            ];
            break;
            
        case 'finance':
            // Payment management
            $items[] = [
                'id' => 'payment',
                'title' => 'Payment Management',
                'icon' => 'fas fa-money-bill-wave',
                'url' => 'index.php?page=payment'
            ];
            break;
            
        case 'driver':
            // Profile for all users
            $items[] = [
                'id' => 'profile',
                'title' => 'My Profile',
                'icon' => 'fas fa-user',
                'url' => 'index.php?page=profile'
            ];
            break;
            
        case 'customer':
            // Booking management
            $items[] = [
                'id' => 'booking',
                'title' => 'Book Ride',
                'icon' => 'fas fa-calendar-plus',
                'url' => 'index.php?page=booking'
            ];
            break;
            
        default:
            // Core1 modules - Fleet management
            if (hasPermission('access_fleet')) {
                $items[] = [
                    'id' => 'fleet',
                    'title' => 'Fleet Management',
                    'icon' => 'fas fa-taxi',
                    'url' => 'index.php?page=fleet'
                ];
            }
            
            // Driver management
            if (hasPermission('manage_drivers')) {
                $items[] = [
                    'id' => 'drivers',
                    'title' => 'Driver Management',
                    'icon' => 'fas fa-id-card',
                    'url' => 'index.php?page=drivers'
                ];
            }
            
            // Taxi dispatch
            if (hasPermission('manage_dispatch')) {
                $items[] = [
                    'id' => 'dispatch',
                    'title' => 'Taxi Dispatch',
                    'icon' => 'fas fa-map-marked-alt',
                    'url' => 'index.php?page=dispatch'
                ];
            }
            
            // Customer management
            if (hasPermission('manage_customers')) {
                $items[] = [
                    'id' => 'customers',
                    'title' => 'Customer Management',
                    'icon' => 'fas fa-users',
                    'url' => 'index.php?page=customers'
                ];
            }
            
            // Fuel monitoring
            if (hasPermission('manage_fuel')) {
                $items[] = [
                    'id' => 'fuel',
                    'title' => 'Fuel Monitoring',
                    'icon' => 'fas fa-gas-pump',
                    'url' => 'index.php?page=fuel'
                ];
            }
            
            // Storeroom management
            if (hasPermission('manage_storeroom')) {
                $items[] = [
                    'id' => 'storeroom',
                    'title' => 'Storeroom Management',
                    'icon' => 'fas fa-warehouse',
                    'url' => 'index.php?page=storeroom'
                ];
            }
            
            // Playground Booking Management
            if (hasPermission('manage_storeroom') || $role === 'super_admin' || $role === 'admin' || $role === 'dispatch') {
                $items[] = [
                    'id' => 'playground_booking',
                    'title' => 'Playground Booking',
                    'icon' => 'fas fa-futbol',
                    'url' => 'index.php?page=playground_booking'
                ];
            }
            
            // Booking management
            if (hasPermission('manage_bookings')) {
                $items[] = [
                    'id' => 'booking',
                    'title' => 'Booking Management',
                    'icon' => 'fas fa-calendar-check',
                    'url' => 'index.php?page=booking'
                ];
            } elseif ($role === 'customer') {
                $items[] = [
                    'id' => 'booking',
                    'title' => 'Book Ride',
                    'icon' => 'fas fa-calendar-plus',
                    'url' => 'index.php?page=booking'
                ];
            }
            
            // GPS tracking
            if (hasPermission('access_gps')) {
                $items[] = [
                    'id' => 'gps',
                    'title' => 'GPS Tracking',
                    'icon' => 'fas fa-map-marker-alt',
                    'url' => 'index.php?page=gps'
                ];
            }
            
            // Payment management
            if (hasPermission('manage_payments')) {
                $items[] = [
                    'id' => 'payment',
                    'title' => 'Payment Management',
                    'icon' => 'fas fa-money-bill-wave',
                    'url' => 'index.php?page=payment'
                ];
            }
            
            // User maintenance
            if (hasPermission('manage_users')) {
                $items[] = [
                    'id' => 'users',
                    'title' => 'User Maintenance',
                    'icon' => 'fas fa-user-cog',
                    'url' => 'index.php?page=users'
                ];
            }
            
            // Transport analytics
            if (hasPermission('access_analytics')) {
                $items[] = [
                    'id' => 'analytics',
                    'title' => 'Transport Analytics',
                    'icon' => 'fas fa-chart-line',
                    'url' => 'index.php?page=analytics'
                ];
            }
            
            // System settings
            if (hasPermission('manage_system')) {
                $items[] = [
                    'id' => 'system',
                    'title' => 'System Settings',
                    'icon' => 'fas fa-cog',
                    'url' => 'index.php?page=system'
                ];
            }
            
            // Profile for all users
            if (hasPermission('access_profile')) {
                $items[] = [
                    'id' => 'profile',
                    'title' => 'My Profile',
                    'icon' => 'fas fa-user',
                    'url' => 'index.php?page=profile'
                ];
            }
    }
    
    return $items;
}

/**
 * Assign a role to a user
 */
function assignRoleToUser($userId, $role) {
    $conn = connectToCore2DB();
    
    // Sanitize inputs
    $userId = (int)$userId;
    $role = $conn->real_escape_string($role);
    
    // Update the user role
    $query = "UPDATE users SET role = '$role' WHERE user_id = $userId";
    if ($conn->query($query)) {
        $conn->close();
        return ['success' => true, 'message' => 'Role assigned successfully.'];
    } else {
        $error = $conn->error;
        $conn->close();
        return ['success' => false, 'message' => 'Failed to assign role: ' . $error];
    }
}

/**
 * Get user role by user ID
 */
function getUserRole($userId) {
    $conn = connectToCore2DB();
    
    // Sanitize input
    $userId = (int)$userId;
    
    $query = "SELECT role FROM users WHERE user_id = $userId";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $conn->close();
        return $user['role'];
    }
    
    $conn->close();
    return null;
}

/**
 * Define role permissions
 */
function defineRolePermissions() {
    $permissions = [
        'super_admin' => [
            // Dashboard access
            'view_dashboard', 'access_dashboard',
            
            // Booking management
            'view_bookings', 'create_booking', 'edit_booking', 'delete_booking', 'manage_bookings', 'access_booking',
            
            // Customer management
            'view_customers', 'create_customer', 'edit_customer', 'delete_customer', 'manage_customers', 'access_customers',
            
            // Fleet management
            'view_fleet', 'create_vehicle', 'edit_vehicle', 'delete_vehicle', 'manage_fleet', 'access_fleet',
            
            // Driver management
            'view_drivers', 'create_driver', 'edit_driver', 'delete_driver', 'manage_drivers', 'access_drivers',
            
            // Storeroom management
            'view_storeroom', 'create_item', 'edit_item', 'delete_item', 'manage_storeroom', 'access_storeroom', 'inventory_management',
            
            // User management
            'view_users', 'create_user', 'edit_user', 'delete_user', 'manage_users', 'access_users',
            
            // System settings
            'view_system_settings', 'edit_system_settings', 'manage_system', 'access_system',
            
            // Other modules
            'view_logs', 'access_logs',
            'manage_dispatch', 'access_dispatch',
            'manage_fuel', 'access_fuel',
            'manage_payments', 'access_payment',
            'access_gps', 'view_driver_location',
            'access_analytics', 'view_reports', 'export_data',
            'access_profile', 'view_own_assignments', 'update_status',
            
            // Activity Logs
            'view_activity_logs', 'access_activity_logs'
        ],
        'admin' => [
            // Dashboard access
            'view_dashboard', 'access_dashboard',
            
            // Booking management
            'view_bookings', 'create_booking', 'edit_booking', 'manage_bookings', 'access_booking',
            
            // Customer management
            'view_customers', 'create_customer', 'edit_customer', 'manage_customers', 'access_customers',
            
            // Fleet management
            'view_fleet', 'create_vehicle', 'edit_vehicle', 'manage_fleet', 'access_fleet',
            
            // Driver management
            'view_drivers', 'create_driver', 'edit_driver', 'manage_drivers', 'access_drivers',
            
            // Storeroom management
            'view_storeroom', 'create_item', 'edit_item', 'manage_storeroom', 'access_storeroom', 'inventory_management',
            
            // User management
            'view_users', 'create_user', 'edit_user', 'manage_users', 'access_users',
            
            // System settings
            'view_system_settings', 'manage_system', 'access_system',
            
            // Other modules
            'view_logs', 'access_logs',
            'manage_dispatch', 'access_dispatch',
            'manage_fuel', 'access_fuel',
            'manage_payments', 'access_payment',
            'access_gps', 'view_driver_location',
            'access_analytics', 'view_reports', 'export_data',
            'access_profile'
        ],
        'dispatch' => [
            'view_dashboard', 'access_dashboard',
            'view_bookings', 'access_booking', 'manage_bookings',
            'view_fleet', 'access_fleet',
            'view_drivers', 'access_drivers',
            'access_dispatch', 'manage_dispatch',
            'view_customers', 'access_customers',
            'access_gps', 'view_driver_location', 'simulate_driver_locations',
            'access_profile'
        ],
        'finance' => [
            'view_dashboard', 'access_dashboard',
            'view_bookings', 'access_booking',
            'access_payment', 'manage_payments',
            'access_analytics', 'view_reports', 'export_data',
            'access_profile'
        ],
        'driver' => [
            'view_dashboard', 'access_dashboard',
            'access_profile', 'view_own_assignments', 'update_status'
        ],
        'customer' => [
            'view_dashboard', 'access_dashboard',
            'access_profile', 
            'access_booking', 'view_own_bookings', 'create_booking'
        ]
    ];
    
    return $permissions;
}
?> 