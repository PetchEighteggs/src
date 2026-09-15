<?php
$_Config_absolute_path = dirname(dirname(__DIR__));
require_once $_Config_absolute_path . '/configuration.php';

try {
    $DB = new PDO("mysql:host=$DBServer;dbname=$DBName;charset=utf8mb4", $DBUsername, $DBPassword);
    $DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $DB->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]));
}
?>
