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

if (empty($deptID)) {
    echo "<script>alert('Session Expired!'); window.location.href='../index.php';</script>";
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
        $id = $_POST['ExecID'] ?? 0;
        $permRequired = (!empty($id)) ? 'CanEdit' : 'CanAdd';
    }
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'executives', $permRequired)) {
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

$stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID = ?");
$stmtSlug->execute([$deptID]);
$deptSlug = $stmtSlug->fetchColumn();

try {
    $uploadDir = $MS->micrositeUploadDirectory($sServerImgDir, $deptSlug);
} catch (RuntimeException $e) {
    $MS->micrositeUploadFail($e->getMessage());
}

if ($action == 'save') {
    $execID = $_POST['ExecID'] ?? 0;
    $nameTH = $_POST['NameTH'] ?? '';
    $nameEN = $_POST['NameEN'] ?? '';
    $positionTH = $_POST['PositionTH'] ?? '';
    $positionEN = $_POST['PositionEN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = $_POST['OrderNo'] ?? 0;
    
    $imageName = "";
    $oldImageName = "";
    if ($execID > 0) {
        $stmtImg = $DB->prepare("SELECT Image FROM microsite_executives WHERE ExecID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmtImg->execute([$execID, $deptID]);
        $imageName = $stmtImg->fetchColumn() ?: "";
        $oldImageName = $imageName;
    }
    $deleteImageFlag = ($_POST['delete_image'] ?? '0') === '1';
    if ($deleteImageFlag) {
        $imageName = '';
    }
    
    try {
        $newName = $MS->micrositeStoreUpload(
            $_FILES['Image'] ?? [],
            $uploadDir,
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            $SetMaxSize,
            true
        );
        if ($newName !== null) {
            $imageName = $newName;
        }
    } catch (RuntimeException $e) {
        $MS->micrositeUploadFail($e->getMessage());
    }

    try {
        if ($execID > 0) {
            $stmt = $DB->prepare("UPDATE microsite_executives SET NameTH = ?, NameEN = ?, PositionTH = ?, PositionEN = ?, Image = ?, IsActive = ?, OrderNo = ?, UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE ExecID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$nameTH, $nameEN, $positionTH, $positionEN, $imageName, $isActive, $orderNo, $staffID, $requestIP, $execID, $deptID]);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_executives (DeptID, NameTH, NameEN, PositionTH, PositionEN, Image, IsActive, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $nameTH, $nameEN, $positionTH, $positionEN, $imageName, $isActive, $orderNo, $staffID, $requestIP]);
        }
        if (($newName !== null || $deleteImageFlag) && $oldImageName !== '' && $oldImageName !== $imageName && is_file($uploadDir . $oldImageName)) {
            @unlink($uploadDir . $oldImageName);
        }
        $logAction = ($execID > 0 ? 'แก้ไขผู้บริหาร ID: ' : 'เพิ่มผู้บริหาร ID: ') . ($execID > 0 ? $execID : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 6, ($execID > 0 ? $execID : $DB->lastInsertId()));
        echo "<script>window.location.href='index.php';</script>";
    } catch (PDOException $e) {
        if ($newName !== null && is_file($uploadDir . $newName)) {
            @unlink($uploadDir . $newName);
        }
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        try {
            // Soft delete, do not unlink image since it might be used by another position/dept
            $stmt = $DB->prepare("UPDATE microsite_executives SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE ExecID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
            $MS->StaffLog('ลบผู้บริหาร ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 6, $id);
            echo "<script>window.location.href='index.php';</script>";
        } catch (PDOException $e) {
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='index.php';</script>";
        }
    }
} elseif ($action == 'bulk_active' || $action == 'bulk_inactive' || $action == 'bulk_delete') {
    $ids = $_GET['ids'] ?? '';
    if (!empty($ids)) {
        $idArray = array_values(array_unique(array_filter(array_map('intval', explode(',', $ids)))));
        if (count($idArray) > 0) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            
            if ($action == 'bulk_active') {
                $params = array_merge([$staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_executives SET IsActive = 'Y', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE ExecID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_inactive') {
                $params = array_merge([$staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_executives SET IsActive = 'N', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE ExecID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_delete') {
                // Soft delete, do not unlink images
                $params = array_merge([$staffID, $requestIP, $staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_executives SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE ExecID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
                $MS->StaffLog('ลบผู้บริหาร Bulk ID: ' . $ids, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 6, 0);
            }
        }
    }
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
?>
