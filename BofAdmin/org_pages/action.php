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
if (($_POST['action'] ?? '') !== 'edit') {
    bofSecurityAbort(400, 'Invalid action');
}
bofSecurityRequireSectionPermission($DB, 'org_pages/index.php', 'CanEdit');
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

function uploadMultipleFiles($fileArray, $pageId, $DB, $tableName, $pathCol) {
    global $sServerDirFile, $sServerImgDir, $_Config, $StaffID, $SetMaxSize, $SetFileSize;
    if (empty($fileArray['name'][0])) return;
    
    $imageExts = $_Config['AllowedExtensions']['Image'] ?? ['jpg', 'jpeg', 'gif', 'png', 'svg', 'webp'];
    
    foreach ($fileArray['name'] as $key => $name) {
        if ($fileArray['error'][$key] !== UPLOAD_ERR_OK) continue;
        
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $isImg = in_array($ext, $imageExts);
        
        $destDir = $isImg ? rtrim($sServerImgDir, '/') . '/structure/' : rtrim($sServerDirFile, '/') . '/structure/';
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0777, true);
        }
        
        $singleFile = [
            'name' => $fileArray['name'][$key],
            'type' => $fileArray['type'][$key],
            'tmp_name' => $fileArray['tmp_name'][$key],
            'error' => $fileArray['error'][$key],
            'size' => $fileArray['size'][$key]
        ];
        
        $maxSize = $isImg ? $SetMaxSize : $SetFileSize;
        $allowed = $isImg ? $_Config['AllowedExtensions']['Image'] : $_Config['AllowedExtensions']['File'];
        $denied = $isImg ? $_Config['DeniedExtensions']['Image'] : $_Config['DeniedExtensions']['File'];

        $res = FU::uploadNewFile($singleFile, $destDir, $allowed, $denied, $maxSize, 1);

        if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
            $dbPath = $res['sFileName'];
            $nameWithoutExt = pathinfo($name, PATHINFO_FILENAME);
            $stmt = $DB->prepare("INSERT INTO `{$tableName}` (`PageID`, `FileName_TH`, `FileName_EN`, `{$pathCol}`, `FileType`, `FileSize`, `CreateDate`, `CreateBy`, `CreateIP`, `DelData`) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'N')");
            $stmt->execute([$pageId, $nameWithoutExt, $nameWithoutExt, $dbPath, $ext, $fileArray['size'][$key], $StaffID, $_SERVER['REMOTE_ADDR']]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { header("Location: index.php"); exit; }

    try {
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
            $StaffID,
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
                $stmt->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $aid]);
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
                $stmt->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $tid]);
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
                    $stmt->execute([$id, $y_th, $y_en, $t_th, $t_en, $d_th, $d_en, $order, $is_header, $StaffID, $_SERVER['REMOTE_ADDR']]);
                } else {
                    $stmt = $DB->prepare("UPDATE org_pages_timelines SET Year_TH = ?, Year_EN = ?, Title_TH = ?, Title_EN = ?, Description_TH = ?, Description_EN = ?, OrderNo = ?, IsHeader = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE TimelineID = ? AND PageID = ?");
                    $stmt->execute([$y_th, $y_en, $t_th, $t_en, $d_th, $d_en, $order, $is_header, $StaffID, $_SERVER['REMOTE_ADDR'], $tid, $id]);
                }
            }
        }

        $stmtTitle = $DB->prepare("SELECT Title_TH FROM org_pages WHERE PageID = ?");
        $stmtTitle->execute([$id]);
        $pageTitle = $stmtTitle->fetchColumn();
        $logMessage = 'แก้ไขหน้า ' . ($pageTitle ? ' (' . $pageTitle . ')' : '');
        
        $MS->StaffLog($logMessage, $_SERVER['REQUEST_URI'], $StaffID, 3, 101, $id);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        die("Error: " . bofSecurityPublicErrorMessage($e));
    }
}

header("Location: index.php");
exit;
?>
