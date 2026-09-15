<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
include_once('../../config/func.class.php');

header('Content-Type: application/json');

$DB = mosConnectPDO();
if (!$DB) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}

$deptId = $_GET['dept_id'] ?? 0;

if (!$deptId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Department ID']);
    exit;
}

try {
    $sql = "SELECT PositionID, Name_TH, Name_EN, IsActive, OrderNo 
            FROM org_positions 
            WHERE DelData = 'N' AND DeptID = :dept_id
            ORDER BY OrderNo ASC, PositionID ASC";
    
    $stmt = $DB->prepare($sql);
    $stmt->execute([':dept_id' => $deptId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);
    
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
