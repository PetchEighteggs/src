<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการกลุ่มย่อยดาวน์โหลด';
include_once('../../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
$groupID = $_GET['group_id'] ?? 0;
$subgroupID = $_GET['id'] ?? 0;

if (empty($deptID)) {
    echo "<script>alert('กรุณาเลือกหน่วยงานก่อนจัดการข้อมูล'); window.location.href = '../index.php';</script>";
    exit;
}
if (empty($groupID)) {
    echo "<script>alert('กรุณาเลือกหมวดหมู่ก่อนจัดการกลุ่มย่อย'); window.location.href = 'group.php';</script>";
    exit;
}

$userGroupID = $_SESSION['GroupID'] ?? '';
$canAdd = false; $canEdit = false;
if ($userGroupID === 'A') {
    $canAdd = $canEdit = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'download', 'CanEdit');
    $canAdd  = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'download', 'CanAdd');
}

if (($subgroupID && !$canEdit) || (!$subgroupID && !$canAdd)) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = 'subgroup.php?group_id=$groupID';</script>";
    exit;
}

$mode = $subgroupID ? 'edit' : 'add';
$data = [
    'SubGroupNameTH' => '',
    'SubGroupNameEN' => '',
    'OrderNo' => 0,
    'IsActive' => 'Y'
];

if ($mode === 'edit') {
    try {
        $stmt = $DB->prepare("SELECT * FROM microsite_download_subgroups WHERE SubGroupID = ? AND GroupID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$subgroupID, $groupID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $data = $row;
        } else {
            echo "<script>alert('ไม่พบข้อมูลกลุ่มย่อย'); window.location.href = 'subgroup.php?group_id=$groupID';</script>";
            exit;
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    try {
        $stmt = $DB->prepare("SELECT MAX(OrderNo) FROM microsite_download_subgroups WHERE GroupID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$groupID]);
        $max = $stmt->fetchColumn();
        $data['OrderNo'] = $max ? $max + 1 : 1;
    } catch (PDOException $e) {}
}

$stmtGroup = $DB->prepare("SELECT GroupNameTH FROM microsite_download_groups WHERE GroupID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
$stmtGroup->execute([$groupID, $deptID]);
$groupName = $stmtGroup->fetchColumn();
?>

<div class="glass-panel p-6">
    <form id="dataForm" action="subgroup_action.php" method="POST" onsubmit="showLoading()">
        <input type="hidden" name="action" value="<?php echo $mode; ?>">
        <input type="hidden" name="group_id" value="<?php echo $groupID; ?>">
        <input type="hidden" name="OrderNo" value="<?php echo htmlspecialchars($data['OrderNo'] ?? '1'); ?>">
        <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?php echo $subgroupID; ?>">
        <?php endif; ?>

        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="subgroup.php?group_id=<?php echo $groupID; ?>" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors" title="กลับไปหน้ารวม">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div class="flex-1">
                <h2 class="text-xl font-bold text-gray-800">
                    <?php echo $mode === 'add' ? 'เพิ่มกลุ่มย่อยใหม่' : 'แก้ไขกลุ่มย่อย'; ?>
                </h2>
                <div class="text-sm text-gray-500 mt-1">หมวดหมู่: <span class="font-semibold"><?php echo htmlspecialchars($groupName); ?></span></div>
            </div>
            
            <!-- Status Toggle -->
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-655">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo ($data['IsActive'] == 'Y') ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>

        <div class="w-full space-y-6">
            <div class="bg-gray-50 p-6 rounded-xl border border-gray-200 space-y-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-2">ชื่อกลุ่มย่อย (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                    <input type="text" name="SubGroupNameTH" value="<?php echo htmlspecialchars($data['SubGroupNameTH']); ?>" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-2">ชื่อกลุ่มย่อย (<?php echo $Lang2;?>)</label>
                    <input type="text" name="SubGroupNameEN" value="<?php echo htmlspecialchars($data['SubGroupNameEN'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                </div>
            </div>

            <div class="flex gap-4 pt-4 border-t justify-end">
                <a href="subgroup.php?group_id=<?php echo $groupID; ?>" class="px-6 py-3 bg-gray-100 text-gray-650 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
                <button type="submit" class="btn-primary px-8 py-3 text-lg flex items-center justify-center gap-2">
                    <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('dataForm').addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'กรุณากรอกข้อมูลให้ครบถ้วน',
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#3085d6'
        });
    }
});
</script>

<?php include_once('../../includes/footer.php'); ?>
