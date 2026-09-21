<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once('../../../configuration.php');
include_once('../../../config/ms_com.php');
require_once('../../includes/action_security.php');

header('Content-Type: application/json; charset=utf-8');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['response' => 'json', 'login_url' => '../../login.php']);
bofSecurityRequireMethod(['GET'], ['response' => 'json']);
if (!$DB) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}

$deptID = $_SESSION['ActiveDeptID'] ?? 0;

try {
    $sql = "SELECT MenuID, MenuNameTH, MenuNameEN, IsActive, OrderNo 
            FROM microsite_menus
            WHERE DeptID = ? AND COALESCE(DelData, 'N') = 'N'
            ORDER BY OrderNo ASC, MenuID ASC";
            
    $stmt = $DB->prepare($sql);
    $stmt->execute([$deptID]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);
    
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
