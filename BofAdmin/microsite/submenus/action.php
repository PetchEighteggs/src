<?php
define('_VALID_ACCESS', 1);
session_start();
require_once('../../../configuration.php');
require_once('../../../config/ms_com.php');
require_once('../../../config/ms.class.php');
$DB = mosConnectPDO();
$MS = new MS($DB);

$action = $_REQUEST['action'] ?? '';
$deptID = $_SESSION['ActiveDeptID'] ?? 0;

if (empty($deptID) || empty($action)) {
    header("Location: ../index.php");
    exit;
}

// Verify Access
$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = false;
if ($userGroupID === 'A') {
    $hasAccess = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    if ($action === 'delete' || $action === 'bulk_delete') {
        $permRequired = 'CanDelete';
    } else if ($action === 'bulk_active' || $action === 'bulk_inactive') {
        $permRequired = 'CanEdit';
    } else {
        $id = $_POST['SubMenuID'] ?? 0;
        $permRequired = (!empty($id)) ? 'CanEdit' : 'CanAdd';
    }
    // Checking against 'menus' module as submenus are part of menus
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'menus', $permRequired)) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์จัดการข้อมูลนี้'); window.location.href = 'index.php';</script>";
    exit;
}

$audit = $MS->micrositeAuditContext();
$staffID = $audit['staff_id'];
$requestIP = $audit['ip'];

if ($action == 'save') {
    $id = $_POST['SubMenuID'] ?? 0;
    $menuID = $_POST['MenuID'] ?? 0;
    $subMenuNameTH = $_POST['SubMenuNameTH'] ?? '';
    $subMenuNameEN = $_POST['SubMenuNameEN'] ?? '';
    $url = $_POST['URL'] ?? '';
    $target = '_self'; // Removed from UI
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = $_POST['OrderNo'] ?? 0;

    if ($id > 0) {
        // Update
        $sql = "UPDATE microsite_submenus SET MenuID=?, SubMenuNameTH=?, SubMenuNameEN=?, URL=?, Target=?, IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubMenuID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'";
        $stmt = $DB->prepare($sql);
        $stmt->execute([$menuID, $subMenuNameTH, $subMenuNameEN, $url, $target, $isActive, $staffID, $requestIP, $id, $deptID]);
    } else {
        // Insert
        $sql = "INSERT INTO microsite_submenus (DeptID, MenuID, SubMenuNameTH, SubMenuNameEN, URL, Target, IsActive, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')";
        $stmt = $DB->prepare($sql);
        $stmt->execute([$deptID, $menuID, $subMenuNameTH, $subMenuNameEN, $url, $target, $isActive, $orderNo, $staffID, $requestIP]);
    }

    $MS->StaffLog(($id > 0 ? 'Update' : 'Add') . ' SubMenu ID: ' . ($id > 0 ? $id : $DB->lastInsertId()), $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1);

    echo "<script>window.location.href='index.php';</script>";
}
else if ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        $stmt = $DB->prepare("UPDATE microsite_submenus SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubMenuID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
        $MS->StaffLog('Delete SubMenu ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1);
    }
    header("Location: index.php");
    exit;
}
else if ($action == 'bulk_active' || $action == 'bulk_inactive' || $action == 'bulk_delete') {
    $ids = $_GET['ids'] ?? '';
    if (!empty($ids)) {
        $idArray = array_values(array_unique(array_filter(array_map('intval', explode(',', $ids)))));
        if (count($idArray) > 0) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            
            if ($action == 'bulk_active') {
                $params = array_merge([$staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_submenus SET IsActive='Y', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubMenuID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'";
            } else if ($action == 'bulk_inactive') {
                $params = array_merge([$staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_submenus SET IsActive='N', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubMenuID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'";
            } else if ($action == 'bulk_delete') {
                $params = array_merge([$staffID, $requestIP, $staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_submenus SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubMenuID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'";
            }
            
            $stmt = $DB->prepare($sql);
            $stmt->execute($params);
            
            $logActionStr = str_replace('bulk_', 'Bulk ', $action);
            $MS->StaffLog("$logActionStr SubMenu IDs: $ids", $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1);
        }
    }
    header("Location: index.php");
    exit;
}
?>
