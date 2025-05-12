<?php
/**
 * Profile Image Functions
 * Utility functions for handling profile images for users and drivers
 */

/**
 * Get the profile image path for a user
 * 
 * @param array $userData User data array containing profile_picture field
 * @param array $driverData Optional driver data array containing profile_image field
 * @return string|null Path to the profile image or null if not found
 */
function getProfileImagePath($userData, $driverData = null) {
    // Get the website root for absolute path resolution
    $docRoot = getWebsiteRoot();
    
    // Add debug logging
    error_log("Looking for profile image: " . 
              (isset($userData['profile_picture']) ? $userData['profile_picture'] : 'no user picture') . ", " .
              ($driverData && isset($driverData['profile_image']) ? $driverData['profile_image'] : 'no driver image'));
    
    // List of all role-specific profile directories to check
    $roleDirectories = [
        'super_admin_profiles',
        'admin_profiles',
        'finance_profiles',
        'dispatch_profiles',
        'driver_profiles',
        'customer_profiles',
        'user_profiles' // Generic fallback
    ];
    
    // First check for user's profile_picture (core2_movers.users.profile_picture)
    if (isset($userData['profile_picture']) && !empty($userData['profile_picture'])) {
        // Check if the profile_picture is a full path or just a filename
        if (strpos($userData['profile_picture'], '/') !== false) {
            // Profile picture has path info
            
            // Direct check for role-specific paths
            foreach ($roleDirectories as $roleDir) {
                if (strpos($userData['profile_picture'], $roleDir . '/') === 0) {
                    // This is a path from the database - construct the full path
                    $profilePath = 'uploads/' . $userData['profile_picture'];
                    
                    // Check if the file exists
                    if (file_exists($profilePath)) {
                        error_log("Found image at: " . $profilePath);
                        return $profilePath;
                    } else {
                        // Try with absolute path
                        $absPath = $docRoot . 'uploads/' . $userData['profile_picture'];
                        if (file_exists($absPath)) {
                            error_log("Found image at (abs): " . $absPath);
                            // Return relative path for web URL construction
                            return 'uploads/' . $userData['profile_picture'];
                        }
                        error_log("Image not found at: " . $profilePath . " or " . $absPath);
                    }
                }
            }
            
            // Check directly as provided - it could be a legacy path
            if (file_exists($userData['profile_picture'])) {
                error_log("Found image at direct path: " . $userData['profile_picture']);
                return $userData['profile_picture'];
            } else {
                error_log("Image not found at direct path: " . $userData['profile_picture']);
            }
        }
        
        // If profile_picture is just a filename without a path, check all role-specific folders
        foreach ($roleDirectories as $roleDir) {
            $profilePath = 'uploads/' . $roleDir . '/' . $userData['profile_picture'];
            if (file_exists($profilePath)) {
                error_log("Found image at: " . $profilePath);
                return $profilePath;
            } else {
                // Try with absolute path
                $absPath = $docRoot . 'uploads/' . $roleDir . '/' . $userData['profile_picture'];
                if (file_exists($absPath)) {
                    error_log("Found image at (abs): " . $absPath);
                    // Return relative path for web URL construction
                    return 'uploads/' . $roleDir . '/' . $userData['profile_picture'];
                }
            }
        }
        
        // Check assets directory as fallback
        $assetPath = 'assets/img/users/' . $userData['profile_picture'];
        if (file_exists($assetPath)) {
            error_log("Found image at: " . $assetPath);
            return $assetPath;
        } else {
            // Try with absolute path
            $absPath = $docRoot . 'assets/img/users/' . $userData['profile_picture'];
            if (file_exists($absPath)) {
                error_log("Found image at (abs): " . $absPath);
                // Return relative path for web URL construction
                return 'assets/img/users/' . $userData['profile_picture'];
            }
            error_log("Image not found at: " . $assetPath . " or " . $absPath);
        }
    }
    
    // Then check for driver's profile_image as fallback if user profile_picture not found
    if ($driverData && isset($driverData['profile_image']) && !empty($driverData['profile_image'])) {
        // Check driver image path similar to user images
        if (strpos($driverData['profile_image'], '/') !== false) {
            // If it has path information
            if (file_exists($driverData['profile_image'])) {
                error_log("Found driver image at direct path: " . $driverData['profile_image']);
                return $driverData['profile_image'];
            } else {
                // Try as relative to uploads
                $profilePath = 'uploads/' . $driverData['profile_image'];
                if (file_exists($profilePath)) {
                    error_log("Found driver image at: " . $profilePath);
                    return $profilePath;
                } else {
                    // Try with absolute path
                    $absPath = $docRoot . 'uploads/' . $driverData['profile_image'];
                    if (file_exists($absPath)) {
                        error_log("Found driver image at (abs): " . $absPath);
                        return 'uploads/' . $driverData['profile_image'];
                    }
                    error_log("Driver image not found at complex paths");
                }
            }
        }
        
        // Try standard driver profile path
        $profilePath = 'uploads/driver_profiles/' . $driverData['profile_image'];
        if (file_exists($profilePath)) {
            error_log("Found driver image at standard path: " . $profilePath);
            return $profilePath;
        } else {
            // Try with absolute path
            $absPath = $docRoot . 'uploads/driver_profiles/' . $driverData['profile_image'];
            if (file_exists($absPath)) {
                error_log("Found driver image at (abs): " . $absPath);
                return 'uploads/driver_profiles/' . $driverData['profile_image'];
            }
            error_log("Driver image not found at standard paths");
        }
        
        // Check assets directory as fallback
        $assetPath = 'assets/img/drivers/' . $driverData['profile_image'];
        if (file_exists($assetPath)) {
            error_log("Found driver image at assets: " . $assetPath);
            return $assetPath;
        } else {
            // Try with absolute path
            $absPath = $docRoot . 'assets/img/drivers/' . $driverData['profile_image'];
            if (file_exists($absPath)) {
                error_log("Found driver image at assets (abs): " . $absPath);
                return 'assets/img/drivers/' . $driverData['profile_image'];
            }
            error_log("Driver image not found in assets");
        }
    }
    
    // Default image if available
    $defaultPath = 'assets/img/default_user.jpg';
    if (file_exists($defaultPath)) {
        error_log("Using default image at: " . $defaultPath);
        return $defaultPath;
    } else {
        // Try with absolute path
        $absDefaultPath = $docRoot . 'assets/img/default_user.jpg';
        if (file_exists($absDefaultPath)) {
            error_log("Using default image at (abs): " . $absDefaultPath);
        return 'assets/img/default_user.jpg';
        }
    }
    
    error_log("No image found for user/driver");
    return null;
}

/**
 * Get the HTML for displaying a profile image or initials
 * 
 * @param array $userData User data array (must include firstname and lastname)
 * @param array $driverData Optional driver data array
 * @param int $size Size of the image/initials container in pixels
 * @param string $classes Additional CSS classes for the container
 * @return string HTML for the profile image or initials
 */
function getProfileImageHtml($userData, $driverData = null, $size = 40, $classes = '') {
    $imgPath = getProfileImagePath($userData, $driverData);
    $html = '';
    
    // Debug info about image paths
    $debugInfo = '';
    if (isset($userData['profile_picture'])) {
        $debugInfo .= 'User picture: ' . $userData['profile_picture'] . '; ';
    } else {
        $debugInfo .= 'No user picture; ';
    }
    if ($driverData && isset($driverData['profile_image'])) {
        $debugInfo .= 'Driver image: ' . $driverData['profile_image'] . '; ';
    } else {
        $debugInfo .= 'No driver image; ';
    }
    $debugInfo .= 'Path used: ' . ($imgPath ?: 'none');
    
    // Make sure we have the required user data
    if (!isset($userData['firstname']) || !isset($userData['lastname'])) {
        return '<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center ' . $classes . '" style="width: ' . $size . 'px; height: ' . $size . 'px;" data-debug="' . htmlspecialchars($debugInfo) . '">
            <i class="fas fa-user"></i>
        </div>';
    }
    
    // Get user initials for the fallback
    $initials = strtoupper(substr($userData['firstname'], 0, 1) . substr($userData['lastname'], 0, 1));
    
    if ($imgPath) {
        $html = '<img src="' . htmlspecialchars($imgPath) . '" alt="Profile" class="rounded-circle ' . $classes . '" style="width: ' . $size . 'px; height: ' . $size . 'px; object-fit: cover;" data-debug="' . htmlspecialchars($debugInfo) . '">';
    } else {
        $html = '<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center ' . $classes . '" style="width: ' . $size . 'px; height: ' . $size . 'px;" data-debug="' . htmlspecialchars($debugInfo) . '">
            ' . $initials . '
        </div>';
    }
    
    return $html;
}

/**
 * Get the absolute path to the website root directory
 * This handles different contexts (direct access vs. included files)
 * 
 * @return string Absolute path to website root with trailing slash
 */
function getWebsiteRoot() {
    // Start with the document root
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    
    // Normalize path (remove trailing slash if present)
    $docRoot = rtrim($docRoot, '/\\');
    
    // Check if we're in XAMPP environment on Windows
    if (stripos(PHP_OS, 'WIN') !== false && (strpos($docRoot, 'xampp') !== false || strpos($docRoot, 'htdocs') !== false)) {
        // If document root doesn't include public_html but we're in it, add it
        if (strpos($docRoot, 'public_html') === false) {
            $scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
            if (strpos($scriptPath, 'public_html') !== false) {
                // Extract the path up to and including public_html
                $pattern = '/(.*?public_html)/';
                if (preg_match($pattern, $scriptPath, $matches)) {
                    $docRoot = $matches[1];
                }
            }
        }
        
        // Extra check for Windows path formatting
        if (strpos($docRoot, ':') !== false) {
            // Ensure consistent directory separators (convert all to forward slashes)
            $docRoot = str_replace('\\', '/', $docRoot);
        }
    }
    
    // Make sure we end with a directory separator (use forward slash for URLs)
    $docRoot = rtrim($docRoot, '/\\') . '/';
    
    // Log the resolved root for debugging
    error_log("Website root resolved to: " . $docRoot);
    
    return $docRoot;
}

/**
 * Upload a profile image
 * 
 * @param mixed $file Either $_FILES array element for the uploaded file or string key in $_FILES
 * @param string $type Type of profile ('user' or role name like 'driver', 'customer', etc.)
 * @param int $id ID of the user
 * @param array $userData Optional user data containing firstname and lastname
 * @param bool $updateDatabase Whether to automatically update the database with the new profile picture (default: true)
 * @return array Upload result with success status, message, and filename
 */
function uploadProfileImage($file, $type = 'user', $id = 0, $userData = null, $updateDatabase = true) {
    $result = [
        'success' => false,
        'message' => '',
        'filename' => ''
    ];
    
    // If $file is a string, look it up in $_FILES
    if (is_string($file) && isset($_FILES[$file])) {
        // Store the file field name for logging before replacing it with the actual file data
        $fileFieldName = $file;
        $file = $_FILES[$file];
        error_log("Using file from \$_FILES[" . $fileFieldName . "]");
    }
    
    // Validate upload
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $result['message'] = 'No file was uploaded.';
        return $result;
    }
    
    // Validate file type using both MIME type and extension
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    
    // Check MIME type
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        $result['message'] = 'Invalid file type. Only JPG, JPEG, PNG, and WEBP are allowed.';
        return $result;
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        $result['message'] = 'Invalid file extension. Only JPG, JPEG, PNG, and WEBP are allowed.';
        return $result;
    }
    
    // Validate file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        $result['message'] = 'File is too large. Maximum size is 2MB.';
        return $result;
    }
    
    // Get the absolute path to the website root
    $docRoot = getWebsiteRoot();
    
    // Log important path information for debugging
    error_log("Document root: " . $docRoot);
    error_log("Current directory: " . getcwd());
    error_log("Script filename: " . $_SERVER['SCRIPT_FILENAME']);
    
    // Normalize type to ensure it corresponds to a valid role folder
    $folderType = $type;
    // Map 'user' to the user's role if available in userData
    if ($type === 'user' && isset($userData['role'])) {
        $folderType = $userData['role'];
    }
    
    // For customer specifically, make sure we save in customer_profiles
    if ($folderType === 'customer') {
        $folderPrefix = 'customer_profiles';
    } else {
        // Map role to folder name
        $validRoles = ['super_admin', 'admin', 'finance', 'dispatch', 'driver', 'customer'];
        if (in_array($folderType, $validRoles)) {
            $folderPrefix = $folderType . '_profiles';
        } else {
            // Default to 'user_profiles' if role is not recognized
            $folderPrefix = 'user_profiles';
        }
    }
    
    // Set base upload directory with absolute path
    $baseUploadDir = $docRoot . 'uploads' . DIRECTORY_SEPARATOR . $folderPrefix . DIRECTORY_SEPARATOR;
    
    error_log("Base upload directory: " . $baseUploadDir);
    
    // Create the base directory if it doesn't exist
    if (!file_exists($baseUploadDir)) {
        error_log("Base upload directory doesn't exist, creating: " . $baseUploadDir);
        if (!mkdir($baseUploadDir, 0777, true)) {
            $result['message'] = 'Failed to create base upload directory: ' . $baseUploadDir;
            return $result;
        }
    }
    
    // Create a personalized folder structure if user data is available
    if ($userData && isset($userData['firstname']) && isset($userData['lastname'])) {
        // Sanitize the firstname and lastname for folder name
        $firstname = preg_replace('/[^a-z0-9]/', '_', strtolower($userData['firstname']));
        $lastname = preg_replace('/[^a-z0-9]/', '_', strtolower($userData['lastname']));
        
        // Create folder name with firstname_lastname_id format
        $folderName = $firstname . '_' . $lastname . '_' . $id;
        $uploadDir = $baseUploadDir . $folderName . DIRECTORY_SEPARATOR;
    } else if ($id > 0) {
        // If no userData but we have an ID, try to get the user data from the database
        $conn2 = connectToCore2DB();
        if ($conn2) {
            $query = "SELECT firstname, lastname FROM users WHERE user_id = ?";
            $stmt = $conn2->prepare($query);
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result2 = $stmt->get_result();
                if ($result2 && $result2->num_rows > 0) {
                    $userData = $result2->fetch_assoc();
                    
                    // Sanitize the firstname and lastname for folder name
                    $firstname = preg_replace('/[^a-z0-9]/', '_', strtolower($userData['firstname']));
                    $lastname = preg_replace('/[^a-z0-9]/', '_', strtolower($userData['lastname']));
                    
                    // Create folder name with firstname_lastname_id format
                    $folderName = $firstname . '_' . $lastname . '_' . $id;
                    $uploadDir = $baseUploadDir . $folderName . DIRECTORY_SEPARATOR;
                } else {
                    // Default folder if user not found
                    $folderName = 'user_' . $id;
                    $uploadDir = $baseUploadDir . $folderName . DIRECTORY_SEPARATOR;
                }
                $stmt->close();
            } else {
                // Default folder if statement failed
                $folderName = 'user_' . $id;
                $uploadDir = $baseUploadDir . $folderName . DIRECTORY_SEPARATOR;
            }
            $conn2->close();
        } else {
            // Default folder if connection failed
            $folderName = 'user_' . $id;
            $uploadDir = $baseUploadDir . $folderName . DIRECTORY_SEPARATOR;
        }
    } else {
        // Standard path if user data is missing and no ID available
        $uploadDir = $baseUploadDir;
        $folderName = '';
    }
    
    error_log("User-specific upload directory: " . $uploadDir);
    
    // Create the user-specific directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        error_log("User directory doesn't exist, creating: " . $uploadDir);
        if (!mkdir($uploadDir, 0777, true)) {
            $result['message'] = 'Failed to create user-specific directory: ' . $uploadDir;
            return $result;
        }
    }
    
    // Generate filename with timestamp to ensure uniqueness
    $timestamp = time();
    $filename = 'profile_' . $timestamp . '.' . $extension;
    
    // Make sure the filename doesn't already exist (extremely unlikely with timestamp)
    $targetPath = $uploadDir . $filename;
    while (file_exists($targetPath)) {
        // If somehow exists, add a random number to ensure uniqueness
        $timestamp = time() . '_' . mt_rand(1000, 9999);
        $filename = 'profile_' . $timestamp . '.' . $extension;
        $targetPath = $uploadDir . $filename;
    }
    
    // Define the relative path for database storage
    if (!empty($folderName)) {
        $relativeFilename = $folderPrefix . '/' . $folderName . '/' . $filename;
    } else {
        // Standard path if user data is missing
        $relativeFilename = $folderPrefix . '/' . $filename;
    }
    
    error_log("Full target path: " . $targetPath);
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $result['success'] = true;
        $result['message'] = 'Profile image uploaded successfully.';
        
        // Store the path relative to uploads directory for database storage
        $result['filename'] = $relativeFilename;
        
        // Also return the full filepath relative to document root for debugging
        $result['filepath'] = str_replace($docRoot, '', $targetPath);
        
        // Log success
        error_log("File uploaded successfully to: " . $targetPath);
        error_log("Relative path for database: " . $result['filename']);
        
        // Update the profile_picture field in the database if this is a customer
        // Only if updateDatabase is true (to prevent duplicate records)
        if ($updateDatabase && $folderType === 'customer' && $id > 0) {
            error_log("Automatically updating customer profile picture in database");
            updateCustomerProfilePicture($id, $relativeFilename, false);
        } else {
            error_log("Skipping automatic database update for customer profile picture (updateDatabase: " . ($updateDatabase ? 'true' : 'false') . ")");
        }
    } else {
        $result['message'] = 'Failed to save the uploaded file. Target path: ' . $targetPath;
        error_log("Failed to upload file to: " . $targetPath);
        
        // Check if the directory is writable
        if (!is_writable(dirname($targetPath))) {
            $result['message'] .= ' Directory is not writable.';
            error_log("Directory is not writable: " . dirname($targetPath));
        }
        
        // Check if the tmp file exists and is readable
        if (!file_exists($file['tmp_name'])) {
            $result['message'] .= ' Temporary file does not exist.';
            error_log("Temporary file does not exist: " . $file['tmp_name']);
        } else if (!is_readable($file['tmp_name'])) {
            $result['message'] .= ' Temporary file is not readable.';
            error_log("Temporary file is not readable: " . $file['tmp_name']);
        }
    }
    
    return $result;
}

/**
 * Update customer profile picture in both databases
 * 
 * @param int $customerId ID of the customer
 * @param string $picturePath Path to the profile picture relative to uploads directory
 * @param bool $closeConnections Whether to close database connections after use (default: true)
 * @return bool True if update was successful, false otherwise
 */
function updateCustomerProfilePicture($customerId, $picturePath, $closeConnections = true) {
    // Always create fresh connections to avoid "already closed" errors
    $conn1 = connectToCore1DB();
    $conn2 = connectToCore2DB();
    
    $success = true;
    
    // Update profile_picture in core1_movers.customers
    if ($conn1) {
        // First check if customer record exists in core1_movers.customers
        $checkQuery = "SELECT customer_id FROM customers WHERE user_id = ?";
        $checkStmt = $conn1->prepare($checkQuery);
        
        if ($checkStmt) {
            $checkStmt->bind_param('i', $customerId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $customerExists = ($checkResult && $checkResult->num_rows > 0);
            $checkStmt->close();
            
            if ($customerExists) {
                // Update existing record
                $query = "UPDATE customers SET profile_picture = ? WHERE user_id = ?";
                $stmt = $conn1->prepare($query);
                
                if ($stmt) {
                    $stmt->bind_param('si', $picturePath, $customerId);
                    if (!$stmt->execute()) {
                        error_log("Failed to update profile picture in core1_movers.customers: " . $stmt->error);
                        $success = false;
                    } else {
                        error_log("Successfully updated profile picture for customer user_id: $customerId");
                    }
                    $stmt->close();
                } else {
                    error_log("Failed to prepare statement for core1_movers.customers: " . $conn1->error);
                    $success = false;
                }
            } else {
                // Don't create customer records automatically here. Just log that it doesn't exist.
                error_log("Customer record for user_id: $customerId does not exist. Not creating a new record here.");
                $success = false;
            }
        } else {
            error_log("Failed to prepare check statement for core1_movers.customers: " . $conn1->error);
            $success = false;
        }
        
        if ($closeConnections) {
            $conn1->close();
        }
    } else {
        error_log("Failed to connect to core1_movers database");
        $success = false;
    }
    
    // Update profile_picture in core2_movers.users for consistency
    if ($conn2) {
        $query = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
        $stmt = $conn2->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param('si', $picturePath, $customerId);
            if (!$stmt->execute()) {
                error_log("Failed to update profile picture in core2_movers.users: " . $stmt->error);
                $success = false;
            } else {
                error_log("Successfully updated profile picture in users table for user_id: $customerId");
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for core2_movers.users: " . $conn2->error);
            $success = false;
        }
        
        if ($closeConnections) {
            $conn2->close();
        }
    } else {
        error_log("Failed to connect to core2_movers database");
        $success = false;
    }
    
    return $success;
}

/**
 * Upload a document file with organized folder structure
 * 
 * @param array $file $_FILES array element for the uploaded file
 * @param string $type Type of profile ('user' or role name like 'driver', 'admin', etc.)
 * @param int $id ID of the user
 * @param array $userData Optional user data containing firstname and lastname
 * @return array Upload result with success status, message, and filename
 */
function uploadDocument($file, $type = 'user', $id = 0, $userData = null) {
    $result = [
        'success' => false,
        'message' => '',
        'filename' => ''
    ];
    
    // Validate upload
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $result['message'] = 'No file was uploaded.';
        return $result;
    }
    
    // Validate file type using both MIME type and extension
    $allowedTypes = [
        'application/pdf', 
        'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];
    
    $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx'];
    
    // Check MIME type
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        $result['message'] = 'Invalid file type. Only documents (PDF, DOC, DOCX, TXT, XLS, XLSX, PPT, PPTX) are allowed.';
        return $result;
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        $result['message'] = 'Invalid file extension. Only documents (PDF, DOC, DOCX, TXT, XLS, XLSX, PPT, PPTX) are allowed.';
        return $result;
    }
    
    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        $result['message'] = 'File is too large. Maximum size is 10MB.';
        return $result;
    }
    
    // Get the absolute path to the website root
    $docRoot = getWebsiteRoot();
    
    // Log important path information for debugging
    error_log("Document upload - Root: " . $docRoot);
    error_log("Document upload - Current directory: " . getcwd());
    
    // Normalize type to ensure it corresponds to a valid role folder
    $folderType = $type;
    // Map 'user' to the user's role if available in userData
    if ($type === 'user' && isset($userData['role'])) {
        $folderType = $userData['role'];
    }
    
    // Map role to folder name
    $validRoles = ['super_admin', 'admin', 'finance', 'dispatch', 'driver', 'customer'];
    if (in_array($folderType, $validRoles)) {
        $folderPrefix = $folderType . '_profiles';
    } else {
        // Default to 'user_profiles' if role is not recognized
        $folderPrefix = 'user_profiles';
    }
    
    // Set base upload directory with absolute path
    $baseUploadDir = $docRoot . 'uploads' . DIRECTORY_SEPARATOR . $folderPrefix . DIRECTORY_SEPARATOR;
    
    error_log("Document upload - Base directory: " . $baseUploadDir);
    
    // Create the base directory if it doesn't exist
    if (!file_exists($baseUploadDir)) {
        error_log("Base upload directory doesn't exist, creating: " . $baseUploadDir);
        if (!mkdir($baseUploadDir, 0777, true)) {
            $result['message'] = 'Failed to create base upload directory: ' . $baseUploadDir;
            return $result;
        }
    }
    
    // Create a personalized folder structure if user data is available
    if ($userData && isset($userData['firstname']) && isset($userData['lastname'])) {
        // Sanitize the firstname and lastname for folder name
        $firstname = preg_replace('/[^a-z0-9]/', '_', strtolower($userData['firstname']));
        $lastname = preg_replace('/[^a-z0-9]/', '_', strtolower($userData['lastname']));
        
        // Create folder name with firstname_lastname_id format
        $folderName = $firstname . '_' . $lastname . '_' . $id;
        $uploadDir = $baseUploadDir . $folderName . DIRECTORY_SEPARATOR;
        
        error_log("Document upload - User directory: " . $uploadDir);
        
        // Create the user-specific directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            error_log("User directory doesn't exist, creating: " . $uploadDir);
            if (!mkdir($uploadDir, 0777, true)) {
                $result['message'] = 'Failed to create user-specific directory: ' . $uploadDir;
                return $result;
            }
        }
        
        // Add a documents subfolder for organization
        $docsDir = $uploadDir . 'documents' . DIRECTORY_SEPARATOR;
        if (!file_exists($docsDir)) {
            error_log("Documents directory doesn't exist, creating: " . $docsDir);
            if (!mkdir($docsDir, 0777, true)) {
                $result['message'] = 'Failed to create documents directory: ' . $docsDir;
                return $result;
            }
        }
        
        // Generate filename with timestamp to ensure uniqueness and preserve original name
        $timestamp = time();
        $safeFilename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $filename = $safeFilename . '_' . $timestamp . '.' . $extension;
        
        // Make sure the filename doesn't already exist (extremely unlikely with timestamp)
        $targetPath = $docsDir . $filename;
        while (file_exists($targetPath)) {
            // If somehow exists, add a random number to ensure uniqueness
            $timestamp = time() . '_' . mt_rand(1000, 9999);
            $filename = $safeFilename . '_' . $timestamp . '.' . $extension;
            $targetPath = $docsDir . $filename;
        }
        
        // Define the relative path for database storage
        $relativeFilename = $folderPrefix . '/' . $folderName . '/documents/' . $filename;
    } else {
        // Standard path if user data is missing
        $docsDir = $baseUploadDir . 'documents' . DIRECTORY_SEPARATOR;
        if (!file_exists($docsDir)) {
            if (!mkdir($docsDir, 0777, true)) {
                $result['message'] = 'Failed to create documents directory: ' . $docsDir;
                return $result;
            }
        }
        
        // Generate a unique filename
        $timestamp = time();
        $safeFilename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $filename = 'doc_' . $id . '_' . $safeFilename . '_' . $timestamp . '.' . $extension;
        $targetPath = $docsDir . $filename;
        
        // Define the relative path for database storage
        $relativeFilename = $folderPrefix . '/documents/' . $filename;
    }
    
    error_log("Document upload - Target path: " . $targetPath);
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $result['success'] = true;
        $result['message'] = 'Document uploaded successfully.';
        
        // Store the path relative to uploads directory for database storage
        $result['filename'] = $relativeFilename;
        
        // Also return the full filepath relative to document root for debugging
        $result['filepath'] = str_replace($docRoot, '', $targetPath);
        
        // Log success
        error_log("Document uploaded successfully to: " . $targetPath);
        error_log("Relative path for database: " . $result['filename']);
    } else {
        $result['message'] = 'Failed to save the uploaded file. Target path: ' . $targetPath;
        error_log("Failed to upload document to: " . $targetPath);
        
        // Check if the directory is writable
        if (!is_writable(dirname($targetPath))) {
            $result['message'] .= ' Directory is not writable.';
            error_log("Directory is not writable: " . dirname($targetPath));
        }
        
        // Check if the tmp file exists and is readable
        if (!file_exists($file['tmp_name'])) {
            $result['message'] .= ' Temporary file does not exist.';
            error_log("Temporary file does not exist: " . $file['tmp_name']);
        } else if (!is_readable($file['tmp_name'])) {
            $result['message'] .= ' Temporary file is not readable.';
            error_log("Temporary file is not readable: " . $file['tmp_name']);
        }
    }
    
    return $result;
}

/**
 * Handle multiple file uploads
 * 
 * @param array $files $_FILES array for multiple file uploads
 * @param string $type Type of profile ('user' or role name)
 * @param int $id User ID
 * @param array $userData User data with firstname, lastname, and role
 * @return array Results of all uploads with success, messages, and file paths
 */
function handleMultipleUploads($files, $type = 'user', $id = 0, $userData = null) {
    $results = [
        'success' => true,
        'messages' => [],
        'files' => []
    ];
    
    // Check if multiple files are being uploaded
    if (is_array($files) && isset($files['name']) && is_array($files['name'])) {
        // Process each file individually
        foreach ($files['name'] as $key => $value) {
            // Skip empty file slots
            if (empty($files['name'][$key])) continue;
            
            // Create a file array structure for a single file
            $file = [
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            ];
            
            // Determine if this is a document or image based on mime type
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);
            
            $result = null;
            
            // Process as image or document based on mime type
            if (strpos($mimeType, 'image/') === 0) {
                $result = uploadProfileImage($file, $type, $id, $userData);
            } else {
                $result = uploadDocument($file, $type, $id, $userData);
            }
            
            // Store the result
            if ($result['success']) {
                $results['files'][] = $result;
                $results['messages'][] = "File '{$file['name']}' uploaded successfully.";
            } else {
                $results['success'] = false;
                $results['messages'][] = "Error uploading '{$file['name']}': " . $result['message'];
            }
        }
    } else {
        // Single file upload
        $fileType = isset($files['type']) ? $files['type'] : '';
        $result = null;
        
        if (strpos($fileType, 'image/') === 0) {
            $result = uploadProfileImage($files, $type, $id, $userData);
        } else {
            $result = uploadDocument($files, $type, $id, $userData);
        }
        
        if ($result['success']) {
            $results['files'][] = $result;
            $results['messages'][] = "File uploaded successfully.";
        } else {
            $results['success'] = false;
            $results['messages'][] = "Error uploading file: " . $result['message'];
        }
    }
    
    return $results;
}

/**
 * Get the URL for a user's profile image based on role and user details
 * 
 * @param int|array $user_id User ID or full user data array
 * @param string|null $role User role (optional if $user_id is an array)
 * @param string|null $firstname User first name (optional if $user_id is an array)
 * @param string|null $lastname User last name (optional if $user_id is an array)
 * @return string URL to the profile image or default image
 */
function getUserProfileImageUrl($user_id, $role = null, $firstname = null, $lastname = null) {
    // If first parameter is an array, extract values from it (legacy support)
    $user = [];
    if (is_array($user_id)) {
        $user = $user_id;
        // Log the legacy usage
        error_log("getUserProfileImageUrl called with legacy array parameter");
    } else {
        // Build user array from individual parameters
        $user = [
            'user_id' => $user_id,
            'role' => $role,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'profile_picture' => '' // No profile picture path provided
        ];
        error_log("getUserProfileImageUrl called with individual parameters: ID=$user_id, role=$role, name=$firstname $lastname");
    }
    
    // Log for debugging
    error_log("getUserProfileImageUrl processing user: " . 
             (isset($user['firstname']) ? $user['firstname'] : 'unknown') . " " . 
             (isset($user['lastname']) ? $user['lastname'] : 'unknown') . 
             " (ID: " . (isset($user['user_id']) ? $user['user_id'] : 'unknown') . 
             ", Role: " . (isset($user['role']) ? $user['role'] : 'unknown') . ")");
    
    // Get website root for consistent path resolution
    $websiteRoot = getWebsiteRoot();
    error_log("Website root for profile image: " . $websiteRoot);
             
    // Check if profile_picture path is already in database (full path stored)
    if (!empty($user['profile_picture'])) {
        // If the path already includes 'uploads/', use it directly
        if (strpos($user['profile_picture'], 'uploads/') === 0) {
            // Check if file exists
            if (file_exists($user['profile_picture'])) {
                error_log("Using existing profile_picture path: " . $user['profile_picture']);
                return $user['profile_picture'];
            } else if (file_exists($websiteRoot . $user['profile_picture'])) {
                // Try with website root prepended
                error_log("Using existing profile_picture with website root: " . $websiteRoot . $user['profile_picture']);
                return $user['profile_picture']; // Return without website root for URL consistency
            } else {
                error_log("File does not exist at stored path: " . $user['profile_picture']);
            }
        } else if (file_exists('uploads/' . $user['profile_picture'])) {
            // Try with 'uploads/' prefix
            error_log("Using profile_picture with uploads/ prefix: uploads/" . $user['profile_picture']);
            return 'uploads/' . $user['profile_picture'];
        } else if (file_exists($websiteRoot . 'uploads/' . $user['profile_picture'])) {
            // Try with website root and uploads/ prefix
            error_log("Using profile_picture with website root and uploads/ prefix");
            return 'uploads/' . $user['profile_picture']; // Return without website root for URL consistency
        }
    }
    
    // Make sure we have the required data
    if (empty($user['user_id']) || empty($user['role']) || 
        empty($user['firstname']) || empty($user['lastname'])) {
        error_log("Missing required user data for profile picture - defaulting to default image");
        return 'assets/img/default_user.jpg';
    }
    
    // If no existing path or file doesn't exist at path, generate role-based path
    $role = $user['role'];
    $userId = $user['user_id'];
    $firstname = preg_replace('/[^a-z0-9]/', '_', strtolower($user['firstname']));
    $lastname = preg_replace('/[^a-z0-9]/', '_', strtolower($user['lastname']));
    
    // Build the correct path based on role, firstname, lastname, and user_id
    // Format: public_html/uploads/{role}_profiles/{firstname}_{lastname}_{user_id}/
    $folderName = $firstname . '_' . $lastname . '_' . $userId;
    $rolePath = 'uploads/' . $role . '_profiles/';
    $userFolder = $rolePath . $folderName . '/';
    
    error_log("Looking for profile images in: " . $userFolder);
    
    // Try to find most recent image in the directory (Check both relative and absolute paths)
    if (is_dir($userFolder) || is_dir($websiteRoot . $userFolder)) {
        // Try to use relative path first, then fallback to absolute path with website root
        $directoryToUse = is_dir($userFolder) ? $userFolder : $websiteRoot . $userFolder;
        
        $files = glob($directoryToUse . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        if (!empty($files)) {
            // Sort by modified time (newest first)
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            // Return the most recent file (convert absolute path to relative URL if needed)
            $foundFile = $files[0];
            if (strpos($foundFile, $websiteRoot) === 0) {
                $foundFile = substr($foundFile, strlen($websiteRoot));
            }
            
            error_log("Found profile image: " . $foundFile);
            return $foundFile;
        } else {
            error_log("No profile images found in directory: " . $directoryToUse);
        }
    } else {
        error_log("User directory does not exist: " . $userFolder);
        
        // Check if the role directory exists and search there
        if (is_dir($rolePath) || is_dir($websiteRoot . $rolePath)) {
            // Use the correct path that exists
            $rolePathToUse = is_dir($rolePath) ? $rolePath : $websiteRoot . $rolePath;
            
            error_log("Looking for profile images in role directory: " . $rolePathToUse);
            
            // Try to find user ID-based files
            $files = glob($rolePathToUse . '*' . $userId . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
            if (!empty($files)) {
                // Sort by modified time (newest first)
                usort($files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                
                // Return the file (convert absolute path to relative URL if needed)
                $foundFile = $files[0];
                if (strpos($foundFile, $websiteRoot) === 0) {
                    $foundFile = substr($foundFile, strlen($websiteRoot));
                }
                
                error_log("Found profile image by user ID: " . $foundFile);
                return $foundFile;
            }
            
            // Try to find name-based files
            $namePattern = $firstname . '*' . $lastname . '*.{jpg,jpeg,png,gif}';
            $nameFiles = glob($rolePathToUse . $namePattern, GLOB_BRACE);
            if (!empty($nameFiles)) {
                // Sort by modified time (newest first)
                usort($nameFiles, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                
                // Return the file (convert absolute path to relative URL if needed)
                $foundFile = $nameFiles[0];
                if (strpos($foundFile, $websiteRoot) === 0) {
                    $foundFile = substr($foundFile, strlen($websiteRoot));
                }
                
                error_log("Found profile image by name: " . $foundFile);
                return $foundFile;
            }
        }
    }
    
    // Try a generic search in the role profiles directory
    $genericPath = 'uploads/' . $role . '_profiles/';
    if (is_dir($genericPath) || is_dir($websiteRoot . $genericPath)) {
        // Use the correct path that exists
        $genericPathToUse = is_dir($genericPath) ? $genericPath : $websiteRoot . $genericPath;
        
        // Try to find files with common profile image names
        $commonFilenames = [
            $genericPathToUse . 'profile_' . $userId . '.jpg',
            $genericPathToUse . 'profile_' . $userId . '.png',
            $genericPathToUse . 'avatar_' . $userId . '.jpg',
            $genericPathToUse . 'avatar_' . $userId . '.png',
            $genericPathToUse . $userId . '.jpg',
            $genericPathToUse . $userId . '.png',
            $genericPathToUse . $firstname . '_' . $lastname . '.jpg',
            $genericPathToUse . $firstname . '_' . $lastname . '.png'
        ];
        
        foreach ($commonFilenames as $filename) {
            if (file_exists($filename)) {
                // Convert absolute path to relative URL if needed
                $foundFile = $filename;
                if (strpos($foundFile, $websiteRoot) === 0) {
                    $foundFile = substr($foundFile, strlen($websiteRoot));
                }
                
                error_log("Found profile image with common filename: " . $foundFile);
                return $foundFile;
            }
        }
    }
    
    // If profile image not found with role-based path, try using getProfileImagePath function
    // This will check all possible locations and return the correct path or a default
    if (function_exists('getProfileImagePath')) {
        $userData = [
            'profile_picture' => $user['profile_picture'] ?? '',
            'firstname' => $user['firstname'],
            'lastname' => $user['lastname']
        ];
        
        if ($role === 'driver') {
            $driverData = [
                'profile_image' => $user['profile_picture'] ?? ''
            ];
            $imagePath = getProfileImagePath($userData, $driverData);
        } else {
            $imagePath = getProfileImagePath($userData);
        }
        
        if ($imagePath) {
            error_log("Found profile image via getProfileImagePath: " . $imagePath);
            return $imagePath;
        }
    }
    
    // Check for default image both relatively and absolutely
    $defaultImage = 'assets/img/default_user.jpg';
    if (file_exists($defaultImage) || file_exists($websiteRoot . $defaultImage)) {
        error_log("Using default image");
        return $defaultImage;
    }
    
    // Absolute fallback if the default image isn't found
    error_log("No profile image found and default image missing, using hardcoded path");
    return 'assets/img/default_user.jpg';
} 