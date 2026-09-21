<?php
define('_VALID_ACCESS', 1);
session_start();
require_once('../../../configuration.php');
require_once('../../../config/ms_com.php');
require_once('../../../config/ms.class.php');
require_once('../../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../../login.php']);
bofSecurityRequireMethod(['POST']);
bofSecurityRequireCsrf();
$MS = new MS($DB);

$action = $_POST['action'] ?? '';
$deptID = $_SESSION['ActiveDeptID'] ?? 0;

if (empty($deptID)) {
    echo "<script>alert('Session Expired!'); window.location.href='../index.php';</script>";
    exit;
}

$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = ($userGroupID === 'A');
if (!$hasAccess && $userGroupID === 'S') {
    $permissionStaffID = (int)($_SESSION['StaffID'] ?? 0);
    require_once('../../includes/microsite_permission.php');
    if ($action === 'delete' || $action === 'bulk_delete') {
        $permRequired = 'CanDelete';
    } elseif ($action === 'bulk_active' || $action === 'bulk_inactive') {
        $permRequired = 'CanEdit';
    } else {
        $permRequired = !empty($_POST['id']) ? 'CanEdit' : 'CanAdd';
    }
    $hasAccess = checkMicrositeMenuPermission($DB, $permissionStaffID, $deptID, 'contact', $permRequired);
}
if (!$hasAccess) {
    http_response_code(403);
    echo "<script>alert('คุณไม่มีสิทธิ์จัดการข้อมูลนี้'); window.location.href='group.php';</script>";
    exit;
}

$audit = $MS->micrositeAuditContext();
$staffID = $audit['staff_id'];
$requestIP = $audit['ip'];

if ($action == 'save') {
    $id      = $_POST['id'] ?? 0;
    $nameTH  = $_POST['GroupNameTH'] ?? '';
    $nameEN  = $_POST['GroupNameEN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = intval($_POST['OrderNo'] ?? 1);

    try {
        if (!empty($id)) {
            $stmt = $DB->prepare("UPDATE microsite_contact_groups SET GroupNameTH=?, GroupNameEN=?, IsActive=?, OrderNo=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$nameTH, $nameEN, $isActive, $orderNo, $staffID, $requestIP, $id, $deptID]);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_contact_groups (DeptID, GroupNameTH, GroupNameEN, IsActive, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $nameTH, $nameEN, $isActive, $orderNo, $staffID, $requestIP]);
        }
        $MS->StaffLog((!empty($id) ? 'แก้ไข' : 'เพิ่ม') . 'หมวดหมู่ติดต่อ', $_SERVER['REQUEST_URI'], $staffID, '', 13, ($id ?: $DB->lastInsertId()));
        echo "<script>window.location.href='group.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }

} elseif ($action == 'delete') {
    $id = intval($_POST['id'] ?? 0);
    try {
        $stmt = $DB->prepare("UPDATE microsite_contact_groups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
        $MS->StaffLog('ลบหมวดหมู่ติดต่อ ID: ' . $id, $_SERVER['REQUEST_URI'], $staffID, '', 13, $id);
        echo "<script>alert('ลบข้อมูลเรียบร้อย'); window.location.href='group.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} else if (($action === 'bulk_delete' || $action === 'bulk_active' || $action === 'bulk_inactive') && !empty($_POST['ids'])) {
    try {
        $ids = explode(',', $_POST['ids']);
        $validIds = [];
        foreach ($ids as $val) {
            $val = (int)trim($val);
            if ($val > 0) $validIds[] = $val;
        }

        if (!empty($validIds)) {
            $validIds = array_values(array_unique($validIds));
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            
            if ($action === 'bulk_delete') {
                $stmt = $DB->prepare("UPDATE microsite_contact_groups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmt->execute(array_merge([$staffID, $requestIP, $staffID, $requestIP], $validIds, [$deptID]));
            } else if ($action === 'bulk_active') {
                $stmt = $DB->prepare("UPDATE microsite_contact_groups SET IsActive='Y', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmt->execute(array_merge([$staffID, $requestIP], $validIds, [$deptID]));
            } else if ($action === 'bulk_inactive') {
                $stmt = $DB->prepare("UPDATE microsite_contact_groups SET IsActive='N', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmt->execute(array_merge([$staffID, $requestIP], $validIds, [$deptID]));
            }
            
            $thaiActions = ['bulk_active' => 'เปิดใช้งานกลุ่มติดต่อหน่วยงานแบบกลุ่ม', 'bulk_inactive' => 'ระงับการใช้งานกลุ่มติดต่อหน่วยงานแบบกลุ่ม', 'bulk_delete' => 'ลบกลุ่มติดต่อหน่วยงานแบบกลุ่ม'];
            $logActionStr = $thaiActions[$action] ?? 'เปลี่ยนสถานะกลุ่มติดต่อหน่วยงานแบบกลุ่ม';
            $MS->StaffLog($logActionStr, $_SERVER['REQUEST_URI'], $staffID, '', 13);
        }
        header("Location: group.php");
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'group.php';</script>";
    }
}
?>
