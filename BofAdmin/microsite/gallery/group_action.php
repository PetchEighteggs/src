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
$staffID = $audit['staff_id'];
$requestIP = $audit['ip'];

if ($action == 'save') {
    $id = $_POST['id'] ?? 0;
    $nameTH = $_POST['GroupNameTH'] ?? '';
    $nameEN = $_POST['GroupNameEN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = $_POST['OrderNo'] ?? 1;

    try {
        if (!empty($id)) {
            $stmt = $DB->prepare("UPDATE microsite_gallery_groups SET GroupNameTH = ?, GroupNameEN = ?, IsActive = ?, OrderNo = ?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$nameTH, $nameEN, $isActive, $orderNo, $staffID, $requestIP, $id, $deptID]);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_gallery_groups (DeptID, GroupNameTH, GroupNameEN, IsActive, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $nameTH, $nameEN, $isActive, $orderNo, $staffID, $requestIP]);
        }
        $logAction = (!empty($id) ? 'แก้ไขหมวดหมู่ภาพกิจกรรม ID: ' : 'เพิ่มหมวดหมู่ภาพกิจกรรม ID: ') . (!empty($id) ? $id : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 12, (!empty($id) ? $id : $DB->lastInsertId()));
        echo "<script>window.location.href='group.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    try {
        $stmt = $DB->prepare("UPDATE microsite_gallery_groups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
        $MS->StaffLog('ลบหมวดหมู่ภาพกิจกรรม ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 12, $id);
        echo "<script>window.location.href='group.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('ไม่สามารถลบหมวดหมู่นี้ได้เนื่องจากมีรูปภาพเชื่อมโยงอยู่ หรือเกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} else if (($action === 'bulk_delete' || $action === 'bulk_active' || $action === 'bulk_inactive') && !empty($_GET['ids'])) {
    try {
        $ids = explode(',', $_GET['ids']);
        $validIds = [];
        foreach ($ids as $val) {
            $val = (int)trim($val);
            if ($val > 0) $validIds[] = $val;
        }

        if (!empty($validIds)) {
            $validIds = array_values(array_unique($validIds));
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            
            if ($action === 'bulk_delete') {
                $stmt = $DB->prepare("UPDATE microsite_gallery_groups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmt->execute(array_merge([$staffID, $requestIP, $staffID, $requestIP], $validIds, [$deptID]));
            } else if ($action === 'bulk_active') {
                $stmt = $DB->prepare("UPDATE microsite_gallery_groups SET IsActive='Y', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmt->execute(array_merge([$staffID, $requestIP], $validIds, [$deptID]));
            } else if ($action === 'bulk_inactive') {
                $stmt = $DB->prepare("UPDATE microsite_gallery_groups SET IsActive='N', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmt->execute(array_merge([$staffID, $requestIP], $validIds, [$deptID]));
            }
            
            $thaiActions = ['bulk_active' => 'เปิดใช้งานหมวดหมู่ภาพกิจกรรมแบบกลุ่ม', 'bulk_inactive' => 'ระงับการใช้งานหมวดหมู่ภาพกิจกรรมแบบกลุ่ม', 'bulk_delete' => 'ลบหมวดหมู่ภาพกิจกรรมแบบกลุ่ม'];
            $logActionStr = $thaiActions[$action] ?? 'เปลี่ยนสถานะหมวดหมู่ภาพกิจกรรมแบบกลุ่ม';
            $MS->StaffLog($logActionStr, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 12);
        }
        header("Location: group.php");
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'group.php';</script>";
    }
}
?>
