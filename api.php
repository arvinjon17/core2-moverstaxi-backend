<?php
/**
 * API Router
 * This file routes API requests to the appropriate endpoint
 */

// Get the requested resource and action
$resource = $_GET['resource'] ?? '';
$action = $_GET['action'] ?? '';

// Forward the request to the API index
require_once __DIR__ . '/api/index.php'; 