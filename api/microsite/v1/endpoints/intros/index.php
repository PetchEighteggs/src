<?php
// src/api/microsite/v1/endpoints/intros/index.php
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
    
    // Fetch Intros
    $sql = "
        SELECT 
            i.IntroID, i.Title_TH, i.Title_EN, i.Image, 
            i.StartDate, i.EndDate, d.Slug as DeptSlug
        FROM microsite_intros i
        LEFT JOIN microsite_departments d ON i.DeptID = d.DeptID
        WHERE i.DeptID = :dept_id 
          AND i.IsActive = 'Y' 
          AND i.DelData = 'N'
          AND (i.StartDate IS NULL OR i.StartDate = '0000-00-00 00:00:00' OR i.StartDate <= :now)
          AND (i.EndDate IS NULL OR i.EndDate = '0000-00-00 00:00:00' OR i.EndDate >= :now2)
        ORDER BY i.OrderNo ASC, i.IntroID DESC
    ";
    
    $stmt = $DB->prepare($sql);
    $stmt->bindValue(':dept_id', $dept_id, PDO::PARAM_INT);
    $stmt->bindValue(':now', $now, PDO::PARAM_STR);
    $stmt->bindValue(':now2', $now, PDO::PARAM_STR);
    $stmt->execute();
    
    $intros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];

    foreach ($intros as $row) {
        $deptSlug = $row['DeptSlug'] ?? 'unknown';
        $imageUrl = getImageUrl($row['Image'], $deptSlug);

        $result[] = [
            'id' => $row['IntroID'],
            'title' => $row['Title_TH'],
            'title_en' => $row['Title_EN'],
            'image' => $imageUrl,
            'start_date' => $row['StartDate'],
            'end_date' => $row['EndDate']
        ];
    }

    sendResponse($result, 200, "Intros retrieved successfully");

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
