<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    header('Location: ../login.php');
    exit;
}

include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');

$DB = mosConnectPDO();
$MS = new MS($DB);

$action = (string)mosGetParam($_REQUEST, 'action', '');
$adminId = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$now = date('Y-m-d H:i:s');

try {
    $sectionStmt = $DB->prepare("SELECT SectionID, SectionGroupID FROM bof_section WHERE SectionURL = 'csr_stats/index.php' LIMIT 1");
    $sectionStmt->execute();
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC) ?: ['SectionID' => 0, 'SectionGroupID' => 4];

    if ($action === 'update_config') {
        $eyebrow_th = (string)mosGetParam($_POST, 'Eyebrow_TH', '');
        $heading_th = (string)mosGetParam($_POST, 'Heading_TH', '');
        $summary_th = (string)mosGetParam($_POST, 'Summary_TH', '');
        $eyebrow_en = (string)mosGetParam($_POST, 'Eyebrow_EN', '');
        $heading_en = (string)mosGetParam($_POST, 'Heading_EN', '');
        $summary_en = (string)mosGetParam($_POST, 'Summary_EN', '');

        // Check if config exists
        $stmt = $DB->prepare("SELECT COUNT(*) FROM csr_config");
        $stmt->execute();
        $hasConfig = $stmt->fetchColumn() > 0;

        if ($hasConfig) {
            $updateStmt = $DB->prepare("UPDATE csr_config SET 
                Eyebrow_TH=?, Heading_TH=?, Summary_TH=?, 
                Eyebrow_EN=?, Heading_EN=?, Summary_EN=?,
                UpdateDate=?, UpdateBy=?, UpdateIP=? 
                LIMIT 1");
            $updateStmt->execute([
                $eyebrow_th, $heading_th, $summary_th, 
                $eyebrow_en, $heading_en, $summary_en,
                $now, $adminId, $ip
            ]);
        } else {
            $insertStmt = $DB->prepare("INSERT INTO csr_config 
                (Eyebrow_TH, Heading_TH, Summary_TH, Eyebrow_EN, Heading_EN, Summary_EN, CreateDate, CreateBy, CreateIP) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([
                $eyebrow_th, $heading_th, $summary_th, 
                $eyebrow_en, $heading_en, $summary_en,
                $now, $adminId, $ip
            ]);
        }
        
        $MS->StaffLog('ตั้งค่าข้อมูลสถิติ CSR', $_SERVER['REQUEST_URI'], $adminId, (int)$section['SectionGroupID'], (int)$section['SectionID']);
        header('Location: index.php');
        exit;
    } 
    elseif ($action === 'create') {
        $title_th = (string)mosGetParam($_POST, 'Title_TH', '');
        $title_en = (string)mosGetParam($_POST, 'Title_EN', '');
        $valueText = (string)mosGetParam($_POST, 'ValueText', '');
        $subValueText = (string)mosGetParam($_POST, 'SubValueText', '');
        $unit_th = (string)mosGetParam($_POST, 'Unit_TH', '');
        $unit_en = (string)mosGetParam($_POST, 'Unit_EN', '');
        $isActive = (string)mosGetParam($_POST, 'IsActive', 'N');

        // Get max order
        $orderStmt = $DB->prepare("SELECT COALESCE(MAX(OrderNo), 0) + 1 FROM csr_stats WHERE DelData='N'");
        $orderStmt->execute();
        $nextOrder = $orderStmt->fetchColumn();

        $insertStmt = $DB->prepare("INSERT INTO csr_stats 
            (Title_TH, Title_EN, ValueText, SubValueText, Unit_TH, Unit_EN, OrderNo, IsActive, CreateDate, CreateBy, CreateIP) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([
            $title_th, $title_en, $valueText, $subValueText, $unit_th, $unit_en, 
            $nextOrder, $isActive, $now, $adminId, $ip
        ]);
        
        $statId = (int)$DB->lastInsertId();
        $MS->StaffLog('เพิ่มสถิติ CSR', $_SERVER['REQUEST_URI'], $adminId, (int)$section['SectionGroupID'], (int)$section['SectionID'], $statId);
        
        header('Location: index.php');
        exit;
    }
    elseif ($action === 'update') {
        $statId = (int)mosGetParam($_POST, 'StatID', 0);
        $title_th = (string)mosGetParam($_POST, 'Title_TH', '');
        $title_en = (string)mosGetParam($_POST, 'Title_EN', '');
        $valueText = (string)mosGetParam($_POST, 'ValueText', '');
        $subValueText = (string)mosGetParam($_POST, 'SubValueText', '');
        $unit_th = (string)mosGetParam($_POST, 'Unit_TH', '');
        $unit_en = (string)mosGetParam($_POST, 'Unit_EN', '');
        $isActive = (string)mosGetParam($_POST, 'IsActive', 'N');

        if ($statId > 0) {
            $updateStmt = $DB->prepare("UPDATE csr_stats SET 
                Title_TH=?, Title_EN=?, ValueText=?, SubValueText=?, Unit_TH=?, Unit_EN=?, IsActive=?,
                UpdateDate=?, UpdateBy=?, UpdateIP=? 
                WHERE StatID=? AND DelData='N'");
            $updateStmt->execute([
                $title_th, $title_en, $valueText, $subValueText, $unit_th, $unit_en, $isActive,
                $now, $adminId, $ip, $statId
            ]);
            $MS->StaffLog('แก้ไขสถิติ CSR', $_SERVER['REQUEST_URI'], $adminId, (int)$section['SectionGroupID'], (int)$section['SectionID'], $statId);
        }
        header('Location: index.php');
        exit;
    }
    elseif ($action === 'delete') {
        $statId = (int)mosGetParam($_GET, 'StatID', 0);
        if ($statId > 0) {
            $delStmt = $DB->prepare("UPDATE csr_stats SET 
                DelData='Y', DelDataDate=?, DelDataBy=?, DelDataIP=? 
                WHERE StatID=?");
            $delStmt->execute([$now, $adminId, $ip, $statId]);
            $MS->StaffLog('ลบสถิติ CSR', $_SERVER['REQUEST_URI'], $adminId, (int)$section['SectionGroupID'], (int)$section['SectionID'], $statId);
        }
        header('Location: index.php');
        exit;
    }
    
    header('Location: index.php?result=error&message=' . urlencode('ไม่มีคำสั่งที่ต้องการ'));
    exit;
} catch (PDOException $e) {
    error_log('csr_stats action error: ' . $e->getMessage());
    header('Location: index.php?result=error&message=' . urlencode('เกิดข้อผิดพลาดในระบบ'));
    exit;
}
