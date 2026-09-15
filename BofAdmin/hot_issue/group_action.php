<?php
define('_VALID_ACCESS', 1);
session_start();
require_once('../../configuration.php');
require_once('../../config/ms_com.php');
require_once('../../config/ms.class.php');
$DB = mosConnectPDO();
$MS = new MS($DB);

$action = $_REQUEST['action'] ?? '';




if ($action == 'save') {
    $id = $_POST['id'] ?? 0;
    $nameTH = $_POST['GroupNameTH'] ?? '';
    $nameEN = $_POST['GroupNameEN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = $_POST['OrderNo'] ?? 1;
    $staffID = $_SESSION['admin_id'] ?? 1;
    $ip = $_SERVER['REMOTE_ADDR'];

    try {
        if (!empty($id)) {
            $stmt = $DB->prepare("UPDATE hot_issue_group SET GroupNameTH=?, GroupNameEN=?, OrderNo=?, IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID=?");
            $stmt->execute([$nameTH, $nameEN, $orderNo, $isActive, $staffID, $ip, $id]);
        } else {
            $stmt = $DB->prepare("INSERT INTO hot_issue_group (GroupNameTH, GroupNameEN, OrderNo, IsActive, CreateDate, CreateBy, CreateIP) VALUES (?, ?, ?, ?, NOW(), ?, ?)");
            $stmt->execute([$nameTH, $nameEN, $orderNo, $isActive, $staffID, $ip]);
        }
        $logAction = (!empty($id) ? 'แก้ไขหมวดหมู่ Hot Issue ID: ' : 'เพิ่มหมวดหมู่ Hot Issue ID: ') . (!empty($id) ? $id : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, (!empty($id) ? $id : $DB->lastInsertId()));
        echo "<script>window.location.href='group.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    try {
        $stmt = $DB->prepare("UPDATE hot_issue_group SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE GroupID=?");
        $stmt->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id]);
        $MS->StaffLog('ลบหมวดหมู่ Hot Issue ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, $id);
        echo "<script>alert('ลบข้อมูลเรียบร้อยแล้ว'); window.location.href='group.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
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
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            $staffID = $_SESSION['admin_id'] ?? 1;
            $ip = $_SERVER['REMOTE_ADDR'];
            
            if ($action === 'bulk_delete') {
                $stmt = $DB->prepare("UPDATE hot_issue_group SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE GroupID IN ($placeholders)");
                $stmt->execute(array_merge([$staffID, $ip], $validIds));
            } elseif ($action === 'bulk_active') {
                $stmt = $DB->prepare("UPDATE hot_issue_group SET IsActive='Y', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders)");
                $stmt->execute(array_merge([$staffID, $ip], $validIds));
            } elseif ($action === 'bulk_inactive') {
                $stmt = $DB->prepare("UPDATE hot_issue_group SET IsActive='N', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders)");
                $stmt->execute(array_merge([$staffID, $ip], $validIds));
            }
            
            $thaiActions = ['bulk_active' => 'เปิดใช้งานหมวดหมู่ Hot Issue แบบกลุ่ม', 'bulk_inactive' => 'ระงับการใช้งานหมวดหมู่ Hot Issue แบบกลุ่ม', 'bulk_delete' => 'ลบหมวดหมู่ Hot Issue แบบกลุ่ม'];
            $logActionStr = $thaiActions[$action] ?? 'เปลี่ยนสถานะหมวดหมู่ Hot Issue แบบกลุ่ม';
            $MS->StaffLog($logActionStr, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10);
        }
        header("Location: group.php");
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'group.php';</script>";
    }
}
?>
