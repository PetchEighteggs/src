<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include_once('../../../configuration.php');
include_once('../../../config/ms_com.php');
include_once('../../../config/ms.class.php');
include_once('../../../config/func.class.php');

header('Content-Type: application/json');

$DB = mosConnectPDO();
if (!$DB) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}

$deptID = $_SESSION['ActiveDeptID'] ?? 0;

// Get JSON POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (empty($data['order']) || !is_array($data['order'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data format']);
    exit;
}

// Check Permissions
$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = false;
if ($userGroupID === 'A') {
    $hasAccess = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'menus', 'CanEdit')) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์จัดการข้อมูลนี้']);
    exit;
}

try {
    $DB->beginTransaction();
    
    $stmt = $DB->prepare("UPDATE microsite_menus SET OrderNo = ? WHERE MenuID = ? AND DeptID = ?");
    
    foreach ($data['order'] as $item) {
        $id = $item['id'] ?? 0;
        $orderNo = $item['order'] ?? 99;
        
        if ($id > 0) {
            $stmt->execute([$orderNo, $id, $deptID]);
        }
    }
    
    $DB->commit();
    
    $MS = new MS($DB);
    $MS->StaffLog('จัดเรียงลำดับเมนูหลัก', $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1);
    
    echo json_encode(['status' => 'success', 'message' => 'Order updated successfully']);
    
} catch (PDOException $e) {
    $DB->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
