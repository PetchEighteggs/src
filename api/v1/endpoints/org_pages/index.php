<?php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';

$idParam = isset($_GET['id']) ? $_GET['id'] : 0;
$id = is_numeric($idParam) ? intval($idParam) : 0;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug) && !is_numeric($idParam) && !empty($idParam)) {
    $slug = trim($idParam);
}

try {
    if (isset($DB)) {
        $sql = "SELECT * FROM org_pages WHERE 1=1 ";
        $params = [];

        if ($id > 0) {
            $sql .= " AND PageID = :id";
            $params[':id'] = $id;
        } elseif (!empty($slug)) {
            $sql .= " AND PageSlug = :slug";
            $params[':slug'] = $slug;
        }

        $sql .= " ORDER BY PageID ASC";

        $stmt = $DB->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        $pagesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        $imageExts = ['jpg', 'jpeg', 'gif', 'png', 'svg', 'webp'];

        foreach ($pagesRaw as $row) {
            $pid = $row['PageID'];
            
            // Get Attachments
            $attStmt = $DB->prepare("SELECT FileName_TH, FileName_EN, FilePath, FileType, FileSize FROM org_pages_attachments WHERE PageID = ? AND DelData = 'N' ORDER BY OrderNo ASC");
            $attStmt->execute([$pid]);
            $attachmentsRaw = $attStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $attachments = [];
            foreach($attachmentsRaw as $att) {
                $ext = strtolower($att['FileType'] ?? '');
                if (in_array($ext, $imageExts)) {
                    $safeFilePath = 'uploads/images/structure/' . ltrim($att['FilePath'], '/');
                } else {
                    $safeFilePath = 'uploads/files/structure/' . ltrim($att['FilePath'], '/');
                }
                
                $fileName = $lang === 'en' && !empty($att['FileName_EN']) ? $att['FileName_EN'] : $att['FileName_TH'];
                $sizeVal = isset($att['FileSize']) ? round((int)$att['FileSize'] / 1048576, 2) : 0;
                
                $attachments[] = [
                    'name' => $fileName,
                    'url' => $_Config_live_site . '/' . $safeFilePath,
                    'type' => strtoupper($ext),
                    'size' => $sizeVal,
                    'sizeStr' => $sizeVal > 0 ? $sizeVal . ' Mb' : '0 Mb'
                ];
            }
            
            // Get Timelines
            $tlStmt = $DB->prepare("SELECT Year_TH, Year_EN, Title_TH, Title_EN, Description_TH, Description_EN, IsHeader FROM org_pages_timelines WHERE PageID = ? AND DelData = 'N' ORDER BY OrderNo ASC");
            $tlStmt->execute([$pid]);
            $timelinesRaw = $tlStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $timelines = [];
            foreach($timelinesRaw as $tl) {
                $year = $lang === 'en' && !empty($tl['Year_EN']) ? $tl['Year_EN'] : $tl['Year_TH'];
                $title = $lang === 'en' && !empty($tl['Title_EN']) ? $tl['Title_EN'] : $tl['Title_TH'];
                $desc = $lang === 'en' && !empty($tl['Description_EN']) ? $tl['Description_EN'] : $tl['Description_TH'];
                
                $timelines[] = [
                    'year' => $year,
                    'title' => $title,
                    'description' => $desc,
                    'isHeader' => (isset($tl['IsHeader']) && $tl['IsHeader'] === 'Y')
                ];
            }

            $title = $lang === 'en' && !empty($row['Title_EN']) ? $row['Title_EN'] : $row['Title_TH'];
            $contentRaw = $lang === 'en' && !empty($row['Content_EN']) ? $row['Content_EN'] : $row['Content_TH'];
            $content = processContentUrls($contentRaw);

            $item = [
                'id' => (int)$pid,
                'slug' => $row['PageSlug'],
                'title' => $title,
                'content' => $content,
                'updateDate' => $row['UpdateDate'],
                'attachments' => $attachments,
                'timelines' => $timelines
            ];

            $response[] = $item;
        }

        if ($id > 0 || !empty($slug)) {
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
