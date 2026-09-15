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
    echo "<script>alert('ไม่มีสิทธิ์เข้าถึง'); window.location.href='../index.php';</script>";
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
        $id = $_POST['MediaID'] ?? 0;
        $permRequired = (!empty($id)) ? 'CanEdit' : 'CanAdd';
    }
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'multimedia', $permRequired)) {
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
$slug = $stmtSlug->fetchColumn();
try {
    $uploadDir = $MS->micrositeUploadDirectory($sServerImgDir, $slug);
} catch (RuntimeException $e) {
    $MS->micrositeUploadFail($e->getMessage());
}

if ($action === 'save') {
    $id = $_POST['MediaID'] ?? 0;
    $groupID = $_POST['GroupID'] ?? 0;
    $titleTH = trim($_POST['Title_TH'] ?? '');
    $titleEN = trim($_POST['Title_EN'] ?? '');
    $url = trim($_POST['URL'] ?? '');
    $urlEN = trim($_POST['URL_EN'] ?? '');
    $target = $_POST['Target'] ?? '_blank';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = (int)($_POST['OrderNo'] ?? 0);
    
    // Get current image
    $currentImage = '';
    if ($id > 0) {
        $stmt = $DB->prepare("SELECT Image FROM microsite_multimedia WHERE MediaID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$id, $deptID]);
        $currentImage = $stmt->fetchColumn() ?: '';
    }

    // Handle File Upload
    $imageName = $currentImage;
    $deleteImageFlag = ($_POST['delete_image'] ?? '0') === '1';
    if ($deleteImageFlag) {
        $imageName = '';
    }
    try {
        $newFileName = $MS->micrositeStoreUpload($_FILES['CoverImage'] ?? [], $uploadDir, ['jpg', 'jpeg', 'png', 'gif', 'webp'], $SetMaxSize, true);
        if ($newFileName !== null) {
            $imageName = $newFileName;
        }
    } catch (RuntimeException $e) {
        $MS->micrositeUploadFail($e->getMessage());
    }

    try {
        if ($id > 0) {
            $upd = $DB->prepare("
                UPDATE microsite_multimedia SET 
                GroupID=?, Title_TH=?, Title_EN=?, Image=?, URL_TH=?, URL_EN=?, Target=?, IsActive=?, OrderNo=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=?
                WHERE MediaID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'
            ");
            $upd->execute([
                $groupID, $titleTH, $titleEN, $imageName, $url, $urlEN, $target, $isActive, $orderNo, $staffID, $requestIP, $id, $deptID
            ]);
        } else {
            if ($orderNo === 0) {
                $stmtOrder = $DB->prepare("SELECT MAX(OrderNo) FROM microsite_multimedia WHERE DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmtOrder->execute([$deptID]);
                $orderNo = ((int)$stmtOrder->fetchColumn()) + 1;
            }
            
            $ins = $DB->prepare("
                INSERT INTO microsite_multimedia 
                (DeptID, GroupID, Title_TH, Title_EN, Image, URL_TH, URL_EN, Target, IsActive, OrderNo, CreateDate, CreateBy, CreateIP, DelData)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')
            ");
            $ins->execute([
                $deptID, $groupID, $titleTH, $titleEN, $imageName, $url, $urlEN, $target, $isActive, $orderNo, $staffID, $requestIP
            ]);
        }
        if (($newFileName !== null || $deleteImageFlag) && $currentImage !== '' && $currentImage !== $imageName && is_file($uploadDir . $currentImage)) {
            @unlink($uploadDir . $currentImage);
        }
        $logAction = ($id > 0 ? 'แก้ไขสื่อมัลติมีเดีย ID: ' : 'เพิ่มสื่อมัลติมีเดีย ID: ') . ($id > 0 ? $id : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 11, ($id > 0 ? $id : $DB->lastInsertId()));
        echo "<script>window.location.href='index.php';</script>";
    } catch (PDOException $e) {
        if ($newFileName !== null && is_file($uploadDir . $newFileName)) {
            @unlink($uploadDir . $newFileName);
        }
        echo "<script>alert('เกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }

} else if ($action === 'delete') {
    $id = $_GET['id'] ?? 0;
    
    $del = $DB->prepare("UPDATE microsite_multimedia SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE MediaID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
    $del->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
    
    $MS->StaffLog('ลบสื่อมัลติมีเดีย ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 11, $id);
    
    echo "<script>window.location.href='index.php';</script>";
} else if (($action === 'bulk_delete' || $action === 'bulk_active' || $action === 'bulk_inactive') && !empty($_GET['ids'])) {
    try {
        $ids = explode(',', $_GET['ids']);
        $validIds = [];
        foreach ($ids as $val) {
            $val = (int)trim($val);
            if ($val > 0) $validIds[] = $val;
        }

        if (!empty($validIds)) {
            $validIds = array_values(array_unique($validIds));
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            
            if ($action === 'bulk_delete') {
                $del = $DB->prepare("UPDATE microsite_multimedia SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE MediaID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $del->execute(array_merge([$staffID, $requestIP, $staffID, $requestIP], $validIds, [$deptID]));
                
            } else if ($action === 'bulk_active') {
                $upd = $DB->prepare("UPDATE microsite_multimedia SET IsActive='Y', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE MediaID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $upd->execute(array_merge([$staffID, $requestIP], $validIds, [$deptID]));
            } else if ($action === 'bulk_inactive') {
                $upd = $DB->prepare("UPDATE microsite_multimedia SET IsActive='N', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE MediaID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $upd->execute(array_merge([$staffID, $requestIP], $validIds, [$deptID]));
            }
            
            $thaiActions = ['bulk_active' => 'เปิดใช้งานสื่อมัลติมีเดียแบบกลุ่ม', 'bulk_inactive' => 'ระงับการใช้งานสื่อมัลติมีเดียแบบกลุ่ม', 'bulk_delete' => 'ลบสื่อมัลติมีเดียแบบกลุ่ม'];
            $logActionStr = $thaiActions[$action] ?? 'เปลี่ยนสถานะสื่อมัลติมีเดียแบบกลุ่ม';
            $MS->StaffLog($logActionStr, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 11);
        }
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'index.php';</script>";
    }
}
?>
