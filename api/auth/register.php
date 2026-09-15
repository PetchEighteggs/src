<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';

$data = json_decode(file_get_contents("php://input"));

if(isset($data->email) && isset($data->password) && isset($data->name)) {
    $email = filter_var($data->email, FILTER_SANITIZE_EMAIL);
    $name = filter_var($data->name, FILTER_SANITIZE_STRING); // ใช้ FILTER_SANITIZE_FULL_SPECIAL_CHARS ใน PHP 8+ ถ้ามี
    $password = $data->password;
    
    // บังคับเปิด 2FA อัตโนมัติเสมอ (1 = เปิด)
    $is_2fa_enabled = 1;

    // ตรวจสอบอีเมลซ้ำ
    $checkEmail = $DB->prepare("SELECT id FROM members WHERE email = ? LIMIT 1");
    $checkEmail->execute([$email]);
    if($checkEmail->fetch()) {
        echo json_encode(["status" => "error", "message" => "อีเมลนี้มีผู้ใช้งานแล้วในระบบ"]);
        exit;
    }

    // เข้ารหัสผ่านแบบ Bcrypt
    $hashPassword = password_hash($password, PASSWORD_DEFAULT);

    // บันทึกลงฐานข้อมูล
    $insert = $DB->prepare("INSERT INTO members (email, password, name, is_2fa_enabled) VALUES (?, ?, ?, ?)");
    if($insert->execute([$email, $hashPassword, $name, $is_2fa_enabled])) {
        echo json_encode([
            "status" => "success", 
            "message" => "เพิ่มอีเมลเข้าสู่ระบบสำเร็จแล้ว!",
            "data" => [
                "email" => $email,
                "name" => $name
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "กรุณาส่งข้อมูลให้ครบถ้วน (email, password, name)"]);
}
?>
