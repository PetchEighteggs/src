<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
include_once('../../config/func.class.php');

$DB = mosConnectPDO();
$MS = new MS($DB);
$sectionStmt = $DB->prepare('SELECT SectionID FROM bof_section WHERE SectionURL = ? LIMIT 1');
$sectionStmt->execute(['weblinks/index.php']);
$weblinksSectionId = (int) $sectionStmt->fetchColumn();

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    header("Location: ../login.php");
    exit;
}

// Auto-check and add soft delete columns if not exist
try {
    $checkDel = $DB->query("SHOW COLUMNS FROM `weblinks_groups` LIKE 'DelData'")->fetch();
    if (!$checkDel) {
        $DB->exec("ALTER TABLE `weblinks_groups` ADD COLUMN `DelData` char(1) DEFAULT 'N'");
        $DB->exec("ALTER TABLE `weblinks_groups` ADD COLUMN `DelDataDate` datetime DEFAULT NULL");
        $DB->exec("ALTER TABLE `weblinks_groups` ADD COLUMN `DelDataBy` int DEFAULT NULL");
        $DB->exec("ALTER TABLE `weblinks_groups` ADD COLUMN `DelDataIP` varchar(50) DEFAULT NULL");
    }
    $checkUpdate = $DB->query("SHOW COLUMNS FROM `weblinks_groups` LIKE 'UpdateDate'")->fetch();
    if (!$checkUpdate) {
        $DB->exec("ALTER TABLE `weblinks_groups` ADD COLUMN `UpdateDate` datetime DEFAULT NULL");
        $DB->exec("ALTER TABLE `weblinks_groups` ADD COLUMN `UpdateBy` int DEFAULT NULL");
        $DB->exec("ALTER TABLE `weblinks_groups` ADD COLUMN `UpdateIP` varchar(50) DEFAULT NULL");
    }
    $checkCreate = $DB->query("SHOW COLUMNS FROM `weblinks_groups` LIKE 'CreateBy'")->fetch();
    if (!$checkCreate) {
        $DB->exec("ALTER TABLE `weblinks_groups` ADD COLUMN `CreateBy` int DEFAULT NULL");
        $DB->exec("ALTER TABLE `weblinks_groups` ADD COLUMN `CreateIP` varchar(50) DEFAULT NULL");
    }
} catch (PDOException $e) {}

$action = mosGetParam($_FORM, 'action', '');
$id = mosGetParam($_FORM, 'id', '');

if ($action === 'delete' && $id != '') {
    $stmt = $DB->prepare("UPDATE weblinks_groups SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ? WHERE GroupID = ?");
    $stmt->execute([$_SESSION['BofAdmin_StaffID'] ?? $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id]);
    $MS->StaffLog('ลบหมวดหมู่เว็บลิ้งค์', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $weblinksSectionId, $id);
    $_SESSION['sys_msg'] = "ลบข้อมูลสำเร็จ";
    header("Location: group.php");
    exit;
} else if ($action == 'bulk_delete') {
    $ids = mosGetParam($_FORM, 'ids', '');
    if (!empty($ids)) {
        $idArray = array_filter(explode(',', $ids), 'is_numeric');
        if (!empty($idArray)) {
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            $params = array_merge([$_SESSION['BofAdmin_StaffID'] ?? $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], $idArray);
            $stmt = $DB->prepare("UPDATE weblinks_groups SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE GroupID IN ($placeholders)");
            $stmt->execute($params);
            $MS->StaffLog('ลบหมวดหมู่เว็บลิ้งค์', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $weblinksSectionId);
        }
    }
    header("Location: group.php");
    exit;
} else if (in_array($action, ['bulk_active', 'bulk_inactive'])) {
    $ids = mosGetParam($_FORM, 'ids', '');
    if (!empty($ids)) {
        $idArray = array_filter(explode(',', $ids), 'is_numeric');
        if (!empty($idArray)) {
            $status = ($action === 'bulk_active') ? 'Y' : 'N';
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));
            $params = array_merge([$status, $_SESSION['BofAdmin_StaffID'] ?? $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']], $idArray);
            $stmt = $DB->prepare("UPDATE weblinks_groups SET IsActive=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? WHERE GroupID IN ($placeholders)");
            $stmt->execute($params);
            $MS->StaffLog('เปลี่ยนสถานะหมวดหมู่เว็บลิ้งค์', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $weblinksSectionId);
        }
    }
    header("Location: group.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nameTH = mosGetParam($_POST, 'GroupNameTH', '');
    $nameEN = mosGetParam($_POST, 'GroupNameEN', '');
    $orderNo = mosGetParam($_POST, 'OrderNo', 999);
    $isActive = mosGetParam($_POST, 'IsActive', 'Y');

    if ($action === 'add') {
        $stmt = $DB->prepare("INSERT INTO weblinks_groups (GroupNameTH, GroupNameEN, OrderNo, IsActive, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, NOW(), ?, ?, 'N')");
        $stmt->execute([
            $nameTH, $nameEN, $orderNo, $isActive,
            $_SESSION['BofAdmin_StaffID'] ?? $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']
        ]);
        $newId = $DB->lastInsertId();
        $MS->StaffLog('เพิ่มหมวดหมู่เว็บลิ้งค์', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $weblinksSectionId, $newId);
        $_SESSION['sys_msg'] = "เพิ่มข้อมูลสำเร็จ";
    } elseif ($action === 'edit' && $id != '') {
        $stmt = $DB->prepare("UPDATE weblinks_groups SET GroupNameTH = ?, GroupNameEN = ?, OrderNo = ?, IsActive = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE GroupID = ?");
        $stmt->execute([
            $nameTH, $nameEN, $orderNo, $isActive,
            $_SESSION['BofAdmin_StaffID'] ?? $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR'], $id
        ]);
        $MS->StaffLog('แก้ไขหมวดหมู่เว็บลิ้งค์', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, $weblinksSectionId, $id);
        $_SESSION['sys_msg'] = "แก้ไขข้อมูลสำเร็จ";
    }

    header("Location: group.php");
    exit;
}

header("Location: group.php");
exit;
