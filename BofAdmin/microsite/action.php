<?php
define('_VALID_ACCESS', 1);
session_start();

require_once('../../configuration.php');
require_once('../../config/ms_com.php');
require_once('../../config/ms.class.php');
require_once('../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
$MS = new MS($DB);

$action = $_GET['action'] ?? '';

if ($action === 'switch_dept') {
    $deptID = $_GET['id'] ?? 0;
    
    $userGroupID = $_SESSION['GroupID'] ?? '';
    
    if ($userGroupID === 'A') {

        $stmt = $DB->prepare("SELECT DeptID, NameTH FROM microsite_departments WHERE DeptID = ?");
        $stmt->execute([$deptID]);
        if ($dept = $stmt->fetch(PDO::FETCH_ASSOC)) {
					$_SESSION['ActiveDeptID'] = $dept['DeptID'];
					$_SESSION['ActiveDeptName'] = $dept['NameTH'];
					$MS->StaffLog('สลับหน่วยงานเป็น ID: ' . $deptID, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, 4, '', $deptID);
					header("Location: view.php");
					exit;
        }
    } else if ($userGroupID === 'S') {

        $staffID = $_SESSION['StaffID'] ?? 0;
        $stmt = $DB->prepare("
					SELECT d.DeptID, d.NameTH 
					FROM microsite_departments d
					JOIN staff_microsite_access a ON d.DeptID = a.DeptID
					WHERE d.DeptID = ? AND a.StaffID = ?
        ");
        $stmt->execute([$deptID, $staffID]);
        if ($dept = $stmt->fetch(PDO::FETCH_ASSOC)) {
					$_SESSION['ActiveDeptID'] = $dept['DeptID'];
					$_SESSION['ActiveDeptName'] = $dept['NameTH'];
					$MS->StaffLog('สลับหน่วยงานเป็น ID: ' . $deptID, $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, 4, '', $deptID);
					
					// Find first allowed menu for this staff
					$menuStmt = $DB->prepare("
						SELECT mi.MenuKey 
						FROM staff_microsite_menu_permission smp
						JOIN microsite_menu_items mi ON smp.MenuID = mi.MenuID
						WHERE smp.StaffID = ? AND smp.DeptID = ? AND smp.CanView = 'Y'
						ORDER BY mi.MenuOrder ASC LIMIT 1
					");
					$menuStmt->execute([$staffID, $deptID]);
					$firstMenu = $menuStmt->fetchColumn();

					if ($firstMenu && $firstMenu !== 'settings') {
						header("Location: {$firstMenu}/index.php");
					} else {
						header("Location: view.php");
					}
					exit;
        }
    }
    
    header("Location: index.php");
    exit;
} else if ($action === 'exit_dept') {
    unset($_SESSION['ActiveDeptID']);
    unset($_SESSION['ActiveDeptName']);
    $MS->StaffLog('ออกจากระบบหน่วยงาน (Microsite)', $_SERVER['REQUEST_URI'], $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1, 4, '');
    header("Location: index.php");
    exit;
}
