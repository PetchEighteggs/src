<?php
// src/api/v1/endpoints/ethics_committee/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB, $ImgPath;

try {
    if (isset($DB)) {
        // Query ethics committee members
        $sql = "SELECT EthicsID, NameTH, NameEN, PositionTH, PositionEN, Image 
                FROM org_ethics_committee 
                WHERE DelData = 'N' AND IsActive = 'Y' 
                ORDER BY OrderNo ASC, EthicsID ASC";
        
        $stmt = $DB->prepare($sql);
        $stmt->execute();
        $membersRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        foreach ($membersRaw as $row) {
            $imageKey = !empty($row['Image']) ? $ImgPath . 'ethics_committee/' . $row['Image'] : '';
            
            $item = [
                'id' => (int)$row['EthicsID'],
                'imageKey' => $imageKey,
                'thName' => $row['NameTH'],
                'enName' => $row['NameEN'],
                'position' => $row['PositionTH'],
                'positionEn' => $row['PositionEN']
            ];
            $response[] = $item;
        }
        
        echo json_encode(['data' => $response], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['data' => []]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
