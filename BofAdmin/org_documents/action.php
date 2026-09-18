<?php
define('_VALID_ACCESS', 1);
session_start();
require_once('../../configuration.php');
require_once('../../config/ms_com.php');
require_once('../../config/ms.class.php');
require_once('../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

$action = $_REQUEST['action'] ?? '';

$uploadDir = $sServerDirFile . "org_documents/";
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
    $serviceType = $_POST['ServiceType'] ?? 'policy';

    $titleTH = $_POST['TitleTH'] ?? '';
    $titleEN = $_POST['TitleEN'] ?? '';
    $detailTH = $_POST['DescriptionTH'] ?? '';
    $detailEN = $_POST['DescriptionEN'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = isset($_POST['OrderNo']) ? $_POST['OrderNo'] : null;
    
    $fileName = "";
    if ($downloadID > 0) {
        if ($orderNo === null) {
            $stmtOrder = $DB->prepare("SELECT OrderNo FROM org_docs WHERE DocID = ?");
            $stmtOrder->execute([$downloadID]);
            $orderNo = (int)$stmtOrder->fetchColumn();
        }
        $stmtFile = $DB->prepare("SELECT File FROM org_docs WHERE DocID = ?");
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
        $newName = "org_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES['UploadFile']['tmp_name'], $uploadDir . $newName)) {
            if (!empty($fileName) && file_exists($uploadDir . $fileName)) {
                @unlink($uploadDir . $fileName);
            }
            $fileName = $newName;
        }
    }

    try {
        if ($downloadID > 0) {
            $stmt = $DB->prepare("UPDATE org_docs SET GroupID = ?, ServiceType = ?, TitleTH = ?, TitleEN = ?, DescriptionTH = ?, DescriptionEN = ?, File = ?, IsActive = ?, OrderNo = ?, UpdateDate = NOW() WHERE DocID = ?");
            $stmt->execute([$groupID, $serviceType, $titleTH, $titleEN, $detailTH, $detailEN, $fileName, $isActive, $orderNo, $downloadID]);
            $docIDToUse = $downloadID;
        } else {
            if (empty($orderNo)) {
                $stmtMax = $DB->prepare("SELECT MAX(OrderNo) FROM org_docs WHERE ServiceType = ?");
                $stmtMax->execute([$serviceType]);
                $maxOrder = $stmtMax->fetchColumn();
                $orderNo = $maxOrder ? $maxOrder + 1 : 1;
            }
            $stmt = $DB->prepare("INSERT INTO org_docs (GroupID, ServiceType, TitleTH, TitleEN, DescriptionTH, DescriptionEN, File, IsActive, OrderNo, Views, CreateDate, UpdateDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())");
            $stmt->execute([$groupID, $serviceType, $titleTH, $titleEN, $detailTH, $detailEN, $fileName, $isActive, $orderNo]);
            $docIDToUse = $DB->lastInsertId();
        }
        
        // Handle deletion of specific attachments
        if (!empty($_POST['delete_attachments']) && is_array($_POST['delete_attachments'])) {
            foreach ($_POST['delete_attachments'] as $attId) {
                $stmtF = $DB->prepare("SELECT FilePath FROM org_doc_attachments WHERE AttachID = ?");
                $stmtF->execute([$attId]);
                $fPath = $stmtF->fetchColumn();
                if ($fPath && file_exists($uploadDir . $fPath)) {
                    @unlink($uploadDir . $fPath);
                }
                $DB->prepare("DELETE FROM org_doc_attachments WHERE AttachID = ?")->execute([$attId]);
            }
        }
        
        // Handle multiple file uploads
        if (isset($_FILES['UploadFiles'])) {
            $totalFiles = count($_FILES['UploadFiles']['name']);
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['UploadFiles']['error'][$i] == 0) {
                    $ext = strtolower(pathinfo($_FILES['UploadFiles']['name'][$i], PATHINFO_EXTENSION));
                    $size = $_FILES['UploadFiles']['size'][$i];
                    $originalName = $_FILES['UploadFiles']['name'][$i];
                    $newName = "org_" . time() . "_" . uniqid() . "." . $ext;
                    if (move_uploaded_file($_FILES['UploadFiles']['tmp_name'][$i], $uploadDir . $newName)) {
                        $stmtAtt = $DB->prepare("INSERT INTO org_doc_attachments (DocID, FileName, FilePath, FileType, FileSize, Downloads, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, 0, 0, NOW(), ?, ?, 'N')");
                        $stmtAtt->execute([$docIDToUse, $originalName, $newName, $ext, $size, $StaffID, $_SERVER['REMOTE_ADDR']]);
                    }
                }
            }
        }

        $moduleNames = ['policy' => 'นโยบายและยุทธศาสตร์', 'digital_plan' => 'แผนปฏิบัติการดิจิทัล', 'annual_plan' => 'แผนปฏิบัติราชการประจำปี', 'budget' => 'งบประมาณรายจ่ายประจำปี', 'report' => 'คำรับรอง/รายงานผล'];
        $moduleName = $moduleNames[$serviceType] ?? 'เอกสารองค์กร';
        $logAction = ($downloadID > 0 ? 'แก้ไข' : 'เพิ่ม') . $moduleName . ' ID: ' . $docIDToUse;
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $StaffID, '', 106, ($downloadID > 0 ? $downloadID : $DB->lastInsertId()));
        header("Location: index.php?type=".urlencode($serviceType));
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    $serviceType = $_GET['type'] ?? 'policy';
    if ($id > 0) {
        try {
            $DB->prepare("UPDATE org_doc_attachments SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID = ?")->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $id]);
            
            $stmt = $DB->prepare("UPDATE org_docs SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID = ?");
            $stmt->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $id]);
            $moduleNames = ['policy' => 'นโยบายและยุทธศาสตร์', 'digital_plan' => 'แผนปฏิบัติการดิจิทัล', 'annual_plan' => 'แผนปฏิบัติราชการประจำปี', 'budget' => 'งบประมาณรายจ่ายประจำปี', 'report' => 'คำรับรอง/รายงานผล'];
            $moduleName = $moduleNames[$serviceType] ?? 'เอกสารองค์กร';
            $MS->StaffLog('ลบ' . $moduleName . ' ID: ' . $id, $_SERVER['REQUEST_URI'], $StaffID, '', 106, $id);
            header("Location: index.php?type=".urlencode($serviceType));
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
} elseif ($action == 'bulk_active' || $action == 'bulk_inactive' || $action == 'bulk_delete') {
    $ids = $_GET['ids'] ?? '';
    $serviceType = $_GET['type'] ?? 'policy';
    if (!empty($ids)) {
        $idArray = array_map('intval', explode(',', $ids));
        if (count($idArray) > 0) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            
            if ($action == 'bulk_active') {
                $params = $idArray;
                $sql = "UPDATE org_docs SET IsActive = 'Y', UpdateDate = NOW() WHERE DocID IN ($placeholders)";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_inactive') {
                $params = $idArray;
                $sql = "UPDATE org_docs SET IsActive = 'N', UpdateDate = NOW() WHERE DocID IN ($placeholders)";
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
            } else if ($action == 'bulk_delete') {
                $params = $idArray;
                $DB->prepare("UPDATE org_doc_attachments SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID IN ($placeholders)")->execute(array_merge([$StaffID, $_SERVER['REMOTE_ADDR']], $params));
                
                $sqlDel = "UPDATE org_docs SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID IN ($placeholders)";
                $stmtDel = $DB->prepare($sqlDel);
                $stmtDel->execute(array_merge([$StaffID, $_SERVER['REMOTE_ADDR']], $params));
                $moduleNames = ['policy' => 'นโยบายและยุทธศาสตร์', 'digital_plan' => 'แผนปฏิบัติการดิจิทัล', 'annual_plan' => 'แผนปฏิบัติราชการประจำปี', 'budget' => 'งบประมาณรายจ่ายประจำปี', 'report' => 'คำรับรอง/รายงานผล'];
                $moduleName = $moduleNames[$serviceType] ?? 'เอกสารองค์กร';
                $MS->StaffLog('ลบ' . $moduleName . ' Bulk ID: ' . $ids, $_SERVER['REQUEST_URI'], $StaffID, '', 106, 0);
            }
        }
    }
    header("Location: index.php?type=" . urlencode($serviceType));
    exit;
}
?>
