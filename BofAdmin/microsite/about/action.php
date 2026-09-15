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
        $id = $_POST['id'] ?? 0;
        $permRequired = (!empty($id)) ? 'CanEdit' : 'CanAdd';
    }
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'about', $permRequired)) {
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
    $uploadDir = $MS->micrositeUploadDirectory($sServerDirFile , $deptSlug);
} catch (RuntimeException $e) {
    $MS->micrositeUploadFail($e->getMessage());
}

if ($action == 'save') {
    $id = $_POST['id'] ?? 0;
    $titleTH = $_POST['Title_TH'] ?? '';
    $titleEN = $_POST['Title_EN'] ?? '';
    $detailTH = $_POST['Detail_TH'] ?? '';
    $detailEN = $_POST['Detail_EN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = $_POST['OrderNo'] ?? 1;
    $groupID = $_POST['GroupID'] ?? 0;
    
    $imageName = '';
    $oldImageName = '';
    if ($id) {
			$stmt = $DB->prepare("SELECT Image FROM microsite_about WHERE AboutID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
			$stmt->execute([$id, $deptID]);
			$imageName = $stmt->fetchColumn();
            $oldImageName = $imageName ?: '';
    }
    
    // Handle Image Delete Flag
    $deleteImageFlag = $_POST['delete_image'] ?? '0';
    if ($deleteImageFlag == '1' && !empty($imageName)) {
        $imageName = '';
    }

    try {
        $newName = $MS->micrositeStoreUpload($_FILES['Image'] ?? [], $uploadDir, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'], $SetFileSize);
        if ($newName !== null) {
            $imageName = $newName;
        }
    } catch (RuntimeException $e) {
        $MS->micrositeUploadFail($e->getMessage());
    }

    try {
        if (!empty($id)) {
            $stmt = $DB->prepare("UPDATE microsite_about SET 
                GroupID = ?, Title_TH = ?, Title_EN = ?, Detail_TH = ?, Detail_EN = ?, 
                Image = ?, IsActive = ?, OrderNo = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ?
                WHERE AboutID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$groupID, $titleTH, $titleEN, $detailTH, $detailEN, $imageName, $isActive, $orderNo, $staffID, $requestIP, $id, $deptID]);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_about 
                (DeptID, GroupID, Title_TH, Title_EN, Detail_TH, Detail_EN, Image, IsActive, OrderNo, CreateDate, CreateBy, CreateIP, DelData)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $groupID, $titleTH, $titleEN, $detailTH, $detailEN, $imageName, $isActive, $orderNo, $staffID, $requestIP]);
        }
        if (($newName !== null || $deleteImageFlag === '1') && $oldImageName !== '' && $oldImageName !== $imageName && is_file($uploadDir . $oldImageName)) {
            @unlink($uploadDir . $oldImageName);
        }
        $logAction = (!empty($id) ? 'แก้ไขเกี่ยวกับหน่วยงาน ID: ' : 'เพิ่มเกี่ยวกับหน่วยงาน ID: ') . (!empty($id) ? $id : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 7, (!empty($id) ? $id : $DB->lastInsertId()));
        echo "<script>window.location.href='index.php';</script>";
    } catch (PDOException $e) {
        if ($newName !== null && is_file($uploadDir . $newName)) {
            @unlink($uploadDir . $newName);
        }
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    try {
        $stmt = $DB->prepare("UPDATE microsite_about SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE AboutID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
        $MS->StaffLog('ลบเกี่ยวกับหน่วยงาน ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 7, $id);
        echo "<script>window.location.href='index.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} elseif ($action == 'bulk_active' || $action == 'bulk_inactive' || $action == 'bulk_delete') {
    $ids = $_GET['ids'] ?? '';
    if (!empty($ids)) {
        $idArray = array_values(array_unique(array_filter(array_map('intval', explode(',', $ids)))));
        if (count($idArray) > 0) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            
            if ($action == 'bulk_active') {
                $params = array_merge([$staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_about SET IsActive = 'Y', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE AboutID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_inactive') {
                $params = array_merge([$staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_about SET IsActive = 'N', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE AboutID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_delete') {
                $params = array_merge([$staffID, $requestIP, $staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_about SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE AboutID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
                $MS->StaffLog('ลบเกี่ยวกับหน่วยงาน Bulk ID: ' . $ids, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 7, 0);
            }
        }
    }
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
?>
