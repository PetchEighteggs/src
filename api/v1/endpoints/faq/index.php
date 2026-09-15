<?php
// src/api/v1/endpoints/faq/index.php
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
            SELECT f.*, g.GroupNameTH as Category_TH, g.GroupNameEN as Category_EN
            FROM faq f 
            LEFT JOIN faq_groups g ON f.GroupID = g.GroupID 
            WHERE f.DelData = 'N' AND f.IsActive = 'Y'
        ";

        $params = [];

        if ($id > 0) {
            $sql .= " AND f.DocID = :id";
            $params[':id'] = $id;
        }

        $sql .= " ORDER BY f.OrderNo ASC, f.CreateDate DESC";

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
        $faqRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $monthsTH = [
            '01'=>'มกราคม', '02'=>'กุมภาพันธ์', '03'=>'มีนาคม', '04'=>'เมษายน',
            '05'=>'พฤษภาคม', '06'=>'มิถุนายน', '07'=>'กรกฎาคม', '08'=>'สิงหาคม',
            '09'=>'กันยายน', '10'=>'ตุลาคม', '11'=>'พฤศจิกายน', '12'=>'ธันวาคม'
        ];

        $response = [];

        foreach ($faqRaw as $row) {
            $title = $lang === 'en' && !empty($row['TitleEN']) ? $row['TitleEN'] : $row['TitleTH'];
            $detail = $lang === 'en' && !empty($row['DetailEN']) ? $row['DetailEN'] : $row['DetailTH'];
            $category = $lang === 'en' && !empty($row['Category_EN']) ? $row['Category_EN'] : $row['Category_TH'];
            
            $formattedDate = '';
            if (!empty($row['CreateDate'])) {
                $ts = strtotime($row['CreateDate']);
                $d = date('j', $ts);
                $m = $monthsTH[date('m', $ts)] ?? '';
                $y = date('Y', $ts) + 543;
                $formattedDate = "วันที่ $d $m $y";
            }

            $item = [
                'id' => (int)$row['DocID'],
                'question' => $title, // Using Title as Question
                'answer' => $detail, // Using Detail as Answer
                'category' => $category,
                'categoryId' => (int)$row['GroupID'],
                'date' => $formattedDate
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
