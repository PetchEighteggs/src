<?php
$pageTitle = "จัดการหมวดหมู่ข่าวสาร";
include_once('../includes/header.php');

$id = mosGetParam($_FORM, 'id', '');
$data = [
    'CategoryName_TH' => '',
    'CategoryName_EN' => '',
    'Slug' => '',
    'OrderNo' => '999',
    'IsActive' => 'Y'
];

if ($id != '') {
    $stmt = $DB->prepare("SELECT * FROM news_category WHERE CategoryID = ? AND DelData = 'N'");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $data = $row;
    }
}
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" onsubmit="showLoading()">
    <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
        <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <h2 class="text-xl font-bold text-gray-800 flex-1">
            <?php echo $id ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่'; ?>
        </h2>
        
        <!-- Status Toggle -->
        <div class="flex items-center gap-3">
            <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="IsActive" value="N">
                <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $data['IsActive'] == 'Y' ? 'checked' : ''; ?>>
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
            </label>
        </div>
    </div>
        <input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
        <?php if($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>

        <div class="w-full space-y-6">
            <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">ชื่อหมวดหมู่ (<?php echo $Lang1; ?>)  <span class="text-red-500">*</span></label>
                    <input type="text" name="CategoryName_TH" value="<?php echo htmlspecialchars($data['CategoryName_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">ชื่อหมวดหมู่ (<?php echo $Lang2; ?>)</label>
                    <input type="text" name="CategoryName_EN" value="<?php echo htmlspecialchars($data['CategoryName_EN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Slug (URL)</label>
                    <input type="text" name="Slug" value="<?php echo htmlspecialchars($data['Slug']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="eg. announcement">
                    <p class="text-xs text-gray-500 mt-1">ใช้สำหรับเป็น URL ของหมวดหมู่ (ภาษาอังกฤษตัวเล็กเท่านั้น) ถ้าไม่กรอกระบบจะดึงจากชื่อหมวดหมู่</p>
                </div>

            </div>

            <div class="flex gap-4 pt-4 border-t justify-end">
                <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
                <button type="submit" class="btn-primary px-8 py-3 text-lg"><i class="ph ph-floppy-disk mr-2"></i> บันทึกข้อมูล</button>
            </div>
        </div>
    </form>
</div>

<?php include_once('../includes/footer.php'); ?>
