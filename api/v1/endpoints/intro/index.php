<?php
// src/api/v1/endpoints/intro/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB, $ImgPath;

$idParam = isset($_GET['id']) ? $_GET['id'] : 0;
$id = is_numeric($idParam) ? intval($idParam) : 0;

try {
    if (isset($DB)) {
        $sql = "SELECT * FROM intro WHERE DelData = 'N' AND IsActive = 'Y'";
        
        // Optional date filter: PublishStart / PublishEnd
        $today = date('Y-m-d');
        $sql .= " AND (PublishStart IS NULL OR PublishStart <= :today) AND (PublishEnd IS NULL OR PublishEnd >= :today2)";

        if ($id > 0) {
            $sql .= " AND IntroID = :id";
        }
        $sql .= " ORDER BY OrderNo ASC, IntroID DESC";
        
        $stmt = $DB->prepare($sql);
        $stmt->bindValue(':today', $today, PDO::PARAM_STR);
        $stmt->bindValue(':today2', $today, PDO::PARAM_STR);
        
        if ($id > 0) {
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $introsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        foreach ($introsRaw as $row) {
            $coverStr = !empty($row['Image']) ? $ImgPath . 'intro/' . $row['Image'] : '';

            $response[] = [
                'id' => (int)$row['IntroID'],
                'title_th' => $row['Title'],
                'title_en' => $row['TitleEN'],
                'image_url' => $coverStr
            ];
        }

        if ($id > 0) {
            echo json_encode(!empty($response) ? $response[0] : null, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    } else {
        echo json_encode(null);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
