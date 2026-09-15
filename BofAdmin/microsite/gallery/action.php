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
        $id = $_POST['GalleryID'] ?? 0;
        $permRequired = (!empty($id)) ? 'CanEdit' : 'CanAdd';
    }
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'gallery', $permRequired)) {
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
    $galleryID = $_POST['GalleryID'] ?? 0;
    $groupID = $_POST['GroupID'] ?? 0;
    $titleTH = $_POST['Title_TH'] ?? '';
    $titleEN = $_POST['Title_EN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = $_POST['OrderNo'] ?? 0;
    
    $coverName = "";
    $oldCoverName = "";
    $uploadedFiles = [];
    $deletedGalleryFiles = [];
    if ($galleryID > 0) {
        $stmtImg = $DB->prepare("SELECT Image FROM microsite_gallery WHERE GalleryID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmtImg->execute([$galleryID, $deptID]);
        $coverName = $stmtImg->fetchColumn() ?: "";
        $oldCoverName = $coverName;
    }
    
    // Upload Cover
    try {
        $newName = $MS->micrositeStoreUpload($_FILES['Image'] ?? [], $uploadDir, ['jpg', 'jpeg', 'png', 'gif', 'webp'], $SetMaxSize, true);
        if ($newName !== null) {
            $coverName = $newName;
            $uploadedFiles[] = $newName;
        }
    } catch (RuntimeException $e) {
        $MS->micrositeUploadFail($e->getMessage());
    }

    try {
        $DB->beginTransaction();
        if ($galleryID > 0) {
            $stmt = $DB->prepare("UPDATE microsite_gallery SET GroupID = ?, Title_TH = ?, Title_EN = ?, Image = ?, IsActive = ?, OrderNo = ?, UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE GalleryID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$groupID, $titleTH, $titleEN, $coverName, $isActive, $orderNo, $staffID, $requestIP, $galleryID, $deptID]);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_gallery (DeptID, GroupID, Title_TH, Title_EN, Image, IsActive, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $groupID, $titleTH, $titleEN, $coverName, $isActive, $orderNo, $staffID, $requestIP]);
            $galleryID = $DB->lastInsertId();
        }

        // Soft-delete gallery images selected in the management modal.
        $deleteImageIDs = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $_POST['delete_images'] ?? '')),
            static fn($imageID) => $imageID > 0
        )));
        if (!empty($deleteImageIDs)) {
            $deletePlaceholders = implode(',', array_fill(0, count($deleteImageIDs), '?'));
            $stmtDeletedFiles = $DB->prepare("SELECT Image FROM microsite_gallery_images WHERE ImageID IN ($deletePlaceholders) AND GalleryID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmtDeletedFiles->execute(array_merge($deleteImageIDs, [$galleryID]));
            $deletedGalleryFiles = $stmtDeletedFiles->fetchAll(PDO::FETCH_COLUMN);

            $stmtDeleteImages = $DB->prepare("UPDATE microsite_gallery_images SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE ImageID IN ($deletePlaceholders) AND GalleryID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmtDeleteImages->execute(array_merge(
                [$staffID, $requestIP, $staffID, $requestIP],
                $deleteImageIDs,
                [$galleryID]
            ));
        }

        // Update the display order of existing gallery images.
        $galleryOrder = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $_POST['gallery_order'] ?? '')),
            static fn($imageID) => $imageID > 0
        )));
        if (!empty($galleryOrder)) {
            $stmtOrder = $DB->prepare("UPDATE microsite_gallery_images SET OrderNo = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE ImageID = ? AND GalleryID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            foreach ($galleryOrder as $index => $imageID) {
                $stmtOrder->execute([$index + 1, $staffID, $requestIP, $imageID, $galleryID]);
            }
        }

        $stmtMaxOrder = $DB->prepare("SELECT COALESCE(MAX(OrderNo), 0) FROM microsite_gallery_images WHERE GalleryID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmtMaxOrder->execute([$galleryID]);
        $nextImageOrder = (int)$stmtMaxOrder->fetchColumn() + 1;
        
        // Upload Gallery Images
        if (isset($_FILES['GalleryImages']) && !empty($_FILES['GalleryImages']['name'][0])) {
            $fileCount = count($_FILES['GalleryImages']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                $galleryFile = [
                    'name' => $_FILES['GalleryImages']['name'][$i],
                    'type' => $_FILES['GalleryImages']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['GalleryImages']['tmp_name'][$i],
                    'error' => $_FILES['GalleryImages']['error'][$i],
                    'size' => $_FILES['GalleryImages']['size'][$i],
                ];
                try {
                    $imgName = $MS->micrositeStoreUpload($galleryFile, $uploadDir, ['jpg', 'jpeg', 'png', 'gif', 'webp'], $SetMaxSize, true);
                } catch (RuntimeException $e) {
                    throw $e;
                }
                if ($imgName !== null) {
                    $uploadedFiles[] = $imgName;
                    $stmtImgIns = $DB->prepare("INSERT INTO microsite_gallery_images (GalleryID, Image, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, NOW(), ?, ?, 'N')");
                    $stmtImgIns->execute([$galleryID, $imgName, $nextImageOrder, $staffID, $requestIP]);
                    $nextImageOrder++;
                }
            }
        }
        $DB->commit();

        foreach ($deletedGalleryFiles as $deletedGalleryFile) {
            if ($deletedGalleryFile !== '' && is_file($uploadDir . $deletedGalleryFile)) {
                @unlink($uploadDir . $deletedGalleryFile);
            }
        }
        if ($newName !== null && $oldCoverName !== '' && $oldCoverName !== $newName && is_file($uploadDir . $oldCoverName)) {
            @unlink($uploadDir . $oldCoverName);
        }
        
        $logAction = ($galleryID > 0 && $_POST['GalleryID'] > 0 ? 'แก้ไขภาพกิจกรรม ID: ' : 'เพิ่มภาพกิจกรรม ID: ') . $galleryID;
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 12, $galleryID);
        
        echo "<script>window.location.href='index.php';</script>";
    } catch (Throwable $e) {
        if ($DB->inTransaction()) {
            $DB->rollBack();
        }
        foreach ($uploadedFiles as $uploadedFile) {
            if (is_file($uploadDir . $uploadedFile)) {
                @unlink($uploadDir . $uploadedFile);
            }
        }
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        try {
            $stmt = $DB->prepare("UPDATE microsite_gallery SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GalleryID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
            $MS->StaffLog('ลบภาพกิจกรรม ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 12, $id);
            echo "<script>window.location.href='index.php';</script>";
        } catch (PDOException $e) {
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='index.php';</script>";
        }
    }
} elseif ($action == 'delete_image') {
    $imageId = $_GET['id'] ?? 0;
    $galleryId = $_GET['gallery_id'] ?? 0;
    
    if ($imageId > 0 && $galleryId > 0) {
        try {
            // Check permission
            $chk = $DB->prepare("SELECT GalleryID FROM microsite_gallery WHERE GalleryID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $chk->execute([$galleryId, $deptID]);
            if ($chk->fetch()) {
                $stmtImg = $DB->prepare("SELECT Image FROM microsite_gallery_images WHERE ImageID = ? AND GalleryID = ? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmtImg->execute([$imageId, $galleryId]);
                $imgName = $stmtImg->fetchColumn();
                
                $del = $DB->prepare("UPDATE microsite_gallery_images SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE ImageID=? AND GalleryID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $del->execute([$staffID, $requestIP, $staffID, $requestIP, $imageId, $galleryId]);
                if (!empty($imgName) && is_file($uploadDir . $imgName)) {
                    @unlink($uploadDir . $imgName);
                }
                $MS->StaffLog("ลบรูปภาพย่อย ID: $imageId ของภาพกิจกรรม ID: $galleryId", $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 12, $galleryId);
            }
            echo "<script>window.location.href='form.php?id=" . $galleryId . "';</script>";
        } catch (PDOException $e) {
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
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
                $sql = "UPDATE microsite_gallery SET IsActive = 'Y', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE GalleryID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_inactive') {
                $params = array_merge([$staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_gallery SET IsActive = 'N', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE GalleryID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_delete') {
                // Check if they belong to this dept
                $params = array_merge($idArray, [$deptID]);
                $stmtCheck = $DB->prepare("SELECT GalleryID FROM microsite_gallery WHERE GalleryID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
                $stmtCheck->execute($params);
                $validItems = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);
                
                $validIds = [];
                foreach ($validItems as $item) {
                    $validIds[] = $item['GalleryID'];
                }
                
                if (count($validIds) > 0) {
                    $validPlaceholders = implode(',', array_fill(0, count($validIds), '?'));
                    $stmtDel = $DB->prepare("UPDATE microsite_gallery SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GalleryID IN ($validPlaceholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                    $stmtDel->execute(array_merge([$staffID, $requestIP, $staffID, $requestIP], $validIds, [$deptID]));
                    
                    $MS->StaffLog('ลบภาพกิจกรรม Bulk ID: ' . implode(',', $validIds), $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 12, 0);
                }
            }
        }
    }
    header("Location: index.php");
    exit;
}
?>
