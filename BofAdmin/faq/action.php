<?php
define('_VALID_ACCESS', 1);
session_start();
require_once('../../configuration.php');
require_once('../../config/ms_com.php');
require_once('../../config/ms.class.php');
require_once('../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

$action = $_REQUEST['action'] ?? '';



function formatToYMD($dateStr) {
    if (empty($dateStr)) return null;
    $parts = explode('/', $dateStr);
    if (count($parts) === 3) {
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return $dateStr;
}

if ($action == 'save') {
    $downloadID = $_POST['DocID'] ?? 0;
    $groupID = $_POST['GroupID'] ?? 0;
    $SubGroupID = 0;
    $titleTH = $_POST['TitleTH'] ?? '';
    $titleEN = $_POST['TitleEN'] ?? '';
    $detailTH = $_POST['DetailTH'] ?? '';
    $detailEN = $_POST['DetailEN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = isset($_POST['OrderNo']) ? $_POST['OrderNo'] : null;
    
    try {
        $adminId = $StaffID;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if ($downloadID > 0) {
            if ($orderNo === null) {
                $stmtOrder = $DB->prepare("SELECT OrderNo FROM faq WHERE DocID = ?");
                $stmtOrder->execute([$downloadID]);
                $orderNo = (int)$stmtOrder->fetchColumn();
            }
            $stmt = $DB->prepare("UPDATE faq SET GroupID = ?, TitleTH = ?, TitleEN = ?, DetailTH = ?, DetailEN = ?, IsActive = ?, OrderNo = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE DocID = ?");
            $stmt->execute([$groupID, $titleTH, $titleEN, $detailTH, $detailEN, $isActive, $orderNo, $adminId, $ip, $downloadID]);
        } else {
            if (empty($orderNo)) {
                $stmtMax = $DB->prepare("SELECT MAX(OrderNo) FROM faq");
                $stmtMax->execute();
                $maxOrder = $stmtMax->fetchColumn();
                $orderNo = $maxOrder ? $maxOrder + 1 : 1;
            }
            $stmt = $DB->prepare("INSERT INTO faq (GroupID, TitleTH, TitleEN, DetailTH, DetailEN, IsActive, OrderNo, CreateDate, CreateBy, CreateIP, UpdateDate, UpdateBy, UpdateIP, DelData) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$groupID, $titleTH, $titleEN, $detailTH, $detailEN, $isActive, $orderNo, $adminId, $ip, $adminId, $ip]);
        }
        $logAction = ($downloadID > 0 ? 'แก้ไขคำถามที่พบบ่อย ID: ' : 'เพิ่มคำถามที่พบบ่อย ID: ') . ($downloadID > 0 ? $downloadID : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $adminId, '', 10, ($downloadID > 0 ? $downloadID : $DB->lastInsertId()));
        echo "<script>window.location.href='index.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        try {
            $adminId = $StaffID;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $stmt = $DB->prepare("UPDATE faq SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ? WHERE DocID = ?");
            $stmt->execute([$adminId, $ip, $id]);
            $MS->StaffLog('ลบคำถามที่พบบ่อย ID: ' . $id, $_SERVER['REQUEST_URI'], $adminId, '', 10, $id);
            echo "<script>window.location.href='index.php';</script>";
        } catch (PDOException $e) {
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='index.php';</script>";
        }
    }
} elseif ($action == 'bulk_active' || $action == 'bulk_inactive' || $action == 'bulk_delete') {
    $ids = $_GET['ids'] ?? '';
    if (!empty($ids)) {
        $idArray = array_map('intval', explode(',', $ids));
        if (count($idArray) > 0) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            $adminId = $StaffID;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            
            if ($action == 'bulk_active') {
                $params = array_merge([$adminId, $ip], $idArray);
                $sql = "UPDATE faq SET IsActive = 'Y', UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE DocID IN ($placeholders)";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_inactive') {
                $params = array_merge([$adminId, $ip], $idArray);
                $sql = "UPDATE faq SET IsActive = 'N', UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE DocID IN ($placeholders)";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_delete') {
                $params = array_merge([$adminId, $ip], $idArray);
                $sqlDel = "UPDATE faq SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ? WHERE DocID IN ($placeholders)";
                $stmtDel = $DB->prepare($sqlDel);
                $stmtDel->execute($params);
                $MS->StaffLog('ลบประกอบกิจการน้ำบาดาล Bulk ID: ' . $ids, $_SERVER['REQUEST_URI'], $adminId, '', 10, 0);
            }
        }
    }
    header("Location: index.php");
    exit;
}
?>
