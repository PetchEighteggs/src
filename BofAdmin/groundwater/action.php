<?php
define('_VALID_ACCESS', 1);
session_start();
require_once('../../configuration.php');
require_once('../../config/ms_com.php');
require_once('../../config/ms.class.php');
$DB = mosConnectPDO();
$MS = new MS($DB);

$action = $_REQUEST['action'] ?? '';

// Verify Access
if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    header("Location: ../login.php");
    exit;
}

$uploadDir = $sServerDirFile . "groundwater/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
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
    $downloadID = $_POST['DocID'] ?? 0;
    $groupID = $_POST['GroupID'] ?? 0;

    $titleTH = $_POST['TitleTH'] ?? '';
    $titleEN = $_POST['TitleEN'] ?? '';
    $detailTH = $_POST['DescriptionTH'] ?? '';
    $detailEN = $_POST['DescriptionEN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = isset($_POST['OrderNo']) ? $_POST['OrderNo'] : null;
    
    $fileName = "";
    if ($downloadID > 0) {
        if ($orderNo === null) {
            $stmtOrder = $DB->prepare("SELECT OrderNo FROM groundwater_docs WHERE DocID = ?");
            $stmtOrder->execute([$downloadID]);
            $orderNo = (int)$stmtOrder->fetchColumn();
        }
        $stmtFile = $DB->prepare("SELECT File FROM groundwater_docs WHERE DocID = ?");
        $stmtFile->execute([$downloadID]);
        $existingFile = $stmtFile->fetch(PDO::FETCH_ASSOC);
        $fileName = $existingFile['File'] ?? "";
        
        if (isset($_POST['delete_file']) && $_POST['delete_file'] == '1' && !empty($fileName)) {
            if (file_exists($uploadDir . $fileName)) {
                @unlink($uploadDir . $fileName);
            }
            $fileName = "";
        }
    }
    
    if (isset($_FILES['UploadFile']) && $_FILES['UploadFile']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['UploadFile']['name'], PATHINFO_EXTENSION));
        $newName = "dl_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES['UploadFile']['tmp_name'], $uploadDir . $newName)) {
            if (!empty($fileName) && file_exists($uploadDir . $fileName)) {
                @unlink($uploadDir . $fileName);
            }
            $fileName = $newName;
        }
    }

    try {
        if ($downloadID > 0) {
            $stmt = $DB->prepare("UPDATE groundwater_docs SET GroupID = ?, TitleTH = ?, TitleEN = ?, DescriptionTH = ?, DescriptionEN = ?, File = ?, IsActive = ?, OrderNo = ?, UpdateDate = NOW() WHERE DocID = ?");
            $stmt->execute([$groupID, $titleTH, $titleEN, $detailTH, $detailEN, $fileName, $isActive, $orderNo, $downloadID]);
            $docIDToUse = $downloadID;
        } else {
            if (empty($orderNo)) {
                $stmtMax = $DB->prepare("SELECT MAX(OrderNo) FROM groundwater_docs");
                $stmtMax->execute();
                $maxOrder = $stmtMax->fetchColumn();
                $orderNo = $maxOrder ? $maxOrder + 1 : 1;
            }
            $stmt = $DB->prepare("INSERT INTO groundwater_docs (GroupID, TitleTH, TitleEN, DescriptionTH, DescriptionEN, File, IsActive, OrderNo, Views, CreateDate, UpdateDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())");
            $stmt->execute([$groupID, $titleTH, $titleEN, $detailTH, $detailEN, $fileName, $isActive, $orderNo]);
            $docIDToUse = $DB->lastInsertId();
        }
        
        // Handle deletion of specific attachments
        if (!empty($_POST['delete_attachments']) && is_array($_POST['delete_attachments'])) {
            foreach ($_POST['delete_attachments'] as $attId) {
                $stmtF = $DB->prepare("SELECT FilePath FROM groundwater_doc_attachments WHERE AttachmentID = ?");
                $stmtF->execute([$attId]);
                $fPath = $stmtF->fetchColumn();
                if ($fPath && file_exists($uploadDir . $fPath)) {
                    @unlink($uploadDir . $fPath);
                }
                $DB->prepare("DELETE FROM groundwater_doc_attachments WHERE AttachmentID = ?")->execute([$attId]);
            }
        }
        
        // Handle multiple file uploads
        if (isset($_FILES['UploadFiles'])) {
            $totalFiles = count($_FILES['UploadFiles']['name']);
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['UploadFiles']['error'][$i] == 0) {
                    $ext = strtolower(pathinfo($_FILES['UploadFiles']['name'][$i], PATHINFO_EXTENSION));
                    $size = $_FILES['UploadFiles']['size'][$i];
                    $newName = "dl_" . time() . "_" . uniqid() . "." . $ext;
                    if (move_uploaded_file($_FILES['UploadFiles']['tmp_name'][$i], $uploadDir . $newName)) {
                        $stmtAtt = $DB->prepare("INSERT INTO groundwater_doc_attachments (DocID, FilePath, FileType, FileSize, Downloads, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, 0, 0, NOW(), ?, ?, 'N')");
                        $stmtAtt->execute([$docIDToUse, $newName, $ext, $size, $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']]);
                    }
                }
            }
        }

        $logAction = ($downloadID > 0 ? 'แก้ไขประกอบกิจการน้ำบาดาล ID: ' : 'เพิ่มประกอบกิจการน้ำบาดาล ID: ') . $docIDToUse;
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, ($downloadID > 0 ? $downloadID : $DB->lastInsertId()));
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        try {
            $DB->prepare("UPDATE groundwater_doc_attachments SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID = ?")->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id]);
            
            $stmt = $DB->prepare("UPDATE groundwater_docs SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID = ?");
            $stmt->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id]);
            $MS->StaffLog('ลบประกอบกิจการน้ำบาดาล ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, $id);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
} elseif ($action == 'bulk_active' || $action == 'bulk_inactive' || $action == 'bulk_delete') {
    $ids = $_GET['ids'] ?? '';
    if (!empty($ids)) {
        $idArray = array_map('intval', explode(',', $ids));
        if (count($idArray) > 0) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            
            if ($action == 'bulk_active') {
                $params = $idArray;
                $sql = "UPDATE groundwater_docs SET IsActive = 'Y', UpdateDate = NOW() WHERE DocID IN ($placeholders)";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_inactive') {
                $params = $idArray;
                $sql = "UPDATE groundwater_docs SET IsActive = 'N', UpdateDate = NOW() WHERE DocID IN ($placeholders)";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_delete') {
                $params = $idArray;
                $DB->prepare("UPDATE groundwater_doc_attachments SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID IN ($placeholders)")->execute(array_merge([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], $params));
                
                $sqlDel = "UPDATE groundwater_docs SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID IN ($placeholders)";
                $stmtDel = $DB->prepare($sqlDel);
                $stmtDel->execute(array_merge([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], $params));
                $MS->StaffLog('ลบประกอบกิจการน้ำบาดาล Bulk ID: ' . $ids, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, 0);
            }
        }
    }
    header("Location: index.php");
    exit;
}
?>