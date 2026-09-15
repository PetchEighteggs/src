<?php
// src/api/microsite/v1/config.php
define('_VALID_ACCESS', 1);

// Limit PHP execution time to prevent long-hanging requests
set_time_limit(15);

// Resolve relative paths to root (src directory)
$basePath = __DIR__ . '/../../../';

// Include global configuration
if (file_exists($basePath . 'configuration.php')) {
    require_once($basePath . 'configuration.php');
} else {
    die(json_encode(['status' => 'error', 'message' => 'Configuration file missing.']));
}

// Include database functions
if (file_exists($basePath . 'config/ms_com.php')) {
    require_once($basePath . 'config/ms_com.php');
}

// Ensure ImgPath and FilePath are globally available
global $ImgPath, $FilePath;

// Establish database connection
try {
    if (function_exists('mosConnectPDO')) {
        $DB = mosConnectPDO();
    } else {
        throw new Exception("Database connection function not found.");
    }
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'code' => 500,
        'message' => 'Database connection failed'
    ]));
}
