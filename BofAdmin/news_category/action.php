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

if ($action === 'delete' && $id != '') {
    $stmt = $DB->prepare("UPDATE news_category SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ? WHERE CategoryID = ?");
    $stmt->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $id]);
    $MS->StaffLog('ลบหมวดหมู่ข่าวสาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 12, $id);
    $_SESSION['sys_msg'] = "ลบข้อมูลสำเร็จ";
    header("Location: index.php");
    exit;
} else if ($action == 'bulk_delete') {
    $ids = mosGetParam($_POST, 'ids', '');
    if (!empty($ids)) {
        $idArray = array_filter(explode(',', $ids), 'is_numeric');
        if (!empty($idArray)) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            $params = array_merge([$StaffID, $_SERVER['REMOTE_ADDR']], $idArray);
            $stmt = $DB->prepare("UPDATE news_category SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE CategoryID IN ($placeholders)");
            $stmt->execute($params);
            
            $MS->StaffLog('ลบหมวดหมู่ข่าวสาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 12);
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
            $stmt = $DB->prepare("UPDATE news_category SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE CategoryID IN ($placeholders)");
            $stmt->execute($params);
            
            $MS->StaffLog('เปลี่ยนสถานะหมวดหมู่ข่าวสาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 12);
        }
    }
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CategoryName_TH = mosGetParam($_POST, 'CategoryName_TH', '');
    $CategoryName_EN = mosGetParam($_POST, 'CategoryName_EN', '');
    $Slug = mosGetParam($_POST, 'Slug', '');
    $OrderNo = mosGetParam($_POST, 'OrderNo', 999);
    $IsActive = mosGetParam($_POST, 'IsActive', 'Y');
    
    if (empty($Slug) && !empty($CategoryName_TH)) {
        // Fallback or use a library to slugify, but for now just use uniqid if empty or keep it basic
        $Slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $CategoryName_EN)));
        if(empty($Slug)) $Slug = uniqid('cat-');
    }

    if ($action === 'add') {
        $stmt = $DB->prepare("INSERT INTO news_category (CategoryName_TH, CategoryName_EN, Slug, OrderNo, IsActive, CreateDate, CreateBy, CreateIP) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([
            $CategoryName_TH, $CategoryName_EN, $Slug, $OrderNo, $IsActive,
            $StaffID, $_SERVER['REMOTE_ADDR']
        ]);
        $newId = $DB->lastInsertId();
        $MS->StaffLog('เพิ่มหมวดหมู่ข่าวสาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 12, $newId);
        $_SESSION['sys_msg'] = "เพิ่มข้อมูลสำเร็จ";
    } elseif ($action === 'edit' && $id != '') {
        $stmt = $DB->prepare("UPDATE news_category SET CategoryName_TH = ?, CategoryName_EN = ?, Slug = ?, OrderNo = ?, IsActive = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE CategoryID = ?");
        $stmt->execute([
            $CategoryName_TH, $CategoryName_EN, $Slug, $OrderNo, $IsActive,
            $StaffID, $_SERVER['REMOTE_ADDR'], $id
        ]);
        $MS->StaffLog('แก้ไขหมวดหมู่ข่าวสาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 12, $id);
        $_SESSION['sys_msg'] = "แก้ไขข้อมูลสำเร็จ";
    }
    
    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;
