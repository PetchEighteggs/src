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
bofSecurityRequireMethod(['POST']);
bofSecurityRequireCsrf();
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

// Auto-check and add URLTH, URLEN & CoverImage columns if not exist
try {
    $checkCol = $DB->query("SHOW COLUMNS FROM `videos` LIKE 'URLTH'")->fetch();
    if (!$checkCol) {
        $DB->exec("ALTER TABLE `videos` ADD COLUMN `URLTH` varchar(500) DEFAULT NULL");
    }
    $checkColEN = $DB->query("SHOW COLUMNS FROM `videos` LIKE 'URLEN'")->fetch();
    if (!$checkColEN) {
        $DB->exec("ALTER TABLE `videos` ADD COLUMN `URLEN` varchar(500) DEFAULT NULL");
    }
    $checkCol2 = $DB->query("SHOW COLUMNS FROM `videos` LIKE 'CoverImage'")->fetch();
    if (!$checkCol2) {
        $DB->exec("ALTER TABLE `videos` ADD COLUMN `CoverImage` varchar(255) DEFAULT NULL");
    }
    // Add soft delete columns if not exist
    $checkDel = $DB->query("SHOW COLUMNS FROM `videos` LIKE 'DelData'")->fetch();
    if (!$checkDel) {
        $DB->exec("ALTER TABLE `videos` ADD COLUMN `DelData` char(1) DEFAULT 'N'");
        $DB->exec("ALTER TABLE `videos` ADD COLUMN `DelDataDate` datetime DEFAULT NULL");
        $DB->exec("ALTER TABLE `videos` ADD COLUMN `DelDataBy` int DEFAULT NULL");
        $DB->exec("ALTER TABLE `videos` ADD COLUMN `DelDataIP` varchar(50) DEFAULT NULL");
    }
} catch (PDOException $e) {}

function formatToYMD($dateStr) {
    if (empty($dateStr)) return null;
    $parts = explode('/', $dateStr);
    if (count($parts) === 3) {
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return $dateStr;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $isActive = $_POST['IsActive'] ?? 'Y';
    $orderNo = isset($_POST['OrderNo']) ? $_POST['OrderNo'] : null;

    if ($action === 'save') {
        $docID = $_POST['DocID'] ?? 0;
        $groupID = $_POST['GroupID'] ?? 0;
        $titleTH = $_POST['TitleTH'] ?? '';
        $titleEN = $_POST['TitleEN'] ?? '';
        $descTH = $_POST['DetailTH'] ?? '';
        $descEN = $_POST['DetailEN'] ?? '';
        $youtubeURLTH = $_POST['URLTH'] ?? '';
        $youtubeURLEN = $_POST['URLEN'] ?? '';
        $startDate = !empty($_POST['StartDate']) ? formatToYMD($_POST['StartDate']) . ' 00:00:00' : null;
        $endDate = !empty($_POST['EndDate']) ? formatToYMD($_POST['EndDate']) . ' 23:59:59' : null;

        // Handle Cover Image
        $coverPath = '';
        if ($docID > 0) {
            // Fetch existing cover image
            $stmtCover = $DB->prepare("SELECT CoverImage FROM videos WHERE DocID = ?");
            $stmtCover->execute([$docID]);
            $existing = $stmtCover->fetch(PDO::FETCH_ASSOC);
            $coverPath = $existing['CoverImage'] ?? '';
        }

        // Check if user wants to delete cover image
        if (isset($_POST['deleteCoverImageFlag']) && $_POST['deleteCoverImageFlag'] == '1') {
            if (!empty($coverPath) && file_exists($sServerImgDir . 'videos/' . $coverPath)) {
                unlink($sServerImgDir . 'videos/' . $coverPath);
                $webpPath = preg_replace('/\.[^.]+$/', '.webp', $coverPath);
                if (file_exists($sServerImgDir . 'videos/' . $webpPath)) unlink($sServerImgDir . 'videos/' . $webpPath);
            }
            $coverPath = '';
            // If new file uploaded after delete
            if (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
                $imgUploadDir = $sServerImgDir . 'videos/';
                if (!is_dir($imgUploadDir)) mkdir($imgUploadDir, 0777, true);
                $res = FU::uploadNewImageAndResize($_FILES['CoverImage'], $imgUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
                if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
                    $coverPath = $res['sFileName'];
                }
            }
        } elseif (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
            $imgUploadDir = $sServerImgDir . 'videos/';
            if (!is_dir($imgUploadDir)) mkdir($imgUploadDir, 0777, true);
            if ($docID > 0 && !empty($coverPath)) {
                $oldFilename = basename($coverPath);
                $res = FU::uploadEditImageAndResize($_FILES['CoverImage'], $imgUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, $oldFilename, 1);
            } else {
                $res = FU::uploadNewImageAndResize($_FILES['CoverImage'], $imgUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
            }
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) $coverPath = $res['sFileName'];
        }

        try {
            if ($docID > 0) {
                if ($orderNo === null) {
                    $stmtOrder = $DB->prepare("SELECT OrderNo FROM videos WHERE DocID = ?");
                    $stmtOrder->execute([$docID]);
                    $orderNo = (int)$stmtOrder->fetchColumn();
                }
                $stmt = $DB->prepare("UPDATE videos SET 
                    GroupID=?, TitleTH=?, TitleEN=?, DetailTH=?, DetailEN=?, 
                    URLTH=?, URLEN=?, StartDate=?, EndDate=?, CoverImage=?, IsActive=?, OrderNo=?, 
                    UpdateDate=NOW(), UpdateBy=?, UpdateIP=? 
                    WHERE DocID=?");
                $stmt->execute([
                    $groupID, $titleTH, $titleEN, $descTH, $descEN,
                    $youtubeURLTH, $youtubeURLEN, $startDate, $endDate, $coverPath, $isActive, $orderNo,
                    $StaffID, $_SERVER['REMOTE_ADDR'], $docID
                ]);
                $MS->StaffLog('แก้ไขวีดิทัศน์', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $docID);
            } else {
                if (empty($orderNo)) {
                    $stmtMax = $DB->prepare("SELECT MAX(OrderNo) FROM videos");
                    $stmtMax->execute();
                    $maxOrder = $stmtMax->fetchColumn();
                    $orderNo = $maxOrder ? $maxOrder + 1 : 1;
                }
                $stmt = $DB->prepare("INSERT INTO videos 
                    (GroupID, TitleTH, TitleEN, DetailTH, DetailEN, URLTH, URLEN, StartDate, EndDate, CoverImage, IsActive, OrderNo, Views, CreateDate, CreateBy, CreateIP, DelData) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?, ?, 'N')");
                $stmt->execute([
                    $groupID, $titleTH, $titleEN, $descTH, $descEN,
                    $youtubeURLTH, $youtubeURLEN, $startDate, $endDate, $coverPath, $isActive, $orderNo,
                    $StaffID, $_SERVER['REMOTE_ADDR']
                ]);
                $newId = $DB->lastInsertId();
                $MS->StaffLog('เพิ่มวีดิทัศน์', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $newId);
            }
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
    if ($action === 'delete') {
    $id = $_POST['id'] ?? 0;
    if ($id > 0) {
        try {
            // Delete cover image file
            $stmtCover = $DB->prepare("SELECT CoverImage FROM videos WHERE DocID = ?");
            $stmtCover->execute([$id]);
            $coverFile = $stmtCover->fetchColumn();
            if (!empty($coverFile) && file_exists($sServerImgDir . 'videos/' . $coverFile)) {
                unlink($sServerImgDir . 'videos/' . $coverFile);
                $webpPath = preg_replace('/\.[^.]+$/', '.webp', $coverFile);
                if (file_exists($sServerImgDir . 'videos/' . $webpPath)) unlink($sServerImgDir . 'videos/' . $webpPath);
            }

            $stmt = $DB->prepare("UPDATE videos SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID=?");
            $stmt->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $id]);
            $MS->StaffLog('ลบวีดิทัศน์', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
} elseif ($action === 'bulk_delete') {
    $ids = $_POST['ids'] ?? '';
    if (!empty($ids)) {
        try {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                
                // Delete cover images
                $stmtFiles = $DB->prepare("SELECT CoverImage FROM videos WHERE DocID IN ($placeholders)");
                $stmtFiles->execute($idArray);
                $files = $stmtFiles->fetchAll(PDO::FETCH_COLUMN);
                foreach ($files as $file) {
                    if (!empty($file) && file_exists($sServerImgDir . 'videos/' . $file)) {
                        unlink($sServerImgDir . 'videos/' . $file);
                        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $file);
                        if (file_exists($sServerImgDir . 'videos/' . $webpPath)) unlink($sServerImgDir . 'videos/' . $webpPath);
                    }
                }

                $params = array_merge([$StaffID, $_SERVER['REMOTE_ADDR']], $idArray);
                $stmt = $DB->prepare("UPDATE videos SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID IN ($placeholders)");
                $stmt->execute($params);
                $MS->StaffLog('ลบวีดิทัศน์', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
            }
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
} elseif (in_array($action, ['bulk_active', 'bulk_inactive'])) {
    $ids = $_POST['ids'] ?? '';
    if (!empty($ids)) {
        try {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $status = ($action === 'bulk_active') ? 'Y' : 'N';
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$status], $idArray);
                $stmt = $DB->prepare("UPDATE videos SET IsActive=? WHERE DocID IN ($placeholders)");
                $stmt->execute($params);
                $MS->StaffLog('เปลี่ยนสถานะวีดิทัศน์', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
            }
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
}
}
?>
