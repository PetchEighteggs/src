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
    header("Location: ../login.php"); exit;
}

function uploadMultipleFiles($fileArray, $pageId, $DB, $tableName, $pathCol) {
    global $sServerDirFile, $sServerImgDir, $_Config;
    if (empty($fileArray['name'][0])) return;
    
    $imageExts = $_Config['AllowedExtensions']['Image'] ?? ['jpg', 'jpeg', 'gif', 'png', 'svg', 'webp'];
    
    foreach ($fileArray['name'] as $key => $name) {
        if ($fileArray['error'][$key] !== UPLOAD_ERR_OK) continue;
        
        $tmpName = $fileArray['tmp_name'][$key];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        $newName = date('ymdHis') . rand(1000, 9999) . '.' . $ext;
        
        if (in_array($ext, $imageExts)) {
            $destDir = rtrim($sServerImgDir, '/') . '/structure';
        } else {
            $destDir = rtrim($sServerDirFile, '/') . '/structure';
        }
        $dbPath = $newName;
        
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0777, true);
        }
        $destPath = $destDir . '/' . $newName;
        
        if (move_uploaded_file($tmpName, $destPath)) {
            $nameWithoutExt = pathinfo($name, PATHINFO_FILENAME);
            $stmt = $DB->prepare("INSERT INTO `{$tableName}` (`PageID`, `FileName_TH`, `FileName_EN`, `{$pathCol}`, `FileType`, `FileSize`, `CreateDate`, `CreateBy`, `CreateIP`, `DelData`) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$pageId, $nameWithoutExt, $nameWithoutExt, $dbPath, $ext, $fileArray['size'][$key], $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { header("Location: index.php"); exit; }

    try {
        // Auto-create attachment table if not exists
        $checkTable = $DB->query("SHOW TABLES LIKE 'org_pages_attachments'")->fetch();
        if (!$checkTable) {
            $DB->exec("CREATE TABLE `org_pages_attachments` (
                `AttachmentID` int(11) NOT NULL AUTO_INCREMENT,
                `PageID` int(11) NOT NULL,
                `FileName_TH` varchar(255) DEFAULT NULL,
                `FileName_EN` varchar(255) DEFAULT NULL,
                `FilePath` varchar(255) NOT NULL,
                `FileType` varchar(10) DEFAULT NULL,
                `FileSize` int(11) DEFAULT 0,
                `OrderNo` int(11) DEFAULT 1,
                `DelData` enum('Y','N') DEFAULT 'N',
                `CreateDate` datetime DEFAULT NULL,
                `CreateBy` int(11) DEFAULT NULL,
                `CreateIP` varchar(50) DEFAULT NULL,
                `DelDataDate` datetime DEFAULT NULL,
                `DelDataBy` int(11) DEFAULT NULL,
                `DelDataIP` varchar(50) DEFAULT NULL,
                PRIMARY KEY (`AttachmentID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }

        // Auto-create timelines table if not exists
        $checkTableTimeline = $DB->query("SHOW TABLES LIKE 'org_pages_timelines'")->fetch();
        if (!$checkTableTimeline) {
            $DB->exec("CREATE TABLE `org_pages_timelines` (
                `TimelineID` int(11) NOT NULL AUTO_INCREMENT,
                `PageID` int(11) NOT NULL,
                `Year_TH` varchar(255) DEFAULT NULL,
                `Year_EN` varchar(255) DEFAULT NULL,
                `Title_TH` varchar(255) DEFAULT NULL,
                `Title_EN` varchar(255) DEFAULT NULL,
                `Description_TH` text DEFAULT NULL,
                `Description_EN` text DEFAULT NULL,
                `OrderNo` int(11) DEFAULT 1,
                `IsHeader` enum('Y','N') DEFAULT 'N',
                `DelData` enum('Y','N') DEFAULT 'N',
                `CreateDate` datetime DEFAULT NULL,
                `CreateBy` int(11) DEFAULT NULL,
                `CreateIP` varchar(50) DEFAULT NULL,
                `UpdateDate` datetime DEFAULT NULL,
                `UpdateBy` int(11) DEFAULT NULL,
                `UpdateIP` varchar(50) DEFAULT NULL,
                `DelDataDate` datetime DEFAULT NULL,
                `DelDataBy` int(11) DEFAULT NULL,
                `DelDataIP` varchar(50) DEFAULT NULL,
                PRIMARY KEY (`TimelineID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } else {
            $checkColsTimeline = $DB->query("SHOW COLUMNS FROM `org_pages_timelines`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('IsHeader', $checkColsTimeline)) {
                $DB->exec("ALTER TABLE `org_pages_timelines` ADD COLUMN `IsHeader` enum('Y','N') DEFAULT 'N' AFTER `OrderNo`");
            }
        }

        // Ensure columns exist in org_pages
        $checkCols = $DB->query("SHOW COLUMNS FROM `org_pages`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('CanFile', $checkCols)) {
            $DB->exec("ALTER TABLE `org_pages` ADD COLUMN `CanFile` enum('Y','N') DEFAULT 'Y'");
        }
        if (!in_array('HasFile', $checkCols)) {
            $DB->exec("ALTER TABLE `org_pages` ADD COLUMN `HasFile` enum('Y','N') DEFAULT 'N'");
        }
        if (!in_array('CanTimeline', $checkCols)) {
            $DB->exec("ALTER TABLE `org_pages` ADD COLUMN `CanTimeline` enum('Y','N') DEFAULT 'Y'");
        }
        if (!in_array('HasTimeline', $checkCols)) {
            $DB->exec("ALTER TABLE `org_pages` ADD COLUMN `HasTimeline` enum('Y','N') DEFAULT 'N'");
        }

        $stmt = $DB->prepare("UPDATE org_pages SET
            Content_TH = ?,
            Content_EN = ?,
            HasFile = ?,
            HasTimeline = ?,
            UpdateDate = NOW(),
            UpdateBy   = ?,
            UpdateIP   = ?
            WHERE PageID = ?");
        $stmt->execute([
            $_POST['Content_TH'] ?? '',
            $_POST['Content_EN'] ?? '',
            isset($_POST['HasFile']) ? 'Y' : 'N',
            isset($_POST['HasTimeline']) ? 'Y' : 'N',
            $_SESSION['admin_id'] ?? 1,
            $_SERVER['REMOTE_ADDR'],
            $id
        ]);

        // Process Attachments Deletion
        $deleteAttachments = $_POST['delete_attachments'] ?? '';
        if (!empty($deleteAttachments)) {
            $aids = array_filter(explode(',', $deleteAttachments), 'is_numeric');
            foreach ($aids as $aid) {
                $stmtAtt = $DB->prepare("SELECT FilePath, FileType FROM org_pages_attachments WHERE AttachmentID = ?");
                $stmtAtt->execute([$aid]);
                $att = $stmtAtt->fetch(PDO::FETCH_ASSOC);
                
                if ($att) {
                    $fPath = $att['FilePath'];
                    $ext = strtolower($att['FileType']);
                    $imageExts = $_Config['AllowedExtensions']['Image'] ?? ['jpg', 'jpeg', 'gif', 'png', 'svg', 'webp'];
                    
                    if (in_array($ext, $imageExts)) {
                        $fullPath = $sServerImgDir . 'structure/' . ltrim($fPath, '/');
                    } else {
                        $fullPath = $sServerDirFile . 'structure/' . ltrim($fPath, '/');
                    }
                    
                    if (file_exists($fullPath)) {
                        @unlink($fullPath);
                    }
                }
                // Soft delete
                $stmt = $DB->prepare("UPDATE org_pages_attachments SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE AttachmentID = ?");
                $stmt->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $aid]);
            }
        }

        // Process New Attachments Upload
        if (isset($_FILES['Attachments'])) {
            uploadMultipleFiles($_FILES['Attachments'], $id, $DB, 'org_pages_attachments', 'FilePath');
        }

        // Process Attachments Order
        $attachmentsOrder = $_POST['attachments_order'] ?? '';
        if (!empty($attachmentsOrder)) {
            $aids = array_filter(explode(',', $attachmentsOrder), 'is_numeric');
            foreach ($aids as $idx => $aid) {
                $DB->prepare("UPDATE org_pages_attachments SET OrderNo = ? WHERE AttachmentID = ?")->execute([$idx + 1, $aid]);
            }
        }

        // Process Timelines Deletion
        $deleteTimelines = $_POST['delete_timelines'] ?? '';
        if (!empty($deleteTimelines)) {
            $tids = array_filter(explode(',', $deleteTimelines), 'is_numeric');
            foreach ($tids as $tid) {
                $stmt = $DB->prepare("UPDATE org_pages_timelines SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE TimelineID = ?");
                $stmt->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $tid]);
            }
        }

        // Process Timelines Save/Update
        if (isset($_POST['TimelineIDs']) && is_array($_POST['TimelineIDs'])) {
            foreach ($_POST['TimelineIDs'] as $index => $tid) {
                $y_th = $_POST['Timeline_Year_TH'][$index] ?? '';
                $y_en = $_POST['Timeline_Year_EN'][$index] ?? '';
                $t_th = $_POST['Timeline_Title_TH'][$index] ?? '';
                $t_en = $_POST['Timeline_Title_EN'][$index] ?? '';
                $d_th = $_POST['Timeline_Desc_TH'][$index] ?? '';
                $d_en = $_POST['Timeline_Desc_EN'][$index] ?? '';
                $is_header = $_POST['Timeline_IsHeader'][$index] ?? 'N';
                $order = $index + 1;
                
                if (empty($y_th) && empty($t_th)) continue; // skip if empty

                if ($tid === 'new') {
                    $stmt = $DB->prepare("INSERT INTO org_pages_timelines (PageID, Year_TH, Year_EN, Title_TH, Title_EN, Description_TH, Description_EN, OrderNo, IsHeader, CreateDate, CreateBy, CreateIP) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                    $stmt->execute([$id, $y_th, $y_en, $t_th, $t_en, $d_th, $d_en, $order, $is_header, $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']]);
                } else {
                    $stmt = $DB->prepare("UPDATE org_pages_timelines SET Year_TH = ?, Year_EN = ?, Title_TH = ?, Title_EN = ?, Description_TH = ?, Description_EN = ?, OrderNo = ?, IsHeader = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE TimelineID = ? AND PageID = ?");
                    $stmt->execute([$y_th, $y_en, $t_th, $t_en, $d_th, $d_en, $order, $is_header, $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $tid, $id]);
                }
            }
        }

        $stmtTitle = $DB->prepare("SELECT Title_TH FROM org_pages WHERE PageID = ?");
        $stmtTitle->execute([$id]);
        $pageTitle = $stmtTitle->fetchColumn();
        $logMessage = 'แก้ไขหน้า ' . ($pageTitle ? ' (' . $pageTitle . ')' : '');
        
        $MS->StaffLog($logMessage, $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, 101, $id);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

header("Location: index.php");
exit;
?>
