<?php
// src/api/v1/endpoints/events/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB, $ImgPath;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Handle parameter from URL Rewrite (could be numeric ID)
$idParam = isset($_GET['id']) ? $_GET['id'] : 0;
$id = is_numeric($idParam) ? intval($idParam) : 0;

// Optional: filter by upcoming or past events
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : ''; // 'upcoming', 'past', or '' (all)

try {
    if (isset($DB)) {
        $sql = "SELECT * FROM events WHERE DelData = 'N'";
        $params = [];

        if ($id > 0) {
            $sql .= " AND EventID = :id";
            $params[':id'] = $id;
        }

        // Active filter
        $sql .= " AND IsActive = 'Y'";

        // Date filter
        $today = date('Y-m-d');
        if ($filter === 'upcoming') {
            $sql .= " AND (EventStartDate >= :today OR (EventEndDate IS NOT NULL AND EventEndDate >= :today2))";
            $params[':today'] = $today;
            $params[':today2'] = $today;
        } elseif ($filter === 'past') {
            $sql .= " AND (COALESCE(EventEndDate, EventStartDate) < :today)";
            $params[':today'] = $today;
        }

        $sql .= " ORDER BY EventStartDate DESC, EventID DESC";

        // Pagination (only for list view)
        if ($id == 0 && $limit > 0) {
            $offset = ($page - 1) * $limit;
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
        }

        $stmt = $DB->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        $eventsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $monthsTH = [
            '01'=>'มกราคม', '02'=>'กุมภาพันธ์', '03'=>'มีนาคม', '04'=>'เมษายน',
            '05'=>'พฤษภาคม', '06'=>'มิถุนายน', '07'=>'กรกฎาคม', '08'=>'สิงหาคม',
            '09'=>'กันยายน', '10'=>'ตุลาคม', '11'=>'พฤศจิกายน', '12'=>'ธันวาคม'
        ];

        $response = [];

        foreach ($eventsRaw as $row) {
            $title = $lang === 'en' && !empty($row['Title_EN']) ? $row['Title_EN'] : $row['Title_TH'];
            $content = $lang === 'en' && !empty($row['Content_EN']) ? $row['Content_EN'] : $row['Content_TH'];
            $location = $lang === 'en' && !empty($row['Location_EN']) ? $row['Location_EN'] : $row['Location_TH'];

            // Format date (Thai style)
            $startDateFormatted = '';
            if (!empty($row['EventStartDate'])) {
                $dt = new DateTime($row['EventStartDate']);
                $d = (int)$dt->format('d');
                $m = $monthsTH[$dt->format('m')] ?? '';
                $y = (int)$dt->format('Y') + 543; // Convert to Buddhist Era
                $startDateFormatted = "$d $m $y";
            }

            $endDateFormatted = '';
            if (!empty($row['EventEndDate'])) {
                $dt = new DateTime($row['EventEndDate']);
                $d = (int)$dt->format('d');
                $m = $monthsTH[$dt->format('m')] ?? '';
                $y = (int)$dt->format('Y') + 543;
                $endDateFormatted = "$d $m $y";
            }

            $coverStr = !empty($row['CoverImage']) ? $ImgPath . 'events/' . $row['CoverImage'] : '';

            $item = [
                'id' => (int)$row['EventID'],
                'title' => $title,
                'content' => $content,
                'cover' => $coverStr,
                'event_start_date' => $row['EventStartDate'] ?: '',
                'event_end_date' => $row['EventEndDate'] ?: '',
                'event_start_date_formatted' => $startDateFormatted,
                'event_end_date_formatted' => $endDateFormatted,
                'time_from' => $row['TimeFrom'] ?: '',
                'time_to' => $row['TimeTo'] ?: '',
                'location' => $location,
                'views' => (int)$row['Views'],
            ];

            $response[] = $item;
        }

        if ($id > 0) {
            // Return single object for detail view, or null if not found
            echo json_encode(!empty($response) ? $response[0] : null, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            // Return array of objects for list view
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    } else {
        echo json_encode([]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
