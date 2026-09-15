<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../configuration.php');
include_once('../config/ms_com.php');

include_once('../config/ms.class.php');

if (isset($_SESSION['StaffID'])) {
    try {
        $DB = mosConnectPDO();
        $MS = new MS($DB);
        $MS->UserLoginLog($_SESSION['StaffID'], 'ออกจากระบบ');
        
        $updTokenStmt = $DB->prepare("UPDATE staff SET StaffToken = NULL, StaffTokenEXP = NULL WHERE StaffID = ?");
        $updTokenStmt->execute([$_SESSION['StaffID']]);
    } catch (PDOException $e) {}
}

session_unset();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: login.php");
exit;