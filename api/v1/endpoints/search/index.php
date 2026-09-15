<?php
// src/api/v1/endpoints/search/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

if (empty($q)) {
    echo json_encode(['status' => 'success', 'data' => [], 'total' => 0]);
    exit;
}

$response = [];
$errors = [];

$monthsTH = [
    '01'=>'มกราคม', '02'=>'กุมภาพันธ์', '03'=>'มีนาคม', '04'=>'เมษายน',
    '05'=>'พฤษภาคม', '06'=>'มิถุนายน', '07'=>'กรกฎาคม', '08'=>'สิงหาคม',
    '09'=>'กันยายน', '10'=>'ตุลาคม', '11'=>'พฤศจิกายน', '12'=>'ธันวาคม'
];

try {
    if (isset($DB)) {
        $keyword = "%{$q}%";
        $allResults = [];

        // 1. News
        try {
            $stmt = $DB->prepare("
                SELECT n.NewsID AS id, n.Title_TH as title_th, n.Title_EN as title_en, 
                       n.Excerpt_TH as excerpt_th, n.Excerpt_EN as excerpt_en, 
                       n.CreateDate as date, n.DateStr, n.CoverImage as image, n.Slug as slug, 
                       n.Views as views, c.CategoryName_TH as cat_th, c.CategoryName_EN as cat_en, c.Slug as cat_id
                FROM news n
                LEFT JOIN news_category c ON n.CategoryID = c.CategoryID
                WHERE (n.Title_TH LIKE :q OR n.Title_EN LIKE :q) AND n.DelData = 'N' 
                ORDER BY n.CreateDate DESC LIMIT :limit
            ");
            $stmt->bindValue(':q', $keyword, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                // Attachments
                $attStmt = $DB->prepare("SELECT FileName_TH as name, FilePath as url FROM news_attachments WHERE NewsID = ? AND DelData = 'N' ORDER BY OrderNo ASC");
                $attStmt->execute([$r['id']]);
                $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);

                $allResults[] = array_merge($r, ['type' => 'news', 'raw_attachments' => $attachments]);
            }
        } catch (PDOException $e) { $errors[] = "news: " . $e->getMessage(); }

        // 2. Events
        try {
            $stmt = $DB->prepare("
                SELECT EventID AS id, Title_TH as title_th, Title_EN as title_en, 
                       Content_TH as excerpt_th, Content_EN as excerpt_en, 
                       EventStartDate as date, CoverImage as image, Views as views
                FROM events 
                WHERE (Title_TH LIKE :q OR Title_EN LIKE :q) AND DelData = 'N' 
                ORDER BY CreateDate DESC LIMIT :limit
            ");
            $stmt->bindValue(':q', $keyword, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $allResults[] = array_merge($r, ['type' => 'events', 'raw_attachments' => []]);
            }
        } catch (PDOException $e) { $errors[] = "events: " . $e->getMessage(); }

        // 3. Videos
        try {
            $stmt = $DB->prepare("
                SELECT v.DocID AS id, v.TitleTH as title_th, v.TitleEN as title_en, 
                       v.DetailTH as excerpt_th, v.DetailEN as excerpt_en, 
                       v.CreateDate as date, v.CoverImage as image, v.Views as views,
                       g.GroupNameTH as cat_th, g.GroupNameEN as cat_en, v.GroupID as cat_id,
                       v.URLTH, v.URLEN
                FROM videos v
                LEFT JOIN videos_groups g ON v.GroupID = g.GroupID
                WHERE (v.TitleTH LIKE :q OR v.TitleEN LIKE :q) AND v.DelData = 'N' 
                ORDER BY v.CreateDate DESC LIMIT :limit
            ");
            $stmt->bindValue(':q', $keyword, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $allResults[] = array_merge($r, ['type' => 'videos', 'raw_attachments' => []]);
            }
        } catch (PDOException $e) { $errors[] = "videos: " . $e->getMessage(); }

        // 4. Groundwater Docs
        try {
            $stmt = $DB->prepare("
                SELECT d.DocID AS id, d.TitleTH as title_th, d.TitleEN as title_en, 
                       d.DescriptionTH as excerpt_th, d.DescriptionEN as excerpt_en, 
                       d.CreateDate as date, d.File as image, d.Views as views, 0 as downloads,
                       g.GroupNameTH as cat_th, g.GroupNameEN as cat_en, d.GroupID as cat_id
                FROM groundwater_docs d
                LEFT JOIN groundwater_doc_groups g ON d.GroupID = g.GroupID
                WHERE (d.TitleTH LIKE :q OR d.TitleEN LIKE :q) AND (d.DelData = 'N' OR d.DelData IS NULL) AND d.IsActive = 'Y' 
                LIMIT :limit
            ");
            $stmt->bindValue(':q', $keyword, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $attStmt = $DB->prepare("SELECT FilePath as url, FileSize as size FROM groundwater_doc_attachments WHERE DocID = ? ORDER BY OrderNo ASC");
                $attStmt->execute([$r['id']]);
                $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);
                $allResults[] = array_merge($r, ['type' => 'groundwater', 'raw_attachments' => $attachments]);
            }
        } catch (PDOException $e) { $errors[] = "groundwater: " . $e->getMessage(); }

        // 5. Service Docs
        try {
            $stmt = $DB->prepare("
                SELECT d.DocID AS id, d.TitleTH as title_th, d.TitleEN as title_en, 
                       d.DescriptionTH as excerpt_th, d.DescriptionEN as excerpt_en, 
                       d.CreateDate as date, d.File as image, d.Views as views, 0 as downloads,
                       g.GroupNameTH as cat_th, g.GroupNameEN as cat_en, d.GroupID as cat_id,
                       d.ServiceType as doc_type
                FROM service_docs d
                LEFT JOIN service_doc_groups g ON d.GroupID = g.GroupID
                WHERE (d.TitleTH LIKE :q OR d.TitleEN LIKE :q) AND (d.DelData = 'N' OR d.DelData IS NULL) AND d.IsActive = 'Y' 
                LIMIT :limit
            ");
            $stmt->bindValue(':q', $keyword, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $attStmt = $DB->prepare("SELECT FilePath as url, FileSize as size FROM service_doc_attachments WHERE DocID = ? ORDER BY OrderNo ASC");
                $attStmt->execute([$r['id']]);
                $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Use doc_type (e.g. reports, laws, manuals) if exists, else fallback to 'service'
                $finalType = !empty($r['doc_type']) ? $r['doc_type'] : 'service';
                $allResults[] = array_merge($r, ['type' => $finalType, 'raw_attachments' => $attachments]);
            }
        } catch (PDOException $e) { $errors[] = "service: " . $e->getMessage(); }

        // Sort by Date (Descending) using PHP
        usort($allResults, function($a, $b) {
            $dateA = !empty($a['date']) ? strtotime($a['date']) : 0;
            $dateB = !empty($b['date']) ? strtotime($b['date']) : 0;
            return $dateB - $dateA;
        });

        // Slice to limit
        $allResults = array_slice($allResults, 0, $limit);

        // Format for output
        foreach ($allResults as $row) {
            $type = $row['type'];
            $title = $lang === 'en' && !empty($row['title_en']) ? $row['title_en'] : $row['title_th'];
            $excerpt = $lang === 'en' && !empty($row['excerpt_en']) ? $row['excerpt_en'] : (!empty($row['excerpt_th']) ? $row['excerpt_th'] : '');
            $category = $lang === 'en' && !empty($row['cat_en']) ? $row['cat_en'] : (!empty($row['cat_th']) ? $row['cat_th'] : '');
            
            // Format Date
            $formattedDate = '';
            if (isset($row['DateStr']) && !empty($row['DateStr']) && $type === 'news') {
                $formattedDate = $row['DateStr'];
                if (strpos($formattedDate, '/') !== false) {
                    $parts = explode('/', $formattedDate);
                    if (count($parts) === 3) {
                        $d = (int)$parts[0];
                        $m = $monthsTH[$parts[1]] ?? '';
                        $y = $parts[2];
                        $formattedDate = "วันที่ $d $m $y";
                    }
                }
            } elseif (!empty($row['date'])) {
                $dt = new DateTime($row['date']);
                $d = (int)$dt->format('d');
                $m = $monthsTH[$dt->format('m')] ?? '';
                $y = (int)$dt->format('Y') + 543;
                $formattedDate = "$d $m $y";
            }

            // Image Path Mapping
            $coverStr = '';
            $fileCoverStr = '';
            if (!empty($row['image'])) {
                if ($type === 'news') {
                    $coverStr = isset($ImgPath) ? $ImgPath . 'news/' . $row['image'] : '';
                } elseif ($type === 'events') {
                    $coverStr = isset($ImgPath) ? $ImgPath . 'events/' . $row['image'] : '';
                } elseif ($type === 'videos') {
                    $coverStr = isset($ImgPath) ? $ImgPath . 'videos/' . $row['image'] : '';
                } elseif ($type === 'groundwater') {
                    $fileCoverStr = isset($FilePath) ? $FilePath . 'groundwater/' . $row['image'] : '';
                } else {
                    // For services (reports, laws, etc)
                    $fileCoverStr = isset($FilePath) ? $FilePath . 'services/' . $row['image'] : '';
                }
            }

            // Map Attachments
            $mappedAttachments = [];
            if (!empty($row['raw_attachments'])) {
                foreach ($row['raw_attachments'] as $a) {
                    $name = !empty($a['name']) ? $a['name'] : basename($a['url']);
                    $url = '';
                    if ($type === 'news') {
                        $url = isset($FilePath) ? $FilePath . $a['url'] : '';
                    } elseif ($type === 'groundwater') {
                        $url = isset($FilePath) ? $FilePath . 'groundwater/' . $a['url'] : '';
                    } else {
                        $url = isset($FilePath) ? $FilePath . 'services/' . $a['url'] : '';
                    }
                    $mappedAttachments[] = [
                        'name' => $name,
                        'url' => $url
                    ];
                }
            }

            $item = [
                'id' => (int)$row['id'],
                'type' => $type,
                'slug' => !empty($row['slug']) ? $row['slug'] : $row['id'],
                'title' => $title,
                'excerpt' => strip_tags($excerpt), // Strip HTML
                'date' => $formattedDate,
                'category' => $category,
                'categoryId' => !empty($row['cat_id']) ? $row['cat_id'] : '',
                'cover' => $coverStr,
                'image' => $coverStr ? [$coverStr] : [],
                'file_cover' => $fileCoverStr,
                'views' => !empty($row['views']) ? (int)$row['views'] : 0,
                'downloads' => !empty($row['downloads']) ? (int)$row['downloads'] : 0,
                'attachments' => $mappedAttachments
            ];
            
            if ($type === 'videos') {
                $item['video_url'] = $lang === 'en' && !empty($row['URLEN']) ? $row['URLEN'] : (!empty($row['URLTH']) ? $row['URLTH'] : '');
            }

            $response[] = $item;
        }

        echo json_encode([
            'status' => empty($errors) ? 'success' : 'partial_success',
            'data' => $response,
            'total' => count($response),
            'debug_errors' => $errors
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
