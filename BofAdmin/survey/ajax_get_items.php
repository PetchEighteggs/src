<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
require_once('../includes/action_security.php');

header('Content-Type: application/json; charset=utf-8');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['response' => 'json', 'login_url' => '../login.php']);
bofSecurityRequireMethod(['GET'], ['response' => 'json']);
bofSecurityRequireSectionPermission($DB, 'survey/index.php', 'CanEdit', ['response' => 'json']);

try {
    $stmt = $DB->prepare("
        SELECT FormID, FormName, Description, IsActive, OrderNo
        FROM survey_form
        ORDER BY OrderNo ASC, FormID DESC
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $items
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . bofSecurityPublicErrorMessage($e)
    ]);
}
