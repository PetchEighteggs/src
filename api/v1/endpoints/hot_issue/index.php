<?php
// src/api/v1/endpoints/hot_issue/index.php
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
        $sql = "SELECT h.IssueID, h.Title, h.TitleEN, h.PublishEnd, h.Image, h.Link, h.LinkEn
                FROM hot_issue h
                WHERE h.DelData = 'N' AND h.IsActive = 'Y'";
        
        $params = [];
        
        if ($idParam > 0) {
            $sql .= " AND h.IssueID = :id";
            $params[':id'] = $idParam;
        }

        // Optional: filter by active dates
        $today = date('Y-m-d');
        $sql .= " AND (h.PublishStart IS NULL OR h.PublishStart = '0000-00-00' OR h.PublishStart <= :today1)";
        $sql .= " AND (h.PublishEnd IS NULL OR h.PublishEnd = '0000-00-00' OR h.PublishEnd >= :today2)";
        $params[':today1'] = $today;
        $params[':today2'] = $today;
        
        $sql .= " ORDER BY h.OrderNo ASC, h.IssueID DESC";
        
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
        $hotIssuesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        foreach ($hotIssuesRaw as $row) {
            $imageKey = !empty($row['Image']) ? $ImgPath . 'hot_issue/' . $row['Image'] : '';
            $title = $lang === 'en' && !empty($row['TitleEN']) ? $row['TitleEN'] : $row['Title'];
            

            
            $expireDate = '';
            if (!empty($row['PublishEnd']) && $row['PublishEnd'] !== '0000-00-00') {
                $expireDate = $row['PublishEnd'];
            }
            
            $item = [
                'id' => (int)$row['IssueID'],
                'title' => $title,
                'expiredate' => $expireDate,
                'image' => $imageKey,
                'link' => $row['Link'] ?: '',
                'linkEn' => $row['LinkEn'] ?: ''
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
