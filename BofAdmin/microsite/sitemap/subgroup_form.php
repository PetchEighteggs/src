<?php
define('_VALID_ACCESS', 1);
$isEdit = isset($_GET['id']) && !empty($_GET['id']);
$pageTitle = $isEdit ? 'แก้ไขรายการหมวดย่อย' : 'เพิ่มรายการหมวดย่อย';
include_once('../../includes/header.php');
require_once('../../includes/action_security.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>window.location.href = '../index.php';</script>";
    exit;
}

$groupID = intval($_GET['group_id'] ?? 0);

// Fetch all active groups for the select dropdown
$allGroups = [];
try {
    $stmtAllG = $DB->prepare("SELECT GroupID, Title_TH FROM microsite_sitemap_groups WHERE DeptID = ? AND (DelData IS NULL OR DelData = 'N') ORDER BY OrderNo ASC, GroupID ASC");
    $stmtAllG->execute([$deptID]);
    $allGroups = $stmtAllG->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// If editing, try to get group_id from the record if not provided or to ensure it matches
$id   = intval($_GET['id'] ?? 0);
$data = ['GroupID' => $groupID, 'Title_TH' => '', 'Title_EN' => '', 'URL_TH' => '', 'URL_EN' => '', 'IsActive' => 'Y', 'OrderNo' => 0];

if ($id) {
    try {
        $stmt = $DB->prepare("SELECT * FROM microsite_sitemap_subgroups WHERE SubGroupID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$id, $deptID]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $data = $res;
            $groupID = $data['GroupID'];
        }
    } catch (PDOException $e) {}
}

$userGroupID = $_SESSION['GroupID'] ?? '';
$canEdit = false; $canAdd = false;
if ($userGroupID === 'A') {
    $canEdit = $canAdd = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'sitemap', 'CanEdit');
    $canAdd  = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'sitemap', 'CanAdd');
}
if (!$canEdit && !$canAdd) {
    echo "<script>alert('ไม่มีสิทธิ์'); window.location.href = 'index.php';</script>";
    exit;
}


?>

<div class="glass-panel p-6">
    <form action="subgroup_action.php" method="POST" onsubmit="showLoading()">
        <?php echo bofSecurityCsrfField(); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id ?: ''); ?>">

        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="subgroup_index.php?group_id=<?php echo $groupID; ?>" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div>
                <h2 class="text-xl font-bold text-gray-800 flex-1">
                    <?php echo $id ? 'แก้ไขรายการเมนูย่อย' : 'เพิ่มรายการเมนูย่อย'; ?>
                </h2>
            </div>
            
            <div class="flex items-center gap-3 ml-auto">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo ($data['IsActive'] == 'Y') ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>

        <div class="bg-gray-50 p-6 rounded-xl border border-gray-200 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-medium mb-2">เมนูหลัก <span class="text-red-500">*</span></label>
                    <select name="group_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        <option value="">-- เลือกเมนูหลัก --</option>
                        <?php foreach ($allGroups as $g): ?>
                        <option value="<?php echo $g['GroupID']; ?>" <?php echo $groupID == $g['GroupID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['Title_TH']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-2">ชื่อเมนูย่อย(<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                    <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($data['Title_TH'] ?? ''); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-2">ชื่อเมนูย่อย (<?php echo $Lang2;?>)</label>
                    <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($data['Title_EN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-2">URL ลิงก์ (<?php echo $Lang1;?>)</label>
                    <input type="text" name="URL_TH" value="<?php echo htmlspecialchars($data['URL_TH'] ?? ''); ?>" placeholder="https://..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-2">URL ลิงก์ (<?php echo $Lang2;?>)</label>
                    <input type="text" name="URL_EN" value="<?php echo htmlspecialchars($data['URL_EN'] ?? ''); ?>" placeholder="https://..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-6 mt-6 flex justify-end gap-3">
            <a href="subgroup_index.php?group_id=<?php echo $groupID; ?>" class="px-6 h-11 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                <i class="ph ph-x"></i> ยกเลิก
            </a>
            <button type="submit" class="btn-primary px-8 h-11 text-base font-semibold flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </form>
</div>

<?php include_once('../../includes/footer.php'); ?>
