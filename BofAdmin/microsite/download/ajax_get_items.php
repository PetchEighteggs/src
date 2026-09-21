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
$subgroupId = isset($_GET['subgroup_id']) ? (int)$_GET['subgroup_id'] : 0;
$deptID = $_SESSION['ActiveDeptID'] ?? 0;
try {
    $sql = "SELECT DownloadID, Title_TH, IsActive, OrderNo 
            FROM microsite_download
            WHERE SubgroupID = ? AND DeptID = ? AND COALESCE(DelData, 'N') != 'Y'
            ORDER BY OrderNo ASC";
    $stmt = $DB->prepare($sql);
    $stmt->execute([$subgroupId, $deptID]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
