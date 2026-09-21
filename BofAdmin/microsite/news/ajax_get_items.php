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
$groupID = $_GET['group_id'] ?? 0;
try {
    $sql = "SELECT NewsID, Title_TH, Title_EN, IsActive, OrderNo 
            FROM microsite_news
            WHERE GroupID = ? AND COALESCE(DelData, 'N') != 'Y'
            ORDER BY OrderNo ASC";
    $stmt = $DB->prepare($sql);
    $stmt->execute([$groupID]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
