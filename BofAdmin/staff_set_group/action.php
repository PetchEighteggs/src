<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/func.class.php');
include_once('../../config/ms.class.php');
require_once('../includes/action_security.php');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
bofSecurityRequireMethod(['POST']);
bofSecurityRequireCsrf();
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);
$action = mosGetParam($_POST, 'action', '');
$id = mosGetParam($_POST, 'id', '');

$SetGroupName = mosGetParam($_POST, 'SetGroupName', '');
$SetOrder = 999;
$Approved = mosGetParam($_POST, 'Approved', 'Y');
$currentUserId = $StaffID;
$ip = $_SERVER['REMOTE_ADDR'];

try {
    if ($action == 'add') {
        $stmt = $DB->prepare("INSERT INTO staff_set_group (SetGroupName, SetOrder, Approved, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, NOW(), ?, ?, 'N')");
        $stmt->execute([$SetGroupName, $SetOrder, $Approved, $currentUserId, $ip]);
        $setId = $DB->lastInsertId();
        
        if (isset($_POST['perm']) && is_array($_POST['perm'])) {
            $stmtPerm = $DB->prepare("INSERT INTO staff_permission (SectionID, SetGroupID, CanApprove, CanAdd, CanEdit, CanDelete) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['perm'] as $sId => $p) {
                $canView = isset($p['view']) ? 'Y' : 'N';
                $canAdd = isset($p['add']) ? 'Y' : 'N';
                $canEdit = isset($p['edit']) ? 'Y' : 'N';
                $canDel = isset($p['del']) ? 'Y' : 'N';
                $stmtPerm->execute([$sId, $setId, $canView, $canAdd, $canEdit, $canDel]);
            }
        }
        
        $MS->StaffLog('เพิ่มกลุ่มผู้ใช้งาน', $_SERVER['REQUEST_URI'], $StaffID, 4, 11, $setId);
        header("Location: index.php");
        exit;
    } 
    else if ($action == 'edit' && $id != '') {
        $stmt = $DB->prepare("UPDATE staff_set_group SET SetGroupName = ?, SetOrder = ?, Approved = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE SetGroupID = ?");
        $stmt->execute([$SetGroupName, $SetOrder, $Approved, $currentUserId, $ip, $id]);
        
        $DB->prepare("DELETE FROM staff_permission WHERE SetGroupID = ?")->execute([$id]);
        if (isset($_POST['perm']) && is_array($_POST['perm'])) {
            $stmtPerm = $DB->prepare("INSERT INTO staff_permission (SectionID, SetGroupID, CanApprove, CanAdd, CanEdit, CanDelete) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['perm'] as $sId => $p) {
							$canView = isset($p['view']) ? 'Y' : 'N';
							$canAdd = isset($p['add']) ? 'Y' : 'N';
							$canEdit = isset($p['edit']) ? 'Y' : 'N';
							$canDel = isset($p['del']) ? 'Y' : 'N';
							$stmtPerm->execute([$sId, $id, $canView, $canAdd, $canEdit, $canDel]);
            }
        }
        
        $MS->StaffLog('แก้ไขกลุ่มผู้ใช้งาน', $_SERVER['REQUEST_URI'], $StaffID, 4, 11, $id);
        header("Location: index.php");
        exit;
    } 
    else if ($action == 'delete' && $id != '') {
        $stmt = $DB->prepare("UPDATE staff_set_group SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ? WHERE SetGroupID = ?");
        $stmt->execute([$currentUserId, $ip, $id]);
        
        $MS->StaffLog('ลบกลุ่มผู้ใช้งาน', $_SERVER['REQUEST_URI'], $StaffID, 4, 11, $id);
        header("Location: index.php");
        exit;
    }
    else if ($action == 'bulk_delete') {
        $ids = mosGetParam($_POST, 'ids', '');
        if (!empty($ids)) {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$currentUserId, $ip], $idArray);
                $stmt = $DB->prepare("UPDATE staff_set_group SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE SetGroupID IN ($placeholders)");
                $stmt->execute($params);
                $MS->StaffLog('ลบกลุ่มผู้ใช้งาน', $_SERVER['REQUEST_URI'], $StaffID, 4, 11);
            }
        }
        header("Location: index.php");
        exit;
    } else if (in_array($action, ['bulk_active', 'bulk_inactive'])) {
        $ids = mosGetParam($_POST, 'ids', '');
        if (!empty($ids)) {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $status = ($action === 'bulk_active') ? 'Y' : 'N';
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$status, $currentUserId, $ip], $idArray);
                $stmt = $DB->prepare("UPDATE staff_set_group SET Approved=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SetGroupID IN ($placeholders)");
                $stmt->execute($params);
                $logStr = ($action === 'bulk_active') ? 'เปิดใช้งาน' : 'ระงับการใช้งาน';
                $MS->StaffLog('เปลี่ยนสถานะกลุ่มผู้ใช้งาน', $_SERVER['REQUEST_URI'], $StaffID, 4, 11);
            }
        }
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

header("Location: index.php");
exit;
