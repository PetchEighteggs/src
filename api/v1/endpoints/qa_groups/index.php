<?php
// src/api/v1/endpoints/qa_groups/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';
$idParam = isset($_GET['id']) ? $_GET['id'] : 0;
$id = is_numeric($idParam) ? intval($idParam) : 0;

try {
    if (isset($DB)) {
        $sql = "SELECT * FROM qa_groups WHERE IsActive = 'Y'";
        
        $params = [];
        if ($id > 0) {
            $sql .= " AND GroupID = :id";
            $params[':id'] = $id;
        }

        $sql .= " ORDER BY OrderNo ASC, GroupID DESC";

        $stmt = $DB->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        $groupsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];

        foreach ($groupsRaw as $row) {
            $name = $lang === 'en' && !empty($row['GroupNameEN']) ? $row['GroupNameEN'] : $row['GroupNameTH'];
            
            $item = [
                'id' => (int)$row['GroupID'],
                'name' => $name
            ];

            $response[] = $item;
        }

        if ($id > 0) {
            echo json_encode(!empty($response) ? $response[0] : null, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    } else {
        echo json_encode([]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
