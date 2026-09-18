<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'ข้อมูลสถิติ CSR';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$statId = max(0, (int)mosGetParam($_GET, 'StatID', 0));
$stat = [
    'StatID' => 0, 'Title_TH' => '', 'Title_EN' => '',
    'ValueText' => '', 'SubValueText' => '', 'Unit_TH' => '', 'Unit_EN' => '',
    'IsActive' => 'Y',
];

if ($statId > 0) {
    $stmt = $DB->prepare("SELECT * FROM csr_stats WHERE StatID=? AND DelData='N' LIMIT 1");
    $stmt->execute([$statId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        header('Location: index.php?result=error&message=' . urlencode('ไม่พบข้อมูลที่ต้องการแก้ไข'));
        exit;
    }
    $stat = array_merge($stat, $record);
}
?>

<div class="glass-panel p-6">
    <form action="action.php" method="post" id="statForm" onsubmit="showLoading()">
        <?php echo bofSecurityCsrfField(); ?>
        <input type="hidden" name="action" value="<?php echo $statId > 0 ? 'update' : 'create'; ?>">
        <input type="hidden" name="StatID" value="<?php echo $statId; ?>">

        <div class="flex flex-col md:flex-row md:items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200"><i class="ph ph-arrow-left text-xl"></i></a>
            <div class="flex-1"><h2 class="text-xl font-bold text-gray-800"><?php echo $statId > 0 ? 'แก้ไขข้อมูลสถิติ' : 'เพิ่มข้อมูลสถิติ'; ?></h2></div>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $stat['IsActive'] === 'Y' ? 'checked' : ''; ?>>
                    <span class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></span>
                </label>
            </div>
        </div>

        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">หัวข้อสถิติ (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                    <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($stat['Title_TH']); ?>" required maxlength="255" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
                </div>
                <div class="flex flex-col">
                  <label class="block text-sm font-semibold text-gray-700 mb-2">หัวข้อสถิติ (<?php echo $Lang2;?>)</label>
                  <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($stat['Title_EN']); ?>" maxlength="255" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">ค่าหลัก <span class="text-red-500">*</span></label>
                    <input type="text" name="ValueText" value="<?php echo htmlspecialchars($stat['ValueText']); ?>" required maxlength="50" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">ค่ารอง <span class="text-gray-400 font-normal ml-2">(แสดงตัวอักษรขนาดเล็กกว่าด้านล่างค่าหลัก)</span></label>
                    <input type="text" name="SubValueText" value="<?php echo htmlspecialchars($stat['SubValueText']); ?>" maxlength="50" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">หน่วย (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                    <input type="text" name="Unit_TH" value="<?php echo htmlspecialchars($stat['Unit_TH']); ?>" required maxlength="100" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">หน่วย (<?php echo $Lang2;?>)</label>
                    <input type="text" name="Unit_EN" value="<?php echo htmlspecialchars($stat['Unit_EN']); ?>" maxlength="100" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 mt-8 pt-6 flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 py-3 text-lg"><i class="ph ph-floppy-disk mr-2"></i> บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<?php include_once('../includes/footer.php'); ?>
