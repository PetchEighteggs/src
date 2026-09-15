<?php
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

    // Fetch Menus
    $stmt = $DB->prepare("
        SELECT MenuID, MenuNameTH, MenuNameEN, URL, Target 
        FROM microsite_menus 
        WHERE DeptID = ? AND IsActive = 'Y' AND DelData != 'Y'
        ORDER BY OrderNo ASC
    ");
    $stmt->execute([$dept_id]);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Submenus
    $stmtSub = $DB->prepare("
        SELECT SubMenuID, MenuID, SubMenuNameTH, SubMenuNameEN, URL, Target 
        FROM microsite_submenus 
        WHERE DeptID = ? AND IsActive = 'Y' AND DelData != 'Y'
        ORDER BY MenuID ASC, OrderNo ASC
    ");
    $stmtSub->execute([$dept_id]);
    $submenus = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

    // Group submenus by MenuID
    $submenusByMenu = [];
    foreach ($submenus as $sub) {
        $submenusByMenu[$sub['MenuID']][] = [
            'id' => $sub['SubMenuID'],
            'title' => $sub['SubMenuNameTH'],
            'url' => $sub['URL'] ?: '#',
            'target' => $sub['Target'] ?? '_self'
        ];
    }

    $result = [];
    foreach ($menus as $menu) {
        $menuId = $menu['MenuID'];
        $result[] = [
            'id' => $menuId,
            'title' => $menu['MenuNameTH'],
            'url' => $menu['URL'] ?: '#',
            'target' => $menu['Target'] ?? '_self',
            'submenus' => $submenusByMenu[$menuId] ?? []
        ];
    }

    sendResponse($result, 200, "Menus retrieved successfully");

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
