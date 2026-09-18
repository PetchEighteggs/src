<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) { echo json_encode(['status' => 'error']); exit; }
include_once('../../../configuration.php');
include_once('../../../config/ms_com.php');
include_once('../../../config/ms.class.php');
header('Content-Type: application/json');
$DB = mosConnectPDO();
$data = json_decode(file_get_contents('php://input'), true);

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = ($userGroupID === 'A');
if (!$hasAccess && $userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'multimedia', 'CanEdit')) $hasAccess = true;
}
if (!$hasAccess) { echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์จัดการข้อมูลนี้']); exit; }

if (isset($data['order']) && is_array($data['order'])) {
    try {
        $DB->beginTransaction();
        $stmt = $DB->prepare("UPDATE microsite_multimedia SET OrderNo = ? WHERE MediaID = ? AND DeptID = ?");
        foreach ($data['order'] as $item) {
            $stmt->execute([$item['order'], $item['id'], $deptID]);
        }
        $DB->commit();
        $MS = new MS($DB);
        $MS->StaffLog('จัดเรียงลำดับรายการสื่อ', $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1);
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        $DB->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>
