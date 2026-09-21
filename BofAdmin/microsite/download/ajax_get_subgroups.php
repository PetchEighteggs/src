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
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$deptID = (int)($_SESSION['ActiveDeptID'] ?? 0);
try {
    $sql = "SELECT s.SubGroupID, s.SubGroupNameTH, s.IsActive, s.OrderNo
            FROM microsite_download_subgroups s
            INNER JOIN microsite_download_groups g ON g.GroupID = s.GroupID
            WHERE s.GroupID = ? AND g.DeptID = ?
              AND COALESCE(s.DelData, 'N') != 'Y'
              AND COALESCE(g.DelData, 'N') != 'Y'
            ORDER BY s.OrderNo ASC";
    $stmt = $DB->prepare($sql);
    $stmt->execute([$groupId, $deptID]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
