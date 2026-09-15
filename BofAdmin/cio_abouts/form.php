<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการข้อมูลเกี่ยวกับ CIO';
include_once('../includes/header.php');

$id = $_GET['id'] ?? null;
$aboutData = [
    'CioID' => '',
    'Title_TH' => '', 'Title_EN' => '',
    'Content_TH' => '', 'Content_EN' => '',
    'OrderNo' => '1', 'IsActive' => 'Y'
];

// Fetch CIO Executives for Dropdown
$executives = [];
try {
    if (isset($DB)) {
        $stmtExec = $DB->query("SELECT CioID, Prefix_TH, FirstName_TH, LastName_TH, Position_TH FROM cio_executives WHERE DelData = 'N' AND IsActive = 'Y' ORDER BY OrderNo ASC, CioID DESC");
        $executives = $stmtExec->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

if ($id) {
    try {
        if(isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM cio_abouts WHERE AboutID = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if($res) $aboutData = $res;
        }
    } catch(PDOException $e) {}
}
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" onsubmit="showLoading()">
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <h2 class="text-xl font-semibold text-gray-800 flex-1">
                <?php echo $id ? 'แก้ไขข้อมูลเกี่ยวกับ CIO' : 'เพิ่มข้อมูลเกี่ยวกับ CIO'; ?>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $aboutData['IsActive'] == 'Y' ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>
        <input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
        <?php if($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
                <!-- ข้อมูลภาษาไทย -->
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="ph ph-translate text-primary"></i> ข้อมูลภาษาไทย</h3>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">หัวข้อ (TH) <span class="text-red-500">*</span></label>
                        <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($aboutData['Title_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">รายละเอียด (TH)</label>
                        <textarea name="Content_TH" id="editor_th" class="ckeditor_br"><?php echo htmlspecialchars($aboutData['Content_TH']); ?></textarea>
                    </div>
                </div>

                <!-- ข้อมูลภาษาอังกฤษ -->
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="ph ph-translate text-blue-500"></i> ข้อมูลภาษาอังกฤษ (Optional)</h3>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">หัวข้อ (EN)</label>
                        <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($aboutData['Title_EN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">รายละเอียด (EN)</label>
                        <textarea name="Content_EN" id="editor_en" class="ckeditor_br"><?php echo htmlspecialchars($aboutData['Content_EN']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-user"></i> เลือกผู้บริหาร</h3>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">ผู้บริหาร CIO <span class="text-red-500">*</span></label>
                        <select name="CioID" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                            <option value="">-- เลือกผู้บริหาร --</option>
                            <?php foreach ($executives as $exec): ?>
                                <option value="<?php echo $exec['CioID']; ?>" <?php echo $aboutData['CioID'] == $exec['CioID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exec['Prefix_TH'] . ' ' . $exec['FirstName_TH'] . ' ' . $exec['LastName_TH'] . ' (' . $exec['Position_TH'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(empty($executives)): ?>
                            <p class="text-sm text-red-500 mt-2">ไม่พบรายชื่อผู้บริหาร กรุณาเพิ่มข้อมูลผู้บริหารก่อน</p>
                        <?php endif; ?>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">ลำดับการแสดงผล</label>
                        <input type="number" name="OrderNo" value="<?php echo htmlspecialchars($aboutData['OrderNo']); ?>" min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-3 pt-6 border-t border-gray-100">
            <a href="index.php" class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium transition-colors">
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
