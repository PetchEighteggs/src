<?php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'th';

$deptIdParam = isset($_GET['dept_id']) ? $_GET['dept_id'] : 0;
$deptId = is_numeric($deptIdParam) ? intval($deptIdParam) : 0;

// limit and order
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
if ($limit <= 0 || $limit > 500) $limit = 100;

try {
    if (isset($DB)) {
        // 1. Fetch Departments first
        $deptSql = "SELECT d.DeptID, d.ShortNameTH as DeptNameTH, d.ShortNameEN as DeptNameEN 
				FROM microsite_departments d
				WHERE d.IsActive = 'Y' AND d.DelData = 'N'
        AND EXISTS (SELECT 1 FROM org_positions p WHERE p.DeptID = d.DeptID AND p.DelData = 'N')";
        $deptParams = [];

        if ($deptId > 0) {
            $deptSql .= " AND d.DeptID = :dept_id";
            $deptParams[':dept_id'] = $deptId;
        }
        
        $deptSql .= " ORDER BY d.OrderNo ASC, d.DeptID ASC";
        
        $stmtDept = $DB->prepare($deptSql);
        foreach ($deptParams as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmtDept->bindValue($key, $value, $type);
        }
        $stmtDept->execute();
        $departmentsRaw = $stmtDept->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch all active positions for these departments
        $posSql = "SELECT PositionID, DeptID, Name_TH, Name_EN, OrderNo 
        FROM org_positions 
        WHERE IsActive = 'Y' AND DelData = 'N'";
        $posParams = [];
        
        if ($deptId > 0) {
            $posSql .= " AND DeptID = :dept_id";
            $posParams[':dept_id'] = $deptId;
        }
        
        $posSql .= " ORDER BY OrderNo ASC, PositionID ASC";
        
        $stmtPos = $DB->prepare($posSql);
        foreach ($posParams as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmtPos->bindValue($key, $value, $type);
        }
        $stmtPos->execute();
        $positionsRaw = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

        // Group positions by DeptID
        $positionsByDept = [];
        foreach ($positionsRaw as $pos) {
            $dId = (int)$pos['DeptID'];
            if (!isset($positionsByDept[$dId])) {
                $positionsByDept[$dId] = [];
            }
            $title = $lang === 'en' && !empty($pos['Name_EN']) ? $pos['Name_EN'] : $pos['Name_TH'];
            $positionsByDept[$dId][] = [
                'id' => (int)$pos['PositionID'],
                'title' => $title,
                'title_th' => $pos['Name_TH'],
                'title_en' => $pos['Name_EN'] ?? ''
            ];
        }

        $response = [];

        foreach ($departmentsRaw as $dept) {
            $dId = (int)$dept['DeptID'];
            $deptName = $lang === 'en' && !empty($dept['DeptNameEN']) ? $dept['DeptNameEN'] : $dept['DeptNameTH'];
            
            $item = [
							'dept_id' => $dId,
							'dept_name' => $deptName,
							'dept_name_th' => $dept['DeptNameTH'],
							'dept_name_en' => $dept['DeptNameEN'] ?? '',
							'positions' => $positionsByDept[$dId] ?? []
            ];
            $response[] = $item;
        }

        echo json_encode([
            'status' => 'success',
            'code' => 200,
            'data' => $response
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'code' => 500,
        'message' => 'Database error',
        'debug' => $e->getMessage()
    ]);
}
