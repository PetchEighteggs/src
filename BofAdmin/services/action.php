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

$uploadDir = $sServerDirFile . "services/";
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
    $groupID = 0; // Fixed group ID to 0
    $serviceType = $_POST['ServiceType'] ?? 'situation';

    if ($downloadID > 0) {
        $stmtType = $DB->prepare("SELECT ServiceType FROM service_docs WHERE DocID = ?");
        $stmtType->execute([$downloadID]);
        $storedType = $stmtType->fetchColumn();
        if ($storedType !== false) $serviceType = $storedType;
    }
    if ($serviceType === 'infographic') {
        $uploadDir = dirname(__DIR__, 2) . '/uploads/images/services/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
            http_response_code(500);
            exit('ไม่สามารถสร้างโฟลเดอร์อัปโหลดรูปภาพได้');
        }
        // Validate all images before deleting attachments or saving records.
        $imageUploads = [];
        if (isset($_FILES['UploadFile'])) $imageUploads[] = $_FILES['UploadFile'];
        if (isset($_FILES['UploadFiles'])) {
            foreach ($_FILES['UploadFiles']['name'] as $i => $name) {
                $imageUploads[] = [
                    'name' => $name,
                    'tmp_name' => $_FILES['UploadFiles']['tmp_name'][$i],
                    'error' => $_FILES['UploadFiles']['error'][$i],
                    'size' => $_FILES['UploadFiles']['size'][$i]
                ];
            }
        }
        $imageTypes = ['jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG];
        foreach ($imageUploads as $upload) {
            if ($upload['error'] === UPLOAD_ERR_NO_FILE) continue;
            $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
            $info = $upload['error'] === UPLOAD_ERR_OK ? @getimagesize($upload['tmp_name']) : false;
            if (!$info || !isset($imageTypes[$ext]) || $info[2] !== $imageTypes[$ext]
                || $upload['size'] > CONF_UPLOAD_MAX_MB * 1024 * 1024) {
                http_response_code(400);
                exit('กรุณาอัปโหลดรูปภาพ JPG, PNG ขนาดไม่เกิน ' . CONF_UPLOAD_MAX_MB . ' MB');
            }
        }
    }

    $titleTH = $_POST['TitleTH'] ?? '';
    $titleEN = $_POST['TitleEN'] ?? '';
    $detailTH = $_POST['DescriptionTH'] ?? '';
    $detailEN = $_POST['DescriptionEN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = isset($_POST['OrderNo']) ? $_POST['OrderNo'] : null;
    
    $fileName = "";
    if ($downloadID > 0) {
        if ($orderNo === null) {
            $stmtOrder = $DB->prepare("SELECT OrderNo FROM service_docs WHERE DocID = ?");
            $stmtOrder->execute([$downloadID]);
            $orderNo = (int)$stmtOrder->fetchColumn();
        }
        $stmtFile = $DB->prepare("SELECT File FROM service_docs WHERE DocID = ?");
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
            $stmt = $DB->prepare("UPDATE service_docs SET GroupID = ?, ServiceType = ?, TitleTH = ?, TitleEN = ?, DescriptionTH = ?, DescriptionEN = ?, File = ?, IsActive = ?, OrderNo = ?, UpdateDate = NOW() WHERE DocID = ?");
            $stmt->execute([$groupID, $serviceType, $titleTH, $titleEN, $detailTH, $detailEN, $fileName, $isActive, $orderNo, $downloadID]);
            $docIDToUse = $downloadID;
        } else {
            if (empty($orderNo)) {
                $stmtMax = $DB->prepare("SELECT MAX(OrderNo) FROM service_docs WHERE ServiceType = ?");
                $stmtMax->execute([$serviceType]);
                $maxOrder = $stmtMax->fetchColumn();
                $orderNo = $maxOrder ? $maxOrder + 1 : 1;
            }
            $stmt = $DB->prepare("INSERT INTO service_docs (GroupID, ServiceType, TitleTH, TitleEN, DescriptionTH, DescriptionEN, File, IsActive, OrderNo, Views, CreateDate, UpdateDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())");
            $stmt->execute([$groupID, $serviceType, $titleTH, $titleEN, $detailTH, $detailEN, $fileName, $isActive, $orderNo]);
            $docIDToUse = $DB->lastInsertId();
        }
        
        // Handle deletion of specific attachments
        if (!empty($_POST['delete_attachments']) && is_array($_POST['delete_attachments'])) {
            foreach ($_POST['delete_attachments'] as $attId) {
                $stmtF = $DB->prepare("SELECT FilePath FROM service_doc_attachments WHERE AttachID = ? AND DocID = ?");
                $stmtF->execute([$attId, $docIDToUse]);
                $fPath = $stmtF->fetchColumn();
                if ($fPath && file_exists($uploadDir . $fPath)) {
                    @unlink($uploadDir . $fPath);
                }
                $DB->prepare("DELETE FROM service_doc_attachments WHERE AttachID = ? AND DocID = ?")->execute([$attId, $docIDToUse]);
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
                        $stmtAtt = $DB->prepare("INSERT INTO service_doc_attachments (DocID, FilePath, FileType, FileSize, Downloads, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, 0, 0, NOW(), ?, ?, 'N')");
                        $stmtAtt->execute([$docIDToUse, $newName, $ext, $size, $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']]);
                    }
                }
            }
        }

        $moduleNames = ['infographic' => 'สื่อประชาสัมพันธ์', 'situation' => 'สถานการณ์น้ำบาดาล', 'reports' => 'รายงานเกี่ยวกับน้ำบาดาล', 'mou' => 'บันทึกความเข้าใจ', 'laws' => 'กฎหมายน้ำบาดาล', 'downloads' => 'สื่อดาวน์โหลด', 'standards' => 'มาตรฐานและราคากลางการเจาะน้ำบาดาล', 'manuals' => 'คู่มือสำหรับประชาชน'];
        $moduleName = $moduleNames[$serviceType] ?? 'ข้อมูลบริการ';
        $logAction = ($downloadID > 0 ? 'แก้ไข' : 'เพิ่ม') . $moduleName . ' ID: ' . $docIDToUse;
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, ($downloadID > 0 ? $downloadID : $DB->lastInsertId()));
        header("Location: index.php?type=".urlencode($serviceType));
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    $serviceType = $_GET['type'] ?? 'situation';
    if ($id > 0) {
        try {
            $DB->prepare("UPDATE service_doc_attachments SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID = ?")->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id]);
            
            $stmt = $DB->prepare("UPDATE service_docs SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID = ?");
            $stmt->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id]);
            $moduleNames = ['infographic' => 'สื่อประชาสัมพันธ์', 'situation' => 'สถานการณ์น้ำบาดาล', 'reports' => 'รายงานเกี่ยวกับน้ำบาดาล', 'mou' => 'บันทึกความเข้าใจ', 'laws' => 'กฎหมายน้ำบาดาล', 'downloads' => 'สื่อดาวน์โหลด', 'standards' => 'มาตรฐานและราคากลางการเจาะน้ำบาดาล', 'manuals' => 'คู่มือสำหรับประชาชน'];
            $moduleName = $moduleNames[$serviceType] ?? 'ข้อมูลบริการ';
            $MS->StaffLog('ลบ' . $moduleName . ' ID: ' . $id, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, $id);
            header("Location: index.php?type=".urlencode($serviceType));
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
} elseif ($action == 'bulk_active' || $action == 'bulk_inactive' || $action == 'bulk_delete') {
    $ids = $_GET['ids'] ?? '';
    $serviceType = $_GET['type'] ?? 'situation';
    if (!empty($ids)) {
        $idArray = array_map('intval', explode(',', $ids));
        if (count($idArray) > 0) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            
            if ($action == 'bulk_active') {
                $params = $idArray;
                $sql = "UPDATE service_docs SET IsActive = 'Y', UpdateDate = NOW() WHERE DocID IN ($placeholders)";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_inactive') {
                $params = $idArray;
                $sql = "UPDATE service_docs SET IsActive = 'N', UpdateDate = NOW() WHERE DocID IN ($placeholders)";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_delete') {
                $params = $idArray;
                $DB->prepare("UPDATE service_doc_attachments SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID IN ($placeholders)")->execute(array_merge([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], $params));
                
                $sqlDel = "UPDATE service_docs SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID IN ($placeholders)";
                $stmtDel = $DB->prepare($sqlDel);
                $stmtDel->execute(array_merge([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], $params));
                $moduleNames = ['infographic' => 'สื่อประชาสัมพันธ์', 'situation' => 'สถานการณ์น้ำบาดาล', 'reports' => 'รายงานเกี่ยวกับน้ำบาดาล', 'mou' => 'บันทึกความเข้าใจ', 'laws' => 'กฎหมายน้ำบาดาล', 'downloads' => 'สื่อดาวน์โหลด', 'standards' => 'มาตรฐานและราคากลางการเจาะน้ำบาดาล', 'manuals' => 'คู่มือสำหรับประชาชน'];
                $moduleName = $moduleNames[$serviceType] ?? 'ข้อมูลบริการ';
                $MS->StaffLog('ลบ' . $moduleName . ' Bulk ID: ' . $ids, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, '', 10, 0);
            }
        }
    }
    header("Location: index.php?type=" . urlencode($serviceType));
    exit;
}
?>
