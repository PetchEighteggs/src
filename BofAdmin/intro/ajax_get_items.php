<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once('../../configuration.php');
include_once('../../config/ms_com.php');

header('Content-Type: application/json');

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$DB = mosConnectPDO();

try {
    $stmt = $DB->prepare("
        SELECT IntroID, Title, Image, IsActive, OrderNo
        FROM intro
        WHERE DelData = 'N'
        ORDER BY OrderNo ASC, IntroID DESC
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $items
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
