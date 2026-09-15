<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการอัลบั้มภาพ';
include_once('../../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>window.location.href = '../index.php';</script>";
    exit;
}

$id = $_GET['id'] ?? 0;
$action = $id > 0 ? 'edit' : 'add';

// Check Permissions
$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = false;
if ($userGroupID === 'A') {
    $hasAccess = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $permRequired = (!empty($id)) ? 'CanEdit' : 'CanAdd';
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'gallery', $permRequired)) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = 'index.php';</script>";
    exit;
}

// Default Data
$data = [
    'GalleryID' => 0,
    'GroupID' => 0,
    'Title_TH' => '',
    'Title_EN' => '',
    'CoverImage' => '',
    'IsActive' => 'Y',
    'OrderNo' => '0'
];

$images = [];

if ($id > 0) {
    try {
        if (isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM microsite_gallery WHERE GalleryID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$id, $deptID]);
            $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fetched) {
                $data = $fetched;
                
                $stmtImgs = $DB->prepare("SELECT * FROM microsite_gallery_images WHERE GalleryID = ? AND COALESCE(DelData, 'N') <> 'Y' ORDER BY OrderNo ASC, ImageID ASC");
                $stmtImgs->execute([$id]);
                $images = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);
            } else {
                echo "<script>alert('ไม่พบข้อมูล'); window.location.href = 'index.php';</script>";
                exit;
            }
        }
    } catch (PDOException $e) {}
}

$stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID = ?");
$stmtSlug->execute([$deptID]);
$deptSlug = $stmtSlug->fetchColumn();

// Fetch Groups
$groups = [];
try {
    if(isset($DB)) {
        $stmtGroups = $DB->prepare("SELECT GroupID, GroupNameTH FROM microsite_gallery_groups WHERE DeptID = ? AND IsActive = 'Y' AND COALESCE(DelData, 'N') <> 'Y' ORDER BY OrderNo ASC");
        $stmtGroups->execute([$deptID]);
        $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {}
?>

<form action="action.php" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="showLoading()">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="GalleryID" value="<?php echo $data['GalleryID']; ?>">

    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <?php echo $action == 'add' ? 'เพิ่มอัลบั้มใหม่' : 'แก้ไขอัลบั้มภาพ'; ?>
                    </h2>
                    <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? ''); ?></span></div>
                </div>
            </div>
            
            <!-- Status Toggle -->
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-650">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo ($data['IsActive'] == 'Y') ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">หมวดหมู่ <span class="text-red-500">*</span></label>
                    <select name="GroupID" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                        <option value="0">-- เลือกหมวดหมู่ --</option>
                        <?php foreach($groups as $g): ?>
                        <option value="<?php echo $g['GroupID']; ?>" <?php echo ($data['GroupID'] ?? 0) == $g['GroupID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['GroupNameTH']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่ออัลบั้ม (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                    <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($data['Title_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่ออัลบั้ม (<?php echo $Lang2;?>)</label>
                    <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($data['Title_EN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                            <i class="ph ph-images"></i> รูปภาพแกลเลอรี่
                        </h3>
                        <?php if (!empty($images)): ?>
                        <button type="button" class="text-xs bg-blue-100 text-blue-700 px-3 py-1.5 rounded-full hover:bg-blue-200 flex items-center gap-1 font-medium transition-colors" onclick="openManageGalleryModal()">
                            <i class="ph ph-squares-four"></i> จัดการรูปภาพ
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if(!empty($images)): ?>
                    <div class="flex flex-wrap gap-2 pb-2 mb-4" id="galleryPreviewList">
                        <?php foreach($images as $img): ?>
                        <div class="relative flex-shrink-0" id="gallery-image-<?php echo (int)$img['ImageID']; ?>" data-id="<?php echo (int)$img['ImageID']; ?>">
                            <img
                                src="<?php echo $ImgPath; ?>microsite/<?php echo rawurlencode($deptSlug); ?>/<?php echo rawurlencode($img['Image']); ?>"
                                alt="<?php echo htmlspecialchars($data['Title_TH'], ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-20 h-16 object-cover rounded-md border border-gray-200 cursor-pointer"
                                onclick="openImageModal(this.src)"
                            >
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <input type="hidden" name="delete_images" id="delete_images" value="">
                    <input type="hidden" name="gallery_order" id="gallery_order" value="<?php echo htmlspecialchars(implode(',', array_column($images, 'ImageID')), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="file" name="GalleryImages[]" id="GalleryInput" multiple accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-400 mt-2">อัปโหลดได้หลายไฟล์ (รองรับ JPG, PNG)</p>
                    <div id="galleryPreviewContainer" class="flex overflow-x-auto gap-2 mt-4 custom-scrollbar pb-2"></div>
                </div>
            </div>
        </div>
        
        <div class="border-t border-gray-100 pt-6 mt-8 flex justify-end gap-3">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-650 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 h-12 text-base font-semibold shadow-lg shadow-primary/30 flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </form>
</div>

<?php if (!empty($images)): ?>
<!-- Manage Gallery Modal -->
<div id="manageGalleryModal" class="fixed inset-0 z-[99] bg-black/60 hidden flex items-center justify-center backdrop-blur-md px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-2xl">
            <div>
                <h3 class="text-xl font-bold text-gray-800">จัดการรูปภาพแกลเลอรี่</h3>
                <p class="text-sm text-gray-500 mt-1">ลากรูปภาพเพื่อสลับลำดับการแสดงผล</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-full w-8 h-8 flex items-center justify-center transition-colors" onclick="closeManageGalleryModal()">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 custom-scrollbar bg-gray-50">
            <div id="manageGalleryGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <?php foreach ($images as $img): ?>
                <div class="relative group cursor-move aspect-video rounded-xl overflow-hidden border-2 border-transparent hover:border-blue-400 hover:shadow-lg transition-all" data-id="<?php echo (int)$img['ImageID']; ?>">
                    <img src="<?php echo $ImgPath; ?>microsite/<?php echo rawurlencode($deptSlug); ?>/<?php echo rawurlencode($img['Image']); ?>" alt="<?php echo htmlspecialchars($data['Title_TH'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-cover pointer-events-none">
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors pointer-events-none"></div>
                    <button type="button"
                       onclick="markDeleteGallery(<?php echo (int)$img['ImageID']; ?>, this)"
                       class="absolute top-2 right-2 bg-red-500/90 hover:bg-red-600 text-white rounded-full w-7 h-7 flex items-center justify-center shadow-sm opacity-0 group-hover:opacity-100 transition-all scale-95 group-hover:scale-100 cursor-pointer"
                       title="ลบรูปภาพ">
                        <i class="ph ph-trash text-sm"></i>
                    </button>
                    <div class="absolute bottom-2 left-2 bg-black/50 text-white text-[10px] px-2 py-0.5 rounded-full opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                        <i class="ph ph-arrows-out-cardinal"></i> ลาก
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-white rounded-b-2xl">
            <button type="button" class="px-5 py-2 text-gray-600 font-medium hover:bg-gray-100 rounded-lg transition-colors" onclick="closeManageGalleryModal()">ยกเลิก</button>
            <button type="button" class="bg-blue-600 text-white px-6 py-2 font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-colors" onclick="saveGalleryOrder()">ยืนยัน</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Image Viewer Modal -->
<div id="imageModal" class="fixed inset-0 z-[9999] bg-black/80 hidden flex items-center justify-center backdrop-blur-md" onclick="closeImageModal(event)">
    <button type="button" onclick="document.getElementById('imageModal').classList.add('hidden')" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center hover:scale-105 transition-all shadow-lg border border-white/10 focus:outline-none">
        <i class="ph ph-x text-2xl font-bold"></i>
    </button>
    <img id="modalImage" src="" class="max-w-[85vw] max-h-[85vh] object-contain rounded-lg shadow-2xl">
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
const galleryInput = document.getElementById('GalleryInput');
const galleryPreviewContainer = document.getElementById('galleryPreviewContainer');
let pendingDeletedImages = [];

galleryInput.addEventListener('change', function (event) {
    galleryPreviewContainer.replaceChildren();

    Array.from(event.target.files || []).forEach((file) => {
        if (!file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.addEventListener('load', function (readerEvent) {
            const previewItem = document.createElement('div');
            previewItem.className = 'relative group flex-shrink-0';

            const previewImage = document.createElement('img');
            previewImage.src = readerEvent.target.result;
            previewImage.alt = file.name;
            previewImage.className = 'w-32 h-24 object-cover rounded-md border border-gray-200 cursor-pointer';
            previewImage.addEventListener('click', () => openImageModal(previewImage.src));

            const fileName = document.createElement('div');
            fileName.className = 'absolute bottom-0 inset-x-0 bg-black/60 text-white text-[10px] truncate px-1 py-0.5 rounded-b-md';
            fileName.title = file.name;
            fileName.textContent = file.name;

            previewItem.appendChild(previewImage);
            previewItem.appendChild(fileName);
            galleryPreviewContainer.appendChild(previewItem);
        });
        reader.readAsDataURL(file);
    });
});

function openManageGalleryModal() {
    const modal = document.getElementById('manageGalleryModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeManageGalleryModal() {
    const modal = document.getElementById('manageGalleryModal');
    if (!modal) return;

    pendingDeletedImages.forEach((imageId) => {
        const item = document.querySelector('#manageGalleryGrid [data-id="' + imageId + '"]');
        if (item) item.style.display = '';
    });
    pendingDeletedImages = [];

    const grid = document.getElementById('manageGalleryGrid');
    const previewList = document.getElementById('galleryPreviewList');
    if (grid && previewList) {
        Array.from(previewList.children).forEach((preview) => {
            const item = grid.querySelector('[data-id="' + preview.dataset.id + '"]');
            if (item) grid.appendChild(item);
        });
    }

    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function markDeleteGallery(imageId, button) {
    const item = button.closest('[data-id]');
    if (!item) return;

    if (!pendingDeletedImages.includes(String(imageId))) {
        pendingDeletedImages.push(String(imageId));
    }
    item.style.display = 'none';
}

function saveGalleryOrder() {
    const grid = document.getElementById('manageGalleryGrid');
    const previewList = document.getElementById('galleryPreviewList');
    if (!grid) return;

    const deleteInput = document.getElementById('delete_images');
    const deletedIds = deleteInput.value ? deleteInput.value.split(',').filter(Boolean) : [];

    pendingDeletedImages.forEach((imageId) => {
        if (!deletedIds.includes(imageId)) deletedIds.push(imageId);

        const preview = document.getElementById('gallery-image-' + imageId);
        if (preview) preview.remove();

        const manageItem = grid.querySelector('[data-id="' + imageId + '"]');
        if (manageItem) manageItem.remove();
    });
    deleteInput.value = deletedIds.join(',');
    pendingDeletedImages = [];

    const orderedIds = Array.from(grid.children)
        .filter((item) => item.style.display !== 'none')
        .map((item) => item.dataset.id)
        .filter(Boolean);

    document.getElementById('gallery_order').value = orderedIds.join(',');

    if (previewList) {
        orderedIds.forEach((id) => {
            const preview = document.getElementById('gallery-image-' + id);
            if (preview) previewList.appendChild(preview);
        });
    }

    const modal = document.getElementById('manageGalleryModal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

const manageGalleryGrid = document.getElementById('manageGalleryGrid');
if (manageGalleryGrid && typeof Sortable !== 'undefined') {
    Sortable.create(manageGalleryGrid, {
        animation: 150,
        ghostClass: 'opacity-50'
    });
}

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

</script>

<?php include_once('../../includes/footer.php'); ?>
