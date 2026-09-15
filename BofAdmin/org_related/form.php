<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการหน่วยงานที่เกี่ยวข้อง';
include_once('../includes/header.php');
?>
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<?php
$canAdd    = $userPermissions['add']    ?? true;
$canEdit   = $userPermissions['edit']   ?? true;
$canDelete = $userPermissions['delete'] ?? true;

$id      = $_GET['id'] ?? null;
$data = [
    'RelatedID'  => 0,
    'Title_TH'   => '', 'Title_EN'   => '',
    'Image'      => '', 
    'URL_TH'     => '', 'URL_EN'     => '',
    'Target'     => '_blank',
    'StartDate'  => date('Y-m-d'), 'EndDate' => '',
    'IsActive'   => 'Y',
    'OrderNo'    => 0,
];

if ($id) {
    try {
        if (isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM org_related WHERE RelatedID = ? AND DelData = 'N' LIMIT 1");
            $stmt->execute([$id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) $data = $res;
            else {
                echo "<script>alert('ไม่พบข้อมูล'); window.location.href='index.php';</script>";
                exit;
            }
        }
    } catch (PDOException $e) {}
}
?>

<form action="action.php" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="showLoading()">
    <input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
    <?php if ($id): ?>
    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
    <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($data['Image'] ?? ''); ?>">
    <?php endif; ?>

    <div class="glass-panel p-6">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php" 
                   class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" 
                   title="กลับ">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <?php echo $id ? 'แก้ไข' : 'เพิ่ม'; ?>หน่วยงานที่เกี่ยวข้อง
                    </h2>
                    
                </div>
            </div>

            <!-- Status Toggle -->
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo ($data['IsActive'] ?? 'Y') == 'Y' ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="order-2 lg:col-span-1 space-y-5">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        โลโก้หน่วยงาน
                    </h3>
                    <div class="mb-3" id="currentCoverContainer" style="<?php echo !empty($data['Image']) && $id ? '' : 'display: none;'; ?>">
                        <div class="w-full h-40 rounded-lg overflow-hidden border border-gray-200 bg-white flex justify-center items-center relative group cursor-pointer">
                            <img id="currentCoverImg" src="<?php echo !empty($data['Image']) ? $ImgPath . 'org_related/' . htmlspecialchars($data['Image']) : ''; ?>" class="max-w-full max-h-full object-contain" alt="Logo" onclick="openImageModal(this.src)">
                            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                            </div>
                            <button type="button" 
                                    onclick="removeCoverImage()"
                                    class="absolute top-2 right-2 w-8 h-8 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center transition-transform hover:scale-110 shadow-lg z-10"
                                    title="ลบรูป">
                                <i class="ph ph-x font-bold"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="deleteImageFlag" id="deleteImageFlag" value="0">
                    <div class="mt-2">
                        <input type="file" name="Image" id="coverImageInput" accept="image/*" 
                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                               onchange="previewCoverImage(this)">
                        <p class="text-xs text-gray-400 mt-2 text-center">แนะนำขนาด 100x100 px (JPG, PNG)</p>
                    </div>
                    <div id="newCoverPreview" class="mt-3 hidden">
                        <div class="w-full h-40 rounded-lg overflow-hidden border border-blue-200 bg-white flex justify-center items-center relative group cursor-pointer">
                            <img id="newCoverImg" src="" class="max-w-full max-h-full object-contain" alt="Preview" onclick="openImageModal(this.src)">
                            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                            </div>
                            <button type="button" onclick="clearCoverPreview()" class="absolute top-2 right-2 w-8 h-8 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center transition-transform hover:scale-110 shadow-lg z-10" title="ยกเลิกรูปนี้">
                                <i class="ph ph-x font-bold"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- วันที่เริ่ม-สิ้นสุด -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <?php
                    $startDateVal = '';
                    if (!empty($data['StartDate']) && $data['StartDate'] !== '0000-00-00 00:00:00' && $data['StartDate'] !== '0000-00-00') {
                        $startDateVal = date('d/m/Y', strtotime($data['StartDate']));
                    }
                    $endDateVal = '';
                    if (!empty($data['EndDate']) && $data['EndDate'] !== '0000-00-00 00:00:00' && $data['EndDate'] !== '0000-00-00') {
                        $endDateVal = date('d/m/Y', strtotime($data['EndDate']));
                    }
                    ?>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">วันที่เริ่มแสดงผล</label>
                            <div class="relative">
                                <input type="text" id="start_date" name="StartDate" value="<?php echo htmlspecialchars($startDateVal); ?>" class="w-full border border-gray-300 rounded-lg pl-3 pr-10 py-2 focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                    <i class="ph ph-calendar text-lg"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">วันที่สิ้นสุดแสดงผล</label>
                            <div class="relative">
                                <input type="text" id="end_date" name="EndDate" value="<?php echo htmlspecialchars($endDateVal); ?>" class="w-full border border-gray-300 rounded-lg pl-3 pr-10 py-2 focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                    <i class="ph ph-calendar text-lg"></i>
                                </div>
                            </div>
                        </div>
                        <p class="text-[11px] text-gray-400 leading-tight">หากไม่ระบุวันที่สิ้นสุด ระบบจะแสดงผลตลอดไป</p>
                    </div>
                </div>



            </div>

            <!-- LEFT MAIN CONTENT (Moved to Left via order) -->
            <div class="order-1 lg:col-span-2 space-y-5">
                <div>
                    <label class="block text-gray-700 font-medium mb-2">
                        ชื่อหน่วยงาน (ภาษาไทย) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="Title_TH" required
                           value="<?php echo htmlspecialchars($data['Title_TH'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-2">ชื่อหน่วยงาน (ภาษาอังกฤษ)</label>
                    <input type="text" name="Title_EN"
                           value="<?php echo htmlspecialchars($data['Title_EN'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <hr class="border-gray-100 my-6">

                <div>
                    <label class="block text-gray-700 font-medium mb-2">
                        ลิงก์เว็บไซต์ (URL ภาษาไทย) <span class="text-red-500">*</span>
                    </label>
                    <input type="url" name="URL_TH" required
                           value="<?php echo htmlspecialchars($data['URL_TH'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                           placeholder="https://www.example.com">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-2">ลิงก์เว็บไซต์ (URL ภาษาอังกฤษ)</label>
                    <input type="url" name="URL_EN"
                           value="<?php echo htmlspecialchars($data['URL_EN'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                           placeholder="https://www.example.com/en">
                </div>



            </div>
        </div>

        <!-- Save Button -->
        <div class="border-t border-gray-100 pt-6 mt-8 flex justify-end gap-3">
            <a href="index.php" 
               class="px-6 h-12 border border-gray-300 text-gray-600 bg-white hover:bg-gray-50 rounded-lg font-medium transition-colors flex items-center gap-2">
                <i class="ph ph-x"></i> ยกเลิก
            </a>
            <?php if ($canEdit || (!$id && $canAdd)): ?>
            <button type="submit" class="btn-primary px-8 h-12 text-base font-semibold shadow-lg shadow-primary/30 flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
// Cover Image Preview
function previewCoverImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('newCoverImg').src = e.target.result;
            document.getElementById('newCoverPreview').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function clearCoverPreview() {
    document.getElementById('coverImageInput').value = '';
    document.getElementById('newCoverPreview').classList.add('hidden');
    document.getElementById('newCoverImg').src = '';
}
function removeCoverImage() {
    document.getElementById('deleteImageFlag').value = '1';
    let c = document.getElementById('currentCoverContainer');
    if (c) { c.style.opacity = '0.3'; c.style.pointerEvents = 'none'; }
}

// Fullscreen Image Modal
function openImageModal(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').classList.remove('hidden');
}
function closeImageModal(e) {
    if (e.target.id === 'imageModal') {
        e.target.classList.add('hidden');
    }
}
</script>

<!-- Flatpickr JS & Thai locale -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof flatpickr !== 'undefined') {
        flatpickr("#start_date", {
            dateFormat: "d/m/Y",
            locale: "th",
            allowInput: true
        });
        flatpickr("#end_date", {
            dateFormat: "d/m/Y",
            locale: "th",
            allowInput: true
        });
    }
});
</script>

<!-- Fullscreen Image Modal -->
<div id="imageModal" class="fixed inset-0 z-[100] bg-black/90 hidden flex items-center justify-center p-4 backdrop-blur-sm" onclick="closeImageModal(event)">
    <button type="button" class="absolute top-4 right-4 text-white/50 hover:text-white transition-colors" onclick="document.getElementById('imageModal').classList.add('hidden')">
        <i class="ph ph-x text-3xl"></i>
    </button>
    <img id="modalImage" src="" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl">
</div>

<?php include_once('../includes/footer.php'); ?>
