<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
require_once('../includes/action_security.php');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
bofSecurityRequireMethod(['POST']);
bofSecurityRequireCsrf();
$MS = new MS($DB);
$staffId = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);
$staffIp = $_SERVER['REMOTE_ADDR'] ?? '';
$action = (string)mosGetParam($_POST, 'action', '');
$cardId = (int)mosGetParam($_POST, 'CardID', 0);

function homeCardsRedirect($status = '', $message = '')
{
    $query = [];
    if ($status !== '' && $message !== '') {
        $query = ['result' => $status, 'message' => $message];
    }
    header('Location: index.php' . ($query ? '?' . http_build_query($query) : ''));
    exit;
}

function homeCardsPermission(PDO $DB, $action)
{
    if (($_SESSION['GroupID'] ?? '') !== 'S') {
        return true;
    }

    $column = in_array($action, ['create'], true)
        ? 'CanAdd'
        : (in_array($action, ['delete', 'bulk_delete'], true) ? 'CanDelete' : 'CanEdit');

    $stmt = $DB->prepare("SELECT sp.`{$column}`
        FROM staff_permission sp
        INNER JOIN bof_section bs ON bs.SectionID = sp.SectionID
        WHERE sp.SetGroupID = ? AND bs.SectionURL = 'home_cards/index.php'
        LIMIT 1");
    $stmt->execute([(int)($_SESSION['SetGroupID'] ?? 0)]);
    return $stmt->fetchColumn() === 'Y';
}

function homeCardsCleanUrl($url)
{
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }
    if (preg_match('/^\/(?!\/)/', $url) || preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }
    throw new InvalidArgumentException('ลิงก์ต้องขึ้นต้นด้วย /, http:// หรือ https://');
}

function homeCardsDeleteImage($directory, $fileName)
{
    $fileName = trim((string)$fileName);
    if ($fileName === '' || basename($fileName) !== $fileName) {
        return;
    }
    $filePath = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($filePath)) {
        @unlink($filePath);
    }
}

if (!homeCardsPermission($DB, $action)) {
    homeCardsRedirect('error', 'คุณไม่มีสิทธิ์ดำเนินการนี้');
}

$uploadDirectory = rtrim($sServerImgDir, '/\\') . DIRECTORY_SEPARATOR . 'home_cards' . DIRECTORY_SEPARATOR;
$uploadedImage = '';

try {
    $sectionStmt = $DB->prepare("SELECT SectionID, SectionGroupID FROM bof_section WHERE SectionURL = 'home_cards/index.php' LIMIT 1");
    $sectionStmt->execute();
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC) ?: ['SectionID' => 0, 'SectionGroupID' => 4];

    if (in_array($action, ['create', 'update'], true)) {
        $slug = strtolower(trim((string)mosGetParam($_POST, 'Slug', '')));
        $titleTh = trim((string)mosGetParam($_POST, 'Title_TH', ''));
        $titleEn = trim((string)mosGetParam($_POST, 'Title_EN', ''));
        $descriptionTh = trim((string)mosGetParam($_POST, 'Description_TH', ''));
        $descriptionEn = trim((string)mosGetParam($_POST, 'Description_EN', ''));
        $linkTh = homeCardsCleanUrl(mosGetParam($_POST, 'Link_TH', ''));
        $linkEn = homeCardsCleanUrl(mosGetParam($_POST, 'Link_EN', ''));
        $bgClass = trim((string)mosGetParam($_POST, 'BgClass', 'bg-grad-0'));
        $direction = (string)mosGetParam($_POST, 'Direction', 'left');
        $isActive = mosGetParam($_POST, 'IsActive', 'N') === 'Y' ? 'Y' : 'N';
        $deleteImage = mosGetParam($_POST, 'delete_image', '0') === '1';
        $image = '';
        $oldImage = '';

        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException('Slug ใช้ได้เฉพาะ a-z, 0-9 และเครื่องหมายขีดกลาง');
        }
        if ($titleTh === '') {
            throw new InvalidArgumentException('กรุณาระบุหัวข้อภาษาไทย');
        }
        if (!preg_match('/^bg-grad-(?:[0-9]|1[0-9]|20)$/', $bgClass)) {
            throw new InvalidArgumentException('สีพื้นหลังไม่ถูกต้อง');
        }
        if (!in_array($direction, ['left', 'right'], true)) {
            throw new InvalidArgumentException('ตำแหน่งเนื้อหาไม่ถูกต้อง');
        }

        $duplicateStmt = $DB->prepare('SELECT CardID FROM home_cards WHERE Slug = ? AND CardID <> ? LIMIT 1');
        $duplicateStmt->execute([$slug, $cardId]);
        if ($duplicateStmt->fetchColumn()) {
            throw new InvalidArgumentException('Slug นี้ถูกใช้งานแล้ว');
        }

        if ($action === 'update') {
            if ($cardId <= 0) {
                throw new InvalidArgumentException('ไม่พบรหัสรายการที่ต้องการแก้ไข');
            }
            $existingStmt = $DB->prepare("SELECT Image FROM home_cards WHERE CardID=? AND DelData='N' LIMIT 1");
            $existingStmt->execute([$cardId]);
            $oldImage = $existingStmt->fetchColumn();
            if ($oldImage === false) {
                throw new InvalidArgumentException('ไม่พบข้อมูลที่ต้องการแก้ไข');
            }
            $image = (string)$oldImage;
        }

        $upload = $_FILES['Image'] ?? null;
        if ($upload && (int)$upload['error'] !== UPLOAD_ERR_NO_FILE) {
            if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('ไม่สามารถอัปโหลดรูปภาพได้ กรุณาลองใหม่อีกครั้ง');
            }
            if ((int)$upload['size'] > 2 * 1024 * 1024) {
                throw new InvalidArgumentException('รูปภาพต้องมีขนาดไฟล์ไม่เกิน 2 MB');
            }

            $extension = strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION));
            $imageInfo = @getimagesize($upload['tmp_name']);
            if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)
                || !$imageInfo
                || !in_array((int)$imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                throw new InvalidArgumentException('รองรับเฉพาะรูปภาพ JPG และ PNG เท่านั้น');
            }

            $uploadResult = FU::uploadNewImageAndResize(
                $upload,
                $uploadDirectory,
                ['jpg', 'jpeg', 'png'],
                $_Config['DeniedExtensions']['Image'],
                2 * 1024 * 1024,
                CONF_IMG_MAX_DIM,
                1254,
                1254,
                1
            );
            if (($uploadResult['Flag'] ?? 1) !== 0 || empty($uploadResult['sFileName'])) {
                throw new InvalidArgumentException($uploadResult['Msg'] ?? 'ไม่สามารถบันทึกรูปภาพได้');
            }
            $uploadedImage = basename($uploadResult['sFileName']);
            $image = $uploadedImage;
            $deleteImage = false;
        } elseif ($deleteImage) {
            $image = '';
        }

        $DB->beginTransaction();

        if ($action === 'create') {
            $orderStmt = $DB->query("SELECT COALESCE(MAX(OrderNo), 0) + 1 FROM home_cards WHERE DelData='N'");
            $orderNo = max(1, (int)$orderStmt->fetchColumn());
            $stmt = $DB->prepare('INSERT INTO home_cards
                (Slug, Title_TH, Title_EN, Description_TH, Description_EN, Link_TH, Link_EN,
                 BgClass, Image, Direction, OrderNo, IsActive, CreateDate, CreateBy, CreateIP, DelData)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, \'N\')');
            $stmt->execute([
                $slug, $titleTh, $titleEn, $descriptionTh, $descriptionEn, $linkTh, $linkEn,
                $bgClass, $image, $direction, $orderNo, $isActive, $staffId, $staffIp,
            ]);
            $cardId = (int)$DB->lastInsertId();
            $logAction = 'เพิ่มการ์ดหน้าหลัก';
        } else {
            $stmt = $DB->prepare('UPDATE home_cards SET
                Slug=?, Title_TH=?, Title_EN=?, Description_TH=?, Description_EN=?,
                Link_TH=?, Link_EN=?, BgClass=?, Image=?, Direction=?, IsActive=?,
                UpdateDate=NOW(), UpdateBy=?, UpdateIP=?
                WHERE CardID=? AND DelData=\'N\'');
            $stmt->execute([
                $slug, $titleTh, $titleEn, $descriptionTh, $descriptionEn, $linkTh, $linkEn,
                $bgClass, $image, $direction, $isActive, $staffId, $staffIp, $cardId,
            ]);
            $logAction = 'แก้ไขการ์ดหน้าหลัก';
        }

        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $staffId, (int)$section['SectionGroupID'], (int)$section['SectionID'], $cardId);
        $DB->commit();

        if ($oldImage !== '' && $oldImage !== $image) {
            homeCardsDeleteImage($uploadDirectory, $oldImage);
        }
        homeCardsRedirect();
    }

    if ($action === 'delete' && $cardId > 0) {
        $stmt = $DB->prepare("UPDATE home_cards SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE CardID=? AND DelData='N'");
        $stmt->execute([$staffId, $staffIp, $cardId]);
        $MS->StaffLog('ลบการ์ดหน้าหลัก', $_SERVER['REQUEST_URI'], $staffId, (int)$section['SectionGroupID'], (int)$section['SectionID'], $cardId);
        homeCardsRedirect();
    }

    if (in_array($action, ['bulk_delete', 'bulk_active', 'bulk_inactive'], true)) {
        $rawIds = (string)mosGetParam($_POST, 'ids', '');
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawIds)), function ($id) {
            return $id > 0;
        })));
        if (!$ids) {
            throw new InvalidArgumentException('กรุณาเลือกรายการ');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'bulk_delete') {
            $sql = "UPDATE home_cards SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE CardID IN ({$placeholders}) AND DelData='N'";
            $params = array_merge([$staffId, $staffIp], $ids);
            $logAction = 'ลบการ์ดหน้าหลักหลายรายการ';
        } else {
            $status = $action === 'bulk_active' ? 'Y' : 'N';
            $sql = "UPDATE home_cards SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE CardID IN ({$placeholders}) AND DelData='N'";
            $params = array_merge([$status, $staffId, $staffIp], $ids);
            $logAction = 'เปลี่ยนสถานะการ์ดหน้าหลักหลายรายการ';
        }
        $stmt = $DB->prepare($sql);
        $stmt->execute($params);
        $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $staffId, (int)$section['SectionGroupID'], (int)$section['SectionID']);
        homeCardsRedirect();
    }

    homeCardsRedirect('error', 'คำสั่งไม่ถูกต้อง');
} catch (InvalidArgumentException $e) {
    if ($DB && $DB->inTransaction()) {
        $DB->rollBack();
    }
    if ($uploadedImage !== '') {
        homeCardsDeleteImage($uploadDirectory, $uploadedImage);
    }
    homeCardsRedirect('error', $e->getMessage());
} catch (PDOException $e) {
    if ($DB && $DB->inTransaction()) {
        $DB->rollBack();
    }
    if ($uploadedImage !== '') {
        homeCardsDeleteImage($uploadDirectory, $uploadedImage);
    }
    error_log('home_cards action: ' . $e->getMessage());
    homeCardsRedirect('error', 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง');
} catch (Throwable $e) {
    if ($DB && $DB->inTransaction()) {
        $DB->rollBack();
    }
    if ($uploadedImage !== '') {
        homeCardsDeleteImage($uploadDirectory, $uploadedImage);
    }
    error_log('home_cards upload: ' . $e->getMessage());
    homeCardsRedirect('error', 'ไม่สามารถประมวลผลรูปภาพได้ กรุณาตรวจสอบไฟล์แล้วลองใหม่อีกครั้ง');
}
