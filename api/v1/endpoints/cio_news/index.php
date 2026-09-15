<?php
// src/api/v1/endpoints/cio_news/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
$idParam = isset($_GET['id']) ? $_GET['id'] : 0;
$id = is_numeric($idParam) ? intval($idParam) : 0;

try {
    if (isset($DB)) {
        $sql = "
            SELECT *
            FROM cio_news 
            WHERE DelData = 'N' AND IsActive = 'Y'
        ";

        $params = [];

        if ($id > 0) {
            $sql .= " AND NewsID = :id";
            $params[':id'] = $id;
        }

        $sql .= " ORDER BY PublishDate DESC, CreateDate DESC";

        if ($limit > 0 && $id == 0) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }

        $stmt = $DB->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $monthsTH = [
            '01'=>'มกราคม', '02'=>'กุมภาพันธ์', '03'=>'มีนาคม', '04'=>'เมษายน',
            '05'=>'พฤษภาคม', '06'=>'มิถุนายน', '07'=>'กรกฎาคม', '08'=>'สิงหาคม',
            '09'=>'กันยายน', '10'=>'ตุลาคม', '11'=>'พฤศจิกายน', '12'=>'ธันวาคม'
        ];

        $response = [];

        foreach ($raw as $row) {
            $title = $lang === 'en' && !empty($row['Title_EN']) ? $row['Title_EN'] : $row['Title_TH'];
            $content = $lang === 'en' && !empty($row['Content_EN']) ? $row['Content_EN'] : $row['Content_TH'];
            
            $formattedDate = '';
            $targetDate = !empty($row['PublishDate']) ? $row['PublishDate'] : $row['CreateDate'];
            if (!empty($targetDate)) {
                $ts = strtotime($targetDate);
                $d = date('j', $ts);
                $m = $monthsTH[date('m', $ts)] ?? '';
                $y = date('Y', $ts) + 543;
                $formattedDate = "วันที่ $d $m $y";
            }

            $coverStr = !empty($row['CoverImage']) ? $ImgPath.'cio_news/'.$row['CoverImage'] : '';

            $item = [
                'id' => (int)$row['NewsID'],
                'groupId' => (int)$row['GroupID'],
                'cioId' => $row['CioID'] ? (int)$row['CioID'] : null,
                'title' => $title,
                'content' => $content,
                'cover' => $coverStr,
                'date' => $formattedDate,
                'views' => (int)$row['Views']
            ];

            $response[] = $item;
        }

        if ($id > 0) {
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
