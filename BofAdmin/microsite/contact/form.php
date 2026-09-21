<?php
define('_VALID_ACCESS', 1);
$isEdit = isset($_GET['id']) && !empty($_GET['id']);
$pageTitle = $isEdit ? 'แก้ไขข้อมูลติดต่อ' : 'เพิ่มข้อมูลติดต่อ';
include_once('../../includes/header.php');
require_once('../../includes/action_security.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>window.location.href = '../index.php';</script>";
    exit;
}

$userGroupID = $_SESSION['GroupID'] ?? '';
$canEdit = false; $canAdd = false;
if ($userGroupID === 'A') {
    $canEdit = $canAdd = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'contact', 'CanEdit');
    $canAdd  = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'contact', 'CanAdd');
}
if (($isEdit && !$canEdit) || (!$isEdit && !$canAdd)) {
    echo "<script>alert('ไม่มีสิทธิ์'); window.location.href = 'index.php';</script>";
    exit;
}

$id   = intval($_GET['id'] ?? 0);
$data = ['Name' => '', 'Title_TH' => '', 'Title_EN' => '', 'Address' => '', 'Tel' => '', 'Fax' => '', 'Email' => '', 'GroupID' => 0, 'IsActive' => 'Y', 'OrderNo' => 0];

if ($id) {
    try {
        $stmt = $DB->prepare("SELECT * FROM microsite_contact WHERE ContactID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$id, $deptID]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) $data = $res;
    } catch (PDOException $e) {}
}

// Load groups
$groups = [];
try {
    $stmtG = $DB->prepare("SELECT GroupID, GroupNameTH FROM microsite_contact_groups WHERE DeptID = ? AND COALESCE(DelData, 'N') <> 'Y' ORDER BY OrderNo ASC, GroupID ASC");
    $stmtG->execute([$deptID]);
    $groups = $stmtG->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" onsubmit="showLoading()">
        <?php echo bofSecurityCsrfField(); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id ?: ''); ?>">

        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <h2 class="text-xl font-bold text-gray-800 flex-1">
                <?php echo $id ? 'แก้ไขข้อมูลติดต่อ' : 'เพิ่มข้อมูลติดต่อ'; ?>
            </h2>
            <!-- Status Toggle -->
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo ($data['IsActive'] == 'Y') ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อ-สกุล / ชื่อหน่วยงาน <span class="text-red-500">*</span></label>
                <input type="text" name="Name" value="<?php echo htmlspecialchars($data['Name']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อตำแหน่ง (<?php echo $Lang1; ?>)</label>
                <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($data['Title_TH'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อตำแหน่ง (<?php echo $Lang2; ?>)</label>
                <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($data['Title_EN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">หมวดหมู่</label>
                <select name="GroupID" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                    <option value="0">-- ไม่ระบุหมวดหมู่ --</option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?php echo $g['GroupID']; ?>" <?php echo ($data['GroupID'] == $g['GroupID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g['GroupNameTH']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">ลำดับแสดงผล</label>
                <input type="number" name="OrderNo" value="<?php echo intval($data['OrderNo'] ?? 0); ?>" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                <input type="text" name="Tel" value="<?php echo htmlspecialchars($data['Tel'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">เบอร์แฟกซ์</label>
                <input type="text" name="Fax" value="<?php echo htmlspecialchars($data['Fax'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">อีเมล</label>
                <input type="email" name="Email" value="<?php echo htmlspecialchars($data['Email'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">ที่อยู่</label>
                <textarea name="Address" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none"><?php echo htmlspecialchars($data['Address'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-6 mt-6 flex justify-end gap-3">
            <a href="index.php" class="px-6 h-11 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                <i class="ph ph-x"></i> ยกเลิก
            </a>
            <button type="submit" class="btn-primary px-8 h-11 text-base font-semibold flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </form>
</div>

<?php include_once('../../includes/footer.php'); ?>
