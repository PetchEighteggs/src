<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
include_once('../../config/func.class.php');
require_once('../includes/action_security.php');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
bofSecurityRequireMethod(['POST']);
bofSecurityRequireCsrf();
$requestedAction = $_POST['action'] ?? '';
$permissionByAction = [
    'add' => 'CanAdd',
    'edit' => 'CanEdit',
    'delete' => 'CanDelete',
    'bulk_delete' => 'CanDelete',
    'bulk_active' => 'CanEdit',
    'bulk_inactive' => 'CanEdit',
];
if (!isset($permissionByAction[$requestedAction])) {
    bofSecurityAbort(400, 'Invalid action');
}
bofSecurityRequireSectionPermission($DB, 'weblinks/index.php', $permissionByAction[$requestedAction]);
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);
$sectionStmt = $DB->prepare('SELECT SectionID FROM bof_section WHERE SectionURL = ? LIMIT 1');
$sectionStmt->execute(['weblinks/index.php']);
$weblinksSectionId = (int) $sectionStmt->fetchColumn();

$action = mosGetParam($_POST, 'action', '');
$id = mosGetParam($_POST, 'id', '');

if ($action === 'delete' && $id != '') {
    $stmt = $DB->prepare("UPDATE weblinks_groups SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ? WHERE GroupID = ?");
    $stmt->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $id]);
    $MS->StaffLog('ลบหมวดหมู่เว็บลิ้งค์', $_SERVER['REQUEST_URI'], $StaffID, 3, $weblinksSectionId, $id);
    $_SESSION['sys_msg'] = "ลบข้อมูลสำเร็จ";
    header("Location: group.php");
    exit;
} else if ($action == 'bulk_delete') {
    $ids = mosGetParam($_POST, 'ids', '');
    if (!empty($ids)) {
        $idArray = array_filter(explode(',', $ids), 'is_numeric');
        if (!empty($idArray)) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            $params = array_merge([$StaffID, $_SERVER['REMOTE_ADDR']], $idArray);
            $stmt = $DB->prepare("UPDATE weblinks_groups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE GroupID IN ($placeholders)");
            $stmt->execute($params);
            $MS->StaffLog('ลบหมวดหมู่เว็บลิ้งค์', $_SERVER['REQUEST_URI'], $StaffID, 3, $weblinksSectionId);
        }
    }
    header("Location: group.php");
    exit;
} else if (in_array($action, ['bulk_active', 'bulk_inactive'])) {
    $ids = mosGetParam($_POST, 'ids', '');
    if (!empty($ids)) {
        $idArray = array_filter(explode(',', $ids), 'is_numeric');
        if (!empty($idArray)) {
            $status = ($action === 'bulk_active') ? 'Y' : 'N';
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            $params = array_merge([$status, $StaffID, $_SERVER['REMOTE_ADDR']], $idArray);
            $stmt = $DB->prepare("UPDATE weblinks_groups SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders)");
            $stmt->execute($params);
            $MS->StaffLog('เปลี่ยนสถานะหมวดหมู่เว็บลิ้งค์', $_SERVER['REQUEST_URI'], $StaffID, 3, $weblinksSectionId);
        }
    }
    header("Location: group.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nameTH = mosGetParam($_POST, 'GroupNameTH', '');
    $nameEN = mosGetParam($_POST, 'GroupNameEN', '');
    $orderNo = mosGetParam($_POST, 'OrderNo', 999);
    $isActive = mosGetParam($_POST, 'IsActive', 'Y');

    if ($action === 'add') {
        $stmt = $DB->prepare("INSERT INTO weblinks_groups (GroupNameTH, GroupNameEN, OrderNo, IsActive, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, NOW(), ?, ?, 'N')");
        $stmt->execute([
            $nameTH, $nameEN, $orderNo, $isActive,
            $StaffID, $_SERVER['REMOTE_ADDR']
        ]);
        $newId = $DB->lastInsertId();
        $MS->StaffLog('เพิ่มหมวดหมู่เว็บลิ้งค์', $_SERVER['REQUEST_URI'], $StaffID, 3, $weblinksSectionId, $newId);
        $_SESSION['sys_msg'] = "เพิ่มข้อมูลสำเร็จ";
    } elseif ($action === 'edit' && $id != '') {
        $stmt = $DB->prepare("UPDATE weblinks_groups SET GroupNameTH = ?, GroupNameEN = ?, OrderNo = ?, IsActive = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE GroupID = ?");
        $stmt->execute([
            $nameTH, $nameEN, $orderNo, $isActive,
            $StaffID, $_SERVER['REMOTE_ADDR'], $id
        ]);
        $MS->StaffLog('แก้ไขหมวดหมู่เว็บลิ้งค์', $_SERVER['REQUEST_URI'], $StaffID, 3, $weblinksSectionId, $id);
        $_SESSION['sys_msg'] = "แก้ไขข้อมูลสำเร็จ";
    }

    header("Location: group.php");
    exit;
}

header("Location: group.php");
exit;
