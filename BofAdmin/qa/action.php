<?php
define('_VALID_ACCESS', 1);
session_start();
require_once('../../configuration.php');
require_once('../../config/ms_com.php');
require_once('../../config/ms.class.php');
require_once('../../config/func.class.php'); 
require_once('../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
bofSecurityRequireMethod(['POST']);
bofSecurityRequireCsrf();
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

// Auto-check and add URLTH, URLEN & CoverImage columns if not exist
try {
    $checkCol = $DB->query("SHOW COLUMNS FROM `qa` LIKE 'URLTH'")->fetch();
    if (!$checkCol) {
        $DB->exec("ALTER TABLE `qa` ADD COLUMN `URLTH` varchar(500) DEFAULT NULL");
    }
    $checkColEN = $DB->query("SHOW COLUMNS FROM `qa` LIKE 'URLEN'")->fetch();
    if (!$checkColEN) {
        $DB->exec("ALTER TABLE `qa` ADD COLUMN `URLEN` varchar(500) DEFAULT NULL");
    }
    $checkCol2 = $DB->query("SHOW COLUMNS FROM `qa` LIKE 'CoverImage'")->fetch();
    if (!$checkCol2) {
        $DB->exec("ALTER TABLE `qa` ADD COLUMN `CoverImage` varchar(255) DEFAULT NULL");
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

$uploadDir = $sServerDirFile . "qa/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = $_POST['DocID'] ?? 0;
        $groupID = $_POST['GroupID'] ?? 0;
        $titleTH = $_POST['TitleTH'] ?? '';
        $titleEN = $_POST['TitleEN'] ?? '';
        $detailTH = $_POST['DetailTH'] ?? '';
        $detailEN = $_POST['DetailEN'] ?? '';
        $youtubeURLTH = $_POST['URLTH'] ?? '';
        $youtubeURLEN = $_POST['URLEN'] ?? '';
        $startDate = null;
        $endDate = null;
        $isActive = $_POST['IsActive'] ?? 'Y';
        $orderNo = isset($_POST['OrderNo']) ? $_POST['OrderNo'] : null;
        
        $adminId = $StaffID;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Handle Cover Image
        $coverPath = '';
        if ($id > 0) {
            $stmtCover = $DB->prepare("SELECT CoverImage FROM qa WHERE DocID = ?");
            $stmtCover->execute([$id]);
            $existing = $stmtCover->fetch(PDO::FETCH_ASSOC);
            $coverPath = $existing['CoverImage'] ?? '';
        }

        if (isset($_POST['deleteCoverImageFlag']) && $_POST['deleteCoverImageFlag'] == '1') {
            if (!empty($coverPath) && file_exists($sServerImgDir . 'qa/' . $coverPath)) {
                @unlink($sServerImgDir . 'qa/' . $coverPath);
                $webpPath = preg_replace('/\.[^.]+$/', '.webp', $coverPath);
                if (file_exists($sServerImgDir . 'qa/' . $webpPath)) @unlink($sServerImgDir . 'qa/' . $webpPath);
            }
            $coverPath = '';
            if (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
                $imgUploadDir = $sServerImgDir . 'qa/';
                if (!is_dir($imgUploadDir)) mkdir($imgUploadDir, 0777, true);
                $res = FU::uploadNewImageAndResize($_FILES['CoverImage'], $imgUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize ?? 0, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
                if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
                    $coverPath = $res['sFileName'];
                }
            }
        } elseif (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
            $imgUploadDir = $sServerImgDir . 'qa/';
            if (!is_dir($imgUploadDir)) mkdir($imgUploadDir, 0777, true);
            if ($id > 0 && !empty($coverPath)) {
                $oldFilename = basename($coverPath);
                $res = FU::uploadEditImageAndResize($_FILES['CoverImage'], $imgUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize ?? 0, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, $oldFilename, 1);
            } else {
                $res = FU::uploadNewImageAndResize($_FILES['CoverImage'], $imgUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize ?? 0, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
            }
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) $coverPath = $res['sFileName'];
        }

        try {
            if ($id > 0) {
                if ($orderNo === null) {
                    $stmtOrder = $DB->prepare("SELECT OrderNo FROM qa WHERE DocID = ?");
                    $stmtOrder->execute([$id]);
                    $orderNo = (int)$stmtOrder->fetchColumn();
                }
                $stmt = $DB->prepare("UPDATE qa SET 
                    GroupID=?, TitleTH=?, TitleEN=?, DetailTH=?, DetailEN=?, 
                    URLTH=?, URLEN=?, CoverImage=?, 
                    IsActive=?, OrderNo=?, 
                    UpdateDate=NOW(), UpdateBy=?, UpdateIP=? 
                    WHERE DocID=?");
                $stmt->execute([
                    $groupID, $titleTH, $titleEN, $detailTH, $detailEN, 
                    $youtubeURLTH, $youtubeURLEN, $coverPath,
                    $isActive, $orderNo, 
                    $adminId, $ip, $id
                ]);
            } else {
                if (empty($orderNo)) {
                    $stmtMax = $DB->prepare("SELECT MAX(OrderNo) FROM qa");
                    $stmtMax->execute();
                    $maxOrder = $stmtMax->fetchColumn();
                    $orderNo = $maxOrder ? $maxOrder + 1 : 1;
                }
                $stmt = $DB->prepare("INSERT INTO qa (
                    GroupID, TitleTH, TitleEN, DetailTH, DetailEN, 
                    URLTH, URLEN, CoverImage, 
                    IsActive, OrderNo, 
                    CreateDate, CreateBy, CreateIP, UpdateDate, UpdateBy, UpdateIP, DelData
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?, ?, 'N')");
                $stmt->execute([
                    $groupID, $titleTH, $titleEN, $detailTH, $detailEN, 
                    $youtubeURLTH, $youtubeURLEN, $coverPath,
                    $isActive, $orderNo, 
                    $adminId, $ip, $adminId, $ip
                ]);
                $id = $DB->lastInsertId();
            }
            
            $logAction = ($_POST['DocID'] > 0 ? 'แก้ไขถาม-ตอบ ID: ' : 'เพิ่มถาม-ตอบ ID: ') . $id;
            $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $adminId, '', 10, $id);
            
            header("Location: index.php");
            exit;
        } catch (PDOException $e) { 
            die("Error: " . $e->getMessage()); 
        }
    }
    $adminId = $StaffID;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        if ($id > 0) {
            try {
                // Delete physical file
                $stmtFile = $DB->prepare("SELECT CoverImage FROM qa WHERE DocID = ?");
                $stmtFile->execute([$id]);
                $row = $stmtFile->fetch(PDO::FETCH_ASSOC);
                $coverFile = $row['CoverImage'] ?? '';
                if (!empty($coverFile) && file_exists($sServerImgDir . 'qa/' . $coverFile)) {
                    @unlink($sServerImgDir . 'qa/' . $coverFile);
                    $webpPath = preg_replace('/\.[^.]+$/', '.webp', $coverFile);
                    if (file_exists($sServerImgDir . 'qa/' . $webpPath)) @unlink($sServerImgDir . 'qa/' . $webpPath);
                }

                $stmt = $DB->prepare("UPDATE qa SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID=?");
                $stmt->execute([$adminId, $ip, $id]);
                
                $MS->StaffLog('ลบถาม-ตอบ ID: ' . $id, $_SERVER['REQUEST_URI'], $adminId, '', 10, $id);
                
                header("Location: index.php");
                exit;
            } catch (PDOException $e) {
                die("Error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'bulk_delete' || $action === 'bulk_active' || $action === 'bulk_inactive') {
        $ids = $_POST['ids'] ?? '';
        if (!empty($ids)) {
            try {
                $idArray = array_filter(explode(',', $ids), 'is_numeric');
                if (!empty($idArray)) {
                    $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                    $params = array_merge([$adminId, $ip], $idArray);
                    
                    if ($action === 'bulk_delete') {
                        // Delete physical files
                        $stmtFile = $DB->prepare("SELECT CoverImage FROM qa WHERE DocID IN ($placeholders)");
                        $stmtFile->execute($idArray);
                        while($row = $stmtFile->fetch(PDO::FETCH_ASSOC)) {
                            $coverFile = $row['CoverImage'] ?? '';
                            if (!empty($coverFile) && file_exists($sServerImgDir . 'qa/' . $coverFile)) {
                                @unlink($sServerImgDir . 'qa/' . $coverFile);
                                $webpPath = preg_replace('/\.[^.]+$/', '.webp', $coverFile);
                                if (file_exists($sServerImgDir . 'qa/' . $webpPath)) @unlink($sServerImgDir . 'qa/' . $webpPath);
                            }
                        }

                        $stmt = $DB->prepare("UPDATE qa SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DocID IN ($placeholders)");
                        $stmt->execute($params);
                        $MS->StaffLog('ลบถาม-ตอบ Bulk ID: ' . $ids, $_SERVER['REQUEST_URI'], $adminId, '', 10, 0);
                    } elseif ($action === 'bulk_active') {
                        $stmt = $DB->prepare("UPDATE qa SET IsActive='Y', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE DocID IN ($placeholders)");
                        $stmt->execute($params);
                    } elseif ($action === 'bulk_inactive') {
                        $stmt = $DB->prepare("UPDATE qa SET IsActive='N', UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE DocID IN ($placeholders)");
                        $stmt->execute($params);
                    }
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
