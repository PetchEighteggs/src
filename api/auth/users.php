<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");

require_once 'db.php';

try {
    // ดึงข้อมูลสมาชิกทั้งหมด
    $stmt = $DB->query("SELECT id, email, name, created_at FROM members ORDER BY id DESC");
    $users = $stmt->fetchAll();
    
    echo json_encode([
        "status" => "success",
        "total_users" => count($users),
        "data" => $users
    ]);
} catch(PDOException $e) {
    error_log('Auth users endpoint failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "ไม่สามารถดึงข้อมูลได้"]);
}
?>
