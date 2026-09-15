<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('../../configuration.php');
include_once('../../config/ms_com.php');

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$DB = mosConnectPDO();
if (($_SESSION['GroupID'] ?? '') === 'S') {
    $permStmt = $DB->prepare("SELECT sp.CanEdit FROM staff_permission sp
        INNER JOIN bof_section bs ON bs.SectionID=sp.SectionID
        WHERE sp.SetGroupID=? AND bs.SectionURL='csr_stats/index.php' LIMIT 1");
    $permStmt->execute([(int)($_SESSION['SetGroupID'] ?? 0)]);
    if ($permStmt->fetchColumn() !== 'Y') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
}

try {
    $stmt = $DB->query("SELECT StatID, Title_TH, ValueText, SubValueText, Unit_TH, IsActive, OrderNo
        FROM csr_stats WHERE DelData='N' ORDER BY OrderNo ASC, StatID DESC");
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('csr_stats ajax_get_items: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
}
