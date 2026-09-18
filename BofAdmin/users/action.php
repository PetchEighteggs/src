<?php
define('_VALID_ACCESS', 1);
session_start();

include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/func.class.php');
include_once('../../config/ms.class.php');
require_once('../includes/action_security.php');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

$action = mosGetParam($_FORM, 'action', '');
$id = mosGetParam($_FORM, 'id', '');

$Username = mosGetParam($_FORM, 'Username', '');
$Password = mosGetParam($_FORM, 'Password', '');
$Name = mosGetParam($_FORM, 'Name', '');
$Email = mosGetParam($_FORM, 'Email', '');
$Tel = mosGetParam($_FORM, 'Tel', '');
$GroupID = mosGetParam($_FORM, 'GroupID', 'S');
$SetGroupID = mosGetParam($_FORM, 'SetGroupID', 0);
$Status = mosGetParam($_FORM, 'Status', 'Y');

try {
    if ($action == 'add') {
        // Check duplicate username
        $stmt = $DB->prepare("SELECT COUNT(*) FROM staff WHERE Username = ? AND DelData = 'N'");
        $stmt->execute([$Username]);
        if ($stmt->fetchColumn() > 0) {
            mosRedirect('form.php', 'ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว');
            exit;
        }

        // We use dummy md5 for now, normally you'd use salt_pass or proper hash
        $hashedPass = md5(salt_pass($Password));

        $stmt = $DB->prepare("INSERT INTO staff (Username, Password, Name, Email, Tel, GroupID, SetGroupID, Status, CreateDate, CreateIP) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->execute([$Username, $hashedPass, $Name, $Email, $Tel, $GroupID, $SetGroupID, $Status, $_SERVER['REMOTE_ADDR']]);

        $newStaffID = $DB->lastInsertId();
        if ($GroupID === 'S' && isset($_POST['accessible_dept']) && !empty($_POST['accessible_dept'])) {
            $insAcc = $DB->prepare("INSERT INTO staff_microsite_access (StaffID, DeptID, CanEdit, CreateDate, CreateBy, CreateIP) VALUES (?, ?, 'Y', NOW(), ?, ?)");
            $insAcc->execute([$newStaffID, $_POST['accessible_dept'], $StaffID, $_SERVER['REMOTE_ADDR']]);
        }

        // Save microsite menu permissions
        if ($GroupID === 'S' && isset($_POST['accessible_dept']) && !empty($_POST['accessible_dept'])
            && isset($_POST['microsite_perm']) && is_array($_POST['microsite_perm'])) {
            $deptId = intval($_POST['accessible_dept']);
            $insMPerm = $DB->prepare("INSERT INTO staff_microsite_menu_permission (StaffID, DeptID, MenuID, CanView, CanAdd, CanEdit, CanDelete, CanApprove) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['microsite_perm'] as $menuId => $perms) {
                $menuIdInt = intval($menuId);
                if ($menuIdInt <= 0) continue;
                $insMPerm->execute([
                    $newStaffID, $deptId, $menuIdInt,
                    (isset($perms['view'])    ? 'Y' : 'N'),
                    (isset($perms['add'])     ? 'Y' : 'N'),
                    (isset($perms['edit'])    ? 'Y' : 'N'),
                    (isset($perms['del'])     ? 'Y' : 'N'),
                    (isset($perms['approve']) ? 'Y' : 'N'),
                ]);
            }
        }

        $MS->StaffLog('เพิ่มผู้ใช้งานระบบ', $_SERVER['REQUEST_URI'], $StaffID, 4, 8, $newStaffID);

    } else if ($action == 'edit' && $id != '') {
        if (!empty($Password)) {
            $hashedPass = md5(salt_pass($Password));
            $stmt = $DB->prepare("UPDATE staff SET Password=?, Name=?, Email=?, Tel=?, GroupID=?, SetGroupID=?, Status=?, UpdateDate=NOW(), UpdateIP=? WHERE StaffID=?");
            $stmt->execute([$hashedPass, $Name, $Email, $Tel, $GroupID, $SetGroupID, $Status, $_SERVER['REMOTE_ADDR'], $id]);
        } else {
            $stmt = $DB->prepare("UPDATE staff SET Name=?, Email=?, Tel=?, GroupID=?, SetGroupID=?, Status=?, UpdateDate=NOW(), UpdateIP=? WHERE StaffID=?");
            $stmt->execute([$Name, $Email, $Tel, $GroupID, $SetGroupID, $Status, $_SERVER['REMOTE_ADDR'], $id]);
        }

        // Update microsite access
        $delAcc = $DB->prepare("DELETE FROM staff_microsite_access WHERE StaffID = ?");
        $delAcc->execute([$id]);

        if ($GroupID === 'S' && isset($_POST['accessible_dept']) && !empty($_POST['accessible_dept'])) {
            $insAcc = $DB->prepare("INSERT INTO staff_microsite_access (StaffID, DeptID, CanEdit, CreateDate, CreateBy, CreateIP) VALUES (?, ?, 'Y', NOW(), ?, ?)");
            $insAcc->execute([$id, $_POST['accessible_dept'], $StaffID, $_SERVER['REMOTE_ADDR']]);
        }

        // Re-save microsite menu permissions (delete old, insert new)
        $delMP = $DB->prepare("DELETE FROM staff_microsite_menu_permission WHERE StaffID = ?");
        $delMP->execute([$id]);
        if ($GroupID === 'S' && isset($_POST['accessible_dept']) && !empty($_POST['accessible_dept'])
            && isset($_POST['microsite_perm']) && is_array($_POST['microsite_perm'])) {
            $deptId = intval($_POST['accessible_dept']);
            $insMPerm = $DB->prepare("INSERT INTO staff_microsite_menu_permission (StaffID, DeptID, MenuID, CanView, CanAdd, CanEdit, CanDelete, CanApprove) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['microsite_perm'] as $menuId => $perms) {
                $menuIdInt = intval($menuId);
                if ($menuIdInt <= 0) continue;
                $insMPerm->execute([
                    $id, $deptId, $menuIdInt,
                    (isset($perms['view'])    ? 'Y' : 'N'),
                    (isset($perms['add'])     ? 'Y' : 'N'),
                    (isset($perms['edit'])    ? 'Y' : 'N'),
                    (isset($perms['del'])     ? 'Y' : 'N'),
                    (isset($perms['approve']) ? 'Y' : 'N'),
                ]);
            }
        }

        $MS->StaffLog('แก้ไขผู้ใช้งานระบบ', $_SERVER['REQUEST_URI'], $StaffID, 4, 8, $id);

    } else if ($action == 'delete' && $id != '') {
        $stmt = $DB->prepare("UPDATE staff SET DelData='Y', DelDataDate=NOW(), DelDataIP=? WHERE StaffID=?");
        $stmt->execute([$_SERVER['REMOTE_ADDR'], $id]);

        $MS->StaffLog('ลบผู้ใช้งานระบบ', $_SERVER['REQUEST_URI'], $StaffID, 4, 8, $id);

    } else if ($action == 'bulk_delete' && isset($_GET['ids'])) {
        $ids = explode(',', $_GET['ids']);
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $DB->prepare("UPDATE staff SET DelData='Y', DelDataDate=NOW(), DelDataIP=? WHERE StaffID IN ($inQuery)");
        $params = [$_SERVER['REMOTE_ADDR']];
        foreach($ids as $i) $params[] = $i;
        $stmt->execute($params);

        $MS->StaffLog('ลบผู้ใช้งานระบบ', $_SERVER['REQUEST_URI'], $StaffID, 4, 8);
        
    } else if ($action == 'bulk_active' && isset($_GET['ids'])) {
        $ids = explode(',', $_GET['ids']);
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $DB->prepare("UPDATE staff SET Status='Y', UpdateDate=NOW(), UpdateIP=? WHERE StaffID IN ($inQuery)");
        $params = [$_SERVER['REMOTE_ADDR']];
        foreach($ids as $i) $params[] = $i;
        $stmt->execute($params);

        $MS->StaffLog('เปลี่ยนสถานะผู้ใช้งานระบบ', $_SERVER['REQUEST_URI'], $StaffID, 4, 8);

    } else if ($action == 'bulk_inactive' && isset($_GET['ids'])) {
        $ids = explode(',', $_GET['ids']);
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $DB->prepare("UPDATE staff SET Status='N', UpdateDate=NOW(), UpdateIP=? WHERE StaffID IN ($inQuery)");
        $params = [$_SERVER['REMOTE_ADDR']];
        foreach($ids as $i) $params[] = $i;
        $stmt->execute($params);

        $MS->StaffLog('เปลี่ยนสถานะผู้ใช้งานระบบ', $_SERVER['REQUEST_URI'], $StaffID, 4, 8);
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

mosRedirect('index.php');
?>
