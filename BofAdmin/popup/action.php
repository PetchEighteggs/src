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
$id = mosGetParam($_FORM, 'PopupID', '');
if ($id == '' && isset($_GET['PopupID'])) {
    $id = mosGetParam($_GET, 'PopupID', '');
}

$Title = mosGetParam($_FORM, 'Title', '');
$TitleEN = mosGetParam($_FORM, 'TitleEN', '');

$Link = mosGetParam($_FORM, 'Link', '');
$LinkEn = mosGetParam($_FORM, 'LinkEn', '');
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

// Upload main cover image
$sServerImgDir = $sServerImgDir . 'popup/';
$delete_main_image = mosGetParam($_FORM, 'delete_main_image', '0');
$Image = '';

if ($id > 0) {
    $existingStmt = $DB->prepare("SELECT Image FROM popup WHERE PopupID = ?");
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
    $allowedExts = ['jpg', 'jpeg', 'png'];
    $res = FU::uploadNewImageAndResize($_FILES['Image'], $sServerImgDir, $allowedExts, $_Config['DeniedExtensions']['Image'], 2097152, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
    if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
        if ($Image && file_exists($sServerImgDir . $Image)) {
            @unlink($sServerImgDir . $Image);
        }
        $Image = $res['sFileName'];
    }
}

try {
    if ($action === 'create') {
        $stmt = $DB->prepare("INSERT INTO popup (Title, TitleEN, PublishStart, PublishEnd, Image, Link, LinkEn, OrderNo, IsActive, CreateDate, CreateBy, CreateIP) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([
            $Title, $TitleEN, $PublishStart, $PublishEnd, $Image,
            $Link, $LinkEn,
            $OrderNo, $IsActive, $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']
        ]);
        $newBannerID = $DB->lastInsertId();

        // Log action
        $MS->StaffLog('เพิ่ม Popup ', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 1, 2, $newBannerID);

    } else if ($action === 'update' && $id > 0) {
        $stmt = $DB->prepare("UPDATE popup SET Title=?, TitleEN=?, PublishStart=?, PublishEnd=?, Image=?, Link=?, LinkEn=?, OrderNo=?, IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE PopupID=?");
        $stmt->execute([
            $Title, $TitleEN, $PublishStart, $PublishEnd, $Image,
            $Link, $LinkEn,
            $OrderNo, $IsActive, $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id
        ]);

        // Log action
        $MS->StaffLog('แก้ไข Popup', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 1, 2, $id);

    } else if ($action === 'delete' && $id > 0) {
        $stmt = $DB->prepare("UPDATE popup SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE PopupID=?");
        $stmt->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id]);

        $MS->StaffLog('ลบ Popup', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 1, 2, $id);

    } else if ($action === 'bulk_delete') {
        $ids = mosGetParam($_FORM, 'ids', '');
        if (!empty($ids)) {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], $idArray);
                $stmt = $DB->prepare("UPDATE popup SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE PopupID IN ($placeholders)");
                $stmt->execute($params);

                $MS->StaffLog('ลบ Popup', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 1, 2);
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
                $stmt = $DB->prepare("UPDATE popup SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE PopupID IN ($placeholders)");
                $stmt->execute($params);

                $MS->StaffLog('เปลี่ยนสถานะ Popup', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 1, 2);
            }
        }
    }
} catch (PDOException $e) {
    file_put_contents(__DIR__ . '/pdo_error.txt', date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n", FILE_APPEND);
}

header("Location: index.php");
exit;

