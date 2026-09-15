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

$group = $_GET['group'] ?? '';

try {
    $sql = "SELECT d.DeptID, d.ShortNameTH as NameTH, d.ShortNameEN as NameEN, d.IsActive, d.OrderNo_Org as OrderNo 
            FROM microsite_departments d
            WHERE d.DelData = 'N' 
            AND d.DescriptionTH IS NOT NULL AND d.DescriptionTH != ''
            ORDER BY d.OrderNo_Org ASC, d.DeptID ASC";
            
    $stmt = $DB->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);
    
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
