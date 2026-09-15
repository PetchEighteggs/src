<?php
// src/api/microsite/v1/endpoints/popup.php
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

    // Fetch active popups
    $stmt = $DB->prepare("
        SELECT PopupID, Title_TH, Title_EN, Image, URL_TH, Target, StartDate, EndDate 
        FROM microsite_popups 
        WHERE DeptID = ? AND IsActive = 'Y' AND (DelData = 'N' OR DelData IS NULL)
        AND (StartDate IS NULL OR StartDate = '0000-00-00 00:00:00' OR StartDate <= NOW())
        AND (EndDate IS NULL OR EndDate = '0000-00-00 00:00:00' OR EndDate >= NOW())
        ORDER BY OrderNo ASC, PopupID DESC
    ");
    $stmt->execute([$dept_id]);
    $popups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    foreach ($popups as $row) {
        $result[] = [
            'id' => $row['PopupID'],
            'title' => $row['Title_TH'],
            'image' => getImageUrl($row['Image'], $slug),
            'url' => (!empty($row['URL_TH'])) ? $row['URL_TH'] : '#',
            'target' => $row['Target'] ?: '_self'
        ];
    }

    sendResponse($result, 200, "Popups retrieved successfully");

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
