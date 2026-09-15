<?php
/**
 * microsite_permission.php
 * Helper function สำหรับเช็คสิทธิ์การใช้งานเมนู Microsite
 * อ่านข้อมูลจาก staff_microsite_menu_permission + microsite_menu_items
 */

/**
 * ตรวจสอบสิทธิ์ของ Staff สำหรับเมนู Microsite
 *
 * @param PDO    $DB        Database connection
 * @param int    $staffID   Staff ID
 * @param int    $deptID    Department ID
 * @param string $menuKey   MenuKey เช่น 'news', 'banner', 'settings'
 * @param string $permType  ประเภทสิทธิ์: CanView | CanAdd | CanEdit | CanDelete | CanApprove
 * @return bool
 */
function checkMicrositeMenuPermission(PDO $DB, int $staffID, int $deptID, string $menuKey, string $permType = 'CanEdit'): bool
{
    // Admin มีสิทธิ์ทุกอย่างเสมอ
    if (($_SESSION['GroupID'] ?? '') === 'A') {
        return true;
    }

    // Whitelist permType ป้องกัน SQL injection
    $allowedPerms = ['CanView', 'CanAdd', 'CanEdit', 'CanDelete', 'CanApprove'];
    if (!in_array($permType, $allowedPerms, true)) {
        return false;
    }

    try {
        $stmt = $DB->prepare("
            SELECT smp.`{$permType}`
            FROM staff_microsite_menu_permission smp
            JOIN microsite_menu_items mi ON smp.MenuID = mi.MenuID
            WHERE smp.StaffID = ?
              AND smp.DeptID  = ?
              AND mi.MenuKey  = ?
            LIMIT 1
        ");
        $stmt->execute([$staffID, $deptID, $menuKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && $row[$permType] === 'Y';
    } catch (PDOException $e) {
        return false;
    }
}
