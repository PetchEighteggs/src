<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการข้อมูลทำเนียบผู้บริหาร';
include_once('../includes/header.php');

$id = $_GET['id'] ?? null;
$personData = [];
$terms = [];

if ($id) {
    try {
        if (isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM org_past_executives WHERE PastExecID = ? AND DelData = 'N' LIMIT 1");
            $stmt->execute([$id]);
            $personData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($personData) {
                $stmt2 = $DB->prepare("SELECT TermYear FROM org_past_executive_terms WHERE PastExecID = ? ORDER BY OrderNo ASC, TermID ASC");
                $stmt2->execute([$id]);
                $terms = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {}
    if (!$personData) { header("Location: index.php"); exit; }
}

?>
<div class="glass-panel p-6">
    <form action="action.php" method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <h2 class="text-xl font-semibold text-gray-800 flex-1">
                <?php echo $id ? 'แก้ไขข้อมูลทำเนียบผู้บริหาร' : 'เพิ่มข้อมูลทำเนียบผู้บริหาร'; ?>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo (!isset($personData['IsActive']) || $personData['IsActive'] === 'Y') ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>

        <input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
        <?php if ($id): ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($personData['Image'] ?? ''); ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2 space-y-6">
                <!-- ข้อมูลส่วนตัว -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-user"></i> ข้อมูลบุคคล</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                          <label class="block text-gray-700 font-medium mb-2">ชื่อ-นามสกุล (<?php echo $Lang1; ?>) <span class="text-red-500">*</span></label>
                          <input type="text" name="NameTH" value="<?php echo htmlspecialchars($personData['NameTH'] ?? ''); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                          <label class="block text-gray-700 font-medium mb-2">ชื่อ-นามสกุล (<?php echo $Lang2; ?>)</label>
                          <input type="text" name="NameEN" value="<?php echo htmlspecialchars($personData['NameEN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                </div>

                <!-- ตำแหน่งและวาระ -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-briefcase"></i> ตำแหน่ง</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">ตำแหน่ง (ภาษาไทย) <span class="text-red-500">*</span></label>
                            <input type="text" name="PositionTH" value="<?php echo htmlspecialchars($personData['PositionTH'] ?? ''); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">ตำแหน่ง (ภาษาอังกฤษ)</label>
                            <input type="text" name="PositionEN" value="<?php echo htmlspecialchars($personData['PositionEN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                    

                </div>

                <!-- วาระการดำรงตำแหน่ง -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-700 flex items-center gap-2"><i class="ph ph-calendar-blank"></i> วาระการดำรงตำแหน่ง</h3>
                        <button type="button" onclick="addTerm()" class="hidden text-sm bg-primary/10 text-primary hover:bg-primary/20 px-3 py-1.5 rounded-lg items-center gap-1 transition-colors">
                            <i class="ph ph-plus font-bold"></i> เพิ่ม
                        </button>
                    </div>
                    
                    <div id="termsContainer" class="space-y-3">
                        <?php 
                        if (empty($terms)) {
                            $terms = [['TermYear' => '']]; // Default 1 row
                        }
                        foreach ($terms as $index => $term): 
                        ?>
                        <div class="term-row flex items-start gap-3 bg-white p-3 rounded-lg border border-gray-200 shadow-sm">
                            <div class="flex-1">
                                <label class="block text-gray-700 font-medium mb-2">ปีวาระ (เช่น 2560 - 2564) <span class="text-red-500">*</span></label>
                                <input type="text" name="Terms[<?php echo $index; ?>][TermYear]" value="<?php echo htmlspecialchars($term['TermYear']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent term-year">
                            </div>
                            <button type="button" onclick="removeTerm(this)" class="hidden mt-5 w-8 h-8 flex-shrink-0 items-center justify-center rounded bg-red-50 text-red-500 hover:bg-red-100 transition-colors" title="ลบวาระนี้">
                                <i class="ph ph-trash"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
            
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพผู้บริหาร</h3>
                    <div class="mb-4">
                        <div class="mb-4 text-center">
                            <div id="previewContainer" class="mb-3" style="<?php echo !empty($personData['Image']) ? '' : 'display: none;'; ?>">
                                <div class="max-w-[250px] aspect-[250/287] mx-auto rounded-lg overflow-hidden border border-gray-200 bg-gray-50 cursor-pointer relative group">
                                    <img id="imagePreview" src="<?php echo !empty($personData['Image']) ? $ImgPath . 'past_executives/' . htmlspecialchars($personData['Image']) : ''; ?>" class="w-full h-full object-cover" onclick="openImageModal(this.src)">
                                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                        <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                                    </div>
                                    <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10" onclick="removePreviewImage(event)" title="ลบรูปภาพ">
                                        <i class="ph ph-x font-bold"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="delete_image" id="delete_image_flag" value="0">
                            <input type="file" name="Image" id="ImageInput" accept="image/*" class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="text-xs text-gray-400 mt-1">ขนาดแนะนำ 420x161 พิกเซล (.jpg, .png)</p>
                        </div>
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

<!-- Image Viewer Modal -->
<div id="imageModal" class="fixed inset-0 z-[9999] bg-black/80 hidden flex items-center justify-center backdrop-blur-md" onclick="closeImageModal(event)">
    <button type="button" onclick="document.getElementById('imageModal').classList.add('hidden')" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center hover:scale-105 transition-all shadow-lg border border-white/10 focus:outline-none">
        <i class="ph ph-x text-2xl font-bold"></i>
    </button>
    <img id="modalImage" src="" class="max-w-[85vw] max-h-[85vh] object-contain rounded-lg shadow-2xl">
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let termIndex = <?php echo count($terms); ?>;

function addTerm() {
    const container = document.getElementById('termsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'term-row flex items-start gap-3 bg-white p-3 rounded-lg border border-gray-200 shadow-sm';
    newRow.innerHTML = `
        <div class="flex-1">
            <label class="block text-gray-700 font-medium mb-2">ปีวาระ (เช่น 2560 - 2564) <span class="text-red-500">*</span></label>
            <input type="text" name="Terms[${termIndex}][TermYear]" required class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent term-year">
        </div>
        <button type="button" onclick="removeTerm(this)" class="mt-5 w-8 h-8 flex-shrink-0 flex items-center justify-center rounded bg-red-50 text-red-500 hover:bg-red-100 transition-colors" title="ลบวาระนี้">
            <i class="ph ph-trash"></i>
        </button>
    `;
    container.appendChild(newRow);
    termIndex++;
}

function removeTerm(btn) {
    const rows = document.querySelectorAll('.term-row');
    if (rows.length > 1) {
        btn.closest('.term-row').remove();
    } else {
        Swal.fire({
            icon: 'warning',
            title: 'ไม่สามารถลบได้',
            text: 'ต้องมีวาระการดำรงตำแหน่งอย่างน้อย 1 รายการ',
            confirmButtonColor: '#3085d6'
        });
    }
}

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
