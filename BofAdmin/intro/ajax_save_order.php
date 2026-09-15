<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');

header('Content-Type: application/json');

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['order']) || !is_array($data['order'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

$DB = mosConnectPDO();
$MS = new MS($DB);

try {
    $DB->beginTransaction();

    $stmt = $DB->prepare("UPDATE intro SET OrderNo = :order WHERE IntroID = :id");

    foreach ($data['order'] as $item) {
        if (!empty($item['id']) && isset($item['order'])) {
            $stmt->execute([
                ':order' => (int)$item['order'],
                ':id' => (int)$item['id']
            ]);
        }
    }

    $DB->commit();
    
    // Log the action
    $MS->StaffLog('จัดเรียงลำดับ Intro', $_SERVER['REQUEST_URI'], $_SESSION['admin_id'] ?? 1, 2, 107, 0);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    $DB->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
