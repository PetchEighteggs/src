<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../../../configuration.php');
require_once('../../../config/ms_com.php');
require_once('../../../config/ms.class.php');
require_once('../../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../../login.php']);
bofSecurityRequireMethod(['POST']);
bofSecurityRequireCsrf();
$MS = new MS($DB);

$action = $_POST['action'] ?? '';
$deptID = $_SESSION['ActiveDeptID'] ?? 0;
$groupID = intval($_POST['group_id'] ?? 0);

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

$redirectUrl = 'subgroup_index.php?group_id=' . $groupID;

if ($action == 'save') {
    $id = intval($_POST['id'] ?? 0);
    if (($id > 0 && !$canEdit) || ($id <= 0 && !$canAdd)) {
        echo "<script>alert('ไม่มีสิทธิ์'); window.location.href = '{$redirectUrl}';</script>";
        exit;
    }
    $titleTH  = $_POST['Title_TH'] ?? '';
    $titleEN  = $_POST['Title_EN'] ?? '';
    $urlTH    = $_POST['URL_TH'] ?? '';
    $urlEN    = $_POST['URL_EN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';

    try {
        if ($id) {
            $stmt = $DB->prepare("UPDATE microsite_sitemap_subgroups SET GroupID=?, Title_TH=?, Title_EN=?, URL_TH=?, URL_EN=?, IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubGroupID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$groupID, $titleTH, $titleEN, $urlTH, $urlEN, $isActive, $staffID, $requestIP, $id, $deptID]);
            $MS->StaffLog('แก้ไข Sitemap Subgroup ID: ' . $id, $_SERVER['REQUEST_URI'], $staffID, '', 14, $id);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_sitemap_subgroups (DeptID, GroupID, Title_TH, Title_EN, URL_TH, URL_EN, IsActive, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $groupID, $titleTH, $titleEN, $urlTH, $urlEN, $isActive, $staffID, $requestIP]);
            $newId = $DB->lastInsertId();
            $MS->StaffLog('เพิ่ม Sitemap Subgroup ID: ' . $newId, $_SERVER['REQUEST_URI'], $staffID, '', 14, $newId);
        }
        echo "<script>window.location.href = '{$redirectUrl}';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }

} elseif ($action == 'delete') {
    if (!$canDelete) {
        echo "<script>alert('ไม่มีสิทธิ์ลบ'); window.location.href = '{$redirectUrl}';</script>";
        exit;
    }
    $id = intval($_POST['id'] ?? 0);
    try {
        $stmt = $DB->prepare("UPDATE microsite_sitemap_subgroups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubGroupID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
        $MS->StaffLog('ลบ Sitemap Subgroup ID: ' . $id, $_SERVER['REQUEST_URI'], $staffID, '', 14, $id);
        echo "<script>window.location.href = '{$redirectUrl}';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = '{$redirectUrl}';</script>";
    }

} elseif ($action == 'bulk_delete') {
    if (!$canDelete) { echo "<script>window.location.href = '{$redirectUrl}';</script>"; exit; }
    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $DB->prepare("UPDATE microsite_sitemap_subgroups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubGroupID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'")->execute(array_merge([$staffID, $requestIP, $staffID, $requestIP], $ids, [$deptID]));
    }
    echo "<script>window.location.href = '{$redirectUrl}';</script>";

} elseif ($action == 'bulk_active' || $action == 'bulk_inactive') {
    if (!$canEdit) { echo "<script>window.location.href = '{$redirectUrl}';</script>"; exit; }
    $isActive = ($action == 'bulk_active') ? 'Y' : 'N';
    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $DB->prepare("UPDATE microsite_sitemap_subgroups SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE SubGroupID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'")->execute(array_merge([$isActive, $staffID, $requestIP], $ids, [$deptID]));
    }
    echo "<script>window.location.href = '{$redirectUrl}';</script>";
}
?>
