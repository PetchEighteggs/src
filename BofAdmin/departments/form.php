<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการหน้าที่ความรับผิดชอบ';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

$deptData = [];
try {
    if (isset($DB)) {
        $stmt = $DB->prepare("SELECT * FROM microsite_departments WHERE DeptID = ? LIMIT 1");
        $stmt->execute([$id]);
        $deptData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

if (!$deptData) { header("Location: index.php"); exit; }
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" onsubmit="showLoading()">
        <?php echo bofSecurityCsrfField(); ?>
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div class="flex-1">
                <h2 class="text-xl font-semibold text-gray-800">
                    แก้ไขหน้าที่ความรับผิดชอบ: <span class="text-primary"><?php echo htmlspecialchars($deptData['ShortNameTH'] ? $deptData['ShortNameTH'] : $deptData['NameTH']); ?></span>
                </h2>
                
            </div>
        </div>

        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-gray-700 font-medium mb-2">ชื่อหน่วยงาน (<?php echo $Lang1;?>)</label>
                <input type="text" name="ShortNameTH" value="<?php echo htmlspecialchars($deptData['ShortNameTH'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <label class="block text-gray-700 font-medium mb-2">ชื่อหน่วยงาน (<?php echo $Lang2;?>)</label>
                <input type="text" name="ShortNameEN" value="<?php echo htmlspecialchars($deptData['ShortNameEN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 font-medium mb-2">รายละเอียดหน้าที่ความรับผิดชอบ (<?php echo $Lang1;?>)</label>
            <textarea name="DescriptionTH" id="editor_th" class="ckeditor_br w-full"><?php echo htmlspecialchars($deptData['DescriptionTH'] ?? ''); ?></textarea>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 font-medium mb-2">รายละเอียดภารกิจ (<?php echo $Lang2;?>)</label>
            <textarea name="DescriptionEN" id="editor_en" class="ckeditor_br w-full"><?php echo htmlspecialchars($deptData['DescriptionEN'] ?? ''); ?></textarea>
        </div>

      

        <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <?php if ($userPermissions['edit']): ?>
            <button type="submit" class="btn-primary px-8 py-3 text-lg"><i class="ph ph-floppy-disk mr-2"></i> บันทึกข้อมูล</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php include_once('../assets/modules/ckeditor.php'); ?>

<?php include_once('../includes/footer.php'); ?>
