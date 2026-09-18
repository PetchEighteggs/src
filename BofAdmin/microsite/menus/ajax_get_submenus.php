<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include_once('../../../configuration.php');
include_once('../../../config/ms_com.php');

header('Content-Type: application/json');

$DB = mosConnectPDO();
if (!$DB) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
$menuID = (int)($_GET['menu_id'] ?? 0);

if ($menuID <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Menu ID']);
    exit;
}

try {
    $sql = "SELECT SubMenuID, SubMenuNameTH, SubMenuNameEN, IsActive, OrderNo 
            FROM microsite_submenus
            WHERE DeptID = ? AND MenuID = ? AND COALESCE(DelData, 'N') = 'N'
            ORDER BY OrderNo ASC, SubMenuID ASC";
            
    $stmt = $DB->prepare($sql);
    $stmt->execute([$deptID, $menuID]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);
    
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
