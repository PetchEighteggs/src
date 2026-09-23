<?php
/** Lightweight fixed-window rate limiting for public API submissions. */
require_once dirname(__DIR__) . '/config/env.php';

function apiRateLimitClientIp(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
}

function apiRateLimitStorageDirectory(): string
{
    $configured = envValue('API_RATE_LIMIT_DIR');
    if (is_string($configured) && $configured !== '') {
        return rtrim($configured, '/\\');
    }
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
        . 'dgr-api-rate-limit-' . substr(hash('sha256', __DIR__), 0, 12);
}

/**
 * Consume one request from a fixed window.
 *
 * No IP address or submitted value is written to disk; only a keyed hash and
 * counters are stored. Storage failure is logged and fails open for uptime.
 */
function apiRateLimitConsume(string $scope, int $limit, int $windowSeconds): array
{
    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);
    $now = time();
    $fallback = [
        'allowed' => true,
        'limit' => $limit,
        'remaining' => $limit,
        'retry_after' => 0,
    ];

    try {
        $directory = apiRateLimitStorageDirectory();
        if (is_link($directory)) {
            throw new RuntimeException('Rate-limit directory cannot be a symbolic link');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create rate-limit directory');
        }

        $key = hash('sha256', $scope . "\0" . apiRateLimitClientIp());
        $path = $directory . DIRECTORY_SEPARATOR . $key . '.json';
        $handle = fopen($path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Unable to lock rate-limit state');
        }

        $raw = stream_get_contents($handle);
        $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($state) || (int)($state['reset_at'] ?? 0) <= $now) {
            $state = ['count' => 0, 'reset_at' => $now + $windowSeconds];
        }

        $allowed = (int)$state['count'] < $limit;
        if ($allowed) {
            $state['count'] = (int)$state['count'] + 1;
        }
        $remaining = max(0, $limit - (int)$state['count']);
        $retryAfter = max(1, (int)$state['reset_at'] - $now);

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        @chmod($path, 0600);

        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . $remaining);
        if (!$allowed) {
            header('Retry-After: ' . $retryAfter);
        }

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'remaining' => $remaining,
            'retry_after' => $allowed ? 0 : $retryAfter,
        ];
    } catch (Throwable $error) {
        error_log('[API Rate Limit] ' . $error->getMessage());
        return $fallback;
    }
}

function apiRateLimitReset(string $scope): void
{
    $directory = apiRateLimitStorageDirectory();
    if (is_link($directory)) {
        return;
    }
    $key = hash('sha256', $scope . "\0" . apiRateLimitClientIp());
    $path = $directory . DIRECTORY_SEPARATOR . $key . '.json';
    if (is_file($path)) {
        @unlink($path);
    }
}
