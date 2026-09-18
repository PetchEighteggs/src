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
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    $uploadDir = $sServerImgDir . 'past_executives/';
    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

    $NameTH = $_POST['NameTH'] ?? '';
    $NameEN = $_POST['NameEN'] ?? '';
    $PositionTH = $_POST['PositionTH'] ?? '';
    $PositionEN = $_POST['PositionEN'] ?? '';
    $IsActive = $_POST['IsActive'] ?? 'N';
    
    $Terms = $_POST['Terms'] ?? [];
    
    $UserIP = $_SERVER['REMOTE_ADDR'] ?? '';
    $UserID = $StaffID;

    $arAllowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $arDenied = ['php', 'exe', 'sh'];
    $maxSize = 10485760; // 10MB

    if ($action === 'add') {
        try {
            $DB->beginTransaction();
            
            // Get Max OrderNo
            $stmtOrder = $DB->query("SELECT MAX(OrderNo) as MaxOrder FROM org_past_executives WHERE DelData = 'N'");
            $maxOrder = $stmtOrder->fetch(PDO::FETCH_ASSOC)['MaxOrder'] ?? 0;
            $newOrderNo = $maxOrder + 1;

            $imageName = '';
            if (!empty($_FILES['Image']['name'])) {
                $res = FU::uploadNewImageAndResize($_FILES['Image'], $uploadDir, $arAllowed, $arDenied, $maxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
                if ($res['Flag'] == 0 && !empty($res['sFileName'])) $imageName = $res['sFileName'];
            }
            $stmt = $DB->prepare("INSERT INTO org_past_executives (NameTH, NameEN, PositionTH, PositionEN, Image, OrderNo, IsActive, CreateDate, CreateBy, CreateIP) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
            $stmt->execute([$NameTH, $NameEN, $PositionTH, $PositionEN, $imageName, $newOrderNo, $IsActive, $UserID, $UserIP]);
            
            $newExecID = $DB->lastInsertId();

            // Insert terms
            if (!empty($Terms)) {
                $stmtTerm = $DB->prepare("INSERT INTO org_past_executive_terms (PastExecID, TermYear, OrderNo) VALUES (?, ?, ?)");
                $termOrder = 1;
                foreach ($Terms as $term) {
                    $termYear = $term['TermYear'] ?? '';
                    if ($termYear !== '') {
                        $stmtTerm->execute([$newExecID, $termYear, $termOrder]);
                        $termOrder++;
                    }
                }
            }

            $DB->commit();
            $MS->StaffLog('เพิ่มทำเนียบผู้บริหาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $newExecID);
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

            $stmt = $DB->prepare("UPDATE org_past_executives SET NameTH = ?, NameEN = ?, PositionTH = ?, PositionEN = ?, Image = ?, IsActive = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE PastExecID = ?");
            $stmt->execute([$NameTH, $NameEN, $PositionTH, $PositionEN, $imageName, $IsActive, $UserID, $UserIP, $id]);

            // Update terms (delete old ones and insert new ones)
            $DB->prepare("DELETE FROM org_past_executive_terms WHERE PastExecID = ?")->execute([$id]);
            
            if (!empty($Terms)) {
                $stmtTerm = $DB->prepare("INSERT INTO org_past_executive_terms (PastExecID, TermYear, OrderNo) VALUES (?, ?, ?)");
                $termOrder = 1;
                foreach ($Terms as $term) {
                    $termYear = $term['TermYear'] ?? '';
                    if ($termYear !== '') {
                        $stmtTerm->execute([$id, $termYear, $termOrder]);
                        $termOrder++;
                    }
                }
            }

            $DB->commit();
            $MS->StaffLog('แก้ไขทำเนียบผู้บริหาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
            $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'บันทึกข้อมูลสำเร็จ'];
        } catch (PDOException $e) {
            $DB->rollBack();
            $_SESSION['sys_msg'] = ['status' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }
    header("Location: index.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'delete') {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $UserIP = $_SERVER['REMOTE_ADDR'] ?? '';
            $UserID = $StaffID;
            $DB->prepare("UPDATE org_past_executives SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE PastExecID=?")->execute([$UserID, $UserIP, $id]);
            $MS->StaffLog('ลบข้อมูลทำเนียบผู้บริหาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
            $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'ลบข้อมูลสำเร็จ'];
        }
    } elseif ($action === 'bulk_delete') {
        $ids = $_GET['ids'] ?? '';
        if (!empty($ids)) {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$StaffID, $_SERVER['REMOTE_ADDR']], $idArray);
                
                $DB->prepare("UPDATE org_past_executives SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE PastExecID IN ($placeholders)")->execute($params);
                
                $MS->StaffLog('ลบข้อมูลทำเนียบผู้บริหาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
                $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'ลบข้อมูลที่เลือกสำเร็จ'];
            }
        }
    } elseif ($action === 'bulk_active' || $action === 'bulk_inactive') {
        $ids = $_GET['ids'] ?? '';
        if (!empty($ids)) {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $status = ($action === 'bulk_active') ? 'Y' : 'N';
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$status], $idArray);
                
                $DB->prepare("UPDATE org_past_executives SET IsActive=? WHERE PastExecID IN ($placeholders)")->execute($params);
                
                $MS->StaffLog('เปลี่ยนสถานะทำเนียบผู้บริหาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
                $_SESSION['sys_msg'] = ['status' => 'success', 'msg' => 'เปลี่ยนสถานะข้อมูลที่เลือกสำเร็จ'];
            }
        }
    }
    header("Location: index.php");
    exit;
}

