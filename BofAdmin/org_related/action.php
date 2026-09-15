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

$backUrl = "index.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $sServerImgDir = $sServerImgDir . 'org_related/';
    $uploadDir   = $sServerImgDir;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    // Parse Dates
    $startDate = null;
    if (!empty($_POST['StartDate'])) {
        $parts = explode('/', $_POST['StartDate']);
        if (count($parts) === 3) {
            $startDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        } else {
            $startDate = $_POST['StartDate'];
        }
    }
    
    $endDate = null;
    if (!empty($_POST['EndDate'])) {
        $parts = explode('/', $_POST['EndDate']);
        if (count($parts) === 3) {
            $endDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        } else {
            $endDate = $_POST['EndDate'];
        }
    }

    // --- ADD ---
    if ($action === 'add') {
        $imgPath = '';
        if (isset($_FILES['Image']) && $_FILES['Image']['error'] === 0) {
            $res = FU::uploadNewImageAndResize($_FILES['Image'], $uploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) $imgPath = $res['sFileName'];
        }
        try {
            $stmt = $DB->prepare("INSERT INTO org_related
                (Title_TH, Title_EN, Image, URL_TH, URL_EN,
                 StartDate, EndDate, IsActive, OrderNo,
                 CreateDate, CreateBy, CreateIP)
                VALUES (?,?,?,?,?,?,?,?,?, NOW(),?,?)");
            $stmt->execute([
                $_POST['Title_TH'] ?? '', $_POST['Title_EN'] ?? '',
                $imgPath, 
                $_POST['URL_TH'] ?? '', $_POST['URL_EN'] ?? '',
                $startDate,
                $endDate,
                $_POST['IsActive'] ?? 'Y',
                (int)($_POST['OrderNo'] ?? 0),
                $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']
            ]);
            $newId = $DB->lastInsertId();

            $MS->StaffLog('เพิ่มหน่วยงานที่เกี่ยวข้อง', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, 107, $newId);
            header("Location: $backUrl"); exit;
        } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    }

    // --- EDIT ---
    elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { header("Location: $backUrl"); exit; }

        $imgPath = $_POST['old_image'] ?? '';
        if (isset($_POST['deleteImageFlag']) && $_POST['deleteImageFlag'] == '1') {
            if (!empty($imgPath) && file_exists($sServerImgDir . $imgPath)) {
                @unlink($sServerImgDir . $imgPath);
                $webpPath = preg_replace('/\.[^.]+$/', '.webp', $imgPath);
                if (file_exists($sServerImgDir . $webpPath)) @unlink($sServerImgDir . $webpPath);
            }
            $imgPath = '';
        } elseif (isset($_FILES['Image']) && $_FILES['Image']['error'] === 0) {
            $oldFilename = basename($imgPath);
            $res = FU::uploadEditImageAndResize($_FILES['Image'], $uploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, $oldFilename, 1);
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) $imgPath = $res['sFileName'];
        }

        try {
            $stmt = $DB->prepare("UPDATE org_related SET
                Title_TH=?, Title_EN=?, Image=?, URL_TH=?, URL_EN=?,
                StartDate=?, EndDate=?, IsActive=?, OrderNo=?,
                UpdateDate=NOW(), UpdateBy=?, UpdateIP=?
                WHERE RelatedID=?");
            $stmt->execute([
                $_POST['Title_TH'] ?? '', $_POST['Title_EN'] ?? '',
                $imgPath, 
                $_POST['URL_TH'] ?? '', $_POST['URL_EN'] ?? '',
                $startDate,
                $endDate,
                $_POST['IsActive'] ?? 'Y',
                (int)($_POST['OrderNo'] ?? 0),
                $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id
            ]);

            $MS->StaffLog('แก้ไขหน่วยงานที่เกี่ยวข้อง', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, 107, $id);
            header("Location: $backUrl"); exit;
        } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    }

} elseif (isset($_GET['action'])) {
    $getAction = $_GET['action'];

    // --- DELETE ---
    if ($getAction === 'delete') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            try {
                $DB->prepare("UPDATE org_related SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE RelatedID=?")
                   ->execute([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id]);
                $MS->StaffLog('ลบหน่วยงานที่เกี่ยวข้อง', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, 107, $id);
            } catch (PDOException $e) {}
        }
        header("Location: $backUrl"); exit;
    }

    // --- BULK DELETE ---
    elseif ($getAction === 'bulk_delete') {
        $ids = $_GET['ids'] ?? '';
        if (!empty($ids)) {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                    $params = array_merge([$_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], array_values($idArray));
                    $DB->prepare("UPDATE org_related SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE RelatedID IN ($placeholders)")
                       ->execute($params);
                } catch (PDOException $e) {}
            }
        }
        header("Location: $backUrl"); exit;
    }

    // --- BULK ACTIVE / INACTIVE ---
    elseif (in_array($getAction, ['bulk_active', 'bulk_inactive'])) {
        $ids = $_GET['ids'] ?? '';
        if (!empty($ids)) {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                try {
                    $status = ($getAction === 'bulk_active') ? 'Y' : 'N';
                    $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                    $DB->prepare("UPDATE org_related SET IsActive=? WHERE RelatedID IN ($placeholders)")
                       ->execute(array_merge([$status], array_values($idArray)));
                } catch (PDOException $e) {}
            }
        }
        header("Location: $backUrl"); exit;
    }
}

header("Location: index.php");
exit;
?>

