<?php
// src/api/v1/endpoints/news_category/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';

// Handle parameter from URL Rewrite (could be numeric ID or string Slug)
$idParam = isset($_GET['id']) ? $_GET['id'] : 0;
$id = is_numeric($idParam) ? intval($idParam) : 0;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug) && !is_numeric($idParam) && !empty($idParam)) {
    $slug = trim($idParam);
}

try {
    if (isset($DB)) {
        $sql = "SELECT * FROM news_category WHERE DelData = 'N' AND IsActive = 'Y'";
        
        $params = [];
        if ($id > 0) {
            $sql .= " AND CategoryID = :id";
            $params[':id'] = $id;
        } elseif (!empty($slug)) {
            $sql .= " AND Slug = :slug";
            $params[':slug'] = $slug;
        }

        $sql .= " ORDER BY OrderNo ASC, CategoryID DESC";

        $stmt = $DB->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        $categoriesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];

        foreach ($categoriesRaw as $row) {
            $response[] = [
                "id" => $row['CategoryID'],
                "name" => $lang === 'en' && !empty($row['CategoryName_EN']) ? $row['CategoryName_EN'] : $row['CategoryName_TH'],
                "name_th" => $row['CategoryName_TH'],
                "name_en" => $row['CategoryName_EN'],
                "slug" => $row['Slug']
            ];
        }

        if ($id > 0 || !empty($slug)) {
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
