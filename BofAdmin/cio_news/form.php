<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการข่าวสาร CIO';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$id = $_GET['id'] ?? null;
$newsData = [
    'CioID' => '',
    'Title_TH' => '', 'Title_EN' => '',
    'Content_TH' => '', 'Content_EN' => '',
    'CoverImage' => '', 'PublishDate' => date('Y-m-d'), 'IsActive' => 'Y'
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
            $stmt = $DB->prepare("SELECT * FROM cio_news WHERE NewsID = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if($res) $newsData = $res;
        }
    } catch(PDOException $e) {}
}

$publishDateVal = '';
if (!empty($newsData['PublishDate']) && $newsData['PublishDate'] !== '0000-00-00') {
    $publishDateVal = date('d/m/Y', strtotime($newsData['PublishDate']));
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
                <?php echo $id ? 'แก้ไขข้อมูลข่าวสาร CIO' : 'เพิ่มข้อมูลข่าวสาร CIO'; ?>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $newsData['IsActive'] == 'Y' ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>
        <input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
        <?php if($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>
        <?php if($id): ?><input type="hidden" name="old_cover" value="<?php echo htmlspecialchars($newsData['CoverImage']); ?>"><?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <div class="mb-4">
                      <label class="block text-gray-700 font-medium mb-2">หัวข้อ (<?php echo $Lang1; ?>) <span class="text-red-500">*</span></label>
                      <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($newsData['Title_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">หัวข้อ (<?php echo $Lang2; ?>)</label>
                        <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($newsData['Title_EN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">รายละเอียด (<?php echo $Lang1; ?>)</label>
                        <textarea name="Content_TH" id="editor_th" class="ckeditor_mini"><?php echo htmlspecialchars($newsData['Content_TH']); ?></textarea>
                    </div>
                    <div class="mb-4">
                      <label class="block text-gray-700 font-medium mb-2">รายละเอียด (<?php echo $Lang2; ?>)</label>
                      <textarea name="Content_EN" id="editor_en" class="ckeditor_mini"><?php echo htmlspecialchars($newsData['Content_EN']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">

                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">ผู้บริหาร CIO</label>
                        <select name="CioID" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                            <option value="">เลือก</option>
                            <?php foreach ($executives as $exec): ?>
                                <option value="<?php echo $exec['CioID']; ?>" <?php echo $newsData['CioID'] == $exec['CioID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exec['Prefix_TH'] . ' ' . $exec['FirstName_TH'] . ' ' . $exec['LastName_TH'] . ' (' . $exec['Position_TH'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-2">วันที่ประกาศ <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="text" id="datepicker" name="PublishDate" value="<?php echo htmlspecialchars($publishDateVal); ?>" required class="w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <i class="ph ph-calendar text-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพปก</h3>
                    <div class="mb-3" id="previewContainer" style="<?php echo $newsData['CoverImage'] ? '' : 'display: none;'; ?>">
                        <div class="w-full h-48 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 cursor-pointer relative group">
                            <?php 
                            $safeCoverPath = '';
                            if (!empty($newsData['CoverImage'])) {
                                if (strpos($newsData['CoverImage'], '/') === false) {
                                    $safeCoverPath = 'cio_news/' . $newsData['CoverImage'];
                                } else {
                                    $safeCoverPath = str_replace('uploads/images/', '', $newsData['CoverImage']);
                                    $safeCoverPath = ltrim($safeCoverPath, '/');
                                }
                            }
                            ?>
                            <img id="imagePreview" src="<?php echo !empty($safeCoverPath) ? $ImgPath . htmlspecialchars($safeCoverPath) : ''; ?>" alt="Preview" class="w-full h-full object-cover" onclick="openImageModal(this.src)">
                            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                            </div>
                            <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10" onclick="removePreviewImage(event)">
                                <i class="ph ph-x font-bold"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="deleteCoverImageFlag" id="deleteCoverImageFlag" value="0">
                    <div class="mt-2 text-center">
                        <input type="file" name="CoverImage" id="CoverImageInput" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">ขนาดที่แนะนำ: <?php echo CONF_IMG_REC_W; ?>x<?php echo CONF_IMG_REC_H; ?> px รองรับ JPG, PNG</p>
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

<script>
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
    document.getElementById('CoverImageInput').value = '';
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('imagePreview').src = '';
    document.getElementById('deleteCoverImageFlag').value = '1';
}

document.getElementById('CoverImageInput').addEventListener('change', function(e) {
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
            document.getElementById('previewContainer').style.display = 'block';
            document.getElementById('deleteCoverImageFlag').value = '0';
        }
        reader.readAsDataURL(file);
    }
});
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
flatpickr("#datepicker", {
    dateFormat: "d/m/Y",
    locale: "th",
    allowInput: true
});
</script>

<?php include_once('../assets/modules/ckeditor.php'); ?>
<?php include_once('../includes/footer.php'); ?>
