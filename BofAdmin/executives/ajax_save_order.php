<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
include_once('../../config/func.class.php');

header('Content-Type: application/json');

$DB = mosConnectPDO();
if (!$DB) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}

// Get JSON POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (empty($data['order']) || !is_array($data['order'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data format']);
    exit;
}

try {
    $DB->beginTransaction();
    
    $stmt = $DB->prepare("UPDATE org_executive_positions SET OrderNo = ? WHERE MapID = ?");
    
    foreach ($data['order'] as $item) {
        $mapId = $item['id'] ?? 0;
        $orderNo = $item['order'] ?? 99;
        
        if ($mapId > 0) {
            $stmt->execute([$orderNo, $mapId]);
        }
    }
    
    $DB->commit();
    echo json_encode(['status' => 'success', 'message' => 'Order updated successfully']);
    
} catch (PDOException $e) {
    $DB->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
