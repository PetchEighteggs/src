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
        $id = $_POST['TopGraphicID'] ?? 0;
        $permRequired = (!empty($id)) ? 'CanEdit' : 'CanAdd';
    }
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'topgraphic', $permRequired)) {
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
    $topID = $_POST['TopGraphicID'] ?? 0;
    $titleTH = $_POST['Title_TH'] ?? '';
		$titleEN = $_POST['Title_EN'] ?? '';
		$urlTH = $_POST['URL_TH'] ?? '';
		$urlEN = $_POST['URL_EN'] ?? '';
    $target = $_POST['Target'] ?? '_blank';
    $startDate = !empty($_POST['StartDate']) ? $_POST['StartDate'] . ' 00:00:00' : null;
    $endDate = !empty($_POST['EndDate']) ? $_POST['EndDate'] . ' 23:59:59' : null;
    $isActive = $_POST['IsActive'] ?? 'Y';
    $isImageOnly = isset($_POST['IsImageOnly']) && $_POST['IsImageOnly'] == 'Y' ? 'Y' : 'N';
    $orderNo = $_POST['OrderNo'] ?? 0;
    
    $imageName = "";
    $oldImageName = "";
    if ($topID > 0) {
        $stmtImg = $DB->prepare("SELECT Image FROM microsite_topgraphics WHERE TopGraphicID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmtImg->execute([$topID, $deptID]);
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
        if ($topID > 0) {
            $stmt = $DB->prepare("UPDATE microsite_topgraphics SET Title_TH = ?, Title_EN = ?, URL_TH = ?, URL_EN = ?, Image = ?, Target = ?, StartDate = ?, EndDate = ?, IsActive = ?, IsImageOnly = ?, OrderNo = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE TopGraphicID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$titleTH, $titleEN, $urlTH, $urlEN, $imageName, $target, $startDate, $endDate, $isActive, $isImageOnly, $orderNo, $staffID, $requestIP, $topID, $deptID]);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_topgraphics (DeptID, Title_TH, Title_EN, URL_TH, URL_EN, Image, Target, StartDate, EndDate, IsActive, IsImageOnly, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $titleTH, $titleEN, $urlTH, $urlEN, $imageName, $target, $startDate, $endDate, $isActive, $isImageOnly, $orderNo, $staffID, $requestIP]);
        }
        if (($newName !== null || $deleteImageFlag) && $oldImageName !== '' && $oldImageName !== $imageName && is_file($uploadDir . $oldImageName)) {
            @unlink($uploadDir . $oldImageName);
        }
        $logAction = ($topID > 0 ? 'แก้ไข Top Graphic ID: ' : 'เพิ่ม Top Graphic ID: ') . ($topID > 0 ? $topID : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $staffID, '', 5, ($topID > 0 ? $topID : $DB->lastInsertId()));
        echo "<script>window.location.href='index.php';</script>";
    } catch (PDOException $e) {
        if ($newName !== null && is_file($uploadDir . $newName)) {
            @unlink($uploadDir . $newName);
        }
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }

} elseif ($action == 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $DB->prepare("UPDATE microsite_topgraphics SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE TopGraphicID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
            $MS->StaffLog('ลบ Top Graphic ID: ' . $id, $_SERVER['REQUEST_URI'], $staffID, '', 5, $id);
            echo "<script>window.location.href='index.php';</script>";
        } catch (PDOException $e) {
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='index.php';</script>";
        }
    }
} elseif ($action == 'bulk_active' || $action == 'bulk_inactive' || $action == 'bulk_delete') {
    $ids = $_POST['ids'] ?? '';
    if (!empty($ids)) {
        $idArray = array_values(array_unique(array_filter(array_map('intval', explode(',', $ids)))));
        if (count($idArray) > 0) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            
            if ($action == 'bulk_active') {
                $params = array_merge([$staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_topgraphics SET IsActive = 'Y', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE TopGraphicID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_inactive') {
                $params = array_merge([$staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_topgraphics SET IsActive = 'N', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE TopGraphicID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_delete') {
                $params = array_merge([$staffID, $requestIP, $staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_topgraphics SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE TopGraphicID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
                $MS->StaffLog('ลบ Top Graphic Bulk ID: ' . $ids, $_SERVER['REQUEST_URI'], $staffID, '', 5, 0);
            }
        }
    }
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
?>
