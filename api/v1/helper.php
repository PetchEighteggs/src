<?php
// src/api/v1/helper.php
if (!defined('_VALID_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Send JSON response
 * 
 * @param array $data The data payload
 * @param int $code HTTP status code
 * @param string $message Response message
 */
function sendResponse($data = null, $code = 200, $message = 'Success') {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    
    // CORS headers
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if ($data !== null) {
        // Just return the raw data directly for v2 to maintain v1 backward compatibility in schema format
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } else {
        // Fallback for errors
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}

/**
 * Send Error response
 */
function sendError($message = 'An error occurred', $code = 400) {
    sendResponse(null, $code, $message);
}
/**
 * Dynamically replace domain paths in rich text content 
 * based on $_Config_live_site from configuration.php
 */
function processContentUrls($content) {
    if (empty($content)) return $content;
    
    global $_Config_live_site;
    $liveSite = rtrim($_Config_live_site, '/');
    
    // 1. Replace any absolute URLs (e.g., http://localhost:8076/uploads/...)
    $content = preg_replace(
        '/(src|href)=["\']http[s]?:\/\/[^\/]+\/uploads\//i',
        '$1="' . $liveSite . '/uploads/',
        $content
    );
    
    // 2. Replace any relative URLs (e.g., /uploads/...)
    $content = preg_replace(
        '/(src|href)=["\']\/uploads\//i',
        '$1="' . $liveSite . '/uploads/',
        $content
    );

    return $content;
}
?>
