<?php
// src/api/v1/endpoints/videos/index.php
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
            SELECT v.*, g.GroupNameTH as Category_TH, g.GroupNameEN as Category_EN
            FROM videos v 
            LEFT JOIN videos_groups g ON v.GroupID = g.GroupID 
            WHERE v.DelData = 'N' AND v.IsActive = 'Y'
        ";

        $params = [];

        if ($id > 0) {
            $sql .= " AND v.DocID = :id";
            $params[':id'] = $id;
        }

        $sql .= " ORDER BY v.OrderNo ASC, v.CreateDate DESC";

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
        $videosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $monthsTH = [
            '01'=>'มกราคม', '02'=>'กุมภาพันธ์', '03'=>'มีนาคม', '04'=>'เมษายน',
            '05'=>'พฤษภาคม', '06'=>'มิถุนายน', '07'=>'กรกฎาคม', '08'=>'สิงหาคม',
            '09'=>'กันยายน', '10'=>'ตุลาคม', '11'=>'พฤศจิกายน', '12'=>'ธันวาคม'
        ];

        $response = [];

        foreach ($videosRaw as $row) {
            $title = $lang === 'en' && !empty($row['TitleEN']) ? $row['TitleEN'] : $row['TitleTH'];
            $detail = $lang === 'en' && !empty($row['DetailEN']) ? $row['DetailEN'] : $row['DetailTH'];
            $category = $lang === 'en' && !empty($row['Category_EN']) ? $row['Category_EN'] : $row['Category_TH'];
            $url = $lang === 'en' && !empty($row['URLEN']) ? $row['URLEN'] : $row['URLTH'];
            
            $formatDate = function($dateStr) use ($monthsTH) {
                if (empty($dateStr) || strpos($dateStr, '0000-00-00') !== false) return '';
                $ts = strtotime($dateStr);
                $d = date('j', $ts);
                $m = $monthsTH[date('m', $ts)] ?? '';
                $y = date('Y', $ts) + 543;
                return "วันที่ $d $m $y";
            };
            
            $formattedDate = $formatDate($row['CreateDate'] ?? '');
            $formattedStartDate = $formatDate($row['StartDate'] ?? '');
            $formattedEndDate = $formatDate($row['EndDate'] ?? '');

            $coverStr = !empty($row['CoverImage']) ? $ImgPath.'videos/'.$row['CoverImage'] : '';

            $item = [
                'id' => (int)$row['DocID'],
                'title' => $title,
                'detail' => $detail,
                'category' => $category,
                'categoryId' => (int)$row['GroupID'],
                'url' => $url,
                'cover' => $coverStr,
                'date' => $formattedDate,
                'startDate' => $formattedStartDate,
                'endDate' => $formattedEndDate,
                'rawStartDate' => $row['StartDate'] ?? '',
                'rawEndDate' => $row['EndDate'] ?? '',
                'views' => isset($row['Views']) ? (int)$row['Views'] : 0,
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
