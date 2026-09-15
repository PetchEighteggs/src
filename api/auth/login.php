<?php
header("Access-Control-Allow-Origin: *"); // อนุญาตให้ React (CORS) เรียกใช้งานได้
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include func.class.php for send_mail function
$_Config_absolute_path = dirname(dirname(__DIR__)); // ไปที่โฟลเดอร์ src
require_once $_Config_absolute_path . '/config/func.class.php';
require_once 'db.php';

// รับข้อมูลจาก React
$data = json_decode(file_get_contents("php://input"));

if(isset($data->email) && isset($data->password)) {
    $email = filter_var($data->email, FILTER_SANITIZE_EMAIL);
    $password = $data->password;

    // ค้นหาสมาชิกด้วย Email
    $stmt = $DB->prepare("SELECT id, email, password, name, is_2fa_enabled FROM members WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if($user) {
        // ตรวจสอบรหัสผ่าน
        if(password_verify($password, $user['password'])) {
            
            // เช็คว่าเปิด 2FA ไว้หรือไม่
            if($user['is_2fa_enabled'] == 1) {
                
                // ล้าง OTP เก่าที่หมดอายุเกิน 1 วันทิ้งอัตโนมัติ เพื่อไม่ให้รกฐานข้อมูล
                $DB->exec("DELETE FROM otp_codes WHERE expires_at < NOW() - INTERVAL 1 DAY");
                
                $otpCode = sprintf("%06d", mt_rand(1, 999999));
                $refCode = 'REF-' . strtoupper(substr(md5(uniqid()), 0, 6));
                $expiresAt = date("Y-m-d H:i:s", strtotime('+5 minutes'));

                // บันทึก OTP ลงฐานข้อมูล
                $insertOtp = $DB->prepare("INSERT INTO otp_codes (member_id, reference_code, otp_code, expires_at) VALUES (?, ?, ?, ?)");
                
                if($insertOtp->execute([$user['id'], $refCode, $otpCode, $expiresAt])) {
                    $enable_real_email = false; // ปรับเป็น true เมื่อต้องการให้ส่งอีเมลจริง / false เพื่อทดสอบโดยไม่ส่งอีเมล

                    if ($enable_real_email) {
                        // ส่งอีเมล (แก้ไขรายละเอียดผู้ส่ง/รับตามความเหมาะสม)
                        $subject = "รหัส OTP ของคุณคือ: " . $otpCode . " (Ref: " . $refCode . ")";
                        $msg = "<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
																<h3 style='color: #005A9C;'>แจ้งเตือนความปลอดภัย</h3>
																<p>มีความพยายามลงชื่อเข้าใช้ระบบ Intranet กรมทรัพยากรน้ำบาดาล จำเป็นต้องมีการยืนยันตัวตนเพิ่มเติมเพื่อความปลอดภัยสูงสุดของข้อมูลภายในองค์กร</p>
																<p>รหัสยืนยัน (OTP) ของคุณคือ: <span style='font-size: 24px; font-weight: bold; color: #009FE3;'>{$otpCode}</span></p>
																<p>รหัสอ้างอิง: <strong>{$refCode}</strong> (รหัสนี้มีอายุการใช้งานภายใน 5 นาที)</p>
																<hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
																<p style='font-size: 14px; color: #666;'>หากคุณเป็นผู้ดำเนินการเข้าสู่ระบบด้วยตนเอง โปรดนำรหัสนี้ไปกรอกลงในหน้าจอเข้าสู่ระบบให้เรียบร้อย<br>
																แต่หากคุณไม่ได้เป็นผู้พยายามลงชื่อเข้าใช้ในครั้งนี้ บัญชีของคุณอาจมีความเสี่ยง โปรดเปลี่ยนรหัสผ่านทันที หรือติดต่อผู้ดูแลระบบสารสนเทศ กรมทรัพยากรน้ำบาดาลเพื่อตรวจสอบความปลอดภัยของบัญชี</p>
                                </div>";
                        $to_name = $user['name'];
                        $to_email = $user['email'];
                        $from_name = $_EmailSender_name;
                        $from_email = $_EmailSender_from;
                        $isSMTP = 1; // 1 = ใช้ SMTP ที่ตั้งค่าใน func.class.php

                        $sendResult = FU::send_mail($subject, $msg, $to_name, $to_email, $from_name, $from_email, $isSMTP);
                    } else {
                        // จำลองว่าส่งอีเมลสำเร็จแล้ว
                        $sendResult = 1;
                    }

                    if($sendResult == 1) {
                        $response = [
                            "status" => "2FA_REQUIRED",
                            "reference_code" => $refCode,
                            "expires_at" => $expiresAt, // เพิ่มเวลาหมดอายุส่งให้ Frontend เพื่อเอาไปทำตัวนับเวลาถอยหลัง
                            "message" => $enable_real_email ? "กรุณาตรวจสอบรหัส OTP ที่อีเมลของคุณ" : "เข้าสู่โหมดทดสอบ (ปิดการส่งอีเมลจริง)"
                        ];
                        
                        // แอบส่ง OTP แนบกลับไปใน Response ด้วยเวลาปิดอีเมล จะได้ทดสอบได้ง่ายๆ
                        if(!$enable_real_email) {
                            $response["test_otp_code"] = $otpCode;
                        }
                        
                        echo json_encode($response);
                    } else {
                        echo json_encode([
                            "status" => "error",
                            "message" => "ไม่สามารถส่งอีเมล OTP ได้ กรุณาลองใหม่อีกครั้ง"
                        ]);
                    }
                } else {
                    echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาดในการสร้าง OTP"]);
                }
            } else {
                // ถ้าไม่ได้เปิด 2FA ให้สร้าง Token และเข้าสู่ระบบเลย
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
                    "token" => $jwtToken,
                    "user" => [
                        "id" => $user['id'],
                        "name" => $user['name'],
                        "email" => $user['email']
                    ]
                ]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "อีเมลหรือรหัสผ่านไม่ถูกต้อง"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "อีเมลหรือรหัสผ่านไม่ถูกต้อง"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ข้อมูลไม่ครบถ้วน"]);
}
?>