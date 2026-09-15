<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
include_once('../../config/func.class.php'); 
$DB = mosConnectPDO();
$MS = new MS($DB);

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    header("Location: ../login.php");
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'save') {
    $positionID = $_POST['PositionID'] ?? 0;
    $deptID = $_POST['DeptID'] ?? 0;
    $nameTH = $_POST['Name_TH'] ?? '';
    $nameEN = $_POST['Name_EN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = $_POST['OrderNo'] !== '' ? (int)$_POST['OrderNo'] : 0;
    
    $adminId = $_SESSION['admin_id'] ?? 1;
    $ip = $_SERVER['REMOTE_ADDR'];

    try {
        if ($positionID > 0) {
            // Update
            $stmt = $DB->prepare("UPDATE org_positions SET 
                DeptID=?, Name_TH=?, Name_EN=?, IsActive=?, OrderNo=?, 
                UpdateDate=NOW(), UpdateBy=?, UpdateIP=? 
                WHERE PositionID=?");
            $stmt->execute([
                $deptID, $nameTH, $nameEN, $isActive, $orderNo, 
                $adminId, $ip, $positionID
            ]);
            $MS->StaffLog('แก้ไขข้อมูลตำแหน่งบุคลากร ID: ' . $positionID, $_SERVER['REQUEST_URI'], $adminId, 3, 10, $positionID);
        } else {
            // Insert
            // ถ้าไม่ได้ระบุ OrderNo จะให้เป็นค่า OrderNo สูงสุด + 1 ในหน่วยงานเดียวกัน
            if (empty($_POST['OrderNo']) && $_POST['OrderNo'] !== '0') {
                $stmtMax = $DB->prepare("SELECT MAX(OrderNo) FROM org_positions WHERE DeptID = ? AND DelData = 'N'");
                $stmtMax->execute([$deptID]);
                $maxOrder = $stmtMax->fetchColumn();
                $orderNo = $maxOrder ? $maxOrder + 1 : 1;
            }

            $stmt = $DB->prepare("INSERT INTO org_positions (
                DeptID, Name_TH, Name_EN, IsActive, OrderNo, 
                CreateDate, CreateBy, CreateIP) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
            $stmt->execute([
                $deptID, $nameTH, $nameEN, $isActive, $orderNo, 
                $adminId, $ip
            ]);
            $currentId = $DB->lastInsertId();
            $MS->StaffLog('เพิ่มข้อมูลตำแหน่งบุคลากร ID: ' . $currentId, $_SERVER['REQUEST_URI'], $adminId, 3, 10, $currentId);
        }

        header("Location: index.php?dept_id=" . $deptID);
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }

} elseif ($action === 'delete') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        $adminId = $_SESSION['admin_id'] ?? 1;
        $ip = $_SERVER['REMOTE_ADDR'];
        try {
            $stmt = $DB->prepare("UPDATE org_positions SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE PositionID=?");
            $stmt->execute([$adminId, $ip, $id]);
            
            $MS->StaffLog('ลบข้อมูลตำแหน่งบุคลากร ID: ' . $id, $_SERVER['REQUEST_URI'], $adminId, 3, 10, $id);
            
            // Redirect back with same filter if exists
            $ref = $_SERVER['HTTP_REFERER'] ?? 'index.php';
            header("Location: " . $ref);
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
} elseif (in_array($action, ['bulk_active', 'bulk_inactive', 'bulk_delete'])) {
    $ids = $_GET['ids'] ?? '';
    if (!empty($ids)) {
        $idArray = array_filter(explode(',', $ids), 'is_numeric');
        if (count($idArray) > 0) {
            $adminId = $_SESSION['admin_id'] ?? 1;
            $ip = $_SERVER['REMOTE_ADDR'];
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            
            try {
                if ($action === 'bulk_active') {
                    $stmt = $DB->prepare("UPDATE org_positions SET IsActive='Y', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE PositionID IN ($placeholders)");
                    $stmt->execute(array_merge([$adminId, $ip], $idArray));
                    $MS->StaffLog('เปิดใช้งานตำแหน่งบุคลากร (Bulk)', $_SERVER['REQUEST_URI'], $adminId, 3, 10);
                } else if ($action === 'bulk_inactive') {
                    $stmt = $DB->prepare("UPDATE org_positions SET IsActive='N', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE PositionID IN ($placeholders)");
                    $stmt->execute(array_merge([$adminId, $ip], $idArray));
                    $MS->StaffLog('ปิดใช้งานตำแหน่งบุคลากร (Bulk)', $_SERVER['REQUEST_URI'], $adminId, 3, 10);
                } else if ($action === 'bulk_delete') {
                    $stmt = $DB->prepare("UPDATE org_positions SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE PositionID IN ($placeholders)");
                    $stmt->execute(array_merge([$adminId, $ip], $idArray));
                    $MS->StaffLog('ลบตำแหน่งบุคลากร (Bulk)', $_SERVER['REQUEST_URI'], $adminId, 3, 10);
                }
            } catch (PDOException $e) {
                die("Error: " . $e->getMessage());
            }
        }
    }
    $ref = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: " . $ref);
    exit;
}
?>
