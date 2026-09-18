<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
require_once('../includes/action_security.php');

header('Content-Type: application/json; charset=utf-8');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['response' => 'json', 'login_url' => '../login.php']);
bofSecurityRequireMethod(['POST'], ['response' => 'json']);
bofSecurityRequireCsrf(null, '_csrf_token', ['response' => 'json']);
$MS = new MS($DB);
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

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || empty($payload['order']) || !is_array($payload['order'])) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

try {
    $DB->beginTransaction();
    $stmt = $DB->prepare("UPDATE home_cards SET OrderNo=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE CardID=? AND DelData='N'");
    $staffId = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);
    $staffIp = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach ($payload['order'] as $position => $item) {
        $id = (int)($item['id'] ?? 0);
        if ($id > 0) {
            $stmt->execute([$position + 1, $staffId, $staffIp, $id]);
        }
    }
    $DB->commit();

    $sectionStmt = $DB->query("SELECT SectionID, SectionGroupID FROM bof_section WHERE SectionURL='home_cards/index.php' LIMIT 1");
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC) ?: ['SectionID' => 0, 'SectionGroupID' => 4];
    $MS->StaffLog('จัดเรียงลำดับการ์ดหน้าหลัก', $_SERVER['REQUEST_URI'], $staffId, (int)$section['SectionGroupID'], (int)$section['SectionID']);
    echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    if ($DB->inTransaction()) {
        $DB->rollBack();
    }
    error_log('home_cards ajax_save_order: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
}
