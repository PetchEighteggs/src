<?php
define('_VALID_ACCESS', 1);
require_once('../../configuration.php');
require_once('../../config/ms_com.php');

try {
    $DB = mosConnectPDO();
    if ($DB) {
        $DB->exec("TRUNCATE TABLE bof_logs");
        echo "<h1>ลบข้อมูลในตาราง bof_logs สำเร็จเรียบร้อยแล้ว! (Truncated via PHP)</h1>";
        echo "<a href='index.php'>กลับไปยังหน้า Logs</a>";
    } else {
        echo "Cannot connect to DB";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
