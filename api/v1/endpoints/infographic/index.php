<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

// สื่อประชาสัมพันธ์: use the same image directory as the services admin form.
$serviceImagePath = rtrim($_Config_live_site, '/') . '/uploads/images/services/';

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
$groupId = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    if (isset($DB)) {
        $sql = "
            SELECT d.*, g.GroupNameTH, g.GroupNameEN 
            FROM service_docs d
            LEFT JOIN service_doc_groups g ON d.GroupID = g.GroupID
            WHERE d.IsActive = 'Y' AND d.ServiceType = 'infographic' AND (d.DelData = 'N' OR d.DelData IS NULL)
        ";
        
        $params = [];

        if ($id > 0) {
            $sql .= " AND d.DocID = :id";
            $params[':id'] = $id;
        }

        if ($groupId > 0) {
            $sql .= " AND d.GroupID = :group_id";
            $params[':group_id'] = $groupId;
        }
        
        $sql .= " ORDER BY d.OrderNo ASC, d.DocID DESC";

        if ($limit > 0 && $id == 0) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }

        $stmt = $DB->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        $docsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];

        foreach ($docsRaw as $row) {
            $docId = $row['DocID'];
            
            // Get Attachments
            $attStmt = $DB->prepare("SELECT * FROM service_doc_attachments WHERE DocID = ? AND (DelData = 'N' OR DelData IS NULL) ORDER BY OrderNo ASC");
            $attStmt->execute([$docId]);
            $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $title = $lang === 'en' && !empty($row['TitleEN']) ? $row['TitleEN'] : $row['TitleTH'];
            $description = $lang === 'en' && !empty($row['DescriptionEN']) ? $row['DescriptionEN'] : $row['DescriptionTH'];
            $groupName = $lang === 'en' && !empty($row['GroupNameEN']) ? $row['GroupNameEN'] : $row['GroupNameTH'];
            
            $item = [
                'id' => (int)$docId,
                'title' => $title,
                'description' => $description,
                'category' => $groupName,
                'categoryId' => (int)$row['GroupID'],
                'views' => isset($row['Views']) ? (int)$row['Views'] : 0,
                'downloads' => isset($row['Downloads']) ? (int)$row['Downloads'] : 0,
                'file_cover' => !empty($row['File']) ? $serviceImagePath.$row['File'] : '',
                'attachments' => array_map(function($a) use ($serviceImagePath) {
                    $ext = strtolower(pathinfo($a['FilePath'], PATHINFO_EXTENSION));
                    $sizeVal = isset($a['FileSize']) ? $a['FileSize'] : 0;
                    return [
                        'id' => $a['AttachID'],
                        'name' => basename($a['FilePath']),
                        'url' => $serviceImagePath.$a['FilePath'],
                        'type' => strtoupper($ext),
                        'size' => $sizeVal,
                        'sizeStr' => $sizeVal > 0 ? $sizeVal . ' Mb' : '0 Mb'
                    ];
                }, $attachments)
            ];

            $response[] = $item;
        }

        if ($id > 0) {
            echo json_encode(!empty($response) ? $response[0] : null, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    } else {
        echo json_encode([]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

