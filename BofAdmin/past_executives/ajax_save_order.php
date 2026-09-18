<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
require_once('../includes/action_security.php');

header('Content-Type: application/json; charset=utf-8');

$DB = mosConnectPDO();
if (!$DB) {
    echo json_encode(['status' => 'error', 'msg' => 'Database connection failed']);
    exit;
}
bofSecurityRequireAuth($DB, ['response' => 'json', 'login_url' => '../login.php']);
bofSecurityRequireMethod(['POST'], ['response' => 'json']);
bofSecurityRequireCsrf(null, '_csrf_token', ['response' => 'json']);
$MS = new MS($DB);
$UserID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderData = $input['orderData'] ?? [];

    if (empty($orderData)) {
        echo json_encode(['status' => 'error', 'msg' => 'ไม่พบข้อมูลที่ต้องการจัดเรียง']);
        exit;
    }

    try {
        $DB->beginTransaction();
        
        $stmt = $DB->prepare("UPDATE org_past_executive_terms SET OrderNo = ? WHERE TermID = ?");
        
        foreach ($orderData as $item) {
            $id = (int)($item['id'] ?? 0);
            $order = (int)($item['order'] ?? 0);
            
            if ($id > 0 && $order > 0) {
                $stmt->execute([$order, $id]);
            }
        }
        
        // Sync parent table's OrderNo to match their highest-ranked term
        $syncSql = "UPDATE org_past_executives p 
				JOIN (
						SELECT PastExecID, MIN(OrderNo) as MinOrder 
						FROM org_past_executive_terms 
						GROUP BY PastExecID
				) t ON p.PastExecID = t.PastExecID 
				SET p.OrderNo = t.MinOrder";
        $DB->exec($syncSql);
        
        $DB->commit();
        $MS->StaffLog('จัดเรียงลำดับทำเนียบผู้บริหาร', $_SERVER['REQUEST_URI'], $UserID, 3, 7);
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        $DB->rollBack();
        echo json_encode(['status' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid request method']);
}
