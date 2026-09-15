<?php
define('_VALID_ACCESS', 1);
session_start();
if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    header("Location: ../login.php");
    exit;
}

include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/func.class.php');
include_once('../../config/ms.class.php');

$DB = mosConnectPDO();
$MS = new MS($DB);

$action = mosGetParam($_FORM, 'action', '');
$id = mosGetParam($_FORM, 'BannerID', '');
if (empty($id)) {
    $id = mosGetParam($_FORM, 'id', '');
}

$Title_TH = mosGetParam($_FORM, 'Title_TH', '');
$Title_EN = mosGetParam($_FORM, 'Title_EN', '');
$Description_TH = mosGetParam($_FORM, 'Description_TH', '');
$Description_EN = mosGetParam($_FORM, 'Description_EN', '');
$CTAText_TH = mosGetParam($_FORM, 'CTAText_TH', '');
$CTAText_EN = mosGetParam($_FORM, 'CTAText_EN', '');
$CTALink_TH = mosGetParam($_FORM, 'CTALink_TH', '');
$CTALink_EN = mosGetParam($_FORM, 'CTALink_EN', '');
$OrderNo = mosGetParam($_FORM, 'OrderNo', 1);
$IsActive = mosGetParam($_FORM, 'IsActive', 'Y');
$PublishStart = mosGetParam($_FORM, 'PublishStart', null);
$PublishEnd = mosGetParam($_FORM, 'PublishEnd', null);
if (!empty($PublishStart)) {
    $parts = explode('/', $PublishStart);
    if (count($parts) === 3) {
        $PublishStart = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
} else {
    $PublishStart = null;
}
if (!empty($PublishEnd)) {
    $parts = explode('/', $PublishEnd);
    if (count($parts) === 3) {
        $PublishEnd = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
} else {
    $PublishEnd = null;
}

// Set specific upload directory for hero_banner
$sServerImgDir = $sServerImgDir . 'hero_banner/';

$delete_main_image = mosGetParam($_FORM, 'delete_main_image', '0');
$Image = '';

if ($id > 0) {
    $existingStmt = $DB->prepare("SELECT Image FROM hero_banners WHERE BannerID = ?");
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    $Image = $existing['Image'] ?? '';
}

if ($delete_main_image == '1') {
    if ($Image && file_exists($sServerImgDir . $Image)) {
        @unlink($sServerImgDir . $Image);
    }
    $Image = '';
}

if (isset($_FILES['Image']) && $_FILES['Image']['error'] === 0) {
    global $_Config, $SetMaxSize;
    $res = FU::uploadNewImageAndResize($_FILES['Image'], $sServerImgDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
    if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
        if ($Image && file_exists($sServerImgDir . $Image)) {
            @unlink($sServerImgDir . $Image);
        }
        $Image = $res['sFileName'];
    }
}

try {
    if ($action === 'create') {
        $stmt = $DB->prepare("INSERT INTO hero_banners (Title_TH, Title_EN, Description_TH, Description_EN, Image, CTAText_TH, CTAText_EN, CTALink_TH, CTALink_EN, Template, OrderNo, IsActive, PublishStart, PublishEnd, CreateDate, CreateBy, CreateIP) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([
            $Title_TH, $Title_EN, $Description_TH, $Description_EN, $Image,
            $CTAText_TH, $CTAText_EN, $CTALink_TH, $CTALink_EN,
            'one-grid-slider', $OrderNo, $IsActive, $PublishStart, $PublishEnd,
            $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']
        ]);
        $newBannerID = $DB->lastInsertId();

        // Log action
        $MS->StaffLog('เพิ่มแบนเนอร์หน้าแรก ', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 1, 2, $newBannerID);

    } else if ($action === 'update' && $id > 0) {
        $stmt = $DB->prepare("UPDATE hero_banners SET Title_TH=?, Title_EN=?, Description_TH=?, Description_EN=?, Image=?, CTAText_TH=?, CTAText_EN=?, CTALink_TH=?, CTALink_EN=?, OrderNo=?, IsActive=?, PublishStart=?, PublishEnd=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE BannerID=?");
        $stmt->execute([
            $Title_TH, $Title_EN, $Description_TH, $Description_EN, $Image,
            $CTAText_TH, $CTAText_EN, $CTALink_TH, $CTALink_EN,
            $OrderNo, $IsActive, $PublishStart, $PublishEnd, $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id
        ]);

        // Log action
        $MS->StaffLog('แก้ไขแบนเนอร์หน้าแรก', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 1, 2, $id);

    } else if ($action === 'delete' && $id > 0) {
        $stmt = $DB->prepare("UPDATE hero_banners SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE BannerID=?");
        $stmt->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id]);

        $MS->StaffLog('ลบแบนเนอร์หน้าแรก', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 1, 2, $id);

    } else if ($action === 'bulk_delete') {
        $ids = mosGetParam($_FORM, 'ids', '');
        if (!empty($ids)) {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], $idArray);
                $stmt = $DB->prepare("UPDATE hero_banners SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE BannerID IN ($placeholders)");
                $stmt->execute($params);

                $MS->StaffLog('ลบแบนเนอร์หน้าแรก', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 1, 2);
            }
        }
    } else if (in_array($action, ['bulk_active', 'bulk_inactive'])) {
        $ids = mosGetParam($_FORM, 'ids', '');
        if (!empty($ids)) {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $status = ($action === 'bulk_active') ? 'Y' : 'N';
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$status, $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], $idArray);
                $stmt = $DB->prepare("UPDATE hero_banners SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE BannerID IN ($placeholders)");
                $stmt->execute($params);

                $MS->StaffLog('เปลี่ยนสถานะแบนเนอร์หน้าแรก', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 1, 2);
            }
        }
    }
} catch (PDOException $e) {
    file_put_contents(__DIR__ . '/pdo_error.txt', date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n", FILE_APPEND);
}

header("Location: index.php");
exit;

