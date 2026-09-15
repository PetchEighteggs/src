<?php
// src/api/microsite/v1/endpoints/multimedia.php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';
$dept_id = (int)($_GET['dept_id'] ?? 0);
$group_id = (int)($_GET['group_id'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 10);

if ($page < 1) $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;

try {
    if (!empty($slug) && $dept_id === 0) {
        $stmtDept = $DB->prepare("SELECT DeptID FROM microsite_departments WHERE Slug = ? LIMIT 1");
        $stmtDept->execute([$slug]);
        $dept_id = $stmtDept->fetchColumn();
    }

    if ($dept_id === 0 || $dept_id === false) {
        sendError('Valid Department ID or Slug is required', 400);
    }

    $conditions = ["m.DeptID = :dept_id", "m.IsActive = 'Y'", "(g.IsActive = 'Y' OR m.GroupID = 0 OR m.GroupID IS NULL)"];
    $params = [':dept_id' => $dept_id];

    if ($group_id > 0) {
        $conditions[] = "m.GroupID = :group_id";
        $params[':group_id'] = $group_id;
    }

    $whereClause = implode(' AND ', $conditions);

    // Count Total
    $stmtCount = $DB->prepare("SELECT COUNT(*) FROM microsite_multimedia m LEFT JOIN microsite_multimedia_groups g ON m.GroupID = g.GroupID WHERE $whereClause");
    $stmtCount->execute($params);
    $total_items = $stmtCount->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    // Fetch Data
    $sql = "
        SELECT 
        m.MediaID, m.GroupID, g.GroupNameTH, m.Title_TH, m.Title_EN, 
        m.URL_TH, m.URL_EN, m.Image, m.Views, m.Target, d.Slug as DeptSluge 
        FROM microsite_multimedia m
        LEFT JOIN microsite_multimedia_groups g ON m.GroupID = g.GroupID
        LEFT JOIN microsite_departments d ON m.DeptID = d.DeptID
        WHERE $whereClause
        ORDER BY m.CreateDate DESC, m.MediaID DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $DB->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $mediaList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];

    foreach ($mediaList as $row) {
        $url = $row['URL_TH'];
        
        // Extract Youtube Video ID if it's a Youtube link
        $youtubeId = '';
        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url, $match)) {
            $youtubeId = $match[1];
        }

        $result[] = [
            'id' => $row['MediaID'],
            'title' => $row['Title_TH'],
            'url' => $row['URL_TH'],
            'url_en' => $row['URL_EN'],
            'youtubeId' => $youtubeId,
            'category' => $row['GroupNameTH'] ?? 'วีดีโอ',
            'GroupID' => $row['GroupID'],
            'cover' => getImageUrl($row['Image'], $slug),
            'views' => (int)$row['Views'],
            'date' => date('d/m/Y', strtotime($row['CreateDate']))
        ];
    }

    $pagination = [
        'current_page' => $page,
        'per_page' => $limit,
        'total_items' => (int)$total_items,
        'total_pages' => (int)$total_pages
    ];

    sendResponse($result, 200, "Multimedia retrieved successfully", $pagination);

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}