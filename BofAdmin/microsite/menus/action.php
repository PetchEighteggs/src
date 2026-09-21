<?php
define('_VALID_ACCESS', 1);
session_start();
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
$deptID = (int)($_SESSION['ActiveDeptID'] ?? 0);

if ($deptID <= 0 || $action === '') {
    header('Location: ../index.php');
    exit;
}

$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = $userGroupID === 'A';
if ($userGroupID === 'S') {
    require_once('../../includes/microsite_permission.php');
    if ($action === 'delete' || $action === 'bulk_delete') {
        $permission = 'CanDelete';
    } elseif ($action === 'bulk_active' || $action === 'bulk_inactive') {
        $permission = 'CanEdit';
    } else {
        $permission = !empty($_POST['MenuID']) ? 'CanEdit' : 'CanAdd';
    }
    $hasAccess = checkMicrositeMenuPermission($DB, (int)($_SESSION['StaffID'] ?? 0), $deptID, 'menus', $permission);
}

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์จัดการข้อมูลนี้'); window.location.href='index.php';</script>";
    exit;
}

$audit = $MS->micrositeAuditContext();
$staffID = $audit['staff_id'];
$requestIP = $audit['ip'];

if ($action === 'save') {
    $id = (int)($_POST['MenuID'] ?? 0);
    $menuNameTH = trim($_POST['MenuNameTH'] ?? '');
    $menuNameEN = trim($_POST['MenuNameEN'] ?? '');
    $url = trim($_POST['URL'] ?? '');
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = (int)($_POST['OrderNo'] ?? 0);
    $deleteImage = ($_POST['delete_image'] ?? '0') === '1';

    $moduleType = trim($_POST['ModuleType'] ?? '');

    if (!empty($moduleType) && empty($url)) {
        $url = '#';
    }

    $activeDeptSlug = $_SESSION['ActiveDeptSlug'] ?? '';
    if ($activeDeptSlug === '') {
      $stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID=?");
      $stmtSlug->execute([$deptID]);
      $activeDeptSlug = (string)$stmtSlug->fetchColumn();
    }

    try {
        $uploadDir = $MS->micrositeUploadDirectory($sServerImgDir, $activeDeptSlug);
        $oldImageName = '';
        if ($id > 0) {
            $stmt = $DB->prepare("SELECT Image FROM microsite_menus WHERE MenuID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$id, $deptID]);
            $oldImageName = (string)$stmt->fetchColumn();
        }

        $imageName = $deleteImage ? null : ($oldImageName !== '' ? $oldImageName : null);
        $newName = $MS->micrositeStoreUpload($_FILES['Image'] ?? [], $uploadDir, ['jpg', 'jpeg', 'png', 'gif', 'webp'], $SetMaxSize, true);
        if ($newName !== null) {
            $imageName = $newName;
        }

        try {
            if ($id > 0) {
                $stmt = $DB->prepare("UPDATE microsite_menus SET MenuNameTH=?, MenuNameEN=?, Image=?, URL=?, Target='_self', IsActive=?, ModuleType=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE MenuID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmt->execute([$menuNameTH, $menuNameEN, $imageName, $url, $isActive, $moduleType, $staffID, $requestIP, $id, $deptID]);
            } else {
                $stmt = $DB->prepare("INSERT INTO microsite_menus (DeptID, MenuNameTH, MenuNameEN, Image, URL, Target, IsActive, OrderNo, ModuleType, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, '_self', ?, ?, ?, NOW(), ?, ?, 'N')");
                $stmt->execute([$deptID, $menuNameTH, $menuNameEN, $imageName, $url, $isActive, $orderNo, $moduleType, $staffID, $requestIP]);
            }
        } catch (PDOException $e) {
            if ($newName !== null && is_file($uploadDir . $newName)) {
                @unlink($uploadDir . $newName);
            }
            throw $e;
        }

        if (($newName !== null || $deleteImage) && $oldImageName !== '' && $oldImageName !== $imageName && is_file($uploadDir . $oldImageName)) {
            @unlink($uploadDir . $oldImageName);
        }

        $recordID = $id > 0 ? $id : (int)$DB->lastInsertId();
        $MS->StaffLog(($id > 0 ? 'แก้ไข' : 'เพิ่ม') . 'เมนู Microsite ID: ' . $recordID, $_SERVER['REQUEST_URI'], $staffID, '', 2, $recordID);
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $stmt = $DB->prepare("UPDATE microsite_menus SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE MenuID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
      $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
      $MS->StaffLog('ลบเมนู Microsite ID: ' . $id, $_SERVER['REQUEST_URI'], $staffID, '', 2, $id);
    }
    header('Location: index.php');
    exit;
}

if (in_array($action, ['bulk_active', 'bulk_inactive', 'bulk_delete'], true)) {
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')))));
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'bulk_delete') {
            $sql = "UPDATE microsite_menus SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE MenuID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'";
            $params = array_merge([$staffID, $requestIP, $staffID, $requestIP], $ids, [$deptID]);
        } else {
            $status = $action === 'bulk_active' ? 'Y' : 'N';
            $sql = "UPDATE microsite_menus SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE MenuID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'";
            $params = array_merge([$status, $staffID, $requestIP], $ids, [$deptID]);
        }
        $stmt = $DB->prepare($sql);
        $stmt->execute($params);
        $MS->StaffLog('Bulk ' . $action . ' เมนู Microsite', $_SERVER['REQUEST_URI'], $staffID, '', 2);
    }
}
header('Location: index.php');
exit;
