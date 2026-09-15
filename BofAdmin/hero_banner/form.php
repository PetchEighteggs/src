<?php
define('_VALID_ACCESS', 1);
$pageTitle = "จัดการแบนเนอร์หน้าแรก";
include_once('../includes/header.php');
?>
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<?php

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$banner = [];

try {
    if (isset($DB)) {
        // Auto-check and add PublishStart & PublishEnd columns
        $checkCols = $DB->query("SHOW COLUMNS FROM `hero_banners` LIKE 'PublishStart'")->fetch();
        if (!$checkCols) {
            $DB->exec("ALTER TABLE `hero_banners` ADD COLUMN `PublishStart` DATE DEFAULT NULL");
        }
        $checkColsEnd = $DB->query("SHOW COLUMNS FROM `hero_banners` LIKE 'PublishEnd'")->fetch();
        if (!$checkColsEnd) {
            $DB->exec("ALTER TABLE `hero_banners` ADD COLUMN `PublishEnd` DATE DEFAULT NULL");
        }
    }
} catch (PDOException $e) {}

try {
    if (isset($DB) && $id > 0) {
        $stmt = $DB->prepare("SELECT * FROM hero_banners WHERE BannerID = ? AND DelData = 'N'");
        $stmt->execute([$id]);
        $banner = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$banner) {
            echo "<script>Swal.fire('ข้อผิดพลาด', 'ไม่พบข้อมูลแบนเนอร์', 'error').then(() => window.location.href='index.php');</script>";
            exit;
        }
    }
} catch (PDOException $e) {}

// Fallback defaults for new record
if (!$banner) {
    $banner = [
			'BannerID' => 0,
			'Title_TH' => '',
			'Title_EN' => '',
			'Description_TH' => '',
			'Description_EN' => '',
			'Image' => '',
			'CTAText_TH' => 'รายละเอียด',
			'CTAText_EN' => 'Details',
			'CTALink_TH' => '',
			'CTALink_EN' => '',
			'Template' => 'three-grid',
			'IsActive' => 'Y',
			'OrderNo' => 1,
			'PublishStart' => '',
			'PublishEnd' => ''
    ];
}
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" enctype="multipart/form-data" class="w-full" onsubmit="showLoading()">
        <input type="hidden" name="action" value="<?php echo $id > 0 ? 'update' : 'create'; ?>">
        <input type="hidden" name="BannerID" value="<?php echo $id; ?>">

        <!-- Top Action Bar -->
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors" title="ย้อนกลับ">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <h2 class="text-xl font-semibold text-gray-800 flex-1">
                <?php echo $id > 0 ? 'แก้ไขข้อมูลแบนเนอร์' : 'เพิ่มแบนเนอร์'; ?>
            </h2>
            
            <!-- Status Toggle -->
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $banner['IsActive'] === 'Y' ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>

        <!-- Main Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Center Column: Master Banner Settings -->
            <div id="left-column-panel" class="lg:col-span-12 space-y-6">
                
                
                <div class="space-y-4">

                    <!-- Grid container for master fields -->
                    <div id="master-fields-grid" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                        
                        <!-- Left Area: Text and Date Inputs -->
                        <div id="master-text-inputs" class="lg:col-span-7 space-y-4 bg-gray-50 p-4 rounded-xl border border-gray-200 h-fit">
                            <!-- Titles -->
                            <div id="titles-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 font-medium mb-2">หัวข้อ (<?php echo $Lang1; ?>) <span class="text-red-500">*</span></label>
                                    <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($banner['Title_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-medium mb-2">หัวข้อ (<?php echo $Lang2; ?>)</label>
                                    <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($banner['Title_EN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                            </div>

                            <!-- Descriptions -->
                            <div id="descriptions-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 font-medium mb-2">คำอธิบาย (<?php echo $Lang1; ?>)</label>
                                    <textarea name="Description_TH" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($banner['Description_TH'] ?? ''); ?></textarea>
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-medium mb-2">คำอธิบาย (<?php echo $Lang2; ?>)</label>
                                    <textarea name="Description_EN" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($banner['Description_EN'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- CTA Text & Link -->
                            <div class="border-t border-gray-150 pt-4 mt-2">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">คำบนปุ่ม (<?php echo $Lang1; ?>)</label>
                                        <input type="text" name="CTAText_TH" value="<?php echo htmlspecialchars($banner['CTAText_TH']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">คำบนปุ่ม (<?php echo $Lang2; ?>)</label>
                                        <input type="text" name="CTAText_EN" value="<?php echo htmlspecialchars($banner['CTAText_EN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mt-3">
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">ลิงก์ URL (<?php echo $Lang1; ?>)</label>
                                        <input type="text" name="CTALink_TH" value="<?php echo htmlspecialchars($banner['CTALink_TH'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">ลิงก์ URL (<?php echo $Lang2; ?>)</label>
                                        <input type="text" name="CTALink_EN" value="<?php echo htmlspecialchars($banner['CTALink_EN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    </div>
                                </div>
                            </div>

                            <!-- OrderNo (Hidden) -->
                            <input type="hidden" name="OrderNo" value="<?php echo (int)$banner['OrderNo']; ?>">

                            <!-- Display Period -->
                            <?php
                            $publishStartVal = '';
                            if (!empty($banner['PublishStart']) && $banner['PublishStart'] !== '0000-00-00') {
                                $publishStartVal = date('d/m/Y', strtotime($banner['PublishStart']));
                            }
                            $publishEndVal = '';
                            if (!empty($banner['PublishEnd']) && $banner['PublishEnd'] !== '0000-00-00') {
                                $publishEndVal = date('d/m/Y', strtotime($banner['PublishEnd']));
                            }
                            ?>
                            <div class="border-t border-gray-150 pt-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">วันที่เริ่มแสดงผล</label>
                                        <div class="relative">
                                            <input type="text" id="publish_start" name="PublishStart" value="<?php echo htmlspecialchars($publishStartVal); ?>" class="w-full border border-gray-200 rounded-lg pl-4 pr-10 py-2 focus:outline-none focus:border-primary bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                                <i class="ph ph-calendar text-lg"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">วันที่สิ้นสุดแสดงผล</label>
                                        <div class="relative">
                                            <input type="text" id="publish_end" name="PublishEnd" value="<?php echo htmlspecialchars($publishEndVal); ?>" class="w-full border border-gray-200 rounded-lg pl-4 pr-10 py-2 focus:outline-none focus:border-primary bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                                <i class="ph ph-calendar text-lg"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Area: Image Upload -->
                        <div id="master-image-upload" class="lg:col-span-5 space-y-2 bg-gray-50 p-4 rounded-xl border border-gray-200 h-fit">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">รูปภาพแบนเนอร์  <span class="text-red-500">*</span></label>
                                
                                <div class="mb-3" id="mainPreviewContainer" style="<?php echo !empty($banner['Image']) ? '' : 'display: none;'; ?>">
                                    <div class="w-full h-56 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 relative group cursor-pointer flex items-center justify-center p-2">
                                        <img id="mainPreview" src="<?php echo !empty($banner['Image']) ? $ImgPath.'hero_banner/'.htmlspecialchars($banner['Image']) : ''; ?>" alt="Preview" class="max-w-full max-h-full object-contain" onclick="previewImage(this.src)">
                                        <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                            <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                                        </div>
                                        <!-- Remove Button -->
                                        <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110" onclick="clearMainImage(event)">
                                            <i class="ph ph-x font-bold"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <input type="hidden" name="delete_main_image" id="deleteMainImage" value="0">
                                
                                <div class="mt-2">
                                    <input type="file" name="Image" id="mainImageInput" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" onchange="previewMainImage(this)">
                                    <p class="text-xs text-gray-400 mt-2">แนะนำขนาด 1920x800 px (ไม่เกิน 2MB)</p>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>


        </div>

        <!-- Bottom Action Bar -->
        <div class="border-t border-gray-100 mt-8 pt-6 flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 py-3 text-lg"><i class="ph ph-floppy-disk mr-2"></i> บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="fixed inset-0 bg-black/85 flex items-center justify-center z-[9999] hidden backdrop-blur-md transition-all duration-300" onclick="closeImagePreview()">
    <button type="button" onclick="closeImagePreview()" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center hover:scale-105 transition-all shadow-lg border border-white/10 focus:outline-none">
        <i class="ph ph-x text-2xl font-bold"></i>
    </button>
    <div class="relative max-w-[90vw] max-h-[85vh] p-2" onclick="event.stopPropagation()">
        <img id="previewModalImg" src="" alt="Image Preview" class="max-w-full max-h-[80vh] rounded-xl shadow-2xl border border-white/10 object-contain bg-white/5">
    </div>
</div>

<script>
// Main Cover preview
function previewMainImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('mainPreview');
            const previewContainer = document.getElementById('mainPreviewContainer');
            
            preview.src = e.target.result;
            if (previewContainer) {
                previewContainer.style.display = 'block';
            }
            document.getElementById('deleteMainImage').value = '0';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearMainImage(e) {
    if (e) {
        e.stopPropagation(); // prevent modal click
    }
    const input = document.getElementById('mainImageInput');
    const preview = document.getElementById('mainPreview');
    const previewContainer = document.getElementById('mainPreviewContainer');
    
    input.value = '';
    preview.src = '';
    if (previewContainer) {
        previewContainer.style.display = 'none';
    }
    document.getElementById('deleteMainImage').value = '1';
}



// Image Preview controller
function previewImage(src) {
    const modal = document.getElementById('imagePreviewModal');
    const img = document.getElementById('previewModalImg');
    if (modal && img) {
        img.src = src;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeImagePreview() {
    const modal = document.getElementById('imagePreviewModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}



</script>
<!-- Flatpickr JS & Thai locale -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize flatpickr date pickers
    if (typeof flatpickr !== 'undefined') {
        flatpickr("#publish_start", {
            dateFormat: "d/m/Y",
            locale: "th",
            allowInput: true
        });
        flatpickr("#publish_end", {
            dateFormat: "d/m/Y",
            locale: "th",
            allowInput: true
        });
    }
});
</script>
<?php include_once('../includes/footer.php'); ?>
