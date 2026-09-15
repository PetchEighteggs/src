<?php
define('_VALID_ACCESS', 1);
$serviceTypes = [
    'infographic' => 'สื่อประชาสัมพันธ์',
    'situation' => 'ข้อมูลสถานการณ์น้ำบาดาล',
    'reports' => 'รายงานเกี่ยวกับน้ำบาดาล',
    'mou' => 'บันทึกความเข้าใจ',
    'laws' => 'กฎหมายน้ำบาดาล',
    'downloads' => 'สื่อดาวน์โหลด',
    'standards' => 'มาตรฐานและราคากลางการเจาะน้ำบาดาล',
    'manuals' => 'คู่มือสำหรับประชาชน'
];
$serviceType = $_GET['type'] ?? 'situation';
$serviceName = $serviceTypes[$serviceType] ?? 'จัดการข้อมูลบริการ';
$pageTitle = isset($_GET['id']) ? 'แก้ไขหมวดหมู่: ' . $serviceName : 'เพิ่มหมวดหมู่: ' . $serviceName;
include_once('../includes/header.php');




$id = $_GET['id'] ?? null;
$data = ['GroupNameTH' => '', 'GroupNameEN' => '', 'IsActive' => 'Y', 'OrderNo' => 1];

if ($id) {
    try {
        if(isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM service_doc_groups WHERE GroupID = ?");
            $stmt->execute([$id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if($res) $data = $res;
        }
    } catch(PDOException $e) {}
}
?>

<div class="glass-panel p-6">
    <form action="group_action.php" method="POST" onsubmit="showLoading()">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id ?? ''); ?>">
        <input type="hidden" name="OrderNo" value="<?php echo htmlspecialchars($data['OrderNo'] ?? '1'); ?>">
        <input type="hidden" name="ServiceType" value="<?php echo htmlspecialchars($serviceType); ?>">

        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="group.php?type=<?php echo urlencode($serviceType); ?>" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors" title="กลับไปหน้ารวม">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
                <?php echo $id ? 'แก้ไขหมวดหมู่: ' . $serviceName : 'เพิ่มหมวดหมู่: ' . $serviceName; ?>
            </h2>
            
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
                    <label class="block text-gray-700 font-medium mb-2">ชื่อหมวดหมู่ (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                    <input type="text" name="GroupNameTH" value="<?php echo htmlspecialchars($data['GroupNameTH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-2">ชื่อหมวดหมู่ (<?php echo $Lang2;?>)</label>
                    <input type="text" name="GroupNameEN" value="<?php echo htmlspecialchars($data['GroupNameEN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                </div>
            </div>

            <div class="flex gap-4 pt-4 border-t justify-end">
                <a href="group.php?type=<?php echo urlencode($serviceType); ?>" class="px-6 py-3 bg-gray-100 text-gray-650 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
                <button type="submit" class="btn-primary px-8 py-3 text-lg flex items-center justify-center gap-2">
                    <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
                </button>
            </div>
        </div>
    </form>
</div>
<?php include_once('../includes/footer.php'); ?>
