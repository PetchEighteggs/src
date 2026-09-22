<?php
/**
 * Shared security helpers for Backoffice actions.
 *
 * Phase 2A only provides reusable functions. No existing page is protected
 * until it explicitly includes this file and calls the relevant require
 * functions.
 *
 * HTML form page:
 *   require_once __DIR__ . '/../includes/action_security.php';
 *   echo bofSecurityCsrfField();
 *
 * Form action, after creating $DB:
 *   require_once __DIR__ . '/../includes/action_security.php';
 *   bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
 *   bofSecurityRequireMethod(['POST']);
 *   bofSecurityRequireCsrf();
 *
 * JSON actions should send the token in the X-CSRF-Token header and pass
 * ['response' => 'json'] to each require function.
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

if (!defined('BOF_SECURITY_CSRF_SESSION_KEY')) {
    define('BOF_SECURITY_CSRF_SESSION_KEY', '_bof_csrf');
}

if (!defined('BOF_SECURITY_CSRF_LIFETIME')) {
    define('BOF_SECURITY_CSRF_LIFETIME', 7200);
}

/**
 * Start or resume the Backoffice session with secure cookie defaults.
 */
function bofSecurityEnsureSession(): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }

    if (headers_sent()) {
        return false;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    return session_start([
        'use_strict_mode' => 1,
        'cookie_httponly' => 1,
        'cookie_secure' => $isHttps ? 1 : 0,
        'cookie_samesite' => 'Lax',
    ]);
}

/**
 * Decide whether the caller expects a JSON response.
 */
function bofSecurityWantsJson(): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    return strpos($accept, 'application/json') !== false
        || strpos($contentType, 'application/json') !== false
        || $requestedWith === 'xmlhttprequest';
}

/**
 * Write a security event without request bodies, credentials or CSRF tokens.
 */
function bofSecurityLogFailure(string $event): void
{
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $context = [
        'event' => $event,
        'staff_id' => (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0),
        'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'method' => strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')),
        'path' => is_string($path) ? $path : '',
    ];

    $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log('[BofAdmin Security] ' . ($encoded !== false ? $encoded : $event));
}

/**
 * Stop a request using either the existing browser flow or a JSON response.
 *
 * Supported options:
 * - response: auto|json|html
 * - login_url: Backoffice login URL used for HTTP 401 browser requests
 */
function bofSecurityAbort(int $status, string $message, array $options = []): void
{
    $responseType = (string)($options['response'] ?? 'auto');
    $sendJson = $responseType === 'json'
        || ($responseType === 'auto' && bofSecurityWantsJson());

    http_response_code($status);

    if ($sendJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => 'error',
            'code' => $status,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($status === 401 && !empty($options['login_url'])) {
        $loginUrl = str_replace(["\r", "\n"], '', (string)$options['login_url']);
        header('Location: ' . $loginUrl);
        exit;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="th"><meta charset="utf-8">'
        . '<title>Request denied</title><body><p>'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</p></body></html>';
    exit;
}

/**
 * Return true when the request method is in the explicit allowlist.
 */
function bofSecurityMethodAllowed(array $allowedMethods): bool
{
    $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
    $allowedMethods = array_map('strtoupper', $allowedMethods);

    return in_array($requestMethod, $allowedMethods, true);
}

/**
 * Enforce an HTTP method allowlist.
 */
function bofSecurityRequireMethod(array $allowedMethods, array $options = []): void
{
    if (bofSecurityMethodAllowed($allowedMethods)) {
        return;
    }

    $normalized = array_values(array_unique(array_map('strtoupper', $allowedMethods)));
    if ($normalized) {
        header('Allow: ' . implode(', ', $normalized));
    }

    bofSecurityLogFailure('method_not_allowed');
    bofSecurityAbort(405, (string)($options['message'] ?? 'Method not allowed'), $options);
}

/**
 * Return a stable per-session CSRF token, rotating it after its lifetime.
 */
function bofSecurityCsrfToken(bool $rotate = false): string
{
    if (!bofSecurityEnsureSession()) {
        throw new RuntimeException('Unable to start a session for CSRF protection.');
    }

    $stored = $_SESSION[BOF_SECURITY_CSRF_SESSION_KEY] ?? null;
    $isValid = is_array($stored)
        && isset($stored['token'], $stored['created_at'])
        && is_string($stored['token'])
        && strlen($stored['token']) === 64
        && (time() - (int)$stored['created_at']) < BOF_SECURITY_CSRF_LIFETIME;

    if ($rotate || !$isValid) {
        $stored = [
            'token' => bin2hex(random_bytes(32)),
            'created_at' => time(),
        ];
        $_SESSION[BOF_SECURITY_CSRF_SESSION_KEY] = $stored;
    }

    return $stored['token'];
}

/**
 * Render a hidden CSRF field for a regular HTML form.
 */
function bofSecurityCsrfField(string $fieldName = '_csrf_token'): string
{
    return sprintf(
        '<input type="hidden" name="%s" value="%s">',
        htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars(bofSecurityCsrfToken(), ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Render a meta tag that JavaScript can copy to the X-CSRF-Token header.
 */
function bofSecurityCsrfMeta(string $metaName = 'csrf-token'): string
{
    return sprintf(
        '<meta name="%s" content="%s">',
        htmlspecialchars($metaName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars(bofSecurityCsrfToken(), ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Obtain a CSRF token without consuming a JSON request body.
 */
function bofSecurityRequestCsrfToken(string $fieldName = '_csrf_token'): string
{
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
    if (is_string($headerToken) && $headerToken !== '') {
        return trim($headerToken);
    }

    $formToken = $_POST[$fieldName] ?? '';
    return is_string($formToken) ? trim($formToken) : '';
}

/**
 * Validate a submitted CSRF token using a timing-safe comparison.
 */
function bofSecurityValidateCsrf(?string $submittedToken = null, string $fieldName = '_csrf_token'): bool
{
    if (!bofSecurityEnsureSession()) {
        return false;
    }

    $stored = $_SESSION[BOF_SECURITY_CSRF_SESSION_KEY] ?? null;
    if (!is_array($stored) || !isset($stored['token'], $stored['created_at'])) {
        return false;
    }

    if ((time() - (int)$stored['created_at']) >= BOF_SECURITY_CSRF_LIFETIME) {
        unset($_SESSION[BOF_SECURITY_CSRF_SESSION_KEY]);
        return false;
    }

    $submittedToken = $submittedToken ?? bofSecurityRequestCsrfToken($fieldName);
    return is_string($stored['token'])
        && $submittedToken !== ''
        && hash_equals($stored['token'], $submittedToken);
}

/**
 * Enforce CSRF validation. JSON callers should use the X-CSRF-Token header.
 */
function bofSecurityRequireCsrf(
    ?string $submittedToken = null,
    string $fieldName = '_csrf_token',
    array $options = []
): void {
    if (bofSecurityValidateCsrf($submittedToken, $fieldName)) {
        return;
    }

    bofSecurityLogFailure('csrf_validation_failed');
    bofSecurityAbort(403, (string)($options['message'] ?? 'Invalid or expired security token'), $options);
}

/**
 * Validate the current Backoffice session against the staff table.
 */
function bofSecurityValidateStaffSession(PDO $db, bool $extendExpiry = false): bool
{
    if (!bofSecurityEnsureSession()) {
        return false;
    }

    if (
        empty($_SESSION['BofAdmin_LoggedIn'])
        || $_SESSION['BofAdmin_LoggedIn'] !== true
        || empty($_SESSION['StaffID'])
        || empty($_SESSION['StaffToken'])
    ) {
        return false;
    }

    try {
        $stmt = $db->prepare(
            'SELECT StaffTokenEXP FROM staff
             WHERE StaffID = ? AND StaffToken = ?
             LIMIT 1'
        );
        $stmt->execute([
            (int)$_SESSION['StaffID'],
            (string)$_SESSION['StaffToken'],
        ]);
        $expiresAt = $stmt->fetchColumn();

        if (!$expiresAt || strtotime((string)$expiresAt) <= time()) {
            return false;
        }

        if ($extendExpiry) {
            $newExpiry = date('Y-m-d H:i:s', time() + 7200);
            $update = $db->prepare(
                'UPDATE staff SET StaffTokenEXP = ?, LastAccess = NOW() WHERE StaffID = ?'
            );
            $update->execute([$newExpiry, (int)$_SESSION['StaffID']]);
        }

        return true;
    } catch (Throwable $e) {
        error_log('[BofAdmin Security] Session validation failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Enforce a valid Backoffice login and database-backed staff token.
 */
function bofSecurityRequireAuth(PDO $db, array $options = []): void
{
    if (bofSecurityValidateStaffSession($db, !empty($options['extend_expiry']))) {
        return;
    }

    bofSecurityLogFailure('authentication_failed');
    unset(
        $_SESSION['BofAdmin_LoggedIn'],
        $_SESSION['StaffID'],
        $_SESSION['admin_id'],
        $_SESSION['StaffToken']
    );

    $options['login_url'] = $options['login_url'] ?? '/BofAdmin/login.php?error=session_expired';
    bofSecurityAbort(401, (string)($options['message'] ?? 'Authentication required'), $options);
}

/**
 * Check a standard Backoffice section permission.
 */
function bofSecurityHasSectionPermission(PDO $db, string $sectionUrl, string $permission): bool
{
    if (($_SESSION['GroupID'] ?? '') === 'A') {
        return true;
    }

    if (($_SESSION['GroupID'] ?? '') !== 'S') {
        return false;
    }

    $allowedPermissions = ['CanView', 'CanAdd', 'CanEdit', 'CanDelete', 'CanApprove'];
    if (!in_array($permission, $allowedPermissions, true)) {
        return false;
    }

    $setGroupId = (int)($_SESSION['SetGroupID'] ?? 0);
    if ($setGroupId <= 0 || $sectionUrl === '') {
        return false;
    }

    try {
        $stmt = $db->prepare(
            "SELECT sp.`{$permission}`
             FROM staff_permission sp
             INNER JOIN bof_section bs ON bs.SectionID = sp.SectionID
             WHERE sp.SetGroupID = ?
               AND bs.SectionURL = ?
               AND bs.SectionShow = 'Y'
             LIMIT 1"
        );
        $stmt->execute([$setGroupId, $sectionUrl]);
        return $stmt->fetchColumn() === 'Y';
    } catch (Throwable $e) {
        error_log('[BofAdmin Security] Section permission check failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Enforce a standard Backoffice section permission.
 */
function bofSecurityRequireSectionPermission(
    PDO $db,
    string $sectionUrl,
    string $permission,
    array $options = []
): void {
    if (bofSecurityHasSectionPermission($db, $sectionUrl, $permission)) {
        return;
    }

    bofSecurityLogFailure('section_permission_denied');
    bofSecurityAbort(403, (string)($options['message'] ?? 'Permission denied'), $options);
}

/**
 * Check permission for a department-specific Microsite menu.
 */
function bofSecurityHasMicrositePermission(
    PDO $db,
    int $departmentId,
    string $menuKey,
    string $permission
): bool {
    if (($_SESSION['GroupID'] ?? '') === 'A') {
        return true;
    }

    if (($_SESSION['GroupID'] ?? '') !== 'S') {
        return false;
    }

    $allowedPermissions = ['CanView', 'CanAdd', 'CanEdit', 'CanDelete', 'CanApprove'];
    if (!in_array($permission, $allowedPermissions, true)) {
        return false;
    }

    $staffId = (int)($_SESSION['StaffID'] ?? 0);
    if ($staffId <= 0 || $departmentId <= 0 || $menuKey === '') {
        return false;
    }

    try {
        $stmt = $db->prepare(
            "SELECT smp.`{$permission}`
             FROM staff_microsite_menu_permission smp
             INNER JOIN microsite_menu_items mi ON mi.MenuID = smp.MenuID
             WHERE smp.StaffID = ?
               AND smp.DeptID = ?
               AND mi.MenuKey = ?
             LIMIT 1"
        );
        $stmt->execute([$staffId, $departmentId, $menuKey]);
        return $stmt->fetchColumn() === 'Y';
    } catch (Throwable $e) {
        error_log('[BofAdmin Security] Microsite permission check failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Enforce permission for a department-specific Microsite menu.
 */
function bofSecurityRequireMicrositePermission(
    PDO $db,
    int $departmentId,
    string $menuKey,
    string $permission,
    array $options = []
): void {
    if (bofSecurityHasMicrositePermission($db, $departmentId, $menuKey, $permission)) {
        return;
    }

    bofSecurityLogFailure('microsite_permission_denied');
    bofSecurityAbort(403, (string)($options['message'] ?? 'Permission denied'), $options);
}
