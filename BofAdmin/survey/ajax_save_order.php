<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
require_once('../includes/action_security.php');

header('Content-Type: application/json; charset=utf-8');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['response' => 'json', 'login_url' => '../login.php']);
bofSecurityRequireMethod(['POST'], ['response' => 'json']);
bofSecurityRequireCsrf(null, '_csrf_token', ['response' => 'json']);
bofSecurityRequireSectionPermission($DB, 'survey/index.php', 'CanEdit', ['response' => 'json']);

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['order']) || !is_array($data['order'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

$MS = new MS($DB);

try {
    $DB->beginTransaction();

    $stmt = $DB->prepare("UPDATE survey_form SET OrderNo = :order WHERE FormID = :id");

    foreach ($data['order'] as $item) {
        if (!empty($item['id']) && isset($item['order'])) {
            $stmt->execute([
                ':order' => (int)$item['order'],
                ':id' => (int)$item['id']
            ]);
        }
    }

    $DB->commit();

    if (method_exists($MS, 'StaffLog')) {
        $MS->StaffLog('จัดเรียงลำดับ แบบสอบถาม', $_SERVER['REQUEST_URI'], (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0), 2, 107, 0);
    }

    echo json_encode(['status' => 'success', 'message' => 'บันทึกการจัดเรียงลำดับเรียบร้อยแล้ว']);
} catch (PDOException $e) {
    if ($DB->inTransaction()) {
        $DB->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . bofSecurityPublicErrorMessage($e)
    ]);
}
