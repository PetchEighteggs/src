<?php
// src/api/v1/endpoints/news/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;

// Handle parameter from URL Rewrite (could be numeric ID or string Slug)
$idParam = isset($_GET['id']) ? $_GET['id'] : 0;
$id = is_numeric($idParam) ? intval($idParam) : 0;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug) && !is_numeric($idParam) && !empty($idParam)) {
    $slug = trim($idParam);
}

try {
    if (isset($DB)) {
        $sql = "
                    SELECT n.*, c.CategoryName_TH as Category_TH, c.CategoryName_EN as Category_EN, c.Slug as Category_Slug
                    FROM news n 
                    LEFT JOIN news_category c ON n.CategoryID = c.CategoryID 
                    WHERE n.DelData = 'N' 
        ";

        $params = [];

        if ($id > 0) {
            $sql .= " AND n.NewsID = :id";
            $params[':id'] = $id;
        } elseif (!empty($slug)) {
            $sql .= " AND n.Slug = :slug";
            $params[':slug'] = $slug;
        }

        $sql .= " ORDER BY n.CreateDate DESC";

        if ($limit > 0 && $id == 0 && empty($slug)) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }

        // Fetch News
        $stmt = $DB->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        $newsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $monthsTH = [
                    '01'=>'มกราคม', '02'=>'กุมภาพันธ์', '03'=>'มีนาคม', '04'=>'เมษายน',
                    '05'=>'พฤษภาคม', '06'=>'มิถุนายน', '07'=>'กรกฎาคม', '08'=>'สิงหาคม',
                    '09'=>'กันยายน', '10'=>'ตุลาคม', '11'=>'พฤศจิกายน', '12'=>'ธันวาคม'
        ];

        $response = [];

        foreach ($newsRaw as $row) {
            $nid = $row['NewsID'];
            
            // Get Gallery (Mocking from DB structure)
            $galStmt = $DB->prepare("SELECT ImagePath FROM news_gallery WHERE NewsID = ? AND DelData = 'N' ORDER BY OrderNo ASC");
            $galStmt->execute([$nid]);
            $galleryPaths = $galStmt->fetchAll(PDO::FETCH_COLUMN);

            // Get Attachments
            $attStmt = $DB->prepare("SELECT FileName_TH as name, FilePath as url FROM news_attachments WHERE NewsID = ? AND DelData = 'N' ORDER BY OrderNo ASC");
            $attStmt->execute([$nid]);
            $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Adjust to the exact format required by Frontend JSON mock
            $title = $lang === 'en' && !empty($row['Title_EN']) ? $row['Title_EN'] : $row['Title_TH'];
            $excerpt = $lang === 'en' && !empty($row['Excerpt_EN']) ? $row['Excerpt_EN'] : $row['Excerpt_TH'];
            $content = $lang === 'en' && !empty($row['Content_EN']) ? $row['Content_EN'] : $row['Content_TH'];
            $category = $lang === 'en' && !empty($row['Category_EN']) ? $row['Category_EN'] : $row['Category_TH'];

            // Use the category's slug from the database as the categoryId
            $categoryId = !empty($row['CategoryName_EN']) ? $row['CategoryName_EN'] : '';

            // Date Formatting
            $formattedDate = $row['DateStr'];
            if (!empty($formattedDate) && strpos($formattedDate, '/') !== false) {
                $parts = explode('/', $formattedDate);
                if (count($parts) === 3) {
                    $d = (int)$parts[0];
                    $m = $monthsTH[$parts[1]] ?? '';
                    $y = $parts[2];
                    $formattedDate = "วันที่ $d $m $y";
                }
            }

            $coverStr = $row['CoverImage'] ? $ImgPath.'news/'.$row['CoverImage'] : '';
            
            $item = [
                'id' => (int)$nid,
                'slug' => $row['Slug'] ?: (int)$nid,
                'title' => $title,
                'date' => $formattedDate,
                'category' => $category,
                'categoryId' => $categoryId,
                'excerpt' => $excerpt,
                'cover' => $coverStr,
                'image' => $coverStr ? [$coverStr] : [],
                'gallery' => array_map(function($p) use ($ImgPath) { return $ImgPath.'news/gallery/'.$p; }, $galleryPaths),
                'content' => $content,
                'views' => (int)$row['Views'],
                'publisher' => $row['Publisher'] ?: '',
                'attachments' => array_map(function($a) use ($FilePath) {
                    return ['name' => $a['name'], 'url' => $FilePath.$a['url']];
                }, $attachments)
            ];

            $response[] = $item;
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
