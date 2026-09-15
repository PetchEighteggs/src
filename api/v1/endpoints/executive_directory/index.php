<?php
// src/api/v1/endpoints/executive_directory/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB, $ImgPath;

try {
    if (isset($DB)) {
        // Query Past Executives and terms (1 to 1 mapping with terms) matching sort.php
        $sql = "SELECT p.PastExecID, p.Image, p.NameTH, p.NameEN, p.PositionTH, p.PositionEN, t.TermID, t.TermYear
				FROM org_past_executive_terms t
				JOIN org_past_executives p ON t.PastExecID = p.PastExecID
				WHERE p.DelData = 'N' AND p.IsActive = 'Y'
				ORDER BY t.OrderNo ASC, p.OrderNo ASC";
        
        $stmt = $DB->prepare($sql);
        $stmt->execute();
        $executivesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        foreach ($executivesRaw as $row) {
            $imageKey = !empty($row['Image']) ? $ImgPath . 'past_executives/' . $row['Image'] : '';
            
            $item = [
                'id' => (int)$row['TermID'],
                'pastExecId' => (int)$row['PastExecID'],
                'imageKey' => $imageKey,
                'thName' => $row['NameTH'],
                'enName' => $row['NameEN'],
                'position' => $row['PositionTH'],
                'positionEn' => $row['PositionEN'],
                'terms' => !empty($row['TermYear']) ? [$row['TermYear']] : []
            ];
            $response[] = $item;
        }
        
        echo json_encode(['executiveDirectory' => $response], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['executiveDirectory' => []]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
