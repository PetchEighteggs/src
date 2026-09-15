<?php
// src/api/microsite/v1/endpoints/banner/index.php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';
$dept_id = (int)($_GET['dept_id'] ?? 0);
$group_id = (int)($_GET['group_id'] ?? 0);

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

    $now = date('Y-m-d H:i:s');
    
    // Build query conditions
    $conditions = [
        "b.DeptID = :dept_id", 
        "b.IsActive = 'Y'",
        "b.DelData = 'N'",
        "(g.IsActive = 'Y' OR b.GroupID = 0 OR b.GroupID IS NULL)",
        "(b.StartDate IS NULL OR b.StartDate = '0000-00-00 00:00:00' OR b.StartDate <= :now)",
        "(b.EndDate IS NULL OR b.EndDate = '0000-00-00 00:00:00' OR b.EndDate >= :now2)"
    ];
    $params = [
        ':dept_id' => $dept_id,
        ':now' => $now,
        ':now2' => $now
    ];

    if ($group_id > 0) {
        $conditions[] = "b.GroupID = :group_id";
        $params[':group_id'] = $group_id;
    }

    $whereClause = implode(' AND ', $conditions);

    // Fetch Data
    $sql = "
        SELECT 
            b.BannerID, b.GroupID, g.GroupNameTH, b.Title_TH, b.Title_EN, 
            b.Detail_TH, b.Detail_EN, b.Image, b.URL_TH, b.URL_EN, 
            b.StartDate, b.EndDate, b.Target, b.Color, d.Slug as DeptSlug
        FROM microsite_banner b
        LEFT JOIN microsite_banner_groups g ON b.GroupID = g.GroupID
        LEFT JOIN microsite_departments d ON b.DeptID = d.DeptID
        WHERE $whereClause
        ORDER BY b.OrderNo ASC, b.BannerID DESC
    ";
    
    $stmt = $DB->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];

    foreach ($banners as $row) {
        $deptSlug = $row['DeptSlug'] ?? 'unknown';
        $imageUrl = getImageUrl($row['Image'], $deptSlug);

        $result[] = [
            'id' => $row['BannerID'],
            'title' => $row['Title_TH'],
            'title_en' => $row['Title_EN'],
            'detail' => $row['Detail_TH'],
            'detail_en' => $row['Detail_EN'],
            'image' => $imageUrl,
            'url_th' => $row['URL_TH'],
            'url_en' => $row['URL_EN'],
            'target' => $row['Target'],
            'color' => $row['Color'],
            'group' => $row['GroupNameTH'],
            'group_id' => $row['GroupID'],
            'start_date' => $row['StartDate'],
            'end_date' => $row['EndDate']
        ];
    }

    sendResponse($result, 200, "Banners retrieved successfully");

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
