<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
include_once('../../config/func.class.php'); 
$DB = mosConnectPDO();
$MS = new MS($DB);

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    $uploadDir = $sServerImgDir;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $isActive = $_POST['IsActive'] ?? 'Y';
    $cioID = !empty($_POST['CioID']) ? $_POST['CioID'] : null;
    
    // Fix empty dates to NULL
    $eventStartDate = !empty($_POST['EventStartDate']) ? $_POST['EventStartDate'] : null;
    $eventEndDate = !empty($_POST['EventEndDate']) ? $_POST['EventEndDate'] : null;

    if ($eventStartDate) {
        $parts = explode('/', $eventStartDate);
        if (count($parts) === 3) {
            $eventStartDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
    }
    if ($eventEndDate) {
        $parts = explode('/', $eventEndDate);
        if (count($parts) === 3) {
            $eventEndDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
    }

    if ($action === 'add') {
        $coverPath = '';
        if (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
            $eventsUploadDir = $sServerImgDir . 'cio_events/';
            if (!is_dir($eventsUploadDir)) mkdir($eventsUploadDir, 0777, true);
            $res = FU::uploadNewImageAndResize($_FILES['CoverImage'], $eventsUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) $coverPath = $res['sFileName'];
        }

        try {
            $stmt = $DB->prepare("INSERT INTO cio_events (
                CioID, Title_TH, Title_EN, Content_TH, Content_EN, 
                EventStartDate, EventEndDate, TimeFrom, TimeTo, 
                Location_TH, Location_EN, CoverImage, IsActive, 
                CreateDate, CreateBy, CreateIP) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
            $stmt->execute([
                $cioID, $_POST['Title_TH'], $_POST['Title_EN'], $_POST['Content_TH'], $_POST['Content_EN'], 
                $eventStartDate, $eventEndDate, $_POST['TimeFrom'], $_POST['TimeTo'], 
                $_POST['Location_TH'], $_POST['Location_EN'], $coverPath, $isActive, 
                $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']
            ]);
            $newId = $DB->lastInsertId();

            $MS->StaffLog('เพิ่มปฏิทินกิจกรรม CIO', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, 13, $newId);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    } 
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $coverPath = $_POST['old_cover'] ?? '';
        
        if (isset($_POST['deleteCoverImageFlag']) && $_POST['deleteCoverImageFlag'] == '1') {
            if (!empty($coverPath) && file_exists($sServerImgDir . 'cio_events/' . $coverPath)) {
                unlink($sServerImgDir . 'cio_events/' . $coverPath);
                $webpPath = preg_replace('/\.[^.]+$/', '.webp', $coverPath);
                if (file_exists($sServerImgDir . 'cio_events/' . $webpPath)) unlink($sServerImgDir . 'cio_events/' . $webpPath);
            }
            $coverPath = '';
        } elseif (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
            $oldFilename = basename($coverPath);
            $eventsUploadDir = $sServerImgDir . 'cio_events/';
            if (!is_dir($eventsUploadDir)) mkdir($eventsUploadDir, 0777, true);
            $res = FU::uploadEditImageAndResize($_FILES['CoverImage'], $eventsUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, $oldFilename, 1);
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) $coverPath = $res['sFileName'];
        }

        try {
            $stmt = $DB->prepare("UPDATE cio_events SET 
                CioID=?, Title_TH=?, Title_EN=?, Content_TH=?, Content_EN=?, 
                EventStartDate=?, EventEndDate=?, TimeFrom=?, TimeTo=?, 
                Location_TH=?, Location_EN=?, CoverImage=?, IsActive=?, 
                UpdateDate=NOW(), UpdateBy=?, UpdateIP=? 
                WHERE EventID=?");
            $stmt->execute([
                $cioID, $_POST['Title_TH'], $_POST['Title_EN'], $_POST['Content_TH'], $_POST['Content_EN'], 
                $eventStartDate, $eventEndDate, $_POST['TimeFrom'], $_POST['TimeTo'], 
                $_POST['Location_TH'], $_POST['Location_EN'], $coverPath, $isActive, 
                $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id
            ]);

            $MS->StaffLog('แก้ไขปฏิทินกิจกรรม CIO', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, 13, $id);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id'];
    try {
        $stmt = $DB->prepare("UPDATE cio_events SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE EventID=?");
        $stmt->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id]);
        
        $MS->StaffLog('ลบปฏิทินกิจกรรม CIO', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, 13, $id);
        
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
                $params = array_merge([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], $idArray);
                
                $stmt = $DB->prepare("UPDATE cio_events SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE EventID IN ($placeholders)");
                $stmt->execute($params);
                
                $MS->StaffLog('ลบปฏิทินกิจกรรม CIO', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, 13);
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
                
                $stmt = $DB->prepare("UPDATE cio_events SET IsActive=? WHERE EventID IN ($placeholders)");
                $stmt->execute($params);
                
                $MS->StaffLog('เปลี่ยนสถานะปฏิทินกิจกรรม CIO', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, 13);
            }
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
}

