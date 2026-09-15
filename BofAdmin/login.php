<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../configuration.php');
include_once('../config/ms_com.php');
include_once('../config/ms.class.php');

$DB = mosConnectPDO();
if (!$DB) { die("No DB connection"); }

if (isset($_SESSION['BofAdmin_LoggedIn']) && $_SESSION['BofAdmin_LoggedIn'] === true) {
    header("Location: index.php");
    exit;
}

function decodeStr($enVariable) {
    if (empty($enVariable)) return "";
    $enVariable = str_replace("WewEb", "%", $enVariable);
    $enVariable = str_rot13($enVariable);
    $enVariable = urldecode($enVariable);
    $enVariable = utf8_decode($enVariable);
    $enVariable = base64_decode($enVariable);
    $enVariable = strrev($enVariable);
    $current = 0;
    $temp = "";
    for ($i = 0; $i < strlen($enVariable); $i++) {
        if ($current % 2 == 0) {
            $temp .= $enVariable[$i];
        }
        $current++;
    }
    $temp = str_replace("?O", "=", $temp);
    return $temp;
}

if (isset($_GET['error'])) {
	if ($_GET['error'] === 'no_permission') {
		$error = "คุณไม่มีสิทธิ์เข้าใช้งานระบบ";
	} elseif ($_GET['error'] === 'session_expired') {
		$error = "เซสชันของคุณหมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้ง";
	}
	session_unset();
}

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mosGetParam($_POST, 'username', '');
    $password = mosGetParam($_POST, 'password', '');
    
    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $DB->prepare("SELECT * FROM staff WHERE Username = ? AND DelData = 'N' AND Status = 'Y'");
            $stmt->execute([$username]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRow) {
                $dbPassword = $userRow['Password'];
                $isPasswordCorrect = false;

                if (password_verify($password, $dbPassword)) {
                    $isPasswordCorrect = true;
                    if (password_needs_rehash($dbPassword, PASSWORD_DEFAULT)) {
                        $bcryptHash = password_hash($password, PASSWORD_DEFAULT);
                        $updStmt = $DB->prepare("UPDATE staff SET Password = ?, UpdateDate = NOW() WHERE StaffID = ?");
                        $updStmt->execute([$bcryptHash, $userRow['StaffID']]);
                    }
                } 
                elseif ($dbPassword === md5(salt_pass($password))) {
                    $isPasswordCorrect = true;
                    $bcryptHash = password_hash($password, PASSWORD_DEFAULT);
                    $updStmt = $DB->prepare("UPDATE staff SET Password = ?, UpdateDate = NOW() WHERE StaffID = ?");
                    $updStmt->execute([$bcryptHash, $userRow['StaffID']]);
                }
                elseif ($dbPassword === md5($password)) {
                    $isPasswordCorrect = true;
                    $bcryptHash = password_hash($password, PASSWORD_DEFAULT);
                    $updStmt = $DB->prepare("UPDATE staff SET Password = ?, UpdateDate = NOW() WHERE StaffID = ?");
                    $updStmt->execute([$bcryptHash, $userRow['StaffID']]);
                }
                elseif (decodeStr($dbPassword) === $password) {
                    $isPasswordCorrect = true;
                    $bcryptHash = password_hash($password, PASSWORD_DEFAULT);
                    $updStmt = $DB->prepare("UPDATE staff SET Password = ?, UpdateDate = NOW() WHERE StaffID = ?");
                    $updStmt->execute([$bcryptHash, $userRow['StaffID']]);
                }

                if ($isPasswordCorrect) {
                    $staffToken = bin2hex(random_bytes(24));
                    $staffTokenExp = date('Y-m-d H:i:s', strtotime('+2 hours'));
                    $lastAccessIP = $_SERVER['REMOTE_ADDR'] ?? '';
                    
                    $updTokenStmt = $DB->prepare("UPDATE staff SET StaffToken = ?, StaffTokenEXP = ?, LastAccess = NOW(), LastAccessIP = ? WHERE StaffID = ?");
                    $updTokenStmt->execute([$staffToken, $staffTokenExp, $lastAccessIP, $userRow['StaffID']]);

                    $_SESSION['BofAdmin_LoggedIn'] = true;
                    $_SESSION['BofAdmin_User'] = $userRow['Username'];
                    $_SESSION['Name'] = $userRow['Name'];
                    $_SESSION['StaffID'] = $userRow['StaffID'];
                    $_SESSION['admin_id'] = $userRow['StaffID'];
                    $_SESSION['GroupID'] = $userRow['GroupID'];
                    $_SESSION['SetGroupID'] = $userRow['SetGroupID'];
                    $_SESSION['StaffToken'] = $staffToken;

                    if ($userRow['GroupID'] === 'S') {
                        $deptStmt = $DB->prepare("
                            SELECT d.DeptID, d.NameTH 
                            FROM staff_microsite_access a 
                            JOIN microsite_departments d ON a.DeptID = d.DeptID 
                            WHERE a.StaffID = ? AND d.IsActive = 'Y'
                        ");
                        $deptStmt->execute([$userRow['StaffID']]);
                        $depts = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $_SESSION['AllowedDepts'] = $depts;
                        if (count($depts) === 1) {
                            $_SESSION['ActiveDeptID'] = $depts[0]['DeptID'];
                            $_SESSION['ActiveDeptName'] = $depts[0]['NameTH'];
                        }
                    }
                    
                    // Log success
                    $MS = new MS($DB);
                    $MS->UserLoginLog($userRow['StaffID'], 'การเข้าสู่ระบบ');

                    header("Location: index.php");
                    exit;
                } else {
                    $error = "รหัสผ่านไม่ถูกต้อง";
                }
            } else {
                $error = "ไม่พบชื่อผู้ใช้งานนี้ในระบบ หรือถูกระงับสิทธิ์";
            }
        } catch(PDOException $e) {
            $error = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $e->getMessage();
        }
    } else {
        $error = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DGR Backoffice</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Kanit', sans-serif; }
        body {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #005A9C 0%, #009FE3 100%);
            position: relative; overflow: hidden;
        }
        .bg-shape {
            position: absolute; border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 10s infinite ease-in-out alternate;
        }
        .shape1 { width: 400px; height: 400px; top: -100px; left: -100px; }
        .shape2 { width: 300px; height: 300px; bottom: -50px; right: -100px; animation-delay: -5s; }
        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            100% { transform: translateY(50px) rotate(20deg); }
        }
        .login-container {
            width: 100%; max-width: 400px; padding: 40px;
            background: rgba(255, 255, 255, 0.95); border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2); backdrop-filter: blur(10px);
            z-index: 10; transform: translateY(20px); opacity: 0;
            animation: slideUp 0.8s ease forwards;
        }
        @keyframes slideUp { to { transform: translateY(0); opacity: 1; } }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo img { width: 80px; height: auto; }
        .logo h2 { color: #333; font-size: 24px; margin-top: 15px; font-weight: 600; }
        .logo p { color: #666; font-size: 14px; margin-top: 5px; }
        .form-group { margin-bottom: 25px; position: relative; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; font-size: 14px; }
        .form-control {
            width: 100%; padding: 15px 20px; border: 2px solid #e1e5ea;
            border-radius: 10px; font-size: 16px; transition: all 0.3s ease;
            outline: none; background: #f8fafc;
        }
        .form-control:focus {
            border-color: #009FE3; background: #fff;
            box-shadow: 0 0 0 4px rgba(0, 159, 227, 0.1);
        }
        .btn-login {
            width: 100%; padding: 15px; background: linear-gradient(135deg, #005A9C, #009FE3);
            color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600;
            cursor: pointer; transition: all 0.3s ease; box-shadow: 0 5px 15px rgba(0, 159, 227, 0.3);
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0, 159, 227, 0.4); }
        .btn-login:active { transform: translateY(1px); }
        .error-msg {
            color: #dc3545; background: #ffe6e6; padding: 10px; border-radius: 8px;
            margin-bottom: 20px; font-size: 14px; text-align: center;
        }
        .footer-text { text-align: center; margin-top: 25px; font-size: 12px; color: #888; }
    </style>
        <script src="https://unpkg.com/@phosphor-icons/web"></script>
    </head>
    <body>
        <div class="bg-shape shape1"></div>
        <div class="bg-shape shape2"></div>
        <div class="login-container">
            <div class="logo">
                <img src="assets/images/logo.png" alt="DGR Logo" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI0MCIgZmlsbD0iIzAwNUE5QyIvPjwvc3ZnPg=='">
                <h2>DGR Backoffice</h2>
                <p>ระบบจัดการเว็บไซต์ กรมทรัพยากรน้ำบาดาล</p>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="error-msg"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้งาน</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="กรอกชื่อผู้ใช้งาน..." required>
                </div>
                <div class="form-group">
                    <label for="password">รหัสผ่าน</label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="form-control" placeholder="กรอกรหัสผ่าน..." required style="padding-right: 45px;">
                        <button type="button" id="togglePassword" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #888; cursor: pointer; padding: 0;">
                            <i class="ph ph-eye" id="eyeIcon" style="font-size: 20px;"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
            </form>
            
            <div class="footer-text">
                &copy; <?php echo date('Y'); ?> กรมทรัพยากรน้ำบาดาล. All rights reserved.
            </div>
        </div>

        <script>
            const togglePassword = document.querySelector('#togglePassword');
            const password = document.querySelector('#password');
            const eyeIcon = document.querySelector('#eyeIcon');

            togglePassword.addEventListener('click', function (e) {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                
                if (type === 'password') {
                    eyeIcon.classList.remove('ph-eye-slash');
                    eyeIcon.classList.add('ph-eye');
                } else {
                    eyeIcon.classList.remove('ph-eye');
                    eyeIcon.classList.add('ph-eye-slash');
                }
            });
        </script>
</body>
</html>