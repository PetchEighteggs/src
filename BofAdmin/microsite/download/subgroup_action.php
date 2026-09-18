<?php


define('_VALID_ACCESS', 1);
// session_start() is usually already active or called in config, but keeping it standard:
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../../../configuration.php');
require_once('../../../config/ms_com.php');
require_once('../../../config/ms.class.php');
require_once('../../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../../login.php']);
$MS = new MS($DB);

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
$staffID = $_SESSION['StaffID'] ?? 0;
$userGroupID = $_SESSION['GroupID'] ?? '';

if (empty($deptID)) {
    header('Location: ../index.php');
    exit;
}

$action = $_REQUEST['action'] ?? '';
$groupID = $_REQUEST['group_id'] ?? 0;

if (empty($groupID)) {
    header('Location: group.php');
    exit;
}

$groupCheck = $DB->prepare("SELECT COUNT(*) FROM microsite_download_groups WHERE GroupID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
$groupCheck->execute([$groupID, $deptID]);
if ((int)$groupCheck->fetchColumn() === 0) {
    header('Location: group.php');
    exit;
}

$audit = $MS->micrositeAuditContext();
$staffID = $audit['staff_id'];
$requestIP = $audit['ip'];

// Redirect helper
function redirectBack($groupId, $msg = '', $type = 'success') {
    if ($msg) {
        $_SESSION['flash_message'] = $msg;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: subgroup.php?group_id=$groupId");
    exit;
}

try {
    $DB->beginTransaction();
    
    if ($action === 'add') {
        $stmt = $DB->prepare("INSERT INTO microsite_download_subgroups (GroupID, SubGroupNameTH, SubGroupNameEN, OrderNo, IsActive, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
        $stmt->execute([
            $groupID,
            $_POST['SubGroupNameTH'],
            $_POST['SubGroupNameEN'] ?? '',
            $_POST['OrderNo'] ?? 1,
            $_POST['IsActive'] ?? 'Y',
            $staffID,
            $requestIP
        ]);
        $DB->commit();
        redirectBack($groupID, 'เพิ่มกลุ่มย่อยสำเร็จ');
        
    } elseif ($action === 'edit') {
        $stmt = $DB->prepare("UPDATE microsite_download_subgroups SET SubGroupNameTH=?, SubGroupNameEN=?, OrderNo=?, IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubGroupID=? AND GroupID=? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([
            $_POST['SubGroupNameTH'],
            $_POST['SubGroupNameEN'] ?? '',
            $_POST['OrderNo'] ?? 1,
            $_POST['IsActive'] ?? 'Y',
            $staffID,
            $requestIP,
            $_POST['id'],
            $groupID
        ]);
        $DB->commit();
        redirectBack($groupID, 'แก้ไขข้อมูลสำเร็จ');
        
    } elseif ($action === 'delete') {
        $stmt = $DB->prepare("UPDATE microsite_download_subgroups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubGroupID=? AND GroupID=? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $_GET['id'], $groupID]);
        $DB->commit();
        redirectBack($groupID, 'ลบข้อมูลสำเร็จ');
        
    } elseif (in_array($action, ['bulk_active', 'bulk_inactive', 'bulk_delete'])) {
        $ids = explode(',', $_GET['ids'] ?? '');
        $ids = array_filter(array_map('intval', $ids));
        
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($action === 'bulk_delete') {
                $params = [$staffID, $requestIP, $staffID, $requestIP];
                $params = array_merge($params, $ids);
                $params[] = $groupID;
                $stmt = $DB->prepare("UPDATE microsite_download_subgroups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubGroupID IN ($placeholders) AND GroupID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmt->execute($params);
                $msg = 'ลบข้อมูลที่เลือกสำเร็จ';
            } else {
                $status = $action === 'bulk_active' ? 'Y' : 'N';
                $params = [$status, $staffID, $requestIP];
                $params = array_merge($params, $ids);
                $params[] = $groupID;
                $stmt = $DB->prepare("UPDATE microsite_download_subgroups SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubGroupID IN ($placeholders) AND GroupID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmt->execute($params);
                $msg = 'อัปเดตสถานะสำเร็จ';
            }
            $DB->commit();
            redirectBack($groupID, $msg);
        }
        redirectBack($groupID);
    }
    
} catch (PDOException $e) {
    $DB->rollBack();
    redirectBack($groupID, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), 'error');
}
