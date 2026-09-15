<?php
// src/api/v1/endpoints/org_related/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB, $ImgPath;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
$idParam = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    if (isset($DB)) {
        $sql = "SELECT RelatedID, Title_TH, Title_EN, Image, URL_TH, URL_EN 
                FROM org_related
                WHERE DelData = 'N' AND IsActive = 'Y'";
        
        $params = [];
        
        if ($idParam > 0) {
            $sql .= " AND RelatedID = :id";
            $params[':id'] = $idParam;
        }

        // Filter by active dates
        $today = date('Y-m-d');
        $sql .= " AND (StartDate IS NULL OR StartDate = '0000-00-00' OR StartDate <= :today1)";
        $sql .= " AND (EndDate IS NULL OR EndDate = '0000-00-00' OR EndDate >= :today2)";
        $params[':today1'] = $today;
        $params[':today2'] = $today;
        
        $sql .= " ORDER BY OrderNo ASC, RelatedID DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        
        $stmt = $DB->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        $relatedRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        foreach ($relatedRaw as $row) {
            $imageKey = !empty($row['Image']) ? $ImgPath . 'org_related/' . $row['Image'] : '';
            $title = $lang === 'en' && !empty($row['Title_EN']) ? $row['Title_EN'] : $row['Title_TH'];
            $url = $lang === 'en' && !empty($row['URL_EN']) ? $row['URL_EN'] : $row['URL_TH'];
            
            $item = [
                'id' => (int)$row['RelatedID'],
                'title' => $title,
                'image' => $imageKey,
                'url' => $url ?: ''
            ];
            $response[] = $item;
        }
        
        if ($idParam > 0) {
            echo json_encode(!empty($response) ? $response[0] : null, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    } else {
        echo json_encode([]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
