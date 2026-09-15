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
        $id = $_POST['BannerID'] ?? 0;
        $permRequired = (!empty($id)) ? 'CanEdit' : 'CanAdd';
    }
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'banner', $permRequired)) {
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

function formatToYMD($dateStr) {
    if (empty($dateStr)) return null;
    $parts = explode('/', $dateStr);
    if (count($parts) === 3) {
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return $dateStr;
}

$stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID = ?");
$stmtSlug->execute([$deptID]);
$deptSlug = $stmtSlug->fetchColumn();

try {
    $uploadDir = $MS->micrositeUploadDirectory($sServerImgDir, $deptSlug);
} catch (RuntimeException $e) {
    $MS->micrositeUploadFail($e->getMessage());
}

if ($action == 'save') {
    $bannerID = $_POST['BannerID'] ?? 0;
    $groupID = $_POST['GroupID'] ?? 0;
    $titleTH = $_POST['Title_TH'] ?? '';
    $titleEN = $_POST['Title_EN'] ?? '';
    $urlTH = $_POST['URL_TH'] ?? '';
    $urlEN = $_POST['URL_EN'] ?? '';
    $target = $_POST['Target'] ?? '_blank';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = $_POST['OrderNo'] ?? 0;
    $detailTH = $_POST['Detail_TH'] ?? '';
    $detailEN = $_POST['Detail_EN'] ?? '';
    $startDate = !empty($_POST['StartDate']) ? formatToYMD($_POST['StartDate']) . ' 00:00:00' : null;
    $endDate = !empty($_POST['EndDate']) ? formatToYMD($_POST['EndDate']) . ' 23:59:59' : null;
    
    $imageName = "";
    $oldImageName = "";
    if ($bannerID > 0) {
        $stmtImg = $DB->prepare("SELECT Image FROM microsite_banner WHERE BannerID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmtImg->execute([$bannerID, $deptID]);
        $imageName = $stmtImg->fetchColumn() ?: "";
        $oldImageName = $imageName;
    }
    $deleteImageFlag = ($_POST['delete_image'] ?? '0') === '1';
    if ($deleteImageFlag) {
        $imageName = '';
    }

    try {
        $newName = $MS->micrositeStoreUpload($_FILES['Image'] ?? [], $uploadDir, ['jpg', 'jpeg', 'png', 'gif', 'webp'], $SetMaxSize, true);
        if ($newName !== null) {
            $imageName = $newName;
        }
    } catch (RuntimeException $e) {
        $MS->micrositeUploadFail($e->getMessage());
    }

    try {
        if ($bannerID > 0) {
            $stmt = $DB->prepare("UPDATE microsite_banner SET GroupID = ?, Title_TH = ?, Title_EN = ?, Detail_TH = ?, Detail_EN = ?, Image = ?, URL_TH = ?, URL_EN = ?, Target = ?, IsActive = ?, OrderNo = ?, StartDate = ?, EndDate = ?, UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE BannerID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$groupID, $titleTH, $titleEN, $detailTH, $detailEN, $imageName, $urlTH, $urlEN, $target, $isActive, $orderNo, $startDate, $endDate, $staffID, $requestIP, $bannerID, $deptID]);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_banner (DeptID, GroupID, Title_TH, Title_EN, Detail_TH, Detail_EN, Image, URL_TH, URL_EN, Target, IsActive, OrderNo, StartDate, EndDate, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $groupID, $titleTH, $titleEN, $detailTH, $detailEN, $imageName, $urlTH, $urlEN, $target, $isActive, $orderNo, $startDate, $endDate, $staffID, $requestIP]);
        }
        if (($newName !== null || $deleteImageFlag) && $oldImageName !== '' && $oldImageName !== $imageName && is_file($uploadDir . $oldImageName)) {
            @unlink($uploadDir . $oldImageName);
        }
        $logAction = ($bannerID > 0 ? 'แก้ไขช่องทางประชาสัมพันธ์ (Banner) ID: ' : 'เพิ่มช่องทางประชาสัมพันธ์ (Banner) ID: ') . ($bannerID > 0 ? $bannerID : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 9, ($bannerID > 0 ? $bannerID : $DB->lastInsertId()));
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
            $stmt = $DB->prepare("UPDATE microsite_banner SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE BannerID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
            $MS->StaffLog('ลบช่องทางประชาสัมพันธ์ (Banner) ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 9, $id);
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
                $sql = "UPDATE microsite_banner SET IsActive = 'Y', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE BannerID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_inactive') {
                $params = array_merge([$staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_banner SET IsActive = 'N', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE BannerID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_delete') {
                $params = array_merge([$staffID, $requestIP, $staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_banner SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE BannerID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
                $MS->StaffLog('ลบช่องทางประชาสัมพันธ์ (Banner) Bulk ID: ' . $ids, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 9, 0);
            }
        }
    }
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
?>
