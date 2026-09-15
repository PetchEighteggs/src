<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// sitemap/action.php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../../../configuration.php');
require_once('../../../config/ms_com.php');
require_once('../../../config/ms.class.php');
$DB = mosConnectPDO();
$MS = new MS($DB);

$action = $_REQUEST['action'] ?? '';
$deptID = $_SESSION['ActiveDeptID'] ?? 0;

if (empty($deptID) || empty($action)) {
  header("Location: index.php");
    exit;
}

$userGroupID = $_SESSION['GroupID'] ?? '';
$canEdit = false; $canDelete = false; $canAdd = false;
if ($userGroupID === 'A') {
    $canEdit = $canDelete = $canAdd = true;
} else if ($userGroupID === 'S') {
	$staffID = $_SESSION['StaffID'] ?? 0;
	require_once('../../includes/microsite_permission.php');
	$canEdit   = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'sitemap', 'CanEdit');
	$canDelete = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'sitemap', 'CanDelete');
	$canAdd    = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'sitemap', 'CanAdd');
}

$audit = $MS->micrositeAuditContext();
$staffID = $audit['staff_id'];
$requestIP = $audit['ip'];

if ($action == 'save') {
    if (!$canEdit && !$canAdd) {
      echo "<script>alert('ไม่มีสิทธิ์'); window.location.href = 'index.php';</script>";
      exit;
    }
    $id       = intval($_POST['id'] ?? 0);
    $titleTH  = $_POST['Title_TH'] ?? '';
    $titleEN  = $_POST['Title_EN'] ?? '';
    $urlTH    = $_POST['URL_TH'] ?? '';
    $urlEN    = $_POST['URL_EN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';

    try {
        if ($id) {
            $stmt = $DB->prepare("UPDATE microsite_sitemap_groups SET Title_TH=?, Title_EN=?, URL_TH=?, URL_EN=?, IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$titleTH, $titleEN, $urlTH, $urlEN, $isActive, $staffID, $requestIP, $id, $deptID]);
            $MS->StaffLog('แก้ไข Sitemap Group ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? 1, '', 14, $id);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_sitemap_groups (DeptID, Title_TH, Title_EN, URL_TH, URL_EN, IsActive, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $titleTH, $titleEN, $urlTH, $urlEN, $isActive, $staffID, $requestIP]);
            $newId = $DB->lastInsertId();
            $MS->StaffLog('เพิ่ม Sitemap Group ID: ' . $newId, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? 1, '', 14, $newId);
        }
        echo "<script>window.location.href = 'index.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }

} elseif ($action == 'delete') {
    if (!$canDelete) {
        echo "<script>alert('ไม่มีสิทธิ์ลบ'); window.location.href = 'index.php';</script>";
        exit;
    }
    $id = intval($_GET['id'] ?? 0);
    try {
        $stmt = $DB->prepare("UPDATE microsite_sitemap_groups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
        $MS->StaffLog('ลบ Sitemap Group ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? 1, '', 14, $id);
        echo "<script>window.location.href = 'index.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'index.php';</script>";
    }

} elseif ($action == 'bulk_delete') {
    if (!$canDelete) { echo "<script>window.location.href = 'index.php';</script>"; exit; }
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $DB->prepare("UPDATE microsite_sitemap_groups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'")->execute(array_merge([$staffID, $requestIP, $staffID, $requestIP], $ids, [$deptID]));
    }
    echo "<script>window.location.href = 'index.php';</script>";

} elseif ($action == 'bulk_active' || $action == 'bulk_inactive') {
    if (!$canEdit) { echo "<script>window.location.href = 'index.php';</script>"; exit; }
    $isActive = ($action == 'bulk_active') ? 'Y' : 'N';
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $DB->prepare("UPDATE microsite_sitemap_groups SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'")->execute(array_merge([$isActive, $staffID, $requestIP], $ids, [$deptID]));
    }
    echo "<script>window.location.href = 'index.php';</script>";
}
?>
