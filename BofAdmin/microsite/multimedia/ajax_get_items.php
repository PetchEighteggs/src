<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) { echo json_encode(['status' => 'error']); exit; }
include_once('../../../configuration.php');
include_once('../../../config/ms_com.php');
header('Content-Type: application/json');
$DB = mosConnectPDO();
$groupID = (int)($_GET['group_id'] ?? 0);
$deptID = $_SESSION['ActiveDeptID'] ?? 0;
try {
    $sql = "SELECT MediaID, Title_TH, Title_EN, IsActive, OrderNo 
            FROM microsite_multimedia
            WHERE GroupID = ? AND DeptID = ? AND COALESCE(DelData, 'N') != 'Y'
            ORDER BY OrderNo ASC";
    $stmt = $DB->prepare($sql);
    $stmt->execute([$groupID, $deptID]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
