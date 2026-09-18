<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
require_once('../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

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
            $StaffID,
            $_SERVER['REMOTE_ADDR'],
            $id
        ]);
        $MS->StaffLog('แก้ไขหน้าที่ความรับผิดชอบหน่วยงาน', $_SERVER['REQUEST_URI'], $StaffID, 3, 105, $id);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

header("Location: index.php");
exit;
?>
