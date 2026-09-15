<?php
define('_VALID_ACCESS', 1);
$pageTitle = ($id = $_GET['id'] ?? null) ? 'แก้ไขนามสงเคราะห์' : 'เพิ่มนามสงเคราะห์';
include_once('../includes/header.php');

$mapData = [
    'NameTH' => '', 'NameEN' => '', 'Image' => '',
    'DeptID' => 0,
    'PositionTH' => '', 'PositionEN' => '',
    'Email' => '', 'Phone' => '', 'Fax' => '',
    'OrderNo' => 0,
    'IsActive' => 'Y'
];
$deptOptions = [];
try {
    if (isset($DB)) {
        $stmtD = $DB->query("SELECT DeptID, NameTH FROM microsite_departments WHERE IsActive='Y' ORDER BY NameTH ASC");
        $deptOptions = $stmtD->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}
if ($id) {
    try {
        if (isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM org_directory WHERE DirID = ? AND DelData = 'N' LIMIT 1");
            $stmt->execute([$id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) $mapData = $res;
        }
    } catch (PDOException $e) {}
}
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
              <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <h2 class="text-xl font-semibold text-gray-800 flex-1">
                <?php echo $id ? 'แก้ไขข้อมูล' : 'เพิ่มข้อมูล'; ?>นามสงเคราะห์
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $mapData['IsActive'] == 'Y' ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>

        <input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
        <?php if ($id): ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Main Fields -->
            <div class="md:col-span-2 space-y-5">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-4">
                    <h3 class="font-semibold text-gray-700 flex items-center gap-2"> ข้อมูลบุคคล</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">ชื่อ-นามสกุล (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                            <input type="text" name="NameTH" value="<?php echo htmlspecialchars($mapData['NameTH']); ?>" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">ชื่อ-นามสกุล (<?php echo $Lang2;?>)</label>
                            <input type="text" name="NameEN" value="<?php echo htmlspecialchars($mapData['NameEN'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                </div>

                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-4">
                    <h3 class="font-semibold text-gray-700 flex items-center gap-2"> ข้อมูลตำแหน่ง</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">ตำแหน่ง (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                            <input type="text" name="PositionTH" value="<?php echo htmlspecialchars($mapData['PositionTH']); ?>" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">ตำแหน่ง (<?php echo $Lang2;?>)</label>
                            <input type="text" name="PositionEN" value="<?php echo htmlspecialchars($mapData['PositionEN'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">เบอร์โทรศัพท์</label>
                            <input type="text" name="Phone" value="<?php echo htmlspecialchars($mapData['Phone'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">เบอร์โทรสาร</label>
                            <input type="text" name="Fax" value="<?php echo htmlspecialchars($mapData['Fax'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">อีเมล</label>
                            <input type="email" name="Email" value="<?php echo htmlspecialchars($mapData['Email'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-buildings"></i> ข้อมูลหน่วยงาน</h3>
                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-2">สังกัดหน่วยงาน</label>
                        <select name="DeptID" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white">
                            <option value="0">-- ไม่ระบุหน่วยงาน --</option>
                            <?php foreach ($deptOptions as $dept): ?>
                            <option value="<?php echo $dept['DeptID']; ?>" <?php echo $mapData['DeptID'] == $dept['DeptID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['NameTH']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพ</h3>
                    <div class="mb-4 text-center">
                        <div id="previewContainer" class="mb-3" style="<?php echo !empty($mapData['Image']) ? '' : 'display: none;'; ?>">
                            <div class="max-w-[200px] aspect-square mx-auto rounded-lg overflow-hidden border border-gray-200 bg-gray-50 cursor-pointer relative group">
                                <?php 
                                $safeImagePath = '';
                                if (!empty($mapData['Image'])) {
                                    $safeImagePath = str_replace('uploads/images/', '', $mapData['Image']);
                                    $safeImagePath = ltrim($safeImagePath, '/');
                                }
                                ?>
                                <img id="imagePreview" src="<?php echo !empty($safeImagePath) ? $ImgPath . htmlspecialchars($safeImagePath) : ''; ?>" class="w-full h-full object-cover" onclick="openImageModal(this.src)">
                                <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                    <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                                </div>
                                <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10" onclick="removePreviewImage(event)" title="ลบรูปภาพ">
                                    <i class="ph ph-x font-bold"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="old_Image" value="<?php echo htmlspecialchars($mapData['Image']); ?>">
                        <input type="hidden" name="delete_image" id="delete_image_flag" value="0">
                        <input type="file" name="Image" id="ImageInput" accept="image/*" class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">ขนาดแนะนำ 400x400 พิกเซล (.jpg, .png)</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <?php if ($userPermissions['add'] || $userPermissions['edit']): ?>
            <button type="submit" class="btn-primary px-8 py-3 text-lg"><i class="ph ph-floppy-disk mr-2"></i> บันทึกข้อมูล</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Image Modal -->
<div id="imageModal" class="fixed inset-0 bg-black/85 flex items-center justify-center z-[9999] hidden backdrop-blur-md" onclick="closeImageModal(event)">
    <button type="button" onclick="document.getElementById('imageModal').classList.add('hidden')" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20 transition-colors">
        <i class="ph ph-x text-2xl"></i>
    </button>
    <div class="max-w-[90vw] max-h-[85vh] p-2">
        <img id="modalImage" src="" alt="Preview" class="max-w-full max-h-[80vh] rounded-xl shadow-2xl object-contain">
    </div>
</div>

<script>
document.getElementById('ImageInput').addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('previewContainer').style.display = 'block';
            document.getElementById('delete_image_flag').value = '0';
        }
        reader.readAsDataURL(this.files[0]);
    }
});

function openImageModal(src) {
    if (src) {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').classList.remove('hidden');
    }
}

function closeImageModal(e) {
    if (e.target.id === 'imageModal') {
        document.getElementById('imageModal').classList.add('hidden');
    }
}

function removePreviewImage(e) {
    e.stopPropagation();
    document.getElementById('ImageInput').value = '';
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('imagePreview').src = '';
    document.getElementById('delete_image_flag').value = '1';
}
</script>

<?php include_once('../includes/footer.php'); ?>