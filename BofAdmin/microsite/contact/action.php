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

if (empty($deptID) || empty($action)) {
    header("Location: index.php");
    exit;
}

// Verify Access
$userGroupID = $_SESSION['GroupID'] ?? '';
$canEdit = false; $canDelete = false; $canAdd = false;
if ($userGroupID === 'A') {
    $canEdit = $canDelete = $canAdd = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $canEdit   = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'contact', 'CanEdit');
    $canDelete = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'contact', 'CanDelete');
    $canAdd    = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'contact', 'CanAdd');
}

$audit = $MS->micrositeAuditContext();
$staffID = $audit['staff_id'];
$requestIP = $audit['ip'];

if ($action == 'save') {
    $id = (int)($_POST['id'] ?? 0);
    if (($id > 0 && !$canEdit) || ($id === 0 && !$canAdd)) {
        echo "<script>alert('คุณไม่มีสิทธิ์จัดการข้อมูลนี้'); window.location.href = 'index.php';</script>";
        exit;
    }
    $name    = $_POST['Name'] ?? '';
    $titleTH = $_POST['Title_TH'] ?? '';
    $titleEN = $_POST['Title_EN'] ?? '';
    $email   = $_POST['Email'] ?? '';
    $groupID = intval($_POST['GroupID'] ?? 0);
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = intval($_POST['OrderNo'] ?? 0);

    try {
        if (!empty($id)) {
            $stmt = $DB->prepare("UPDATE microsite_contact SET Name=?, Title_TH=?, Title_EN=?, Email=?, GroupID=?, IsActive=?, OrderNo=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE ContactID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$name, $titleTH, $titleEN, $email, $groupID, $isActive, $orderNo, $staffID, $requestIP, $id, $deptID]);
            $MS->StaffLog('แก้ไขข้อมูลติดต่อ ID: ' . $id, $_SERVER['REQUEST_URI'], $staffID, '', 13, $id);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_contact (DeptID, Name, Title_TH, Title_EN, Email, GroupID, IsActive, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $name, $titleTH, $titleEN, $email, $groupID, $isActive, $orderNo, $staffID, $requestIP]);
            $newId = $DB->lastInsertId();
            $MS->StaffLog('เพิ่มข้อมูลติดต่อ ID: ' . $newId, $_SERVER['REQUEST_URI'], $staffID, '', 13, $newId);
        }
        echo "<script>alert('บันทึกข้อมูลเรียบร้อย'); window.location.href = 'index.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }

} elseif ($action == 'delete') {
    if (!$canDelete) {
        echo "<script>alert('คุณไม่มีสิทธิ์ลบข้อมูล'); window.location.href = 'index.php';</script>";
        exit;
    }
    $id = intval($_POST['id'] ?? 0);
    try {
        $stmt = $DB->prepare("UPDATE microsite_contact SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE ContactID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
        $MS->StaffLog('ลบข้อมูลติดต่อ ID: ' . $id, $_SERVER['REQUEST_URI'], $staffID, '', 13, $id);
        echo "<script>alert('ลบข้อมูลเรียบร้อย'); window.location.href = 'index.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'index.php';</script>";
    }

} elseif ($action == 'bulk_delete') {
    if (!$canDelete) {
        echo "<script>alert('คุณไม่มีสิทธิ์ลบข้อมูล'); window.location.href = 'index.php';</script>";
        exit;
    }
    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$staffID, $requestIP, $staffID, $requestIP], $ids, [$deptID]);
        $DB->prepare("UPDATE microsite_contact SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE ContactID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'")->execute($params);
        $MS->StaffLog('ลบข้อมูลติดต่อ (bulk) ' . count($ids) . ' รายการ', $_SERVER['REQUEST_URI'], $staffID, '', 13, 0);
    }
    echo "<script>alert('ลบข้อมูลเรียบร้อย " . count($ids) . " รายการ'); window.location.href = 'index.php';</script>";

} elseif ($action == 'bulk_active' || $action == 'bulk_inactive') {
    if (!$canEdit) {
        echo "<script>alert('คุณไม่มีสิทธิ์แก้ไขข้อมูล'); window.location.href = 'index.php';</script>";
        exit;
    }
    $isActive = ($action == 'bulk_active') ? 'Y' : 'N';
    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$isActive, $staffID, $requestIP], $ids, [$deptID]);
        $DB->prepare("UPDATE microsite_contact SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE ContactID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'")->execute($params);
    }
    echo "<script>window.location.href = 'index.php';</script>";
}
?>
