<?php
try {
define('_VALID_ACCESS', 1);
session_start();
require_once('../../../configuration.php');
require_once('../../../config/ms_com.php');
require_once('../../../config/ms.class.php');
$DB = mosConnectPDO();
$MS = new MS($DB);

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
$action = $_REQUEST['action'] ?? '';
if (empty($deptID)) {
    header("Location: ../index.php");
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
        $permission = 'CanDelete';
    } elseif ($action === 'bulk_active' || $action === 'bulk_inactive' || !empty($_POST['id'])) {
        $permission = 'CanEdit';
    } else {
        $permission = 'CanAdd';
    }
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'news', $permission)) {
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

$id = $_REQUEST['id'] ?? 0;

if ($action === 'save') {
    $groupID = $_POST['GroupID'] ?? 0;
    $titleTH = $_POST['Title_TH'] ?? '';
    $titleEN = $_POST['Title_EN'] ?? '';
    $contentTH = $_POST['Content_TH'] ?? '';
    $contentEN = $_POST['Content_EN'] ?? '';
    $detailTH = $_POST['Detail_TH'] ?? '';
    $detailEN = $_POST['Detail_EN'] ?? '';
    $url = $_POST['URL'] ?? '';
    $fileVDO = $_POST['FileVDO'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';

    // Handle Dates (flatpickr format d/m/Y)
    function formatToYMD($d) {
        if (empty($d)) return null;
        $p = explode('/', $d);
        if (count($p) == 3) return $p[2] . '-' . $p[1] . '-' . $p[0];
        return null;
    }
    $publishDate = formatToYMD($_POST['PublishDate'] ?? '') ?: date('Y-m-d');
    $startDate = formatToYMD($_POST['StartDate'] ?? '');
    $endDate = formatToYMD($_POST['EndDate'] ?? '');

    // Get Slug for folder path
    $stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID = ?");
    $stmtSlug->execute([$deptID]);
    $slug = $stmtSlug->fetchColumn();

    // Base Directory
    $baseImagesDir = $MS->micrositeUploadDirectory($sServerImgDir, $slug);
    $baseFilesDir = $MS->micrositeUploadDirectory($sServerDirFile, $slug);
    $newPhysicalFiles = [];
    $oldPhysicalFiles = [];

    try {
        $DB->beginTransaction();
        
        // Get existing image
        $currentImage = "";
        if (!empty($id)) {
            $stmtImg = $DB->prepare("SELECT Image FROM microsite_news WHERE NewsID = ? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmtImg->execute([$id, $deptID]);
            $currentImage = $stmtImg->fetchColumn();
        }
        
        $imageName = $currentImage;
        if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
            if (!empty($currentImage)) {
                $oldPhysicalFiles[] = $baseImagesDir . $currentImage;
            }
            $imageName = "";
        }
        
        $newFileName = $MS->micrositeStoreUpload($_FILES['Image'] ?? [], $baseImagesDir, ['jpg', 'jpeg', 'png', 'gif', 'webp'], $SetMaxSize, true);
        if ($newFileName !== null) {
            if (!empty($currentImage)) {
                $oldPhysicalFiles[] = $baseImagesDir . $currentImage;
            }
            $imageName = $newFileName;
            $newPhysicalFiles[] = $baseImagesDir . $newFileName;
        }

        if (empty($id)) {
            // Insert
            $ins = $DB->prepare("
                INSERT INTO microsite_news (
                    DeptID, GroupID, Title_TH, Title_EN, Content_TH, Content_EN, Detail_TH, Detail_EN, URL, FileVDO,
                    Image, IsActive, PublishDate, StartDate, EndDate, CreateDate, CreateBy, CreateIP, DelData
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')
            ");
            $ins->execute([
                $deptID, $groupID, $titleTH, $titleEN, $contentTH, $contentEN, $detailTH, $detailEN, $url, $fileVDO,
                $imageName, $isActive, $publishDate, $startDate, $endDate, $staffID, $requestIP
            ]);
            $newsID = $DB->lastInsertId();
        } else {
            // Update
            $upd = $DB->prepare("
                UPDATE microsite_news SET 
                    GroupID=?, Title_TH=?, Title_EN=?, Content_TH=?, Content_EN=?, Detail_TH=?, Detail_EN=?, URL=?, FileVDO=?,
                    Image=?, IsActive=?, PublishDate=?, StartDate=?, EndDate=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=?
                WHERE NewsID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'
            ");
            $upd->execute([
                $groupID, $titleTH, $titleEN, $contentTH, $contentEN, $detailTH, $detailEN, $url, $fileVDO,
                $imageName, $isActive, $publishDate, $startDate, $endDate, $staffID, $requestIP,
                $id, $deptID
            ]);
            $newsID = $id;
            
            // Handle file deletions
            if (!empty($_POST['delete_files'])) {
                foreach ($_POST['delete_files'] as $delFileId) {
                    $stmtF = $DB->prepare("SELECT FileName FROM microsite_news_files WHERE FileID = ? AND NewsID = ? AND COALESCE(DelData, 'N') <> 'Y'");
                    $stmtF->execute([$delFileId, $newsID]);
                    if ($fName = $stmtF->fetchColumn()) {
                        $oldPhysicalFiles[] = $baseFilesDir . $fName;
                        $delStmt = $DB->prepare("UPDATE microsite_news_files SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE FileID=? AND NewsID=? AND COALESCE(DelData, 'N') <> 'Y'");
                        $delStmt->execute([$staffID, $requestIP, $staffID, $requestIP, $delFileId, $newsID]);
                    }
                }
            }
        }

        // Handle File Uploads
        if (isset($_FILES['Files']) && is_array($_FILES['Files']['name'])) {
            $fileCount = count($_FILES['Files']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['Files']['error'][$i] === UPLOAD_ERR_OK) {
                    $origName = $_FILES['Files']['name'][$i];
                    $newsFile = [
                        'name' => $origName,
                        'type' => $_FILES['Files']['type'][$i] ?? '',
                        'tmp_name' => $_FILES['Files']['tmp_name'][$i],
                        'error' => $_FILES['Files']['error'][$i],
                        'size' => $_FILES['Files']['size'][$i],
                    ];
                    $newFName = $MS->micrositeStoreUpload($newsFile, $baseFilesDir, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar'], $SetFileSize);
                    if ($newFName !== null) {
                        $newPhysicalFiles[] = $baseFilesDir . $newFName;
                        $insF = $DB->prepare("INSERT INTO microsite_news_files (NewsID, FileName, FileOriginalName, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, NOW(), ?, ?, 'N')");
                        $insF->execute([$newsID, $newFName, $origName, $staffID, $requestIP]);
                    }
                }
            }
        }

        $logAction = (empty($id) ? 'เพิ่มข่าวประชาสัมพันธ์ ID: ' : 'แก้ไขข่าวประชาสัมพันธ์ ID: ') . $newsID;
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 8, $newsID);

        $DB->commit();
        foreach (array_unique($oldPhysicalFiles) as $oldPhysicalFile) {
            if (is_file($oldPhysicalFile)) {
                @unlink($oldPhysicalFile);
            }
        }
        echo "<script>window.location.href='index.php';</script>";
        exit;
    } catch (Throwable $e) {
        if ($DB->inTransaction()) {
            $DB->rollBack();
        }
        foreach (array_unique($newPhysicalFiles) as $newPhysicalFile) {
            if (is_file($newPhysicalFile)) {
                @unlink($newPhysicalFile);
            }
        }
        echo "<script>alert('เกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }
} else if ($action === 'delete' && $id > 0) {
    try {
        // Must belong to active DeptID
        $check = $DB->prepare("SELECT NewsID FROM microsite_news WHERE NewsID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $check->execute([$id, $deptID]);
        if ($news = $check->fetch(PDO::FETCH_ASSOC)) {
            $del = $DB->prepare("UPDATE microsite_news SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE NewsID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $del->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
            
            $MS->StaffLog('ลบข่าวประชาสัมพันธ์ ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 8, $id);
            
        }
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'index.php';</script>";
    }
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
                $del = $DB->prepare("UPDATE microsite_news SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE NewsID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $del->execute(array_merge([$staffID, $requestIP, $staffID, $requestIP], $validIds, [$deptID]));
                
            } else if ($action === 'bulk_active') {
                $upd = $DB->prepare("UPDATE microsite_news SET IsActive='Y', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE NewsID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $upd->execute(array_merge([$staffID, $requestIP], $validIds, [$deptID]));
            } else if ($action === 'bulk_inactive') {
                $upd = $DB->prepare("UPDATE microsite_news SET IsActive='N', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE NewsID IN ($placeholders) AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
                $upd->execute(array_merge([$staffID, $requestIP], $validIds, [$deptID]));
            }
            
            $thaiActions = ['bulk_active' => 'เปิดใช้งานข่าวประชาสัมพันธ์แบบกลุ่ม', 'bulk_inactive' => 'ระงับการใช้งานข่าวประชาสัมพันธ์แบบกลุ่ม', 'bulk_delete' => 'ลบข่าวประชาสัมพันธ์แบบกลุ่ม'];
            $logActionStr = $thaiActions[$action] ?? 'เปลี่ยนสถานะข่าวประชาสัมพันธ์แบบกลุ่ม';
            $MS->StaffLog($logActionStr, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 8);
        }
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'index.php';</script>";
    }
}

header("Location: index.php");
exit;
} catch (Throwable $t) {
    echo "<h1>Action Fatal Error:</h1>";
    echo "<pre>" . $t->getMessage() . "\n" . $t->getTraceAsString() . "</pre>";
    echo "Line: " . $t->getLine();
    echo "File: " . $t->getFile();
}
