<?php
// src/api/v1/endpoints/cio_events/index.php
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
            FROM cio_events 
            WHERE DelData = 'N' AND IsActive = 'Y'
        ";

        $params = [];

        if ($id > 0) {
            $sql .= " AND EventID = :id";
            $params[':id'] = $id;
        }

        $sql .= " ORDER BY EventStartDate DESC, CreateDate DESC";

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
            $location = $lang === 'en' && !empty($row['Location_EN']) ? $row['Location_EN'] : $row['Location_TH'];
            
            $formatDate = function($dateStr) use ($monthsTH) {
                if (empty($dateStr)) return '';
                $ts = strtotime($dateStr);
                $d = date('j', $ts);
                $m = $monthsTH[date('m', $ts)] ?? '';
                $y = date('Y', $ts) + 543;
                return "วันที่ $d $m $y";
            };

            $formattedStartDate = $formatDate($row['EventStartDate']);
            $formattedEndDate = $formatDate($row['EventEndDate']);

            $coverStr = !empty($row['CoverImage']) ? $ImgPath.'cio_events/'.$row['CoverImage'] : '';

            $item = [
                'id' => (int)$row['EventID'],
                'cioId' => !empty($row['CioID']) ? (int)$row['CioID'] : null,
                'title' => $title,
                'content' => $content,
                'location' => $location,
                'cover' => $coverStr,
                'startDate' => $formattedStartDate,
                'endDate' => $formattedEndDate,
                'timeFrom' => $row['TimeFrom'] ?: '',
                'timeTo' => $row['TimeTo'] ?: '',
                'rawStartDate' => $row['EventStartDate'],
                'rawEndDate' => $row['EventEndDate']
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
