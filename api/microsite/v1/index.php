<?php
// src/api/microsite/v1/index.php
require_once 'config.php';
require_once 'helper.php';

// Handle CORS Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

sendError('Invalid API endpoint. Please specify a valid endpoint.', 404);
