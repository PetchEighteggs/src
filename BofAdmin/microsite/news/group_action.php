<?php
define('_VALID_ACCESS', 1);
session_start();
require_once('../../../configuration.php');
require_once('../../../config/ms_com.php');
require_once('../../../config/ms.class.php');
require_once('../../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../../login.php']);
$MS = new MS($DB);

$action = $_REQUEST['action'] ?? '';
$deptID = $_SESSION['ActiveDeptID'] ?? 0;

if (empty($deptID)) {
    echo "<script>alert('Session Expired!'); window.location.href='../index.php';</script>";
    exit;
}
$audit = $MS->micrositeAuditContext();
$auditStaffID = $audit['staff_id'];
$auditIP = $audit['ip'];

if ($action == 'save') {
    $id = $_POST['id'] ?? 0;
    $nameTH = $_POST['GroupNameTH'] ?? '';
    $nameEN = $_POST['GroupNameEN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = $_POST['OrderNo'] ?? 1;

    try {
        if (!empty($id)) {
            $stmt = $DB->prepare("UPDATE microsite_news_groups SET GroupNameTH = ?, GroupNameEN = ?, IsActive = ?, OrderNo = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE GroupID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$nameTH, $nameEN, $isActive, $orderNo, $auditStaffID, $auditIP, $id, $deptID]);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_news_groups (DeptID, GroupNameTH, GroupNameEN, IsActive, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $nameTH, $nameEN, $isActive, $orderNo, $auditStaffID, $auditIP]);
        }
        $logAction = (!empty($id) ? 'แก้ไขหมวดหมู่ข่าวประชาสัมพันธ์ ID: ' : 'เพิ่มหมวดหมู่ข่าวประชาสัมพันธ์ ID: ') . (!empty($id) ? $id : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 8, (!empty($id) ? $id : $DB->lastInsertId()));
        echo "<script>window.location.href='group.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    try {
        $stmt = $DB->prepare("UPDATE microsite_news_groups SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE GroupID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$auditStaffID, $auditIP, $auditStaffID, $auditIP, $id, $deptID]);
        $MS->StaffLog('ลบหมวดหมู่ข่าวประชาสัมพันธ์ ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 8, $id);
        echo "<script>window.location.href='group.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} elseif ($action == 'bulk_active' || $action == 'bulk_inactive' || $action == 'bulk_delete') {
    $ids = $_GET['ids'] ?? '';
    if (!empty($ids)) {
        $idArray = array_map('intval', explode(',', $ids));
        if (count($idArray) > 0) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            
            if ($action == 'bulk_active') {
                $params = array_merge([$auditStaffID, $auditIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_news_groups SET IsActive = 'Y', UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE GroupID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_inactive') {
                $params = array_merge([$auditStaffID, $auditIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_news_groups SET IsActive = 'N', UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE GroupID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_delete') {
                $params = array_merge([$auditStaffID, $auditIP, $auditStaffID, $auditIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_news_groups SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE GroupID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
                $MS->StaffLog('ลบหมวดหมู่ข่าวประชาสัมพันธ์ Bulk ID: ' . $ids, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 8, 0);
            }
        }
    }
    header("Location: group.php");
    exit;
}
?>
