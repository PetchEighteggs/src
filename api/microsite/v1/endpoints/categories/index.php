<?php
// src/api/microsite/v1/endpoints/categories.php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';
$module = $_GET['module'] ?? '';

$valid_modules = [
    'news' => ['table' => 'microsite_news_groups', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN'],
    'download' => ['table' => 'microsite_download_groups', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN'],
    'gallery' => ['table' => 'microsite_gallery_groups', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN'],
    'multimedia' => ['table' => 'microsite_multimedia_groups', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN'],
    'about' => ['table' => 'microsite_about_groups', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN'],
    'banner' => ['table' => 'microsite_banner_groups', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN'],
    'sitemap' => ['table' => 'microsite_sitemap_groups', 'colTH' => 'Title_TH', 'colEN' => 'Title_EN'],
    'contact' => ['table' => 'microsite_contact_groups', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN']
];

if (!array_key_exists($module, $valid_modules)) {
    sendError("Invalid or missing module parameter. Valid modules: " . implode(', ', array_keys($valid_modules)), 400);
}

try {
    // Resolve DeptID
    $stmtDept = $DB->prepare("SELECT DeptID FROM microsite_departments WHERE Slug = ? LIMIT 1");
    $stmtDept->execute([$slug]);
    $dept_id = $stmtDept->fetchColumn();

    if (!$dept_id) {
        sendError('Valid Department Slug is required', 400);
    }

    $table = $valid_modules[$module]['table'];
    $colTH = $valid_modules[$module]['colTH'];
    $colEN = $valid_modules[$module]['colEN'];
    
    $is_download = ($module === 'download');
    $is_sitemap = ($module === 'sitemap');

    // Build the query
    $sql = "
        SELECT GroupID, $colTH AS NameTH, $colEN AS NameEN
        FROM $table 
        WHERE DeptID = :dept_id 
          AND IsActive = 'Y' 
          AND (DelData = 'N' OR DelData IS NULL)
        ORDER BY OrderNo ASC, GroupID ASC
    ";

    $stmt = $DB->prepare($sql);
    $stmt->bindValue(':dept_id', $dept_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];

    // Fetch subgroups if applicable
    $subgroupsByGroup = [];
    if (($is_download || $is_sitemap) && count($groups) > 0) {
        $group_ids = array_column($groups, 'GroupID');
        $in_clause = implode(',', array_fill(0, count($group_ids), '?'));
        
        $subTable = $is_download ? 'microsite_download_subgroups' : 'microsite_sitemap_subgroups';
        $subIdCol = $is_download ? 'SubGroupID' : 'SubGroupID';
        $subColTH = $is_download ? 'SubGroupNameTH' : 'Title_TH';
        $subColEN = $is_download ? 'SubGroupNameEN' : 'Title_EN';
        
        $sqlSub = "
            SELECT $subIdCol AS SubGroupID, GroupID, $subColTH AS SubNameTH, $subColEN AS SubNameEN 
            FROM $subTable 
            WHERE GroupID IN ($in_clause) 
              AND IsActive = 'Y' 
              AND (DelData = 'N' OR DelData IS NULL)
            ORDER BY GroupID ASC, OrderNo ASC, SubGroupID ASC
        ";
        
        $stmtSub = $DB->prepare($sqlSub);
        $stmtSub->execute($group_ids);
        $subgroups = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($subgroups as $sub) {
            $subgroupsByGroup[$sub['GroupID']][] = [
                'id' => $sub['SubGroupID'],
                'name_th' => $sub['SubNameTH'],
                'name_en' => $sub['SubNameEN']
            ];
        }
    }

    // Format output
    foreach ($groups as $row) {
        $item = [
            'id' => $row['GroupID'],
            'name_th' => $row['NameTH'],
            'name_en' => $row['NameEN']
        ];
        
        if ($is_download || $is_sitemap) {
            $item['subgroups'] = $subgroupsByGroup[$row['GroupID']] ?? [];
        }
        
        $result[] = $item;
    }

    sendResponse($result, 200, ucfirst($module) . " categories retrieved successfully");

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
