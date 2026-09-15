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
        $id = $_POST['DownloadID'] ?? 0;
        $permRequired = (!empty($id)) ? 'CanEdit' : 'CanAdd';
    }
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'download', $permRequired)) {
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
    $uploadDir = $MS->micrositeUploadDirectory($sServerDirFile, $deptSlug);
} catch (RuntimeException $e) {
    $MS->micrositeUploadFail($e->getMessage());
}

function formatFileSize($bytes) {
	if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
	if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
	return $bytes . ' B';
}

function formatToYMD($dateStr) {
    if (empty($dateStr)) return null;
    $parts = explode('/', $dateStr);
    if (count($parts) === 3) {
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return $dateStr;
}

if ($action == 'save') {
    $downloadID = (int)($_POST['DownloadID'] ?? 0);
    $groupID = (int)($_POST['GroupID'] ?? 0);
    $subGroupID = (int)($_POST['SubGroupID'] ?? 0);
    $titleTH = $_POST['Title_TH'] ?? '';
    $titleEN = $_POST['Title_EN'] ?? '';
    $detailTH = $_POST['Detail_TH'] ?? '';
    $detailEN = $_POST['Detail_EN'] ?? '';
    $startDate = !empty($_POST['StartDate']) ? formatToYMD($_POST['StartDate']) . ' 00:00:00' : null;
    $endDate = !empty($_POST['EndDate']) ? formatToYMD($_POST['EndDate']) . ' 23:59:59' : null;
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = $_POST['OrderNo'] ?? 0;

    $stmtGroup = $DB->prepare("SELECT COUNT(*) FROM microsite_download_groups WHERE GroupID = ? AND DeptID = ? AND IsActive = 'Y' AND COALESCE(DelData, 'N') <> 'Y'");
    $stmtGroup->execute([$groupID, $deptID]);
    if ($groupID <= 0 || (int)$stmtGroup->fetchColumn() === 0) {
        echo "<script>alert('กรุณาเลือกหมวดหมู่ที่ถูกต้อง'); window.history.back();</script>";
        exit;
    }

    if ($subGroupID > 0) {
        $stmtSubGroup = $DB->prepare("
            SELECT COUNT(*)
            FROM microsite_download_subgroups s
            INNER JOIN microsite_download_groups g ON g.GroupID = s.GroupID
            WHERE s.SubGroupID = ? AND s.GroupID = ? AND g.DeptID = ?
                AND s.IsActive = 'Y' AND g.IsActive = 'Y'
                AND COALESCE(s.DelData, 'N') <> 'Y'
                AND COALESCE(g.DelData, 'N') <> 'Y'
        ");
        $stmtSubGroup->execute([$subGroupID, $groupID, $deptID]);
        if ((int)$stmtSubGroup->fetchColumn() === 0) {
            echo "<script>alert('หมวดหมู่ย่อยไม่สัมพันธ์กับหมวดหมู่ที่เลือก'); window.history.back();</script>";
            exit;
        }
    }
    
    $fileName = "";
    $oldFileName = "";
    if ($downloadID > 0) {
        $stmtFile = $DB->prepare("SELECT File FROM microsite_download WHERE DownloadID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmtFile->execute([$downloadID, $deptID]);
        $existingFile = $stmtFile->fetch(PDO::FETCH_ASSOC);
        $fileName = $existingFile['File'] ?? "";
        $oldFileName = $fileName;
        
        if (isset($_POST['delete_file']) && $_POST['delete_file'] == '1' && !empty($fileName)) {
            $fileName = "";
        }
    }
    
    try {
        $newName = $MS->micrositeStoreUpload($_FILES['UploadFile'] ?? [], $uploadDir, ['zip', 'pdf', 'rar', 'doc', 'docx', 'xls', 'xlsx'], $SetFileSize);
        if ($newName !== null) {
            $fileName = $newName;
        }
    } catch (RuntimeException $e) {
        $MS->micrositeUploadFail($e->getMessage());
    }

    try {
        if ($downloadID > 0) {
            $stmt = $DB->prepare("UPDATE microsite_download SET GroupID = ?, SubGroupID = ?, Title_TH = ?, Title_EN = ?, Detail_TH = ?, Detail_EN = ?, StartDate = ?, EndDate = ?, File = ?, IsActive = ?, OrderNo = ?, UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE DownloadID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$groupID, $subGroupID, $titleTH, $titleEN, $detailTH, $detailEN, $startDate, $endDate, $fileName, $isActive, $orderNo, $staffID, $requestIP, $downloadID, $deptID]);
        } else {
            $stmt = $DB->prepare("INSERT INTO microsite_download (DeptID, GroupID, SubGroupID, Title_TH, Title_EN, Detail_TH, Detail_EN, StartDate, EndDate, File, IsActive, OrderNo, Views, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?, ?, 'N')");
            $stmt->execute([$deptID, $groupID, $subGroupID, $titleTH, $titleEN, $detailTH, $detailEN, $startDate, $endDate, $fileName, $isActive, $orderNo, $staffID, $requestIP]);
        }
        if (($newName !== null || (($_POST['delete_file'] ?? '0') === '1')) && $oldFileName !== '' && $oldFileName !== $fileName && is_file($uploadDir . $oldFileName)) {
            @unlink($uploadDir . $oldFileName);
        }
        $logAction = ($downloadID > 0 ? 'แก้ไขดาวน์โหลด ID: ' : 'เพิ่มดาวน์โหลด ID: ') . ($downloadID > 0 ? $downloadID : $DB->lastInsertId());
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, ($downloadID > 0 ? $downloadID : $DB->lastInsertId()));
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
            $stmt = $DB->prepare("UPDATE microsite_download SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE DownloadID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$staffID, $requestIP, $staffID, $requestIP, $id, $deptID]);
            $MS->StaffLog('ลบดาวน์โหลด ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, $id);
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
                $sql = "UPDATE microsite_download SET IsActive = 'Y', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE DownloadID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_inactive') {
                $params = array_merge([$staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_download SET IsActive = 'N', UpdateDate = NOW(), UpdateBy=?, UpdateIP=? WHERE DownloadID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_delete') {
                $params = array_merge([$staffID, $requestIP, $staffID, $requestIP], $idArray, [$deptID]);
                $sql = "UPDATE microsite_download SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE DownloadID IN ($placeholders) AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
                $MS->StaffLog('ลบดาวน์โหลด Bulk ID: ' . $ids, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, 0);
            }
        }
    }
    header("Location: index.php");
    exit;
}
?>
