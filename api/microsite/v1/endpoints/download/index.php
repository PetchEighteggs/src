<?php
// src/api/microsite/v1/endpoints/download.php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';
$dept_id = (int)($_GET['dept_id'] ?? 0);
$group_id = (int)($_GET['group_id'] ?? 0);
$subgroup_id = (int)($_GET['subgroup_id'] ?? 0);
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

    $conditions = ["d.DeptID = :dept_id", "d.IsActive = 'Y'", "(g.IsActive = 'Y' OR d.GroupID = 0 OR d.GroupID IS NULL)"];
    $params = [':dept_id' => $dept_id];

    if ($group_id > 0) {
        $conditions[] = "d.GroupID = :group_id";
        $params[':group_id'] = $group_id;
    }
    
    if ($subgroup_id > 0) {
        $conditions[] = "d.SubGroupID = :subgroup_id";
        $params[':subgroup_id'] = $subgroup_id;
    }

    $whereClause = implode(' AND ', $conditions);

    // Count Total
    $stmtCount = $DB->prepare("SELECT COUNT(*) FROM microsite_download d LEFT JOIN microsite_download_groups g ON d.GroupID = g.GroupID WHERE $whereClause");
    $stmtCount->execute($params);
    $total_items = $stmtCount->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    // Fetch Data
    $sql = "
        SELECT d.DownloadID, d.GroupID, g.GroupNameTH, d.SubGroupID, s.SubGroupNameTH, 
               d.Title_TH, d.Title_EN, d.Detail_TH, d.File, d.CreateDate 
        FROM microsite_download d
        LEFT JOIN microsite_download_groups g ON d.GroupID = g.GroupID
        LEFT JOIN microsite_download_subgroups s ON d.SubGroupID = s.SubGroupID
        WHERE $whereClause
        ORDER BY d.OrderNo ASC, d.CreateDate DESC, d.DownloadID DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $DB->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];

    foreach ($downloads as $row) {
        $result[] = [
            'id' => $row['DownloadID'],
            'title' => $row['Title_TH'],
            'description' => $row['Detail_TH'],
            'category' => $row['GroupNameTH'] ?? 'เอกสารดาวน์โหลด',
            'GroupID' => $row['GroupID'],
            'subCategory' => $row['SubGroupNameTH'],
            'subGroupID' => $row['SubGroupID'],
            'fileUrl' => getFileUrl($row['File'], $slug),
            'fileName' => $row['File'],
            'date' => date('d/m/Y', strtotime($row['CreateDate']))
        ];
    }

    $pagination = [
        'current_page' => $page,
        'per_page' => $limit,
        'total_items' => (int)$total_items,
        'total_pages' => (int)$total_pages
    ];

    sendResponse($result, 200, "Downloads retrieved successfully", $pagination);

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
