<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการคำถามที่พบบ่อย';
include_once('../includes/header.php');

$id = $_GET['id'] ?? 0;
$action = $id > 0 ? 'edit' : 'add';

$hasAccess = (!empty($id)) ? ($userPermissions['edit'] ?? true) : ($userPermissions['add'] ?? true);

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = 'index.php';</script>";
    exit;
}

$data = [
	'DocID' => 0,
	'GroupID' => 0,
	'SubGroupID' => 0,
	'TitleTH' => '',
	'TitleEN' => '',
	'File' => '',
	'IsActive' => 'Y',
	'OrderNo' => '0'
];
if ($id > 0) {
    try {
			if (isset($DB)) {
					$stmt = $DB->prepare("SELECT * FROM faq WHERE DocID = ?");
					$stmt->execute([$id]);
					$fetched = $stmt->fetch(PDO::FETCH_ASSOC);
					if ($fetched) {
							$data = $fetched;
					} else {
							echo "<script>alert('ไม่พบข้อมูล'); window.location.href = 'index.php';</script>";
							exit;
					}
			}
    } catch (PDOException $e) {}
}
// Fetch Groups
$groups = [];
try {
    if(isset($DB)) {
        $stmtGroups = $DB->prepare("SELECT GroupID, GroupNameTH FROM faq_groups WHERE (DelData != 'Y' OR DelData IS NULL) ORDER BY OrderNo ASC");
        $stmtGroups->execute();
        $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    echo "<!-- DB ERROR: " . htmlspecialchars($e->getMessage()) . " -->";
}
?>
<form action="action.php" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="showLoading()">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="DocID" value="<?php echo $data['DocID']; ?>">
    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
									<h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
										<?php echo $action == 'add' ? 'เพิ่มคำถามที่พบบ่อย' : 'แก้ไขคำถามที่พบบ่อย'; ?>
									</h2>
                </div>
            </div>
            
            <!-- Status Toggle -->
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-650">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo ($data['IsActive'] == 'Y') ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- RIGHT SIDEBAR -->
            <div class="order-2 lg:col-span-1 space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <label class="block text-gray-700 font-medium mb-2">หมวดหมู่ <span class="text-red-500">*</span></label>
                    <select name="GroupID" id="GroupID" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-white">
                        <option value="0">-- เลือกหมวดหมู่ --</option>
                        <?php foreach($groups as $g): ?>
                        <option value="<?php echo $g['GroupID']; ?>" <?php echo ($data['GroupID'] ?? 0) == $g['GroupID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['GroupNameTH']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- MAIN COLUMN -->
            <div class="order-1 lg:col-span-2 space-y-5">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <div class="space-y-4">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">ชื่อคำถาม (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                                <input type="text" name="TitleTH" value="<?php echo htmlspecialchars($data['TitleTH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">ชื่อคำถาม (<?php echo $Lang2;?>)</label>
                                <input type="text" name="TitleEN" value="<?php echo htmlspecialchars($data['TitleEN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">คำตอบ (<?php echo $Lang1;?>)</label>
                            <textarea name="DetailTH" rows="4" class="ckeditor_mini w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($data['DetailTH'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">คำตอบ (<?php echo $Lang2;?>)</label>
                            <textarea name="DetailEN" rows="4" class="ckeditor_mini w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($data['DetailEN'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="border-t border-gray-100 pt-6 mt-8 flex justify-end gap-3">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-650 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 h-12 text-base font-semibold shadow-lg shadow-primary/30 flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </form>
</div>

<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Datepicker Initialization
    if (typeof flatpickr !== 'undefined') {
        flatpickr(".datepicker", {
            dateFormat: "d/m/Y",
            locale: "th",
            allowInput: true
        });
    }
});
</script>

<?php include_once('../assets/modules/ckeditor.php'); ?>
<?php include_once('../includes/footer.php'); ?>