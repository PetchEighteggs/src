<?php
header("Access-Control-Allow-Origin: *"); // อนุญาตให้ React (CORS) เรียกใช้งานได้
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';

// รับข้อมูลจาก React
$data = json_decode(file_get_contents("php://input"));

if(isset($data->reference_code) && isset($data->otp_code)) {
    $refCode = filter_var($data->reference_code, FILTER_SANITIZE_STRING);
    $otpCode = filter_var($data->otp_code, FILTER_SANITIZE_STRING);

    // ตรวจสอบว่ามี OTP นี้อยู่ในระบบและยังไม่หมดอายุหรือถูกใช้ไปแล้วหรือไม่
    $stmt = $DB->prepare("SELECT id, member_id, expires_at, is_used FROM otp_codes WHERE reference_code = ? AND otp_code = ? LIMIT 1");
    $stmt->execute([$refCode, $otpCode]);
    $otpRecord = $stmt->fetch();

    if($otpRecord) {
        if($otpRecord['is_used'] == 1) {
            echo json_encode(["status" => "error", "message" => "รหัส OTP นี้ถูกใช้งานไปแล้ว"]);
            exit;
        }

        $now = date("Y-m-d H:i:s");
        if($now > $otpRecord['expires_at']) {
            echo json_encode(["status" => "error", "message" => "รหัส OTP นี้หมดอายุแล้ว กรุณาขอรหัสใหม่"]);
            exit;
        }

        // อัปเดตสถานะ OTP ว่าใช้งานแล้ว
        $updateStmt = $DB->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?");
        $updateStmt->execute([$otpRecord['id']]);

        // ดึงข้อมูลสมาชิกเพื่อออก Token (เข้าสู่ระบบสำเร็จ)
        $userStmt = $DB->prepare("SELECT id, email, name FROM members WHERE id = ?");
        $userStmt->execute([$otpRecord['member_id']]);
        $user = $userStmt->fetch();

        if($user) {
            // ตัวอย่าง: การสร้าง JWT Token แบบจำลอง (ในการใช้งานจริง ควรใช้ JWT Library)
            $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
            $payload = json_encode(['user_id' => $user['id'], 'email' => $user['email'], 'exp' => time() + (60*60*24)]);
            $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
            $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, 'YOUR_SECRET_KEY', true);
            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            $jwtToken = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

            echo json_encode([
                "status" => "success",
                "message" => "ยืนยันตัวตนสำเร็จ",
                "token" => $jwtToken,
                "user" => [
                    "id" => $user['id'],
                    "name" => $user['name'],
                    "email" => $user['email']
                ]
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "ไม่พบข้อมูลผู้ใช้งาน"]);
        }

    } else {
        echo json_encode(["status" => "error", "message" => "รหัส OTP หรือรหัสอ้างอิงไม่ถูกต้อง"]);
    }
} else {
  echo json_encode(["status" => "error", "message" => "ข้อมูลไม่ครบถ้วน"]);
}
?>