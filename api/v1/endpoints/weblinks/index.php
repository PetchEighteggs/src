<?php
// src/api/v1/endpoints/weblinks/index.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
$idParam = isset($_GET['id']) ? $_GET['id'] : 0;
$id = is_numeric($idParam) ? intval($idParam) : 0;
$groupId = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;

try {
    if (isset($DB)) {
        $sql = "
            SELECT v.*, g.GroupNameTH as Category_TH, g.GroupNameEN as Category_EN
            FROM weblinks v 
            LEFT JOIN weblinks_groups g ON v.GroupID = g.GroupID 
            WHERE (v.DelData = 'N' OR v.DelData IS NULL) AND v.IsActive = 'Y'
              AND (v.StartDate IS NULL OR v.StartDate = '0000-00-00 00:00:00' OR v.StartDate <= NOW())
              AND (v.EndDate IS NULL OR v.EndDate = '0000-00-00 00:00:00' OR v.EndDate >= NOW())
        ";

        $params = [];

        if ($id > 0) {
            $sql .= " AND v.DocID = :id";
            $params[':id'] = $id;
        }

        if ($groupId > 0) {
            $sql .= " AND v.GroupID = :group_id";
            $params[':group_id'] = $groupId;
        }
        $sql .= " ORDER BY v.OrderNo ASC, v.CreateDate DESC, v.DocID DESC";

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
        $weblinksRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $monthsTH = [
            '01'=>'มกราคม', '02'=>'กุมภาพันธ์', '03'=>'มีนาคม', '04'=>'เมษายน',
            '05'=>'พฤษภาคม', '06'=>'มิถุนายน', '07'=>'กรกฎาคม', '08'=>'สิงหาคม',
            '09'=>'กันยายน', '10'=>'ตุลาคม', '11'=>'พฤศจิกายน', '12'=>'ธันวาคม'
        ];

        $response = [];

        foreach ($weblinksRaw as $row) {
            $title = $lang === 'en' && !empty($row['TitleEN']) ? $row['TitleEN'] : $row['TitleTH'];
            $detail = $lang === 'en' && !empty($row['DetailEN']) ? $row['DetailEN'] : $row['DetailTH'];
            $category = $lang === 'en' && !empty($row['Category_EN']) ? $row['Category_EN'] : $row['Category_TH'];
            $url = $lang === 'en' && !empty($row['URLEN']) ? $row['URLEN'] : $row['URLTH'];
            $description = $lang === 'en' && !empty($row['DescriptionEN']) ? $row['DescriptionEN'] : ($row['DescriptionTH'] ?? '');
            $contentType = $row['ContentType'] ?? ((!empty($row['URLTH']) || !empty($row['URLEN'])) ? 'url' : 'detail');
            
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

            $coverStr = !empty($row['CoverImage']) ? $ImgPath.'weblinks/'.$row['CoverImage'] : '';

            $item = [
                'id' => (int)$row['DocID'],
                'title' => $title,
                'description' => $description,
                'contentType' => $contentType,
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
    error_log('Weblinks API: ' . $e->getMessage());
    sendError('Unable to load weblinks.', 500);
}
?>
