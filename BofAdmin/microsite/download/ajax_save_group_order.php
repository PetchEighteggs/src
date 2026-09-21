<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once('../../../configuration.php');
include_once('../../../config/ms_com.php');
include_once('../../../config/ms.class.php');
require_once('../../includes/action_security.php');
header('Content-Type: application/json; charset=utf-8');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['response' => 'json', 'login_url' => '../../login.php']);
bofSecurityRequireMethod(['POST'], ['response' => 'json']);
bofSecurityRequireCsrf(null, '_csrf_token', ['response' => 'json']);
$data = json_decode(file_get_contents('php://input'), true);

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = ($userGroupID === 'A');
if (!$hasAccess && $userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'download', 'CanEdit')) $hasAccess = true;
}
if (!$hasAccess) { echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์จัดการข้อมูลนี้']); exit; }

if (isset($data['order']) && is_array($data['order'])) {
    try {
        $DB->beginTransaction();
        $stmt = $DB->prepare("UPDATE microsite_download_groups SET OrderNo = ? WHERE GroupID = ? AND DeptID = ?");
        foreach ($data['order'] as $item) {
            if ($item['id'] != 0) {
                $stmt->execute([$item['order'], $item['id'], $deptID]);
            }
        }
        $DB->commit();
        $MS = new MS($DB);
        $MS->StaffLog('จัดเรียงลำดับหมวดหมู่ดาวน์โหลดเอกสาร', $_SERVER['REQUEST_URI'], (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0));
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        $DB->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>
