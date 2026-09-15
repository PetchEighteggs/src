<?php
// src/api/microsite/v1/endpoints/topgraphics/index.php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';
$dept_id = (int)($_GET['dept_id'] ?? 0);

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
    
    // Fetch Top Graphics
    $sql = "
        SELECT 
            t.TopGraphicID, t.Title_TH, t.Title_EN, t.Image, 
            t.LinkURL, t.URL_TH, t.URL_EN, t.Target, 
            t.StartDate, t.EndDate, t.IsImageOnly, d.Slug as DeptSlug
        FROM microsite_topgraphics t
        LEFT JOIN microsite_departments d ON t.DeptID = d.DeptID
        WHERE t.DeptID = :dept_id 
          AND t.IsActive = 'Y' 
          AND (t.StartDate IS NULL OR t.StartDate <= :now)
          AND (t.EndDate IS NULL OR t.EndDate >= :now2)
        ORDER BY t.OrderNo ASC, t.TopGraphicID DESC
    ";
    
    $stmt = $DB->prepare($sql);
    $stmt->bindValue(':dept_id', $dept_id, PDO::PARAM_INT);
    $stmt->bindValue(':now', $now, PDO::PARAM_STR);
    $stmt->bindValue(':now2', $now, PDO::PARAM_STR);
    $stmt->execute();
    
    $graphics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];

    foreach ($graphics as $row) {
        $deptSlug = $row['DeptSlug'] ?? 'unknown';
        $imageUrl = getImageUrl($row['Image'], $deptSlug);

        $result[] = [
            'id' => $row['TopGraphicID'],
            'title' => $row['Title_TH'],
            'title_en' => $row['Title_EN'],
            'image' => $imageUrl,
            'link_url' => $row['LinkURL'],
            'url_th' => $row['URL_TH'],
            'url_en' => $row['URL_EN'],
            'target' => $row['Target'],
            'hide_title' => $row['IsImageOnly'] === 'Y',
            'start_date' => $row['StartDate'],
            'end_date' => $row['EndDate']
        ];
    }

    sendResponse($result, 200, "Top graphics retrieved successfully");

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
