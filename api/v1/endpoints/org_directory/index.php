<?php
// src/api/v1/endpoints/org_directory/index.php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB, $ImgPath;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';
$idParam = isset($_GET['id']) ? intval($_GET['id']) : 0;
$deptIdParam = isset($_GET['deptId']) ? intval($_GET['deptId']) : 0;

try {
    if (isset($DB)) {
        // 1. Fetch directory departments
        $sqlDept = "SELECT DeptID, NameTH, NameEN FROM microsite_departments WHERE IsActive = 'Y' ORDER BY NameTH ASC";
        $stmtDept = $DB->query($sqlDept);
        $deptsRaw = $stmtDept->fetchAll(PDO::FETCH_ASSOC);
        
        $directoryDepartments = [];
        foreach ($deptsRaw as $d) {
            $directoryDepartments[] = [
                'id' => (int)$d['DeptID'],
                'nameTh' => $d['NameTH'] ?: '',
                'nameEn' => $d['NameEN'] ?: ''
            ];
        }

        // 2. Fetch directory persons
        $sql = "SELECT p.DirID, p.NameTH, p.NameEN, p.PositionTH, p.PositionEN, 
                       p.DeptID, p.Phone, p.Email, p.Image, p.UpdateDate, 
                       d.NameTH as DeptNameTH, d.NameEN as DeptNameEN 
                FROM org_directory p
                LEFT JOIN microsite_departments d ON p.DeptID = d.DeptID
                WHERE p.DelData = 'N' AND p.IsActive = 'Y'";
        
        $params = [];
        if ($idParam > 0) {
            $sql .= " AND p.DirID = :id";
            $params[':id'] = $idParam;
        }
        if ($deptIdParam > 0) {
            $sql .= " AND p.DeptID = :deptId";
            $params[':deptId'] = $deptIdParam;
        }
        
        $sql .= " ORDER BY p.OrderNo ASC, p.DirID DESC";
        
        $stmt = $DB->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
        $personsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $directoryPersons = [];
        foreach ($personsRaw as $row) {
            $imageKey = !empty($row['Image']) ? $ImgPath . '' . $row['Image'] : '';
            
            // Format date if needed, fallback to raw string
            $updatedAt = '';
            if (!empty($row['UpdateDate']) && $row['UpdateDate'] !== '0000-00-00 00:00:00') {
                $time = strtotime($row['UpdateDate']);
                if ($lang === 'en') {
                    $updatedAt = date('d M Y', $time);
                } else {
                    $months = ["","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
                    $updatedAt = date('d', $time) . " " . $months[date('n', $time)] . " " . (date('Y', $time) + 543);
                }
            }

            $directoryPersons[] = [
                'id' => (int)$row['DirID'],
                'nameTh' => $row['NameTH'] ?: '',
                'nameEn' => $row['NameEN'] ?: '',
                'positionTh' => $row['PositionTH'] ?: '',
                'positionEn' => $row['PositionEN'] ?: '',
                'departmentId' => (int)$row['DeptID'],
                'departmentNameTh' => $row['DeptNameTH'] ?: '',
                'departmentNameEn' => $row['DeptNameEN'] ?: '',
                'phone' => $row['Phone'] ?: '',
                'email' => $row['Email'] ?: '',
                'imagePath' => $imageKey,
                'updatedAt' => $updatedAt
            ];
        }

        $response = [
            'directoryDepartments' => $directoryDepartments,
            'directoryPersons' => $directoryPersons
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['directoryDepartments' => [], 'directoryPersons' => []]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
