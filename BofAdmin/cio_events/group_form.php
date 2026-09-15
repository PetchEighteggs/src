<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการกลุ่มกิจกรรม CIO';
include_once('../includes/header.php');

$id = $_GET['id'] ?? null;
$categoryData = [
    'GroupName_TH' => '', 'GroupName_EN' => '',
    'OrderNo' => '1', 'IsActive' => 'Y'
];

if ($id) {
    try {
        if(isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM cio_event_groups WHERE GroupID = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if($res) $categoryData = $res;
        }
    } catch(PDOException $e) {}
}
?>

<div class="glass-panel p-6">
    <form action="group_action.php" method="POST" onsubmit="showLoading()">
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="group.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <h2 class="text-xl font-semibold text-gray-800 flex-1">
                <?php echo $id ? 'แก้ไขกลุ่มกิจกรรม' : 'เพิ่มกลุ่มกิจกรรม'; ?>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $categoryData['IsActive'] == 'Y' ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>
        <input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
        <?php if($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-6">
                <!-- ข้อมูลภาษาไทย -->
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="ph ph-translate text-primary"></i> ข้อมูลภาษาไทย</h3>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">ชื่อกลุ่ม (TH) <span class="text-red-500">*</span></label>
                        <input type="text" name="GroupName_TH" value="<?php echo htmlspecialchars($categoryData['GroupName_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <!-- ข้อมูลภาษาอังกฤษ -->
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="ph ph-translate text-blue-500"></i> ข้อมูลภาษาอังกฤษ (Optional)</h3>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">ชื่อกลุ่ม (EN)</label>
                        <input type="text" name="GroupName_EN" value="<?php echo htmlspecialchars($categoryData['GroupName_EN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>
            
            <div class="space-y-6">
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">ลำดับการแสดงผล</label>
                        <input type="number" name="OrderNo" value="<?php echo htmlspecialchars($categoryData['OrderNo']); ?>" min="1" class="w-full md:w-1/2 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-3 pt-6 border-t border-gray-100">
            <a href="group.php" class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                ยกเลิก
            </a>
            <button type="submit" class="btn-primary px-8 py-2.5 flex items-center gap-2">
                <i class="ph ph-floppy-disk text-lg"></i>
                บันทึกข้อมูล
            </button>
        </div>
    </form>
</div>

<script>
function showLoading() {
    Swal.fire({
        title: 'กำลังบันทึกข้อมูล...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
}
</script>

<?php include_once('../includes/footer.php'); ?>
