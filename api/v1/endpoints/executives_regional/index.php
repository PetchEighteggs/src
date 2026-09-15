<?php
// src/api/v1/endpoints/executives_regional/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB, $ImgPath;

try {
    if (isset($DB)) {
        // Query Regional executives (ExecGroup = 'regional')
        $sql = "SELECT e.ExecID, e.Image, e.NameTH, e.NameEN, e.Phone, e.Email, p.PositionTH, p.PositionEN
                FROM microsite_executives e
                INNER JOIN org_executive_positions p ON e.ExecID = p.ExecID
                WHERE e.DelData = 'N' AND p.DelData = 'N' AND p.IsActive = 'Y' AND p.ExecGroup = 'regional'
                ORDER BY p.OrderNo ASC, e.ExecID ASC";
        
        $stmt = $DB->prepare($sql);
        $stmt->execute();
        $executivesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        foreach ($executivesRaw as $row) {
            $imageKey = !empty($row['Image']) ? $ImgPath . 'executives/' . $row['Image'] : '';
            
            $item = [
                'id' => (int)$row['ExecID'],
                'imageKey' => $imageKey,
                'thName' => $row['NameTH'],
                'enName' => $row['NameEN'],
                'position' => $row['PositionTH'],
                'positionEn' => $row['PositionEN'],
                'phone' => $row['Phone'] ?: '',
                'email' => $row['Email'] ?: ''
            ];
            $response[] = $item;
        }
        
        echo json_encode(['executivesRegional' => $response], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['executivesRegional' => []]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
