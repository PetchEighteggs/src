<?php
/** JWT helpers for the React member authentication API. */
require_once dirname(__DIR__, 2) . '/config/env.php';

function apiAuthEnvironmentValue(string $name): ?string
{
    $value = envValue($name);
    return is_string($value) && $value !== '' ? $value : null;
}

function apiAuthIsLocalRequest(): bool
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function apiAuthJwtSecret(): string
{
    static $resolvedSecret = null;
    if (is_string($resolvedSecret)) {
        return $resolvedSecret;
    }

    $secret = apiAuthEnvironmentValue('API_JWT_SECRET');
    if (is_string($secret) && strlen($secret) >= 32) {
        $resolvedSecret = $secret;
        return $resolvedSecret;
    }

    // Temporary compatibility for local development only. This derives a
    // local key from the already configured local database credential so no
    // reusable JWT key is committed. Production always fails closed.
    if (apiAuthIsLocalRequest()) {
        global $DBPassword;
        if (is_string($DBPassword ?? null) && $DBPassword !== '') {
            error_log('[API Auth] API_JWT_SECRET is missing; derived local JWT key is in use');
            $resolvedSecret = hash_hmac('sha256', 'dgr-api-local-jwt', $DBPassword);
            return $resolvedSecret;
        }
    }

    throw new RuntimeException('JWT signing key is not configured');
}

function apiAuthBase64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function apiAuthBase64UrlDecode(string $value): string
{
    if (!preg_match('/\A[A-Za-z0-9_-]+\z/D', $value)) {
        throw new InvalidArgumentException('Invalid JWT encoding');
    }
    $padding = strlen($value) % 4;
    if ($padding !== 0) {
        $value .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if ($decoded === false) {
        throw new InvalidArgumentException('Invalid JWT encoding');
    }
    return $decoded;
}

function apiAuthCreateJwt(array $claims): string
{
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    if (!isset($claims['iat'])) {
        $claims['iat'] = time();
    }
    if (!isset($claims['exp'])) {
        $claims['exp'] = time() + 86400;
    }

    $headerJson = json_encode($header, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $claimsJson = json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $encodedHeader = apiAuthBase64UrlEncode($headerJson);
    $encodedClaims = apiAuthBase64UrlEncode($claimsJson);
    $signingInput = $encodedHeader . '.' . $encodedClaims;
    $signature = hash_hmac('sha256', $signingInput, apiAuthJwtSecret(), true);

    return $signingInput . '.' . apiAuthBase64UrlEncode($signature);
}

function apiAuthValidateJwt(string $token): ?array
{
    $parts = explode('.', trim($token));
    if (count($parts) !== 3) {
        return null;
    }

    try {
        $header = json_decode(apiAuthBase64UrlDecode($parts[0]), true, 16, JSON_THROW_ON_ERROR);
        $claims = json_decode(apiAuthBase64UrlDecode($parts[1]), true, 32, JSON_THROW_ON_ERROR);
        $signature = apiAuthBase64UrlDecode($parts[2]);
        if (!is_array($header) || ($header['typ'] ?? '') !== 'JWT' || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }
        if (!is_array($claims) || empty($claims['user_id']) || !isset($claims['exp']) || (int)$claims['exp'] <= time()) {
            return null;
        }
        $expected = hash_hmac('sha256', $parts[0] . '.' . $parts[1], apiAuthJwtSecret(), true);
        return hash_equals($expected, $signature) ? $claims : null;
    } catch (Throwable $error) {
        error_log('[API Auth] JWT validation failed: ' . $error->getMessage());
        return null;
    }
}
