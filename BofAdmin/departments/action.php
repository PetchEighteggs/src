<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
$DB = mosConnectPDO();
$MS = new MS($DB);

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { header("Location: index.php"); exit; }

    try {
        $stmt = $DB->prepare("UPDATE microsite_departments SET
            ShortNameTH   = ?,
            ShortNameEN   = ?,
            DescriptionTH = ?,
            DescriptionEN = ?,
            UpdateDate    = NOW(),
            UpdateBy      = ?,
            UpdateIP      = ?
            WHERE DeptID = ?");
        $stmt->execute([
            $_POST['ShortNameTH'] ?? '',
            $_POST['ShortNameEN'] ?? '',
            $_POST['DescriptionTH'] ?? '',
            $_POST['DescriptionEN'] ?? '',
            $_SESSION['admin_id'] ?? 1,
            $_SERVER['REMOTE_ADDR'],
            $id
        ]);
        $MS->StaffLog('แก้ไขหน้าที่ความรับผิดชอบหน่วยงาน', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, 105, $id);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

header("Location: index.php");
exit;
?>
