<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'ฟอร์มจัดการสื่อมัลติมีเดีย';
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
    if ($action === 'add') {
        $hasAccess = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'multimedia', 'CanAdd');
    } else {
        $hasAccess = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'multimedia', 'CanEdit');
    }
}

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location.href = 'index.php';</script>";
    exit;
}

// Default Data
$data = [
    'MediaID' => 0,
    'Title_TH' => '',
    'CoverImage' => '',
    'URL' => '',
    'URL_EN' => '',
    'Target' => '_blank',
    'IsActive' => 'Y',
    'OrderNo' => '0'
];

if ($id > 0) {
    try {
        if (isset($DB)) {
            $stmt = $DB->prepare("SELECT *, Image AS CoverImage, URL_TH AS URL FROM microsite_multimedia WHERE MediaID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
            $stmt->execute([$id, $deptID]);
            $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fetched) {
                $data = $fetched;
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
        $stmtGroups = $DB->prepare("SELECT GroupID, GroupNameTH FROM microsite_multimedia_groups WHERE DeptID = ? AND IsActive = 'Y' AND COALESCE(DelData, 'N') <> 'Y' ORDER BY OrderNo ASC");
        $stmtGroups->execute([$deptID]);
        $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {}
?>

<form action="action.php" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="showLoading()">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="MediaID" value="<?php echo $data['MediaID']; ?>">

    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        
                        <?php echo $action == 'add' ? 'เพิ่มสื่อมัลติมีเดียใหม่' : 'แก้ไขสื่อมัลติมีเดีย'; ?>
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
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพหน้าปก</h3>
                    
                    <div class="mb-3" id="previewContainer" style="<?php echo !empty($data['CoverImage']) ? '' : 'display: none;'; ?>">
                        <div class="w-full h-48 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 relative group aspect-video cursor-pointer">
                            <img id="imagePreview" src="<?php echo !empty($data['CoverImage']) ? $ImgPath.'microsite/'.$deptSlug.'/'.htmlspecialchars($data['CoverImage']) : ''; ?>" class="w-full h-full object-cover" onclick="openImageModal(this.src)">
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                            </div>
                            <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10" onclick="removePreviewImage(event)" title="ลบรูปภาพ">
                                <i class="ph ph-x font-bold"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="delete_image" id="deleteImageFlag" value="0">
                    
                    <div class="mt-2">
                        <input type="file" name="CoverImage" id="coverImage" accept="image/jpeg,image/png,image/webp" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">ขนาดแนะนำ 1280 × 720 px (.jpg, .png)</p>
                    </div>
                </div>
            </div>

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
                    <label class="block text-sm font-semibold text-gray-700 mb-1">หัวข้อ (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                    <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($data['Title_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">หัวข้อ (<?php echo $Lang2;?>)</label>
                    <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($data['Title_EN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ลิงก์ URL (<?php echo $Lang1; ?>)</label>
                    <input type="text" name="URL" value="<?php echo htmlspecialchars($data['URL'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ลิงก์ URL (<?php echo $Lang2; ?>)</label>
                    <input type="text" name="URL_EN" value="<?php echo htmlspecialchars($data['URL_EN'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">การเปิดลิงก์</label>
                    <select name="Target" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                        <option value="_blank" <?php echo ($data['Target'] == '_blank') ? 'selected' : ''; ?>>เปิดหน้าต่างใหม่</option>
                        <option value="_self" <?php echo ($data['Target'] == '_self') ? 'selected' : ''; ?>>เปิดหน้าต่างเดิม</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="border-t border-gray-100 pt-6 mt-8 flex justify-end gap-3">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-655 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 h-12 text-base font-semibold shadow-lg shadow-primary/30 flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
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

<script>
document.getElementById('coverImage').addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('previewContainer').style.display = 'block';
            document.getElementById('deleteImageFlag').value = '0';
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
    document.getElementById('coverImage').value = '';
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('imagePreview').src = '';
    document.getElementById('deleteImageFlag').value = '1';
}
</script>

<?php include_once('../../includes/footer.php'); ?>
