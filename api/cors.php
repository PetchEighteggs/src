<?php
/** Shared CORS allowlist for API responses. */
require_once dirname(__DIR__) . '/config/env.php';

function apiCorsIsLocalHost(): bool
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function apiCorsAllowedOrigins(): array
{
    $origins = ['https://www2.dgr.go.th'];
    $configured = envValue('API_ALLOWED_ORIGINS');
    if (is_string($configured) && trim($configured) !== '') {
        foreach (explode(',', $configured) as $origin) {
            $origin = rtrim(trim($origin), '/');
            if (filter_var($origin, FILTER_VALIDATE_URL)) {
                $origins[] = $origin;
            }
        }
    }
    return array_values(array_unique($origins));
}

function apiCorsApply(array $methods = ['GET', 'POST', 'OPTIONS'], bool $allowCredentials = false): bool
{
    $origin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    $allowed = $origin === '' || in_array($origin, apiCorsAllowedOrigins(), true);
    if (!$allowed && apiCorsIsLocalHost()) {
        $allowed = preg_match('/\Ahttps?:\/\/(localhost|127\.0\.0\.1)(:\d+)?\z/D', $origin) === 1;
    }

    header('Vary: Origin');
    header('Access-Control-Allow-Methods: ' . implode(', ', array_unique($methods)));
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    if ($allowed && $origin !== '') {
        header('Access-Control-Allow-Origin: ' . $origin);
        if ($allowCredentials) {
            header('Access-Control-Allow-Credentials: true');
        }
    }
    return $allowed;
}

function apiCorsHandlePreflight(array $methods = ['GET', 'POST', 'OPTIONS'], bool $allowCredentials = false): void
{
    $allowed = apiCorsApply($methods, $allowCredentials);
    if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
        return;
    }
    if (!$allowed) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'code' => 403, 'message' => 'Origin not allowed']);
        exit;
    }
    http_response_code(204);
    exit;
}
