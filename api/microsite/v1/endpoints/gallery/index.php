<?php
// src/api/microsite/v1/endpoints/gallery.php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';
$dept_id = (int)($_GET['dept_id'] ?? 0);
$group_id = (int)($_GET['group_id'] ?? 0);
$gallery_id = (int)($_GET['gallery_id'] ?? 0);
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

    if ($gallery_id > 0) {
        // Fetch a specific gallery with all its images (no pagination for images for simplicity, or handle later)
        $stmt = $DB->prepare("
            SELECT g.GalleryID, g.GroupID, gr.GroupNameTH, g.Title_TH, g.Title_EN, g.Image, g.CreateDate 
            FROM microsite_gallery g
            LEFT JOIN microsite_gallery_groups gr ON g.GroupID = gr.GroupID
            WHERE g.GalleryID = ? AND g.DeptID = ? AND g.IsActive = 'Y' AND (gr.IsActive = 'Y' OR g.GroupID = 0 OR g.GroupID IS NULL)
        ");
        $stmt->execute([$gallery_id, $dept_id]);
        $gallery = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gallery) {
            sendError('Gallery not found', 404);
        }

        // Fetch images
        $stmtImg = $DB->prepare("SELECT ImageID, Image FROM microsite_gallery_images WHERE GalleryID = ? ORDER BY OrderNo ASC, ImageID ASC");
        $stmtImg->execute([$gallery_id]);
        $images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

        $imgUrls = [];
        foreach ($images as $img) {
            $imgUrls[] = [
                'id' => $img['ImageID'],
                'url' => getImageUrl($img['Image'], $slug)
            ];
        }

        $result = [
            'id' => $gallery['GalleryID'],
            'title' => $gallery['Title_TH'],
            'description' => '',
            'category' => $gallery['GroupNameTH'] ?? 'แกลเลอรี่',
            'GroupID' => $gallery['GroupID'],
            'cover' => getImageUrl($gallery['Image'], $slug),
            'date' => date('d/m/Y', strtotime($gallery['CreateDate'])),
            'images' => $imgUrls
        ];

        sendResponse($result, 200, "Gallery retrieved successfully");

    } else {
        // Fetch list of galleries
        $conditions = ["g.DeptID = :dept_id", "g.IsActive = 'Y'", "(gr.IsActive = 'Y' OR g.GroupID = 0 OR g.GroupID IS NULL)"];
        $params = [':dept_id' => $dept_id];

        if ($group_id > 0) {
            $conditions[] = "g.GroupID = :group_id";
            $params[':group_id'] = $group_id;
        }

        $whereClause = implode(' AND ', $conditions);

        $stmtCount = $DB->prepare("SELECT COUNT(*) FROM microsite_gallery g LEFT JOIN microsite_gallery_groups gr ON g.GroupID = gr.GroupID WHERE $whereClause");
        $stmtCount->execute($params);
        $total_items = $stmtCount->fetchColumn();
        $total_pages = ceil($total_items / $limit);

        $stmt = $DB->prepare("
            SELECT g.GalleryID, g.GroupID, gr.GroupNameTH, g.Title_TH, g.Title_EN, g.Image, g.CreateDate 
            FROM microsite_gallery g
            LEFT JOIN microsite_gallery_groups gr ON g.GroupID = gr.GroupID
            WHERE $whereClause
            ORDER BY g.CreateDate DESC, g.GalleryID DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $galleries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];

        foreach ($galleries as $row) {
            $result[] = [
                'id' => $row['GalleryID'],
                'title' => $row['Title_TH'],
                'excerpt' => '',
                'category' => $row['GroupNameTH'] ?? 'แกลเลอรี่',
                'GroupID' => $row['GroupID'],
                'cover' => getImageUrl($row['Image'], $slug),
                'date' => date('d/m/Y', strtotime($row['CreateDate']))
            ];
        }

        $pagination = [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => (int)$total_items,
            'total_pages' => (int)$total_pages
        ];

        sendResponse($result, 200, "Galleries retrieved successfully", $pagination);
    }

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
