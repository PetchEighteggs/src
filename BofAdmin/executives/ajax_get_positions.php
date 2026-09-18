<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
include_once('../../config/func.class.php');
require_once('../includes/action_security.php');

header('Content-Type: application/json; charset=utf-8');

$DB = mosConnectPDO();
if (!$DB) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}
bofSecurityRequireAuth($DB, ['response' => 'json', 'login_url' => '../login.php']);
bofSecurityRequireMethod(['GET'], ['response' => 'json']);

$group = $_GET['group'] ?? '';

if (empty($group)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    $sql = "SELECT p.MapID, p.PositionTH, p.OrderNo, p.IsActive, e.NameTH, e.Image 
            FROM org_executive_positions p
            JOIN microsite_executives e ON p.ExecID = e.ExecID
            WHERE p.DelData = 'N' AND e.DelData = 'N' ";
    
    $params = [];
    
    if ($group === 'dept') {
        $sql .= " AND (p.ExecGroup = 'dept' OR (p.ExecGroup = '' AND p.DeptID = 0)) ";
    } elseif ($group === 'past_exec') {
        $sql .= " AND p.ExecGroup = 'past_exec' ";
    } elseif ($group === 'central' || $group === 'regional') {
        $sql .= " AND EXISTS (SELECT 1 FROM microsite_departments d WHERE d.DeptID = p.DeptID AND d.DeptGroup = ?) ";
        $params[] = $group;
    }
    
    $sql .= " ORDER BY p.OrderNo ASC, p.MapID ASC";
    
    $stmt = $DB->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
