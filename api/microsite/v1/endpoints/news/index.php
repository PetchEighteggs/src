<?php
// src/api/microsite/v1/endpoints/news.php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';
$dept_id = (int)($_GET['dept_id'] ?? 0);
$category_id = (int)($_GET['category_id'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 10);

if ($page < 1) $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Resolve dept_id from slug if provided
    if (!empty($slug) && $dept_id === 0) {
        $stmtDept = $DB->prepare("SELECT DeptID FROM microsite_departments WHERE Slug = ? LIMIT 1");
        $stmtDept->execute([$slug]);
        $dept_id = $stmtDept->fetchColumn();
    }

    if ($dept_id === 0 || $dept_id === false) {
        sendError('Valid Department ID or Slug is required', 400);
    }

    // Build query conditions
    $conditions = [
        "n.DeptID = :dept_id", 
        "n.IsActive = 'Y'", 
        "n.DelData = 'N'",
        "(g.IsActive = 'Y' OR n.GroupID = 0 OR n.GroupID IS NULL)",
        "(n.StartDate IS NULL OR n.StartDate = '0000-00-00 00:00:00' OR n.StartDate <= NOW())",
        "(n.EndDate IS NULL OR n.EndDate = '0000-00-00 00:00:00' OR n.EndDate >= NOW())"
    ];
    $params = [':dept_id' => $dept_id];

    if ($category_id > 0) {
        $conditions[] = "n.GroupID = :category_id";
        $params[':category_id'] = $category_id;
    }

    $whereClause = implode(' AND ', $conditions);

    // Get total count for pagination
    $stmtCount = $DB->prepare("SELECT COUNT(*) FROM microsite_news n LEFT JOIN microsite_news_groups g ON n.GroupID = g.GroupID WHERE $whereClause");
    $stmtCount->execute($params);
    $total_items = $stmtCount->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    // Fetch data
    $sql = "
        SELECT 
            n.NewsID, n.GroupID, g.GroupNameTH, n.Title_TH, n.Title_EN, n.Detail_TH, n.Detail_EN,
            n.Content_TH, n.Content_EN, n.Image, n.PublishDate, n.ViewCount, d.Slug as DeptSlug
        FROM microsite_news n
        LEFT JOIN microsite_news_groups g ON n.GroupID = g.GroupID
        LEFT JOIN microsite_departments d ON n.DeptID = d.DeptID
        WHERE $whereClause
        ORDER BY n.PublishDate DESC, n.NewsID DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $DB->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];

    // Format output
    foreach ($newsList as $row) {
        $deptSlug = $row['DeptSlug'] ?? 'unknown';
        $coverUrl = getImageUrl($row['Image'], $deptSlug);
        
        // Fetch files/attachments if needed
        $stmtFiles = $DB->prepare("SELECT * FROM microsite_news_files WHERE NewsID = ? AND (DelData != 'Y' OR DelData IS NULL)");
        $stmtFiles->execute([$row['NewsID']]);
        $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);
        
        $attachments = [];
        foreach ($files as $f) {
            $label = $f['Title_TH'] ?? $f['Title'] ?? $f['FileName']; // Try to guess the label column
            $attachments[] = [
                'id' => $f['FileID'],
                'label' => $label,
                'file' => getFileUrl($f['FileName'], $deptSlug),
                'size' => '' // We can calculate real size if needed
            ];
        }

        $result[] = [
            'id' => $row['NewsID'],
            'slug' => $row['NewsID'],
            'title' => $row['Title_TH'],
            'date' => date('d/m/Y', strtotime($row['PublishDate'])), // Format to Thai date if needed in Helper
            'category' => $row['GroupNameTH'] ?? 'ข่าวประชาสัมพันธ์',
            'GroupID' => $row['GroupID'],
            'excerpt' => strip_tags($row['Detail_TH'] ?: substr($row['Content_TH'], 0, 200)),
            'cover' => $coverUrl,
            'image' => [$coverUrl], // Can fetch multiple images from a gallery table if applicable
            'content' => $row['Content_TH'],
            'views' => (int)$row['ViewCount'],
            'publisher' => $deptSlug,
            'gallery' => [],
            'attachments' => $attachments
        ];
    }

    $pagination = [
        'current_page' => $page,
        'per_page' => $limit,
        'total_items' => (int)$total_items,
        'total_pages' => (int)$total_pages
    ];

    sendResponse($result, 200, "News retrieved successfully", $pagination);

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
