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

$action = mosGetParam($_FORM, 'action', '');
$id = mosGetParam($_FORM, 'id', '');
$ModuleID = 14;

if ($action === 'delete' && $id != '') {
    $stmt = $DB->prepare("UPDATE cio_executives SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ? WHERE CioID = ?");
    $stmt->execute([$_SESSION['BofAdmin_StaffID'] ?? ($_SESSION['admin_id'] ?? 1), $_SERVER['REMOTE_ADDR'], $id]);
    $MS->StaffLog('ลบข้อมูลผู้บริหาร CIO', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $ModuleID, $id);
    $_SESSION['alert'] = ['status' => 'success', 'title' => 'ลบข้อมูลสำเร็จ'];
    header("Location: index.php");
    exit;
} else if ($action == 'bulk_delete') {
    $ids = mosGetParam($_FORM, 'ids', '');
    if (!empty($ids)) {
        $idArray = array_filter(explode(',', $ids), 'is_numeric');
        if (!empty($idArray)) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            $params = array_merge([$_SESSION['BofAdmin_StaffID'] ?? ($_SESSION['admin_id'] ?? 1), $_SERVER['REMOTE_ADDR']], $idArray);
            $stmt = $DB->prepare("UPDATE cio_executives SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE CioID IN ($placeholders)");
            $stmt->execute($params);
            
            $MS->StaffLog('ลบข้อมูลผู้บริหาร CIO', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $ModuleID);
            $_SESSION['alert'] = ['status' => 'success', 'title' => 'ลบรายการที่เลือกสำเร็จ'];
        }
    }
    header("Location: index.php");
    exit;
} else if (in_array($action, ['bulk_active', 'bulk_inactive'])) {
    $ids = mosGetParam($_FORM, 'ids', '');
    if (!empty($ids)) {
        $idArray = array_filter(explode(',', $ids), 'is_numeric');
        if (!empty($idArray)) {
            $status = ($action === 'bulk_active') ? 'Y' : 'N';
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            $params = array_merge([$status, $_SESSION['BofAdmin_StaffID'] ?? ($_SESSION['admin_id'] ?? 1), $_SERVER['REMOTE_ADDR']], $idArray);
            $stmt = $DB->prepare("UPDATE cio_executives SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE CioID IN ($placeholders)");
            $stmt->execute($params);
            
            $MS->StaffLog('เปลี่ยนสถานะผู้บริหาร CIO', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $ModuleID);
            $_SESSION['alert'] = ['status' => 'success', 'title' => ($status === 'Y' ? 'เปิดใช้งานรายการที่เลือกสำเร็จ' : 'ปิดการใช้งานรายการที่เลือกสำเร็จ')];
        }
    }
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $Prefix_TH = mosGetParam($_POST, 'Prefix_TH', '');
    $Prefix_EN = mosGetParam($_POST, 'Prefix_EN', '');
    $FirstName_TH = mosGetParam($_POST, 'FirstName_TH', '');
    $FirstName_EN = mosGetParam($_POST, 'FirstName_EN', '');
    $LastName_TH = mosGetParam($_POST, 'LastName_TH', '');
    $LastName_EN = mosGetParam($_POST, 'LastName_EN', '');
    $Position_TH = mosGetParam($_POST, 'Position_TH', '');
    $Position_EN = mosGetParam($_POST, 'Position_EN', '');
    $Detail_TH = mosGetParam($_POST, 'Detail_TH', '');
    $Detail_EN = mosGetParam($_POST, 'Detail_EN', '');
    $Address = mosGetParam($_POST, 'Address', '');
    $Telephone = mosGetParam($_POST, 'Telephone', '');
    $Email = mosGetParam($_POST, 'Email', '');
    $AppointDate = mosGetParam($_POST, 'AppointDate', '');
    if (empty($AppointDate)) $AppointDate = null;
    $AppointOrderNo = mosGetParam($_POST, 'AppointOrderNo', '');
    $OrderNo = mosGetParam($_POST, 'OrderNo', 1);
    $IsActive = mosGetParam($_POST, 'IsActive', 'N');

    if ($action === 'add') {
        $ImagePath = '';
        if (isset($_FILES['ImagePath']) && $_FILES['ImagePath']['error'] === UPLOAD_ERR_OK) {
            global $_Config, $SetMaxSize;
            $uploadDir = $sServerImgDir . 'cio_executives/';
            if(!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            $res = FU::uploadNewImageAndResize($_FILES['ImagePath'], $uploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
                $ImagePath = 'cio_executives/' . $res['sFileName'];
            }
        }

        $stmt = $DB->prepare("INSERT INTO cio_executives 
            (Prefix_TH, Prefix_EN, FirstName_TH, FirstName_EN, LastName_TH, LastName_EN, Position_TH, Position_EN, Detail_TH, Detail_EN, Address, Telephone, Email, AppointDate, AppointOrderNo, ImagePath, OrderNo, IsActive, CreateDate, CreateBy, CreateIP) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        
        $stmt->execute([
            $Prefix_TH, $Prefix_EN, $FirstName_TH, $FirstName_EN, $LastName_TH, $LastName_EN, $Position_TH, $Position_EN, $Detail_TH, $Detail_EN, $Address, $Telephone, $Email, $AppointDate, $AppointOrderNo, $ImagePath, $OrderNo, $IsActive,
            $_SESSION['BofAdmin_StaffID'] ?? ($_SESSION['admin_id'] ?? 1), $_SERVER['REMOTE_ADDR']
        ]);

        $newId = $DB->lastInsertId();
        $MS->StaffLog('เพิ่มข้อมูลผู้บริหาร CIO', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $ModuleID, $newId);
        $_SESSION['alert'] = ['status' => 'success', 'title' => 'บันทึกข้อมูลสำเร็จ'];

    } elseif ($action === 'edit' && $id != '') {
        $ImagePath = mosGetParam($_POST, 'old_image', '');
        $deleteImageFlag = mosGetParam($_POST, 'deleteImageFlag', '0');

        if ($deleteImageFlag === '1') {
            if (!empty($ImagePath) && file_exists($sServerImgDir . $ImagePath)) {
                @unlink($sServerImgDir . $ImagePath);
                $webpPath = preg_replace('/\.[^.]+$/', '.webp', $ImagePath);
                if (file_exists($sServerImgDir . $webpPath)) @unlink($sServerImgDir . $webpPath);
            }
            $ImagePath = '';
        }

        if (isset($_FILES['ImagePath']) && $_FILES['ImagePath']['error'] === UPLOAD_ERR_OK) {
            global $_Config, $SetMaxSize;
            $uploadDir = $sServerImgDir . 'cio_executives/';
            if(!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            
            if ($deleteImageFlag === '1' || empty($ImagePath)) {
                $res = FU::uploadNewImageAndResize($_FILES['ImagePath'], $uploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
            } else {
                $oldFilename = basename($ImagePath);
                $res = FU::uploadEditImageAndResize($_FILES['ImagePath'], $uploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, $oldFilename, 1);
            }
            
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
                $ImagePath = 'cio_executives/' . $res['sFileName'];
            }
        }

        $stmt = $DB->prepare("UPDATE cio_executives SET 
            Prefix_TH = ?, Prefix_EN = ?, 
            FirstName_TH = ?, FirstName_EN = ?, 
            LastName_TH = ?, LastName_EN = ?, 
            Position_TH = ?, Position_EN = ?, 
            Detail_TH = ?, Detail_EN = ?,
            Address = ?, Telephone = ?, Email = ?,
            AppointDate = ?, AppointOrderNo = ?,
            ImagePath = ?, OrderNo = ?, IsActive = ?, 
            UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? 
            WHERE CioID = ?");
        
        $stmt->execute([
            $Prefix_TH, $Prefix_EN, $FirstName_TH, $FirstName_EN, $LastName_TH, $LastName_EN, $Position_TH, $Position_EN, $Detail_TH, $Detail_EN, $Address, $Telephone, $Email, $AppointDate, $AppointOrderNo, $ImagePath, $OrderNo, $IsActive,
            $_SESSION['BofAdmin_StaffID'] ?? ($_SESSION['admin_id'] ?? 1), $_SERVER['REMOTE_ADDR'], $id
        ]);

        $MS->StaffLog('แก้ไขข้อมูลผู้บริหาร CIO', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $ModuleID, $id);
        $_SESSION['alert'] = ['status' => 'success', 'title' => 'แก้ไขข้อมูลสำเร็จ'];
    }
    
    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;
