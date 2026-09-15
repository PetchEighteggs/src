<?php
// src/api/v1/endpoints/popup/index.php
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
        $sql = "SELECT PopupID, Title, TitleEN, PublishStart, PublishEnd, Image, Link, LinkEn, OrderNo, IsActive FROM popup WHERE DelData = 'N' AND IsActive = 'Y'";
        
        // Optional date filter: PublishStart / PublishEnd
        $today = date('Y-m-d');
        $sql .= " AND (PublishStart IS NULL OR PublishStart <= :today) AND (PublishEnd IS NULL OR PublishEnd >= :today2)";

        if ($id > 0) {
            $sql .= " AND PopupID = :id";
        }
        $sql .= " ORDER BY OrderNo ASC, PopupID DESC";
        
        $stmt = $DB->prepare($sql);
        $stmt->bindValue(':today', $today, PDO::PARAM_STR);
        $stmt->bindValue(':today2', $today, PDO::PARAM_STR);
        
        if ($id > 0) {
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $popupsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        foreach ($popupsRaw as $row) {
            $coverStr = !empty($row['Image']) ? $ImgPath . 'popup/' . $row['Image'] : '';

            $response[] = [
                'id' => (int)$row['PopupID'],
                'title_th' => $row['Title'],
                'title_en' => $row['TitleEN'],
                'link_th' => $row['Link'],
                'link_en' => $row['LinkEn'],
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
