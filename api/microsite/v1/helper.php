<?php
// src/api/microsite/v1/helper.php
if (!defined('_VALID_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Send JSON response
 * 
 * @param array $data The data payload
 * @param int $code HTTP status code
 * @param string $message Response message
 * @param array|null $pagination Pagination metadata if any
 */
function sendResponse($data = [], $code = 200, $message = 'Success', $pagination = null) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    
    // CORS headers (Allow open access for now)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    $response = [
        'status' => ($code >= 200 && $code < 300) ? 'success' : 'error',
        'code' => $code,
        'message' => $message,
    ];

    if ($pagination !== null) {
        $response['num'] = (int)($pagination['total_items'] ?? 0);
    } elseif (is_array($data)) {
        // Distinguish between sequential arrays (lists) and associative arrays (objects)
        // Actually, just counting is fine. If it's a list, it counts items. If it's an object, it counts top-level keys, but often for a single entity we can just set num to 1.
        $isList = array_keys($data) === range(0, count($data) - 1);
        $response['num'] = $isList ? count($data) : 1;
    } else {
        $response['num'] = empty($data) ? 0 : 1;
    }

    if ($data !== null) {
        $response['data'] = $data;
    }

    if ($pagination !== null) {
        $response['pagination'] = $pagination;
    }

    array_walk_recursive($response, function(&$item) {
        if (is_string($item)) {
            $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
        }
    });

    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = json_encode([
            'status' => 'error',
            'code' => 500,
            'message' => 'JSON encoding error: ' . json_last_error_msg()
        ]);
    }
    echo $json;
    exit;
}

/**
 * Send Error response
 */
function sendError($message = 'An error occurred', $code = 400) {
    sendResponse(null, $code, $message);
}

/**
 * Resolve absolute URL for an image
 */
function getImageUrl($path, $slug = '', $type = '') {
    global $ImgPath;
    if (empty($path)) return '';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path; // already full URL
    
    if ($type === 'executives') {
        return $ImgPath . 'executives/' . basename($path);
    }
    
    // If slug is provided, it's typically inside microsite/{slug}/ directory
    if (!empty($slug)) {
        return $ImgPath . 'microsite/' . $slug . '/' . $path;
    }
    return $ImgPath . $path;
}

/**
 * Resolve absolute URL for a document/file
 */
function getFileUrl($path, $slug = '') {
    global $FilePath;
    if (empty($path)) return '';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    
    if (!empty($slug)) {
        return $FilePath . 'microsite/' . $slug . '/' . $path;
    }
    return $FilePath . $path;
}
