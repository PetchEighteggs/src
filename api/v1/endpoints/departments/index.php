<?php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB, $ImgPath;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';

$idParam = isset($_GET['id']) ? $_GET['id'] : 0;
$id = is_numeric($idParam) ? intval($idParam) : 0;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug) && !is_numeric($idParam) && !empty($idParam)) {
    $slug = trim($idParam);
}

// limit and order
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
if ($limit <= 0 || $limit > 100) $limit = 100;

try {
    if (isset($DB)) {
        $sql = "SELECT * FROM microsite_departments WHERE IsActive = 'Y' AND DeptGroup != 'other' AND DescriptionTH IS NOT NULL AND DescriptionTH != '' ";
        $params = [];

        if ($id > 0) {
            $sql .= " AND DeptID = :id";
            $params[':id'] = $id;
        } elseif (!empty($slug)) {
            $sql .= " AND Slug = :slug";
            $params[':slug'] = $slug;
        }

        $sql .= " ORDER BY OrderNo ASC LIMIT " . $limit;

        $stmt = $DB->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        $pagesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];

        foreach ($pagesRaw as $row) {
            $titleTh = !empty($row['ShortNameTH']) ? $row['ShortNameTH'] : $row['ShortNameTH'];
            $titleEn = !empty($row['ShortNameEN']) ? $row['ShortNameEN'] : $row['ShortNameTH'];
            $descTh = !empty($row['DescriptionTH']) ? $row['DescriptionTH'] : $row['DescriptionTH'];
            $descEn = !empty($row['DescriptionEN']) ? $row['DescriptionEN'] : $row['DescriptionTH'];

            $descTh = processContentUrls($descTh);
            $descEn = processContentUrls($descEn);
            $grp = $row['DeptGroup'] ?? '';
            
            $item = [
							'id' => (int)$row['DeptID'],
							'slug' => $row['Slug'],
							'title_th' => $titleTh,
							'title_en' => $titleEn,
							'description_th' => $descTh,
							'description_en' => $descEn
            ];

            if ($id > 0 || (!empty($slug) && $slug == $row['Slug'])) {
              $response = $item;
              break;
            }

            $response[] = $item;
        }

        echo json_encode([
            'status' => 'success',
            'code' => 200,
            'data' => $response
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'code' => 500,
        'message' => 'Database error',
        'debug' => $e->getMessage()
    ]);
}
