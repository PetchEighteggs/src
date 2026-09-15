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

    try {
        if (!empty($id)) {
            $stmt = $DB->prepare("UPDATE faq_groups SET GroupNameTH = ?, GroupNameEN = ?, IsActive = ?, OrderNo = ? WHERE GroupID = ?");
            $stmt->execute([$nameTH, $nameEN, $isActive, $orderNo, $id]);
        } else {
            $stmt = $DB->prepare("INSERT INTO faq_groups (GroupNameTH, GroupNameEN, IsActive, OrderNo, CreateDate) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$nameTH, $nameEN, $isActive, $orderNo]);
        }
        $logAction = (!empty($id) ? 'แก้ไขหมวดหมู่คำถามที่พบบ่อย ID: ' : 'เพิ่มหมวดหมู่คำถามที่พบบ่อย ID: ') . (!empty($id) ? $id : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, (!empty($id) ? $id : $DB->lastInsertId()));
        echo "<script>window.location.href='group.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    try {
        $stmt = $DB->prepare("DELETE FROM faq_groups WHERE GroupID = ?");
        $stmt->execute([$id]);
        $MS->StaffLog('ลบหมวดหมู่คำถามที่พบบ่อย ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, $id);
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
            $inClause = implode(',', $validIds);
            
            if ($action === 'bulk_delete') {
                $del = $DB->query("DELETE FROM faq_groups WHERE GroupID IN ($inClause)");
            } elseif ($action === 'bulk_active') {
                $upd = $DB->query("UPDATE faq_groups SET IsActive = 'Y' WHERE GroupID IN ($inClause)");
            } elseif ($action === 'bulk_inactive') {
                $upd = $DB->query("UPDATE faq_groups SET IsActive = 'N' WHERE GroupID IN ($inClause)");
            }
            
            $thaiActions = ['bulk_active' => 'เปิดใช้งานหมวดหมู่คำถามที่พบบ่อยแบบกลุ่ม', 'bulk_inactive' => 'ระงับการใช้งานหมวดหมู่คำถามที่พบบ่อยแบบกลุ่ม', 'bulk_delete' => 'ลบหมวดหมู่คำถามที่พบบ่อยแบบกลุ่ม'];
            $logActionStr = $thaiActions[$action] ?? 'เปลี่ยนสถานะหมวดหมู่คำถามที่พบบ่อยแบบกลุ่ม';
            $MS->StaffLog($logActionStr, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10);
        }
        header("Location: group.php");
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'group.php';</script>";
    }
}
?>