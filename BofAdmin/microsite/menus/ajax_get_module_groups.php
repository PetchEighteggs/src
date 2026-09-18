<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include_once('../../../configuration.php');
include_once('../../../config/ms_com.php');

header('Content-Type: application/json');

$DB = mosConnectPDO();
if (!$DB) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
$moduleType = $_GET['module_type'] ?? '';

if (empty($moduleType)) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

try {
    $tableName = '';
    $idField = '';
    $nameField = '';
    
    switch ($moduleType) {
        case 'about':
            $tableName = 'microsite_about_groups';
            $idField = 'GroupID';
            $nameField = 'GroupNameTH';
            break;
        case 'news':
            $tableName = 'microsite_news_groups';
            $idField = 'GroupID';
            $nameField = 'GroupNameTH';
            break;
        case 'multimedia':
            $tableName = 'microsite_multimedia_groups';
            $idField = 'GroupID';
            $nameField = 'GroupNameTH';
            break;
        case 'download':
            $tableName = 'microsite_download_groups';
            $idField = 'GroupID';
            $nameField = 'GroupNameTH';
            break;
        case 'gallery':
            $tableName = 'microsite_gallery_groups';
            $idField = 'GroupID';
            $nameField = 'GroupNameTH';
            break;
        default:
            echo json_encode(['status' => 'success', 'data' => []]);
            exit;
    }
    
    // Some tables use COALESCE(DelData, 'N') <> 'Y' and some use DelData IS NULL OR DelData = 'N'
    // Let's use a robust WHERE clause
    $sql = "SELECT $idField as RefID, $nameField as RefName 
            FROM $tableName 
            WHERE DeptID = ? AND (DelData IS NULL OR DelData != 'Y')";
            
    // For some tables there's an OrderNo
    // To be safe we will just order by the ID if we don't know for sure they all have OrderNo
    $sql .= " ORDER BY $idField ASC";
            
    $stmt = $DB->prepare($sql);
    $stmt->execute([$deptID]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);
    
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
