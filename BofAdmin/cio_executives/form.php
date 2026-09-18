<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการผู้บริหารเทคโนโลยีสารสนเทศระดับสูง CIO';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$id = $_GET['id'] ?? null;
$execData = [
    'Prefix_TH' => '', 'Prefix_EN' => '',
    'FirstName_TH' => '', 'FirstName_EN' => '',
    'LastName_TH' => '', 'LastName_EN' => '',
    'Position_TH' => '', 'Position_EN' => '',
    'Detail_TH' => '', 'Detail_EN' => '',
    'ImagePath' => '', 'OrderNo' => '1', 'IsActive' => 'Y'
];

if ($id) {
    try {
			if(isset($DB)) {
				$stmt = $DB->prepare("SELECT * FROM cio_executives WHERE CioID = :id LIMIT 1");
				$stmt->execute([':id' => $id]);
				$res = $stmt->fetch(PDO::FETCH_ASSOC);
				if($res) $execData = $res;
			}
    } catch(PDOException $e) {}
}
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
        <?php echo bofSecurityCsrfField(); ?>
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <h2 class="text-xl font-semibold text-gray-800 flex-1">
                <?php echo $id ? 'แก้ไขข้อมูลผู้บริหาร CIO' : 'เพิ่มข้อมูลผู้บริหาร CIO'; ?>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $execData['IsActive'] == 'Y' ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>
        <input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
        <?php if($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>
        <?php if($id): ?><input type="hidden" name="old_image" value="<?php echo htmlspecialchars($execData['ImagePath']); ?>"><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200 space-y-6">
									  <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">ข้อมูลบุคคล</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">ชื่อ (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                            <input type="text" name="FirstName_TH" value="<?php echo htmlspecialchars($execData['FirstName_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
												<div>
                            <label class="block text-gray-700 font-medium mb-2">นามสกุล (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                            <input type="text" name="LastName_TH" value="<?php echo htmlspecialchars($execData['LastName_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">ชื่อ (<?php echo $Lang2;?>)</label>
                            <input type="text" name="FirstName_EN" value="<?php echo htmlspecialchars($execData['FirstName_EN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">นามสกุล (<?php echo $Lang2;?>)</label>
                            <input type="text" name="LastName_EN" value="<?php echo htmlspecialchars($execData['LastName_EN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">ตำแหน่ง (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                            <input type="text" name="Position_TH" value="<?php echo htmlspecialchars($execData['Position_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">ตำแหน่ง (<?php echo $Lang2;?>)</label>
                            <input type="text" name="Position_EN" value="<?php echo htmlspecialchars($execData['Position_EN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200 space-y-6">
									<h3 class="text-lg font-semibold text-gray-800 border-b pb-2">ข้อมูลเพิ่มเติม</h3>
									<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
										<div>
											<label class="block text-gray-700 font-medium mb-2">เบอร์โทรศัพท์</label>
											<input type="text" name="Telephone" value="<?php echo htmlspecialchars($execData['Telephone'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
										</div>
										<div>
											<label class="block text-gray-700 font-medium mb-2">อีเมล</label>
											<input type="email" name="Email" value="<?php echo htmlspecialchars($execData['Email'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
										</div>
									</div>
									<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
										<div>
											<label class="block text-gray-700 font-medium mb-2">วันที่รับตำแหน่ง</label>
											<div class="relative">
												<input type="text" id="datepicker" name="AppointDate" value="<?php echo htmlspecialchars($execData['AppointDate'] ?? ''); ?>" class="w-full pl-4 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
												<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
													<i class="ph ph-calendar text-lg"></i>
												</div>
											</div>
										</div>
										<div>
											<label class="block text-gray-700 font-medium mb-2">เลขที่คำสั่งแต่งตั้ง</label>
											<input type="text" name="AppointOrderNo" value="<?php echo htmlspecialchars($execData['AppointOrderNo'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
										</div>
									</div>
									<div>
										<label class="block text-gray-700 font-medium mb-2">ที่อยู่</label>
										<textarea name="Address" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($execData['Address'] ?? ''); ?></textarea>
									</div>
									<div class="mt-4">
										<label class="block text-gray-700 font-medium mb-2">รายละเอียดเกี่ยวกับ CIO (<?php echo $Lang1; ?>)</label>
										<textarea name="Detail_TH" id="editor_th" class="ckeditor_br"><?php echo htmlspecialchars($execData['Detail_TH'] ?? ''); ?></textarea>
									</div>
									<div class="mt-4">
										<label class="block text-gray-700 font-medium mb-2">รายละเอียดเกี่ยวกับ CIO (<?php echo $Lang2; ?>)</label>
										<textarea name="Detail_EN" id="editor_en" class="ckeditor_br"><?php echo htmlspecialchars($execData['Detail_EN'] ?? ''); ?></textarea>
									</div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพโปรไฟล์</h3>
                    <div class="mb-3 flex justify-center" id="previewContainer" style="<?php echo $execData['ImagePath'] ? '' : 'display: none;'; ?>">
                        <div class="w-full aspect-[3/2] rounded-lg overflow-hidden border border-gray-200 shadow-sm bg-gray-100 cursor-pointer relative group">
                            <img id="imagePreview" src="<?php echo $execData['ImagePath'] ? $ImgPath.htmlspecialchars(str_replace('uploads/images/', '', $execData['ImagePath'])) : ''; ?>" alt="Preview" class="w-full h-full object-cover" onclick="openImageModal(this.src)">
                            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                            </div>
                            <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10" onclick="removePreviewImage(event)">
                                <i class="ph ph-x font-bold"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="deleteImageFlag" id="deleteImageFlag" value="0">
                    <div class="mt-2 text-center">
                        <input type="file" name="ImagePath" id="ImagePathInput" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">ขนาดที่แนะนำ: 1080 × 720 px รองรับ JPG, PNG</p>
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

<!-- Modal for Image Preview -->
<div id="imageModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-90 flex items-center justify-center p-4" onclick="closeImageModal()">
    <button type="button" class="absolute top-4 right-4 text-white hover:text-gray-300" onclick="closeImageModal()">
        <i class="ph ph-x text-3xl"></i>
    </button>
    <img id="modalImage" src="" alt="Full Preview" class="max-w-full max-h-[90vh] object-contain rounded-lg" onclick="event.stopPropagation()">
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
// Datepicker Initialization
flatpickr("#datepicker", {
    dateFormat: "Y-m-d",     // รูปแบบที่จะ submit ค่าจริงๆ ไปยัง backend (Y-m-d สำหรับ DB DATE field)
    altInput: true,          // ให้แสดงผลอีกรูปแบบใน UI
    altFormat: "d/m/Y",      // รูปแบบที่แสดงบนหน้าจอ (วว/ดด/ปปปป)
    locale: "th",            // แปลภาษาไทยและปฏิทินไทย
    allowInput: true
});

function showLoading() {
    Swal.fire({
        title: 'กำลังบันทึกข้อมูล...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
}

function openImageModal(src) {
    if (!src) return;
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function removePreviewImage(e) {
    e.stopPropagation();
    document.getElementById('ImagePathInput').value = '';
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('imagePreview').src = '';
    document.getElementById('deleteImageFlag').value = '1';
}

document.getElementById('ImagePathInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            Swal.fire('ขนาดไฟล์เกินกำหนด', 'กรุณาอัพโหลดไฟล์ขนาดไม่เกิน 5MB', 'warning');
            this.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('previewContainer').style.display = 'flex';
            document.getElementById('deleteImageFlag').value = '0';
        }
        reader.readAsDataURL(file);
    }
});
</script>

<?php include_once('../assets/modules/ckeditor.php'); ?>
<?php include_once('../includes/footer.php'); ?>
