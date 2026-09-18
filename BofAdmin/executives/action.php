<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
include_once('../../config/func.class.php');
require_once('../includes/action_security.php');

$DB = mosConnectPDO();
if (!$DB) { echo "DB Connection Failed"; exit; }
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
bofSecurityRequireMethod(['POST']);
bofSecurityRequireCsrf();
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    $uploadDir = $sServerImgDir . 'executives/';
    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

    $NameTH = $_POST['NameTH'] ?? '';
    $NameEN = $_POST['NameEN'] ?? '';
    $Phone = $_POST['Phone'] ?? '';
    $Email = $_POST['Email'] ?? '';
    $DetailTH = $_POST['DetailTH'] ?? '';
    $DetailEN = $_POST['DetailEN'] ?? '';
    
    $UserIP = $_SERVER['REMOTE_ADDR'] ?? '';
    $UserID = $StaffID;

    $arAllowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $arDenied = ['php', 'exe', 'sh'];
    $maxSize = 10485760; // 10MB

    if ($action === 'add') {
        try {
            $DB->beginTransaction();
            $imageName = '';
            if (!empty($_FILES['Image']['name'])) {
                $res = FU::uploadNewImageAndResize($_FILES['Image'], $uploadDir, $arAllowed, $arDenied, $maxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
                if ($res['Flag'] == 0 && !empty($res['sFileName'])) $imageName = $res['sFileName'];
            }
            $stmt = $DB->prepare("INSERT INTO microsite_executives (NameTH, NameEN, Phone, Email, DetailTH, DetailEN, Image, CreateDate, CreateBy, CreateIP) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
            $stmt->execute([$NameTH, $NameEN, $Phone, $Email, $DetailTH, $DetailEN, $imageName, $UserID, $UserIP]);
            
            $newExecID = $DB->lastInsertId();
            
            // Save positions
            if (!empty($_POST['Positions']) && is_array($_POST['Positions'])) {
                $stmtPos = $DB->prepare("INSERT INTO org_executive_positions (ExecID, DeptID, ExecGroup, PositionTH, PositionEN, OrderNo, IsActive) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($_POST['Positions'] as $pos) {
                    $deptId = !empty($pos['DeptID']) ? (int)$pos['DeptID'] : 0;
                    $execGroup = $pos['Group'] ?? '';
                    $posTH = $pos['PositionTH'] ?? '';
                    $posEN = $pos['PositionEN'] ?? '';
                    $orderNo = (isset($pos['OrderNo']) && $pos['OrderNo'] !== '') ? (int)$pos['OrderNo'] : 99;
                    $isActive = $pos['IsActive'] ?? 'N';
                    
                    if (!empty($posTH)) {
                        $stmtPos->execute([$newExecID, $deptId, $execGroup, $posTH, $posEN, $orderNo, $isActive]);
                    }
                }
            }

            $DB->commit();
            $MS->StaffLog('เพิ่มบุคลากร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $newExecID);
            $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'เพิ่มข้อมูลสำเร็จ'];
        } catch (PDOException $e) {
            $DB->rollBack();
            $_SESSION['sys_msg'] = ['status' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    } elseif ($action === 'edit' && $id) {
        try {
            $DB->beginTransaction();
            $oldImage = $_POST['old_image'] ?? '';
            $deleteImage = $_POST['delete_image'] ?? '';
            $imageName = $oldImage;

            if ($deleteImage == '1' && $oldImage) {
                if (file_exists($uploadDir . $oldImage)) unlink($uploadDir . $oldImage);
                $webpImage = pathinfo($oldImage, PATHINFO_FILENAME) . '.webp';
                if (file_exists($uploadDir . $webpImage)) unlink($uploadDir . $webpImage);
                $imageName = '';
            }
            if (!empty($_FILES['Image']['name'])) {
                if ($oldImage && file_exists($uploadDir . $oldImage)) unlink($uploadDir . $oldImage);
                $webpImage = pathinfo($oldImage, PATHINFO_FILENAME) . '.webp';
                if ($webpImage && file_exists($uploadDir . $webpImage)) unlink($uploadDir . $webpImage);
                
                $res = FU::uploadNewImageAndResize($_FILES['Image'], $uploadDir, $arAllowed, $arDenied, $maxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
                if ($res['Flag'] == 0 && !empty($res['sFileName'])) $imageName = $res['sFileName'];
            }

            $stmt = $DB->prepare("UPDATE microsite_executives SET NameTH = ?, NameEN = ?, Phone = ?, Email = ?, DetailTH = ?, DetailEN = ?, Image = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE ExecID = ?");
            $stmt->execute([$NameTH, $NameEN, $Phone, $Email, $DetailTH, $DetailEN, $imageName, $UserID, $UserIP, $id]);
            $DB->prepare("UPDATE org_executive_positions SET DelData='Y', DelDataDate=NOW(), DelDataBy=? WHERE ExecID=? AND DelData='N'")->execute([$UserID, $id]);
            
            if (!empty($_POST['Positions']) && is_array($_POST['Positions'])) {
                $stmtPos = $DB->prepare("INSERT INTO org_executive_positions (ExecID, DeptID, ExecGroup, PositionTH, PositionEN, OrderNo, IsActive) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($_POST['Positions'] as $pos) {
                    $deptId = !empty($pos['DeptID']) ? (int)$pos['DeptID'] : 0;
                    $execGroup = $pos['Group'] ?? '';
                    $posTH = $pos['PositionTH'] ?? '';
                    $posEN = $pos['PositionEN'] ?? '';
                    $orderNo = (isset($pos['OrderNo']) && $pos['OrderNo'] !== '') ? (int)$pos['OrderNo'] : 99;
                    $isActive = $pos['IsActive'] ?? 'N';
                    
                    if (!empty($posTH)) {
                        $stmtPos->execute([$id, $deptId, $execGroup, $posTH, $posEN, $orderNo, $isActive]);
                    }
                }
            }

            $DB->commit();
            $MS->StaffLog('แก้ไขบุคลากร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
            $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'บันทึกข้อมูลสำเร็จ'];
        } catch (PDOException $e) {
            $DB->rollBack();
            $_SESSION['sys_msg'] = ['status' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $UserIP = $_SERVER['REMOTE_ADDR'] ?? '';
            $UserID = $StaffID;
            // Soft Delete the person
            $DB->prepare("UPDATE microsite_executives SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE ExecID=?")->execute([$UserID, $UserIP, $id]);
            // Soft Delete all mapping records
            $DB->prepare("UPDATE org_executive_positions SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE ExecID=?")->execute([$UserID, $UserIP, $id]);
            $MS->StaffLog('ลบข้อมูลผู้บริหาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
            $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'ลบข้อมูลสำเร็จ'];
        }
    } elseif ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? '';
        if (!empty($ids)) {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$StaffID, $_SERVER['REMOTE_ADDR']], $idArray);
                
                $DB->prepare("UPDATE microsite_executives SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE ExecID IN ($placeholders)")->execute($params);
                $DB->prepare("UPDATE org_executive_positions SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE ExecID IN ($placeholders)")->execute($params);
                
                $MS->StaffLog('ลบข้อมูลผู้บริหาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
                $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'ลบข้อมูลที่เลือกสำเร็จ'];
            }
        }
    } elseif ($action === 'bulk_active' || $action === 'bulk_inactive') {
        $ids = $_POST['ids'] ?? '';
        if (!empty($ids)) {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $status = ($action === 'bulk_active') ? 'Y' : 'N';
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$status], $idArray);
                
                $DB->prepare("UPDATE org_executive_positions SET IsActive=? WHERE ExecID IN ($placeholders)")->execute($params);
                
                $MS->StaffLog('เปลี่ยนสถานะบุคลากร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
                $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'เปลี่ยนสถานะข้อมูลที่เลือกสำเร็จ'];
            }
        }
    }
    header("Location: index.php");
    exit;
}
