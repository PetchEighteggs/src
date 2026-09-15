<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
include_once('../../config/func.class.php');
$DB = mosConnectPDO();
$MS = new MS($DB);
$sectionStmt = $DB->prepare('SELECT SectionID FROM bof_section WHERE SectionURL = ? LIMIT 1');
$sectionStmt->execute(['weblinks/index.php']);
$weblinksSectionId = (int) $sectionStmt->fetchColumn();

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    header("Location: ../login.php");
    exit;
}

// Auto-check and add URLTH, URLEN & CoverImage columns if not exist
try {
    $checkCol = $DB->query("SHOW COLUMNS FROM `weblinks` LIKE 'URLTH'")->fetch();
    if (!$checkCol) {
        $DB->exec("ALTER TABLE `weblinks` ADD COLUMN `URLTH` varchar(500) DEFAULT NULL");
    }
    $checkColEN = $DB->query("SHOW COLUMNS FROM `weblinks` LIKE 'URLEN'")->fetch();
    if (!$checkColEN) {
        $DB->exec("ALTER TABLE `weblinks` ADD COLUMN `URLEN` varchar(500) DEFAULT NULL");
    }
    $checkCol2 = $DB->query("SHOW COLUMNS FROM `weblinks` LIKE 'CoverImage'")->fetch();
    if (!$checkCol2) {
        $DB->exec("ALTER TABLE `weblinks` ADD COLUMN `CoverImage` varchar(255) DEFAULT NULL");
    }
    // Add soft delete columns if not exist
    $checkDel = $DB->query("SHOW COLUMNS FROM `weblinks` LIKE 'DelData'")->fetch();
    if (!$checkDel) {
        $DB->exec("ALTER TABLE `weblinks` ADD COLUMN `DelData` char(1) DEFAULT 'N'");
        $DB->exec("ALTER TABLE `weblinks` ADD COLUMN `DelDataDate` datetime DEFAULT NULL");
        $DB->exec("ALTER TABLE `weblinks` ADD COLUMN `DelDataBy` int DEFAULT NULL");
        $DB->exec("ALTER TABLE `weblinks` ADD COLUMN `DelDataIP` varchar(50) DEFAULT NULL");
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
        $descriptionTH = $_POST['DescriptionTH'] ?? '';
        $descriptionEN = $_POST['DescriptionEN'] ?? '';
        $contentType = $_POST['ContentType'] ?? 'url';
        if (!in_array($contentType, ['detail', 'url'], true)) {
            http_response_code(400);
            exit('Invalid content type');
        }
        $descTH = $_POST['DetailTH'] ?? '';
        $descEN = $_POST['DetailEN'] ?? '';
        $websiteURLTH = $_POST['URLTH'] ?? '';
        $websiteURLEN = $_POST['URLEN'] ?? '';
        $startDate = !empty($_POST['StartDate']) ? formatToYMD($_POST['StartDate']) . ' 00:00:00' : null;
        $endDate = !empty($_POST['EndDate']) ? formatToYMD($_POST['EndDate']) . ' 23:59:59' : null;

        // Handle Cover Image
        $coverPath = '';
        if ($docID > 0) {
            // Fetch existing cover image
            $stmtCover = $DB->prepare("SELECT CoverImage FROM weblinks WHERE DocID = ?");
            $stmtCover->execute([$docID]);
            $existing = $stmtCover->fetch(PDO::FETCH_ASSOC);
            $coverPath = $existing['CoverImage'] ?? '';
        }

        // Check if user wants to delete cover image
        if (isset($_POST['deleteCoverImageFlag']) && $_POST['deleteCoverImageFlag'] == '1') {
            if (!empty($coverPath) && file_exists($sServerImgDir . 'weblinks/' . $coverPath)) {
                unlink($sServerImgDir . 'weblinks/' . $coverPath);
                $webpPath = preg_replace('/\.[^.]+$/', '.webp', $coverPath);
                if (file_exists($sServerImgDir . 'weblinks/' . $webpPath)) unlink($sServerImgDir . 'weblinks/' . $webpPath);
            }
            $coverPath = '';
            // If new file uploaded after delete
            if (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
                $imgUploadDir = $sServerImgDir . 'weblinks/';
                if (!is_dir($imgUploadDir)) mkdir($imgUploadDir, 0777, true);
                $res = FU::uploadNewImageAndResize($_FILES['CoverImage'], $imgUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
                if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
                    $coverPath = $res['sFileName'];
                }
            }
        } elseif (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
            $imgUploadDir = $sServerImgDir . 'weblinks/';
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
                    $stmtOrder = $DB->prepare("SELECT OrderNo FROM weblinks WHERE DocID = ?");
                    $stmtOrder->execute([$docID]);
                    $orderNo = (int)$stmtOrder->fetchColumn();
                }
                $stmt = $DB->prepare("UPDATE weblinks SET 
                    GroupID=?, TitleTH=?, TitleEN=?, DetailTH=?, DetailEN=?, DescriptionTH=?, DescriptionEN=?, ContentType=?,
                    URLTH=?, URLEN=?, StartDate=?, EndDate=?, CoverImage=?, IsActive=?, OrderNo=?, 
                    UpdateDate=NOW(), UpdateBy=?, UpdateIP=? 
                    WHERE DocID=?");
                $stmt->execute([
                    $groupID, $titleTH, $titleEN, $descTH, $descEN, $descriptionTH, $descriptionEN, $contentType,
                    $websiteURLTH, $websiteURLEN, $startDate, $endDate, $coverPath, $isActive, $orderNo,
                    $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $docID
                ]);
                $MS->StaffLog('แก้ไขเว็บลิ้งค์', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $weblinksSectionId, $docID);
            } else {
                if (empty($orderNo)) {
                    $stmtMax = $DB->prepare("SELECT MAX(OrderNo) FROM weblinks");
                    $stmtMax->execute();
                    $maxOrder = $stmtMax->fetchColumn();
                    $orderNo = $maxOrder ? $maxOrder + 1 : 1;
                }
                $stmt = $DB->prepare("INSERT INTO weblinks 
                    (GroupID, TitleTH, TitleEN, DetailTH, DetailEN, DescriptionTH, DescriptionEN, ContentType, URLTH, URLEN, StartDate, EndDate, CoverImage, IsActive, OrderNo, Views, CreateDate, CreateBy, CreateIP, DelData)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?, ?, 'N')");
                $stmt->execute([
                    $groupID, $titleTH, $titleEN, $descTH, $descEN, $descriptionTH, $descriptionEN, $contentType,
                    $websiteURLTH, $websiteURLEN, $startDate, $endDate, $coverPath, $isActive, $orderNo,
                    $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']
                ]);
                $newId = $DB->lastInsertId();
                $MS->StaffLog('เพิ่มเว็บลิ้งค์', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $weblinksSectionId, $newId);
            }
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        try {
            // Delete cover image file
            $stmtCover = $DB->prepare("SELECT CoverImage FROM weblinks WHERE DocID = ?");
            $stmtCover->execute([$id]);
            $coverFile = $stmtCover->fetchColumn();
            if (!empty($coverFile) && file_exists($sServerImgDir . 'weblinks/' . $coverFile)) {
                unlink($sServerImgDir . 'weblinks/' . $coverFile);
                $webpPath = preg_replace('/\.[^.]+$/', '.webp', $coverFile);
                if (file_exists($sServerImgDir . 'weblinks/' . $webpPath)) unlink($sServerImgDir . 'weblinks/' . $webpPath);
            }

            $stmt = $DB->prepare("UPDATE weblinks SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID=?");
            $stmt->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id]);
            $MS->StaffLog('ลบเว็บลิ้งค์', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $weblinksSectionId, $id);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'bulk_delete') {
    $ids = $_GET['ids'] ?? '';
    if (!empty($ids)) {
        try {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                
                // Delete cover images
                $stmtFiles = $DB->prepare("SELECT CoverImage FROM weblinks WHERE DocID IN ($placeholders)");
                $stmtFiles->execute($idArray);
                $files = $stmtFiles->fetchAll(PDO::FETCH_COLUMN);
                foreach ($files as $file) {
                    if (!empty($file) && file_exists($sServerImgDir . 'weblinks/' . $file)) {
                        unlink($sServerImgDir . 'weblinks/' . $file);
                        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $file);
                        if (file_exists($sServerImgDir . 'weblinks/' . $webpPath)) unlink($sServerImgDir . 'weblinks/' . $webpPath);
                    }
                }

                $params = array_merge([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], $idArray);
                $stmt = $DB->prepare("UPDATE weblinks SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID IN ($placeholders)");
                $stmt->execute($params);
                $MS->StaffLog('ลบเว็บลิ้งค์', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $weblinksSectionId);
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
                $stmt = $DB->prepare("UPDATE weblinks SET IsActive=? WHERE DocID IN ($placeholders)");
                $stmt->execute($params);
                $MS->StaffLog('เปลี่ยนสถานะเว็บลิ้งค์', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $weblinksSectionId);
            }
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
}
?>
