<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
include_once('../../config/func.class.php'); 
require_once('../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $uploadDir = $sServerDirFile . 'cio_documents/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    

    $cioId = !empty($_POST['CioID']) ? $_POST['CioID'] : null;
    $isActive = $_POST['IsActive'] ?? 'N';
    $titleTh = $_POST['Title_TH'] ?? '';
    $titleEn = $_POST['Title_EN'] ?? '';

    if ($action === 'add') {


        $filePath = '';
        $fileType = '';
        $fileSize = 0;

        if (isset($_FILES['FilePath']) && $_FILES['FilePath']['error'] === UPLOAD_ERR_OK) {
            $originalName = $_FILES['FilePath']['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar'];
            if (!in_array($ext, $allowedExts)) {
                echo "<script>alert('ประเภทไฟล์ไม่ได้รับอนุญาต (รองรับเฉพาะ PDF, Word, Excel, PowerPoint, ZIP, RAR)'); window.history.back();</script>";
                exit;
            }
            
            $fileName = time() . '_' . rand(1000, 9999) . '.' . $ext;
            
            if (move_uploaded_file($_FILES['FilePath']['tmp_name'], $uploadDir . $fileName)) {
                $filePath = $fileName;
                $fileType = $ext;
                $fileSize = filesize($uploadDir . $fileName);
            }
        }

        if (empty($filePath)) {
            echo "<script>alert('กรุณาอัพโหลดไฟล์เอกสาร'); window.history.back();</script>";
            exit;
        }

        try {
            $stmt = $DB->prepare("INSERT INTO cio_documents 
                (CioID, Title_TH, Title_EN, FilePath, FileType, FileSize, Downloads, IsActive, CreateDate, CreateBy, CreateIP, DelData, OrderNo) 
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW(), ?, ?, 'N', 9999)");
            $stmt->execute([
                $cioId, $titleTh, $titleEn, 
                $filePath, $fileType, $fileSize, 
                $isActive, $StaffID, $_SERVER['REMOTE_ADDR']
            ]);
            $newId = $DB->lastInsertId();
            
            $MS->StaffLog('เพิ่มเอกสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $newId);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    } 
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $filePath = $_POST['old_file'] ?? '';
        $fileType = $_POST['old_file_type'] ?? '';
        $fileSize = (int)($_POST['old_file_size'] ?? 0);
        
        if (isset($_POST['deleteFileFlag']) && $_POST['deleteFileFlag'] == '1') {
            if (!empty($filePath) && file_exists($uploadDir . $filePath)) {
                unlink($uploadDir . $filePath);
            }
            $filePath = '';
            $fileType = '';
            $fileSize = 0;
            
            if (isset($_FILES['FilePath']) && $_FILES['FilePath']['error'] === UPLOAD_ERR_OK) {
                $originalName = $_FILES['FilePath']['name'];
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                
                $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar'];
                if (!in_array($ext, $allowedExts)) {
                    echo "<script>alert('ประเภทไฟล์ไม่ได้รับอนุญาต (รองรับเฉพาะ PDF, Word, Excel, PowerPoint, ZIP, RAR)'); window.history.back();</script>";
                    exit;
                }
                
                $fileName = time() . '_' . rand(1000, 9999) . '.' . $ext;
                
                if (move_uploaded_file($_FILES['FilePath']['tmp_name'], $uploadDir . $fileName)) {
                    $filePath = $fileName;
                    $fileType = $ext;
                    $fileSize = filesize($uploadDir . $fileName);
                }
            }
        } elseif (isset($_FILES['FilePath']) && $_FILES['FilePath']['error'] === UPLOAD_ERR_OK) {
            // New file selected but delete flag not explicitly set (just overwriting)
            if (!empty($filePath) && file_exists($uploadDir . $filePath)) {
                unlink($uploadDir . $filePath);
            }
            
            $originalName = $_FILES['FilePath']['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar'];
            if (!in_array($ext, $allowedExts)) {
                echo "<script>alert('ประเภทไฟล์ไม่ได้รับอนุญาต (รองรับเฉพาะ PDF, Word, Excel, PowerPoint, ZIP, RAR)'); window.history.back();</script>";
                exit;
            }
            
            $fileName = time() . '_' . rand(1000, 9999) . '.' . $ext;
            
            if (move_uploaded_file($_FILES['FilePath']['tmp_name'], $uploadDir . $fileName)) {
                $filePath = $fileName;
                $fileType = $ext;
                $fileSize = filesize($uploadDir . $fileName);
            }
        }



        if (empty($filePath)) {
            echo "<script>alert('กรุณาอัพโหลดไฟล์เอกสาร'); window.history.back();</script>";
            exit;
        }

        try {
            $stmt = $DB->prepare("UPDATE cio_documents SET 
                CioID=?, Title_TH=?, Title_EN=?, 
                FilePath=?, FileType=?, FileSize=?, 
                IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? 
                WHERE DocumentID=?");
            $stmt->execute([
                $cioId, $titleTh, $titleEn, 
                $filePath, $fileType, $fileSize, 
                $isActive, $StaffID, $_SERVER['REMOTE_ADDR'], $id
            ]);
            
            $MS->StaffLog('แก้ไขเอกสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id'];
    $uploadDir = $sServerDirFile . 'cio_documents/';
    
    try {
        $stmtFile = $DB->prepare("SELECT FilePath FROM cio_documents WHERE DocumentID = ?");
        $stmtFile->execute([$id]);
        if ($row = $stmtFile->fetch(PDO::FETCH_ASSOC)) {
            $filePathStr = $row['FilePath'];
            if (!empty($filePathStr)) {
                $path = $uploadDir . $filePathStr;
                if (file_exists($path) && is_file($path)) {
                    unlink($path);
                }
            }
        }

        $stmt = $DB->prepare("UPDATE cio_documents SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocumentID=?");
        $stmt->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $id]);
        
        $MS->StaffLog('ลบเอกสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'bulk_delete') {
    $ids = $_GET['ids'] ?? '';
    $uploadDir = $sServerDirFile . 'cio_documents/';
    
    if (!empty($ids)) {
        try {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                
                $stmtFile = $DB->prepare("SELECT FilePath FROM cio_documents WHERE DocumentID IN ($placeholders)");
                $stmtFile->execute($idArray);
                while ($row = $stmtFile->fetch(PDO::FETCH_ASSOC)) {
                    $filePathStr = $row['FilePath'];
                    if (!empty($filePathStr)) {
                        $path = $uploadDir . $filePathStr;
                        if (file_exists($path) && is_file($path)) {
                            unlink($path);
                        }
                    }
                }

                $params = array_merge([$StaffID, $_SERVER['REMOTE_ADDR']], $idArray);
                
                $stmt = $DB->prepare("UPDATE cio_documents SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocumentID IN ($placeholders)");
                $stmt->execute($params);
                
                $MS->StaffLog('ลบเอกสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
            }
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
} elseif (isset($_GET['action']) && in_array($_GET['action'], ['bulk_active', 'bulk_inactive'])) {
    $ids = $_GET['ids'] ?? '';
    if (!empty($ids)) {
        try {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $status = ($_GET['action'] === 'bulk_active') ? 'Y' : 'N';
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$status], $idArray);
                
                $stmt = $DB->prepare("UPDATE cio_documents SET IsActive=? WHERE DocumentID IN ($placeholders)");
                $stmt->execute($params);
                
                $MS->StaffLog('เปลี่ยนสถานะเอกสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
            }
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    $id = $_GET['id'] ?? 0;
    $status = $_GET['status'] ?? 'N';
    if ($id) {
        try {
            $stmt = $DB->prepare("UPDATE cio_documents SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE DocumentID=?");
            $stmt->execute([$status, $StaffID, $_SERVER['REMOTE_ADDR'], $id]);
            
            $MS->StaffLog('เปลี่ยนสถานะเอกสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
}
