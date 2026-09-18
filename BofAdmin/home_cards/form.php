<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'ข้อมูลการ์ดหน้าหลัก';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$cardId = max(0, (int)mosGetParam($_GET, 'CardID', 0));
$card = [
    'CardID' => 0, 'Slug' => '', 'Title_TH' => '', 'Title_EN' => '',
    'Description_TH' => '', 'Description_EN' => '', 'Link_TH' => '', 'Link_EN' => '',
    'BgClass' => 'bg-grad-0', 'Image' => '', 'Direction' => 'left', 'IsActive' => 'Y',
];

if ($cardId > 0) {
    $stmt = $DB->prepare("SELECT * FROM home_cards WHERE CardID=? AND DelData='N' LIMIT 1");
    $stmt->execute([$cardId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        header('Location: index.php?result=error&message=' . urlencode('ไม่พบข้อมูลที่ต้องการแก้ไข'));
        exit;
    }
    $card = array_merge($card, $record);
}

$imageUrl = '';
if (!empty($card['Image'])) {
    $imageUrl = preg_match('/^(?:\/|https?:\/\/)/i', $card['Image'])
        ? $card['Image']
        : $ImgPath . 'home_cards/' . $card['Image'];
}
?>
<link rel="stylesheet" href="../assets/css/bgColorClass.css">
<style>
.home-card-color input:checked + span { box-shadow: 0 0 0 3px #fff, 0 0 0 6px #2563eb; transform: translateY(-2px); }
</style>

<div class="glass-panel p-6">
    <form action="action.php" method="post" enctype="multipart/form-data" id="homeCardForm" onsubmit="showLoading()">
        <?php echo bofSecurityCsrfField(); ?>
        <input type="hidden" name="action" value="<?php echo $cardId > 0 ? 'update' : 'create'; ?>">
        <input type="hidden" name="CardID" value="<?php echo $cardId; ?>">

        <div class="flex flex-col md:flex-row md:items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200"><i class="ph ph-arrow-left text-xl"></i></a>
            <div class="flex-1"><h2 class="text-xl font-bold text-gray-800"><?php echo $cardId > 0 ? 'แก้ไขการ์ดหน้าหลัก' : 'เพิ่มการ์ดหน้าหลัก'; ?></h2><p class="text-sm text-gray-500 mt-1">กำหนดข้อความ รูปภาพ ลิงก์ และรูปแบบการแสดงผล</p></div>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N"><input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $card['IsActive'] === 'Y' ? 'checked' : ''; ?>>
                    <span class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></span>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-7 space-y-6">
                <section class="bg-white border border-gray-100 rounded-xl p-5">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="ph ph-identification-card text-primary"></i> ข้อมูลหลัก</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-semibold text-gray-700 mb-2">Slug <span class="text-red-500">*</span></label><input name="Slug" value="<?php echo htmlspecialchars($card['Slug']); ?>" required maxlength="150" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="เช่น apply-permit" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary font-mono"><p class="text-xs text-gray-400 mt-1">ใช้ตัวอักษรภาษาอังกฤษตัวเล็ก ตัวเลข และขีดกลางเท่านั้น</p></div>
                        <div><label class="block text-sm font-semibold text-gray-700 mb-2">หัวข้อ (ภาษาไทย) <span class="text-red-500">*</span></label><input type="text" name="Title_TH" value="<?php echo htmlspecialchars($card['Title_TH']); ?>" required maxlength="255" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary"></div>
                        <div><label class="block text-sm font-semibold text-gray-700 mb-2">หัวข้อ (ภาษาอังกฤษ)</label><input type="text" name="Title_EN" value="<?php echo htmlspecialchars($card['Title_EN']); ?>" maxlength="255" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary"></div>
                        <div><label class="block text-sm font-semibold text-gray-700 mb-2">รายละเอียด (ภาษาไทย)</label><textarea name="Description_TH" rows="4" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary"><?php echo htmlspecialchars($card['Description_TH']); ?></textarea></div>
                        <div><label class="block text-sm font-semibold text-gray-700 mb-2">รายละเอียด (ภาษาอังกฤษ)</label><textarea name="Description_EN" rows="4" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary"><?php echo htmlspecialchars($card['Description_EN']); ?></textarea></div>
                    </div>
                </section>

                <section class="bg-white border border-gray-100 rounded-xl p-5">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="ph ph-link text-primary"></i> ลิงก์ปลายทาง</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="block text-sm font-semibold text-gray-700 mb-2">ลิงก์ (ภาษาไทย)</label><input name="Link_TH" value="<?php echo htmlspecialchars($card['Link_TH']); ?>" maxlength="500" placeholder="/th/... หรือ https://..." class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary"></div>
                        <div><label class="block text-sm font-semibold text-gray-700 mb-2">ลิงก์ (ภาษาอังกฤษ)</label><input name="Link_EN" value="<?php echo htmlspecialchars($card['Link_EN']); ?>" maxlength="500" placeholder="/en/... หรือ https://..." class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary"></div>
                    </div>
                </section>

                <section class="bg-white border border-gray-100 rounded-xl p-5">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="ph ph-align-left text-primary"></i> ตำแหน่งเนื้อหา</h3>
                    <div class="relative">
                        <select name="Direction" class="w-full appearance-none px-4 py-2.5 pr-10 border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-primary cursor-pointer">
                            <option value="left" <?php echo $card['Direction'] === 'left' ? 'selected' : ''; ?>>ซ้าย (Left)</option>
                            <option value="right" <?php echo $card['Direction'] === 'right' ? 'selected' : ''; ?>>ขวา (Right)</option>
                        </select>
                        <i class="ph ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                    </div>
                </section>
            </div>

            <div class="lg:col-span-5 space-y-6">
                <section class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพ</h3>
                    <div id="previewContainer" class="mb-3 flex justify-center" style="<?php echo $imageUrl !== '' ? '' : 'display: none;'; ?>">
                        <div class="w-full max-w-[320px] aspect-square rounded-lg overflow-hidden border border-gray-200 shadow-sm bg-white cursor-pointer relative group">
                            <img id="imagePreview" src="<?php echo htmlspecialchars($imageUrl); ?>" alt="Preview" class="w-full h-full object-contain p-3" onclick="openImageModal(this.src)">
                            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                            </div>
                            <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10" onclick="removePreviewImage(event)" title="นำรูปออก">
                                <i class="ph ph-x font-bold"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="delete_image" id="deleteImage" value="0">
                    <div class="mt-2 text-center">
                        <input type="file" name="Image" id="imageInput" accept="image/jpeg,image/png,.jpg,.jpeg,.png" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">ขนาดที่แนะนำ: 1254 × 1254 px รองรับ JPG, PNG</p>
                    </div>
                </section>

                <section class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="ph ph-palette text-primary"></i> สีพื้นหลัง</h3>
                    <div class="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-4 gap-4">
                    <?php for ($i = 0; $i <= 20; $i++): $class = 'bg-grad-' . $i; ?>
                        <label class="home-card-color cursor-pointer text-center">
                            <input type="radio" name="BgClass" value="<?php echo $class; ?>" class="sr-only" <?php echo $card['BgClass'] === $class ? 'checked' : ''; ?>>
                            <span class="<?php echo $class; ?> block h-16 rounded-xl border border-gray-200 transition-all"></span>
                            <span class="block text-[11px] text-gray-500 mt-1"><?php echo $class; ?></span>
                        </label>
                    <?php endfor; ?>
                    </div>
                </section>

            </div>
        </div>

        <div class="border-t border-gray-100 mt-8 pt-6 flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 py-3 text-lg"><i class="ph ph-floppy-disk mr-2"></i> บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<div id="imageModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-90 flex items-center justify-center p-4" onclick="closeImageModal()">
    <button type="button" class="absolute top-4 right-4 text-white hover:text-gray-300" onclick="closeImageModal()"><i class="ph ph-x text-3xl"></i></button>
    <img id="modalImage" src="" alt="Full Preview" class="max-w-full max-h-[90vh] object-contain rounded-lg" onclick="event.stopPropagation()">
</div>

<script>
document.querySelector('input[name="Slug"]').addEventListener('input', function () {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/-{2,}/g, '-');
});

const imageInput = document.getElementById('imageInput');
const imagePreview = document.getElementById('imagePreview');
const previewContainer = document.getElementById('previewContainer');
const deleteImage = document.getElementById('deleteImage');

imageInput.addEventListener('change', function () {
    const file = this.files && this.files[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png'].includes(file.type)) {
        this.value = '';
        Swal.fire('ชนิดไฟล์ไม่ถูกต้อง', 'กรุณาเลือกไฟล์ JPG หรือ PNG', 'warning');
        return;
    }
    const reader = new FileReader();
    reader.onload = function (event) {
        imagePreview.src = event.target.result;
        previewContainer.style.display = 'flex';
        deleteImage.value = '0';
    };
    reader.readAsDataURL(file);
});

function removePreviewImage(event) {
    if (event) event.stopPropagation();
    imageInput.value = '';
    imagePreview.src = '';
    previewContainer.style.display = 'none';
    deleteImage.value = '1';
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
</script>
<?php include_once('../includes/footer.php'); ?>
