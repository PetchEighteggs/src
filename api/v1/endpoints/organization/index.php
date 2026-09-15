<?php
// src/api/v1/endpoints/organization/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';

try {
    if (isset($DB)) {
        // ดึงเฉพาะส่วนกลางและส่วนภูมิภาค
        $sql = "SELECT DeptID, NameTH, NameEN, Slug, DeptGroup 
                FROM microsite_departments 
                WHERE IsActive = 'Y' AND DeptGroup IN ('central', 'regional')
                ORDER BY DeptGroup ASC, NameTH ASC";
        $stmt = $DB->prepare($sql);
        $stmt->execute();
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [
            'central' => [],
            'regional' => []
        ];
        
        foreach ($departments as $row) {
            $name = $lang === 'en' && !empty($row['NameEN']) ? $row['NameEN'] : $row['NameTH'];
            $url = $row['Slug'];
            
            $item = [
                'id' => (int)$row['DeptID'],
                'name' => $name,
                'nameEn' => $row['NameEN'] ?: '',
                'Slug' => $url
            ];
            
            if ($row['DeptGroup'] === 'central') {
                $response['central'][] = $item;
            } else if ($row['DeptGroup'] === 'regional') {
                $response['regional'][] = $item;
            }
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['central' => [], 'regional' => []]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
