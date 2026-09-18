<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once('../../configuration.php');
include_once('../../config/ms_com.php');
require_once('../includes/action_security.php');

header('Content-Type: application/json; charset=utf-8');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['response' => 'json', 'login_url' => '../login.php']);
bofSecurityRequireMethod(['GET'], ['response' => 'json']);
if (!$DB) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}

try {
    $sql = "SELECT d.DeptID, d.ShortNameTH as NameTH, d.ShortNameEN as NameEN, d.IsActive, d.OrderNo as OrderNo 
            FROM microsite_departments d
            WHERE d.DelData = 'N' 
            AND EXISTS (SELECT 1 FROM org_positions p WHERE p.DeptID = d.DeptID AND p.DelData = 'N')
            ORDER BY d.OrderNo ASC, d.DeptID ASC";
            
    $stmt = $DB->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);
    
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
