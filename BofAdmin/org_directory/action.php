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

$action = mosGetParam($_POST, 'action', '');
$id = mosGetParam($_POST, 'id', '');
$ModuleID = 15;

if ($action === 'delete' && $id != '') {
    $stmt = $DB->prepare("UPDATE org_directory SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ? WHERE DirID = ?");
    $stmt->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $id]);
    $MS->StaffLog('ลบข้อมูลนามสงเคราะห์', $_SERVER['REQUEST_URI'], $StaffID, 3, $ModuleID, $id);
    $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'ลบข้อมูลสำเร็จ'];
    header("Location: index.php");
    exit;
} else if ($action == 'bulk_delete') {
    $ids = mosGetParam($_POST, 'ids', '');
    if (!empty($ids)) {
        $idArray = array_filter(explode(',', $ids), 'is_numeric');
        if (!empty($idArray)) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            $params = array_merge([$StaffID, $_SERVER['REMOTE_ADDR']], $idArray);
            $stmt = $DB->prepare("UPDATE org_directory SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE DirID IN ($placeholders)");
            $stmt->execute($params);
            
            $MS->StaffLog('ลบข้อมูลนามสงเคราะห์แบบกลุ่ม', $_SERVER['REQUEST_URI'], $StaffID, 3, $ModuleID);
            $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'ลบทิ้งข้อมูลที่เลือกสำเร็จ'];
        }
    }
    header("Location: index.php");
    exit;
} else if (in_array($action, ['bulk_active', 'bulk_inactive'])) {
    $ids = mosGetParam($_POST, 'ids', '');
    if (!empty($ids)) {
        $idArray = array_filter(explode(',', $ids), 'is_numeric');
        if (!empty($idArray)) {
            $status = ($action === 'bulk_active') ? 'Y' : 'N';
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            $params = array_merge([$status, $StaffID, $_SERVER['REMOTE_ADDR']], $idArray);
            $stmt = $DB->prepare("UPDATE org_directory SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE DirID IN ($placeholders)");
            $stmt->execute($params);
            
            $MS->StaffLog('เปลี่ยนสถานะนามสงเคราะห์แบบกลุ่ม', $_SERVER['REQUEST_URI'], $StaffID, 3, $ModuleID);
            $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => ($status === 'Y' ? 'เปิดใช้งานข้อมูลที่เลือกสำเร็จ' : 'ปิดการใช้งานข้อมูลที่เลือกสำเร็จ')];
        }
    }
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $NameTH = mosGetParam($_POST, 'NameTH', '');
    $NameEN = mosGetParam($_POST, 'NameEN', '');
    $DeptID = mosGetParam($_POST, 'DeptID', 0);
    $PositionTH = mosGetParam($_POST, 'PositionTH', '');
    $PositionEN = mosGetParam($_POST, 'PositionEN', '');
    $Phone = mosGetParam($_POST, 'Phone', '');
    $Fax = mosGetParam($_POST, 'Fax', '');
    $Email = mosGetParam($_POST, 'Email', '');
    $OrderNo = mosGetParam($_POST, 'OrderNo', 0);
    $IsActive = mosGetParam($_POST, 'IsActive', 'N');

    if ($action === 'add') {
        $Image = '';
        if (isset($_FILES['Image']) && $_FILES['Image']['error'] === UPLOAD_ERR_OK) {
            global $_Config, $SetMaxSize;
            $uploadDir = $sServerImgDir . 'org_directory/';
            if(!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            $res = FU::uploadNewImageAndResize($_FILES['Image'], $uploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
                $Image = 'org_directory/' . $res['sFileName'];
            }
        }

        $stmt = $DB->prepare("INSERT INTO org_directory 
            (NameTH, NameEN, Image, DeptID, PositionTH, PositionEN, Phone, Fax, Email, OrderNo, IsActive, CreateDate, CreateBy, CreateIP) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        
        $stmt->execute([
            $NameTH, $NameEN, $Image, $DeptID, $PositionTH, $PositionEN, $Phone, $Fax, $Email, $OrderNo, $IsActive,
            $StaffID, $_SERVER['REMOTE_ADDR']
        ]);

        $newId = $DB->lastInsertId();
        $MS->StaffLog('เพิ่มข้อมูลนามสงเคราะห์', $_SERVER['REQUEST_URI'], $StaffID, 3, $ModuleID, $newId);
        $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'เพิ่มข้อมูลสำเร็จ'];

    } elseif ($action === 'edit' && $id != '') {
        $Image = mosGetParam($_POST, 'old_Image', '');
        $deleteImageFlag = mosGetParam($_POST, 'delete_image', '0');

        if ($deleteImageFlag === '1') {
            if (!empty($Image)) {
                $actualPath = str_replace('uploads/images/', '', $Image);
                if (file_exists($sServerImgDir . $actualPath)) {
                    @unlink($sServerImgDir . $actualPath);
                    $webpPath = preg_replace('/\.[^.]+$/', '.webp', $actualPath);
                    if (file_exists($sServerImgDir . $webpPath)) @unlink($sServerImgDir . $webpPath);
                }
            }
            $Image = '';
        }

        if (isset($_FILES['Image']) && $_FILES['Image']['error'] === UPLOAD_ERR_OK) {
            global $_Config, $SetMaxSize;
            $uploadDir = $sServerImgDir . 'org_directory/';
            if(!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            
            if ($deleteImageFlag === '1' || empty($Image)) {
                $res = FU::uploadNewImageAndResize($_FILES['Image'], $uploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
            } else {
                $actualPath = str_replace('uploads/images/', '', $Image);
                $oldFilename = basename($actualPath);
                $res = FU::uploadEditImageAndResize($_FILES['Image'], $uploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, $oldFilename, 1);
            }
            
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
                $Image = 'org_directory/' . $res['sFileName'];
            }
        }

        $stmt = $DB->prepare("UPDATE org_directory SET 
            NameTH = ?, NameEN = ?, Image = ?, DeptID = ?, PositionTH = ?, PositionEN = ?, Phone = ?, Fax = ?, Email = ?, OrderNo = ?, IsActive = ?, 
            UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? 
            WHERE DirID = ?");
        
        $stmt->execute([
            $NameTH, $NameEN, $Image, $DeptID, $PositionTH, $PositionEN, $Phone, $Fax, $Email, $OrderNo, $IsActive,
            $StaffID, $_SERVER['REMOTE_ADDR'], $id
        ]);

        $MS->StaffLog('แก้ไขข้อมูลนามสงเคราะห์', $_SERVER['REQUEST_URI'], $StaffID, 3, $ModuleID, $id);
        $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'บันทึกข้อมูลสำเร็จ'];
    }
    
    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;
