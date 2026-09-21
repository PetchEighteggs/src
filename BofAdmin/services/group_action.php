<?php
define('_VALID_ACCESS', 1);
session_start();
require_once('../../configuration.php');
require_once('../../config/ms_com.php');
require_once('../../config/ms.class.php');
require_once('../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
bofSecurityRequireMethod(['POST']);
bofSecurityRequireCsrf();
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

$action = $_POST['action'] ?? '';


if ($action == 'save') {
    $id = $_POST['id'] ?? 0;
    $nameTH = $_POST['GroupNameTH'] ?? '';
    $nameEN = $_POST['GroupNameEN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = $_POST['OrderNo'] ?? 1;
    $serviceType = $_POST['ServiceType'] ?? 'situation';

    try {
        if (!empty($id)) {
            $stmt = $DB->prepare("UPDATE service_doc_groups SET GroupNameTH = ?, GroupNameEN = ?, IsActive = ?, OrderNo = ? WHERE GroupID = ?");
            $stmt->execute([$nameTH, $nameEN, $isActive, $orderNo, $id]);
        } else {
            $stmt = $DB->prepare("INSERT INTO service_doc_groups (GroupNameTH, GroupNameEN, ServiceType, IsActive, OrderNo, CreateDate) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nameTH, $nameEN, $serviceType, $isActive, $orderNo]);
        }
        $logAction = (!empty($id) ? 'แก้ไขหมวดหมู่ข้อมูลบริการ ID: ' : 'เพิ่มหมวดหมู่ข้อมูลบริการ ID: ') . (!empty($id) ? $id : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $StaffID, '', 10, (!empty($id) ? $id : $DB->lastInsertId()));
        echo "<script>window.location.href='group.php?type=".urlencode($serviceType)."';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} elseif ($action == 'delete') {
    $id = $_POST['id'] ?? 0;
    $serviceType = $_POST['type'] ?? 'situation';
    try {
        $stmt = $DB->prepare("DELETE FROM service_doc_groups WHERE GroupID = ?");
        $stmt->execute([$id]);
        $MS->StaffLog('ลบหมวดหมู่ข้อมูลบริการ ID: ' . $id, $_SERVER['REQUEST_URI'], $StaffID, '', 10, $id);
        echo "<script>alert('ลบข้อมูลเรียบร้อยแล้ว'); window.location.href='group.php?type=".urlencode($serviceType)."';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} else if (($action === 'bulk_delete' || $action === 'bulk_active' || $action === 'bulk_inactive') && !empty($_POST['ids'])) {
    $serviceType = $_POST['type'] ?? 'situation';
    try {
        $ids = explode(',', $_POST['ids']);
        $validIds = [];
        foreach ($ids as $val) {
            $val = (int)trim($val);
            if ($val > 0) $validIds[] = $val;
        }

        if (!empty($validIds)) {
            $inClause = implode(',', $validIds);
            
            if ($action === 'bulk_delete') {
                $del = $DB->query("DELETE FROM service_doc_groups WHERE GroupID IN ($inClause)");
            } elseif ($action === 'bulk_active') {
                $upd = $DB->query("UPDATE service_doc_groups SET IsActive = 'Y' WHERE GroupID IN ($inClause)");
            } elseif ($action === 'bulk_inactive') {
                $upd = $DB->query("UPDATE service_doc_groups SET IsActive = 'N' WHERE GroupID IN ($inClause)");
            }
            
            $thaiActions = ['bulk_active' => 'เปิดใช้งานหมวดหมู่ข้อมูลบริการ', 'bulk_inactive' => 'ระงับการใช้งานหมวดหมู่ข้อมูลบริการ', 'bulk_delete' => 'ลบหมวดหมู่ข้อมูลบริการ'];
            $logActionStr = $thaiActions[$action] ?? 'เปลี่ยนสถานะหมวดหมู่ข้อมูลบริการ';
            $MS->StaffLog($logActionStr, $_SERVER['REQUEST_URI'], $StaffID, '', 10);
        }
        header("Location: group.php?type=" . urlencode($serviceType));
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'group.php?type=".urlencode($serviceType)."';</script>";
    }
}
?>
