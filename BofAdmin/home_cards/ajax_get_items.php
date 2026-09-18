<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
require_once('../includes/action_security.php');

header('Content-Type: application/json; charset=utf-8');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['response' => 'json', 'login_url' => '../login.php']);
bofSecurityRequireMethod(['GET'], ['response' => 'json']);
if (($_SESSION['GroupID'] ?? '') === 'S') {
    $permStmt = $DB->prepare("SELECT sp.CanEdit FROM staff_permission sp
        INNER JOIN bof_section bs ON bs.SectionID=sp.SectionID
        WHERE sp.SetGroupID=? AND bs.SectionURL='home_cards/index.php' LIMIT 1");
    $permStmt->execute([(int)($_SESSION['SetGroupID'] ?? 0)]);
    if ($permStmt->fetchColumn() !== 'Y') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
}

try {
    $stmt = $DB->query("SELECT CardID, Title_TH, Description_TH, Slug, BgClass, Image, Direction, IsActive, OrderNo
        FROM home_cards WHERE DelData='N' ORDER BY OrderNo ASC, CardID DESC");
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('home_cards ajax_get_items: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
}
