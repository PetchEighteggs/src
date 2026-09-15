<?php
// src/api/microsite/v1/endpoints/executives.php
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

    // Fetch active executives
    $stmt = $DB->prepare("
        SELECT ExecID, NameTH, NameEN, PositionTH, PositionEN, Image 
        FROM microsite_executives 
        WHERE DeptID = ? AND IsActive = 'Y' 
        ORDER BY OrderNo ASC, ExecID ASC
    ");
    $stmt->execute([$dept_id]);
    $executives = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    foreach ($executives as $row) {
        $result[] = [
            'id' => $row['ExecID'],
            'name' => $row['NameTH'],
            'position' => $row['PositionTH'],
            'description' => '', // Columns don't exist
            'image' => getImageUrl($row['Image'], $slug, 'executives')
        ];
    }

    sendResponse($result, 200, "Executives retrieved successfully");

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
