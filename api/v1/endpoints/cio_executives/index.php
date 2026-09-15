<?php
// src/api/v1/endpoints/cio_executives/index.php
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
            FROM cio_executives 
            WHERE DelData = 'N' AND IsActive = 'Y'
        ";

        $params = [];

        if ($id > 0) {
            $sql .= " AND CioID = :id";
            $params[':id'] = $id;
        }

        $sql .= " ORDER BY OrderNo ASC, CioID ASC";

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
            $prefix = $lang === 'en' && !empty($row['Prefix_EN']) ? $row['Prefix_EN'] : $row['Prefix_TH'];
            $firstName = $lang === 'en' && !empty($row['FirstName_EN']) ? $row['FirstName_EN'] : $row['FirstName_TH'];
            $lastName = $lang === 'en' && !empty($row['LastName_EN']) ? $row['LastName_EN'] : $row['LastName_TH'];
            $position = $lang === 'en' && !empty($row['Position_EN']) ? $row['Position_EN'] : $row['Position_TH'];
            $detail = $lang === 'en' && !empty($row['Detail_EN']) ? $row['Detail_EN'] : $row['Detail_TH'];
            
            $formattedAppointDate = '';
            if (!empty($row['AppointDate'])) {
                $ts = strtotime($row['AppointDate']);
                $d = date('j', $ts);
                $m = $monthsTH[date('m', $ts)] ?? '';
                $y = date('Y', $ts) + 543;
                $formattedAppointDate = "วันที่ $d $m $y";
            }

            $imgStr = !empty($row['ImagePath']) ? $ImgPath.$row['ImagePath'] : '';

            $item = [
                'id' => (int)$row['CioID'],
                'firstName' => $firstName,
                'lastName' => $lastName,
                'fullName' => trim($prefix . ' ' . $firstName . ' ' . $lastName),
                'position' => $position,
                'detail' => $detail,
                'address' => $row['Address'] ?: '',
                'telephone' => $row['Telephone'] ?: '',
                'email' => $row['Email'] ?: '',
                'appointDate' => $formattedAppointDate,
                'appointOrderNo' => $row['AppointOrderNo'] ?: '',
                'image' => $imgStr
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
