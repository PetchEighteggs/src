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
