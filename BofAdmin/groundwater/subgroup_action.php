<?php


define('_VALID_ACCESS', 1);
// session_start() is usually already active or called in config, but keeping it standard:
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../../configuration.php');
require_once('../../config/ms_com.php');
require_once('../../config/ms.class.php');
$DB = mosConnectPDO();
$MS = new MS($DB);


$staffID = $_SESSION['StaffID'] ?? 0;
$userGroupID = $_SESSION['GroupID'] ?? '';



$action = $_REQUEST['action'] ?? '';
$groupID = $_REQUEST['group_id'] ?? 0;

if (empty($groupID)) {
    header('Location: group.php');
    exit;
}

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
        $stmt = $DB->prepare("INSERT INTO groundwater_doc_subgroups (GroupID, SubGroupNameTH, SubGroupNameEN, OrderNo, IsActive, CreateDate, CreateBy, CreateIP) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([
            $groupID,
            $_POST['SubGroupNameTH'],
            $_POST['SubGroupNameEN'] ?? '',
            $_POST['OrderNo'] ?? 1,
            $_POST['IsActive'] ?? 'Y',
            $staffID,
            $_SERVER['REMOTE_ADDR']
        ]);
        $DB->commit();
        redirectBack($groupID, 'เพิ่มกลุ่มย่อยสำเร็จ');
        
    } elseif ($action === 'edit') {
        $stmt = $DB->prepare("UPDATE groundwater_doc_subgroups SET SubGroupNameTH=?, SubGroupNameEN=?, OrderNo=?, IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubGroupID=? AND GroupID=?");
        $stmt->execute([
            $_POST['SubGroupNameTH'],
            $_POST['SubGroupNameEN'] ?? '',
            $_POST['OrderNo'] ?? 1,
            $_POST['IsActive'] ?? 'Y',
            $staffID,
            $_SERVER['REMOTE_ADDR'],
            $_POST['id'],
            $groupID
        ]);
        $DB->commit();
        redirectBack($groupID, 'แก้ไขข้อมูลสำเร็จ');
        
    } elseif ($action === 'delete') {
        $stmt = $DB->prepare("DELETE FROM groundwater_doc_subgroups WHERE SubGroupID=? AND GroupID=?");
        $stmt->execute([$_GET['id'], $groupID]);
        $DB->commit();
        redirectBack($groupID, 'ลบข้อมูลสำเร็จ');
        
    } elseif (in_array($action, ['bulk_active', 'bulk_inactive', 'bulk_delete'])) {
        $ids = explode(',', $_GET['ids'] ?? '');
        $ids = array_filter(array_map('intval', $ids));
        
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($action === 'bulk_delete') {
                $params = $ids;
                $params[] = $groupID;
                $stmt = $DB->prepare("DELETE FROM groundwater_doc_subgroups WHERE SubGroupID IN ($placeholders) AND GroupID=?");
                $stmt->execute($params);
                $msg = 'ลบข้อมูลที่เลือกสำเร็จ';
            } else {
                $status = $action === 'bulk_active' ? 'Y' : 'N';
                $params = [$status, $staffID, $_SERVER['REMOTE_ADDR']];
                $params = array_merge($params, $ids);
                $params[] = $groupID;
                $stmt = $DB->prepare("UPDATE groundwater_doc_subgroups SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubGroupID IN ($placeholders) AND GroupID=?");
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
