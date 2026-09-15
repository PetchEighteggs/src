<?php
// src/api/microsite/v1/endpoints/about.php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';
$dept_id = (int)($_GET['dept_id'] ?? 0);

try {
    if (!empty($slug) && $dept_id === 0) {
        $stmtDept = $DB->prepare("SELECT DeptID FROM microsite_departments WHERE Slug = ? LIMIT 1");
        $stmtDept->execute([$slug]);
        $dept_id = $stmtDept->fetchColumn();
    }

    if ($dept_id === 0 || $dept_id === false) {
        sendError('Valid Department ID or Slug is required', 400);
    }

    // Fetch about content
    $stmt = $DB->prepare("
        SELECT a.GroupID, a.Title_TH, a.Title_EN, a.Detail_TH, a.Detail_EN, a.Image 
        FROM microsite_about a
        LEFT JOIN microsite_about_groups g ON a.GroupID = g.GroupID
        WHERE a.DeptID = ? AND a.IsActive = 'Y' AND (g.IsActive = 'Y' OR a.GroupID = 0 OR a.GroupID IS NULL)
        ORDER BY a.OrderNo ASC, a.AboutID ASC
    ");
    $stmt->execute([$dept_id]);
    $aboutList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    // Format to match aboutContent.json structure
    // e.g. { "147": { "titleTh": "...", "titleEn": "...", "contentTh": "...", "contentEn": "..." } }
    foreach ($aboutList as $row) {
        $group_id = (string)$row['GroupID'];
        
        // Some content might have an image field. We can append it to the content or add an image key.
        $contentTh = $row['Detail_TH'];
        $contentEn = $row['Detail_EN'];
        
        // If there's a standalone image, append it to the content if it's not already there
        if (!empty($row['Image'])) {
            $imgUrl = getImageUrl($row['Image'], $slug);
            $imgTag = '<img src="'.$imgUrl.'" class="w-full my-10" alt="" />';
            $contentTh = $imgTag . $contentTh;
            $contentEn = $imgTag . $contentEn;
        }

        // We only take the first active item per group, or append if multiple.
        // Assuming 1 AboutID per GroupID per DeptID.
        $result[$group_id] = [
            'titleTh' => $row['Title_TH'],
            'titleEn' => $row['Title_EN'],
            'contentTh' => $contentTh,
            'contentEn' => $contentEn
        ];
    }

    sendResponse($result, 200, "About content retrieved successfully");

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
