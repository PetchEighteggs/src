<?php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';

try {
    // Resolve DeptID
    $stmtDept = $DB->prepare("SELECT DeptID FROM microsite_departments WHERE Slug = ? LIMIT 1");
    $stmtDept->execute([$slug]);
    $dept_id = $stmtDept->fetchColumn();

    if (!$dept_id) {
        sendError('Valid Department Slug is required', 400);
    }

    // Fetch Main Menus from microsite_menus that have a valid ModuleType
    $stmtMenu = $DB->prepare("
        SELECT MenuID, MenuNameTH, MenuNameEN, URL, ModuleType
        FROM microsite_menus
        WHERE DeptID = ? AND IsActive = 'Y' AND COALESCE(DelData, 'N') != 'Y' AND ModuleType != '' AND ModuleType IS NOT NULL
        ORDER BY OrderNo ASC
    ");
    $stmtMenu->execute([$dept_id]);
    $mainMenus = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    // Mapping configuration
    $map = [
        'about' => [
            'group' => ['table' => 'microsite_about_groups', 'id' => 'GroupID', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN'],
            'content' => ['table' => 'microsite_about', 'id' => 'AboutID', 'fk' => 'GroupID', 'colTH' => 'Title_TH', 'colEN' => 'Title_EN']
        ],
        'news' => [
            'group' => ['table' => 'microsite_news_groups', 'id' => 'GroupID', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN']
        ],
        'gallery' => [
            'group' => ['table' => 'microsite_gallery_groups', 'id' => 'GroupID', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN']
        ],
        'multimedia' => [
            'group' => ['table' => 'microsite_multimedia_groups', 'id' => 'GroupID', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN']
        ],
        'download' => [
            'group' => ['table' => 'microsite_download_groups', 'id' => 'GroupID', 'colTH' => 'GroupNameTH', 'colEN' => 'GroupNameEN'],
            'content' => ['table' => 'microsite_download_subgroups', 'id' => 'SubGroupID', 'fk' => 'GroupID', 'colTH' => 'SubGroupNameTH', 'colEN' => 'SubGroupNameEN']
        ]
    ];

    foreach ($mainMenus as $menu) {
        $mod = $menu['ModuleType'];
        
        if (!isset($map[$mod])) {
            continue;
        }

        $cfg = $map[$mod];
        $moduleGroups = [];

        // Fetch Groups (Main Menu for module)
        $g = $cfg['group'];
        $sqlGroup = "SELECT {$g['id']} AS id, {$g['colTH']} AS title_th, {$g['colEN']} AS title_en 
                     FROM {$g['table']} 
                     WHERE DeptID = ? AND IsActive = 'Y' AND COALESCE(DelData, 'N') != 'Y' 
                     ORDER BY OrderNo ASC";
        $stmtG = $DB->prepare($sqlGroup);
        $stmtG->execute([$dept_id]);
        $groups = $stmtG->fetchAll(PDO::FETCH_ASSOC);

        $contentByGroup = [];

        // Fetch Content (Sub Menu for module) if defined
        if (isset($cfg['content'])) {
            $c = $cfg['content'];
            
            // Subgroups for download might not have DeptID. We check table name.
            if ($c['table'] === 'microsite_download_subgroups') {
                $sqlContent = "SELECT {$c['id']} AS id, {$c['fk']} AS fk, {$c['colTH']} AS title_th, {$c['colEN']} AS title_en 
                               FROM {$c['table']} 
                               WHERE IsActive = 'Y' AND COALESCE(DelData, 'N') != 'Y' 
                               ORDER BY OrderNo ASC";
                $stmtC = $DB->prepare($sqlContent);
                $stmtC->execute();
            } else {
                $sqlContent = "SELECT {$c['id']} AS id, {$c['fk']} AS fk, {$c['colTH']} AS title_th, {$c['colEN']} AS title_en 
                               FROM {$c['table']} 
                               WHERE DeptID = ? AND IsActive = 'Y' AND COALESCE(DelData, 'N') != 'Y' 
                               ORDER BY OrderNo ASC";
                $stmtC = $DB->prepare($sqlContent);
                $stmtC->execute([$dept_id]);
            }
            
            $contentsData = $stmtC->fetchAll(PDO::FETCH_ASSOC);

            // Group Content by GroupID
            foreach ($contentsData as $row) {
                $contentByGroup[$row['fk']][] = [
                    'id' => $row['id'],
                    'title_th' => $row['title_th'],
                    'title_en' => $row['title_en']
                ];
            }
        }

        // Build Groups Result for this module
        foreach ($groups as $row) {
            $moduleGroups[] = [
                'id' => $row['id'],
                'title_th' => $row['title_th'],
                'title_en' => $row['title_en'],
                'submenus' => $contentByGroup[$row['id']] ?? []
            ];
        }
        
        // ถ้าข้างในไม่มี submenu ก็ไม่ต้องแสดง
        if (!empty($moduleGroups)) {
            $result[] = [
                'id' => $menu['MenuID'],
                'title_th' => $menu['MenuNameTH'],
                'title_en' => $menu['MenuNameEN'],
                'url' => $menu['URL'],
                'module_type' => $mod,
                'submenus' => $moduleGroups
            ];
        }
    }

    sendResponse($result, 200, "Sidebar menus retrieved successfully");

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
