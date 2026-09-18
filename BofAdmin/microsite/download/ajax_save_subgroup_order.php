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

$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = ($userGroupID === 'A');
$deptID = $_SESSION['ActiveDeptID'] ?? 0;

if (!$hasAccess && $userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $hasAccess = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'download', 'CanEdit');
}

if (!$hasAccess) {
    echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
    exit;
}

if (isset($data['order']) && is_array($data['order'])) {
    try {
        $DB->beginTransaction();
        $sql = "UPDATE microsite_download_subgroups SET OrderNo = ? WHERE SubGroupID = ?";
        $stmt = $DB->prepare($sql);
        foreach ($data['order'] as $item) {
            $stmt->execute([$item['order'], $item['id']]);
        }
        $DB->commit();
        $MS = new MS($DB);
        $MS->StaffLog('จัดเรียงลำดับหมวดหมู่ย่อยดาวน์โหลด', $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1);
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        $DB->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>
