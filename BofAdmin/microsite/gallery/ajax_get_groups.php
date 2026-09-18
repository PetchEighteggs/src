<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) { echo json_encode(['status' => 'error']); exit; }
include_once('../../../configuration.php');
include_once('../../../config/ms_com.php');
header('Content-Type: application/json');
$DB = mosConnectPDO();
$deptID = $_SESSION['ActiveDeptID'] ?? 0;
try {
    $sql = "SELECT GroupID, GroupNameTH, IsActive, OrderNo 
            FROM microsite_gallery_groups
            WHERE DeptID = ? AND COALESCE(DelData, 'N') != 'Y'
            ORDER BY OrderNo ASC";
    $stmt = $DB->prepare($sql);
    $stmt->execute([$deptID]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Some galleries might have items without a group (GroupID = 0)
    // Adding a dummy group if needed, but only if they exist.
    $stmtZero = $DB->prepare("SELECT COUNT(*) FROM microsite_gallery WHERE DeptID = ? AND GroupID = 0 AND COALESCE(DelData, 'N') != 'Y'");
    $stmtZero->execute([$deptID]);
    if ($stmtZero->fetchColumn() > 0) {
        array_unshift($data, [
            'GroupID' => 0,
            'GroupNameTH' => 'ไม่มีหมวดหมู่',
            'IsActive' => 'Y',
            'OrderNo' => 0
        ]);
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
