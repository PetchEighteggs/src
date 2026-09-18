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
    $sql = "SELECT IntroID as id, Title_TH as title, Image as image, IsActive 
            FROM microsite_intros
            WHERE DeptID = ? AND COALESCE(DelData, 'N') != 'Y'
            ORDER BY OrderNo ASC";
    $stmt = $DB->prepare($sql);
    $stmt->execute([$deptID]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>