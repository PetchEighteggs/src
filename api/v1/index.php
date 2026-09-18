<?php
require_once 'config.php';
require_once 'helper.php';

// Handle CORS Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    setCorsHeaders();
    exit;
}

sendError('Invalid API endpoint. Please specify a valid endpoint.', 404);
?>