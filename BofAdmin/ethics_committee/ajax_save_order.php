<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderData = $input['orderData'] ?? [];

    if (empty($orderData)) {
        echo json_encode(['status' => 'error', 'msg' => 'ไม่พบข้อมูลที่ต้องการจัดเรียง']);
        exit;
    }

    include_once('../../configuration.php');
    include_once('../../config/ms_com.php');
    include_once('../../config/ms.class.php');
    
    $DB = mosConnectPDO();
    if (!$DB) {
        echo json_encode(['status' => 'error', 'msg' => 'เชื่อมต่อฐานข้อมูลไม่ได้']);
        exit;
    }
    
    $MS = new MS($DB);
    $UserID = $_SESSION['admin_id'] ?? 0;

    try {
        $DB->beginTransaction();
        
        $stmt = $DB->prepare("UPDATE org_ethics_committee SET OrderNo = ? WHERE EthicsID = ?");
        
        foreach ($orderData as $item) {
            $id = (int)($item['id'] ?? 0);
            $order = (int)($item['order'] ?? 0);
            
            if ($id > 0 && $order > 0) {
                $stmt->execute([$order, $id]);
            }
        }
        
        $DB->commit();
        $MS->StaffLog('จัดเรียงลำดับคณะกรรมการจริยธรรมประจำกรม', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 3, 7);
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        $DB->rollBack();
        echo json_encode(['status' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid request method']);
}
