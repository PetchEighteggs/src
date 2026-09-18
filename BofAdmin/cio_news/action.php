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

    // helper to convert d/m/Y to Y-m-d
    function convertDateToDB($dateStr) {
        if (empty($dateStr)) return null;
        $parts = explode('/', $dateStr);
        if (count($parts) === 3) {
            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
        return $dateStr;
    }

    $uploadDir = $sServerImgDir;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    

    $cioId = !empty($_POST['CioID']) ? $_POST['CioID'] : null;
    $isActive = $_POST['IsActive'] ?? 'N';
    $publishDate = convertDateToDB($_POST['PublishDate'] ?? '');

    if ($action === 'add') {


        $coverPath = '';
        if (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
            $newsUploadDir = $sServerImgDir . 'cio_news/';
            if (!is_dir($newsUploadDir)) mkdir($newsUploadDir, 0777, true);
            $res = FU::uploadNewImageAndResize($_FILES['CoverImage'], $newsUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) $coverPath = $res['sFileName'];
        }

        try {
            $stmt = $DB->prepare("INSERT INTO cio_news 
                (CioID, Title_TH, Title_EN, Content_TH, Content_EN, CoverImage, PublishDate, Views, IsActive, CreateDate, CreateBy, CreateIP) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), ?, ?)");
            $stmt->execute([
                $cioId, $_POST['Title_TH'] ?? '', $_POST['Title_EN'] ?? '', 
                $_POST['Content_TH'] ?? '', $_POST['Content_EN'] ?? '',
                $coverPath, $publishDate, $isActive, $StaffID, $_SERVER['REMOTE_ADDR']
            ]);
            $newId = $DB->lastInsertId();
            
            $MS->StaffLog('เพิ่มข่าวสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $newId);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    } 
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $coverPath = $_POST['old_cover'] ?? '';
        
        if (isset($_POST['deleteCoverImageFlag']) && $_POST['deleteCoverImageFlag'] == '1') {
            if (!empty($coverPath) && file_exists($sServerImgDir . 'cio_news/' . $coverPath)) {
                unlink($sServerImgDir . 'cio_news/' . $coverPath);
                $webpPath = preg_replace('/\.[^.]+$/', '.webp', $coverPath);
                if (file_exists($sServerImgDir . 'cio_news/' . $webpPath)) unlink($sServerImgDir . 'cio_news/' . $webpPath);
            }
            $coverPath = '';
            if (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
                $newsUploadDir = $sServerImgDir . 'cio_news/';
                if (!is_dir($newsUploadDir)) mkdir($newsUploadDir, 0777, true);
                $res = FU::uploadNewImageAndResize($_FILES['CoverImage'], $newsUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
                if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
                    $coverPath = $res['sFileName'];
                }
            }
        } elseif (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
            $oldFilename = basename($coverPath);
            $newsUploadDir = $sServerImgDir . 'cio_news/';
            if (!is_dir($newsUploadDir)) mkdir($newsUploadDir, 0777, true);
            $res = FU::uploadEditImageAndResize($_FILES['CoverImage'], $newsUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, $oldFilename, 1);
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) $coverPath = $res['sFileName'];
        }



        try {
            $stmt = $DB->prepare("UPDATE cio_news SET 
                CioID=?, Title_TH=?, Title_EN=?, Content_TH=?, Content_EN=?, 
                CoverImage=?, PublishDate=?, IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? 
                WHERE NewsID=?");
            $stmt->execute([
                $cioId, $_POST['Title_TH'] ?? '', $_POST['Title_EN'] ?? '', 
                $_POST['Content_TH'] ?? '', $_POST['Content_EN'] ?? '',
                $coverPath, $publishDate, $isActive, $StaffID, $_SERVER['REMOTE_ADDR'], $id
            ]);
            
            $MS->StaffLog('แก้ไขข่าวสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id'];
    try {
        $stmtImg = $DB->prepare("SELECT CoverImage FROM cio_news WHERE NewsID = ?");
        $stmtImg->execute([$id]);
        if ($row = $stmtImg->fetch(PDO::FETCH_ASSOC)) {
            $coverImageStr = $row['CoverImage'];
            if (!empty($coverImageStr)) {
                $path = $sServerImgDir . 'cio_news/' . $coverImageStr;
                if (file_exists($path) && is_file($path)) {
                    unlink($path);
                    $webpPath = preg_replace('/\.[^.]+$/', '.webp', $path);
                    if (file_exists($webpPath) && is_file($webpPath)) unlink($webpPath);
                }
            }
        }

        $stmt = $DB->prepare("UPDATE cio_news SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE NewsID=?");
        $stmt->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $id]);
        
        $MS->StaffLog('ลบข่าวสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'bulk_delete') {
    $ids = $_GET['ids'] ?? '';
    if (!empty($ids)) {
        try {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                
                $stmtImg = $DB->prepare("SELECT CoverImage FROM cio_news WHERE NewsID IN ($placeholders)");
                $stmtImg->execute($idArray);
                while ($row = $stmtImg->fetch(PDO::FETCH_ASSOC)) {
                    $coverImageStr = $row['CoverImage'];
                    if (!empty($coverImageStr)) {
                        $path = $sServerImgDir . 'cio_news/' . $coverImageStr;
                        if (file_exists($path) && is_file($path)) {
                            unlink($path);
                            $webpPath = preg_replace('/\.[^.]+$/', '.webp', $path);
                            if (file_exists($webpPath) && is_file($webpPath)) unlink($webpPath);
                        }
                    }
                }

                $params = array_merge([$StaffID, $_SERVER['REMOTE_ADDR']], $idArray);
                
                $stmt = $DB->prepare("UPDATE cio_news SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE NewsID IN ($placeholders)");
                $stmt->execute($params);
                
                $MS->StaffLog('ลบข่าวสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
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
                
                $stmt = $DB->prepare("UPDATE cio_news SET IsActive=? WHERE NewsID IN ($placeholders)");
                $stmt->execute($params);
                
                $MS->StaffLog('เปลี่ยนสถานะข่าวสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
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
            $stmt = $DB->prepare("UPDATE cio_news SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE NewsID=?");
            $stmt->execute([$status, $StaffID, $_SERVER['REMOTE_ADDR'], $id]);
            
            $MS->StaffLog('เปลี่ยนสถานะข่าวสาร CIO', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
}
?>
