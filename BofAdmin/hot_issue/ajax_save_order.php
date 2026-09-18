<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
require_once('../includes/action_security.php');

header('Content-Type: application/json; charset=utf-8');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['response' => 'json', 'login_url' => '../login.php']);
bofSecurityRequireMethod(['POST'], ['response' => 'json']);
bofSecurityRequireCsrf(null, '_csrf_token', ['response' => 'json']);

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['order']) || !is_array($data['order'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

$MS = new MS($DB);

try {
    $DB->beginTransaction();

    $stmt = $DB->prepare("UPDATE hot_issue SET OrderNo = :order WHERE IssueID = :id");

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
    $MS->StaffLog('จัดเรียงลำดับ Hot Issue', $_SERVER['REQUEST_URI'], (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0), 2, 107, 0);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    $DB->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
