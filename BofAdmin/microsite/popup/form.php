<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการ Popup';
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
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'popup', $permRequired)) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = 'index.php';</script>";
    exit;
}

// Default Data
$data = [
	'PopupID' => 0,
	'Title_TH' => '',
	'Title_EN' => '',
	'Image' => '',
	'URL' => '',
	'Target' => '_blank',
	'StartDate' => '',
	'EndDate' => '',
	'IsActive' => 'Y',
	'OrderNo' => '0'
];

if ($id > 0) {
    try {
			if (isset($DB)) {
					$stmt = $DB->prepare("SELECT * FROM microsite_popups WHERE PopupID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
					$stmt->execute([$id, $deptID]);
					$fetched = $stmt->fetch(PDO::FETCH_ASSOC);
					if ($fetched) {
							$data = $fetched;
							if (!empty($data['StartDate'])) $data['StartDate'] = date('Y-m-d', strtotime($data['StartDate']));
							if (!empty($data['EndDate'])) $data['EndDate'] = date('Y-m-d', strtotime($data['EndDate']));
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
?>

<form action="action.php" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="showLoading()">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="PopupID" value="<?php echo $data['PopupID']; ?>">
    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                  <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
									<h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
										<?php echo $action == 'add' ? 'เพิ่ม Popup ใหม่' : 'แก้ไขข้อมูล Popup'; ?>
									</h2>
									<div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? ''); ?></span></div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-655">สถานะการแสดงผล</span>
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
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพ Popup</h3>
                    
                    <div class="mb-3" id="previewContainer" style="<?php echo !empty($data['Image']) ? '' : 'display: none;'; ?>">
                        <div class="w-full h-48 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 relative group cursor-pointer">
                            <img id="imagePreview" src="<?php echo !empty($data['Image']) ? $ImgPath.'microsite/'.$deptSlug.'/'.htmlspecialchars($data['Image']) : ''; ?>" class="w-full h-full object-cover" onclick="openImageModal(this.src)">
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
                        <input type="file" name="Image" id="popupImage" accept="image/jpeg,image/png,image/webp" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">ขนาดแนะนำ 800x600 px (.jpg, .png)</p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">หัวข้อ (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                    <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($data['Title_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>

								<div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">หัวข้อ (<?php echo $Lang2;?>)</label>
                    <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($data['Title_EN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ลิงก์ (<?php echo $Lang1;?>)</label>
                    <input type="text" name="URL_TH" value="<?php echo htmlspecialchars($data['URL_TH']); ?>" placeholder="https://..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
								
								<div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ลิงก์ (<?php echo $Lang2;?>)</label>
                    <input type="text" name="URL_EN" value="<?php echo htmlspecialchars($data['URL_EN']); ?>" placeholder="https://..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
								
                <?php
                    $startDateVal = (!empty($data['StartDate']) && $data['StartDate'] != '0000-00-00 00:00:00') ? date('Y-m-d', strtotime($data['StartDate'])) : '';
                    $endDateVal = (!empty($data['EndDate']) && $data['EndDate'] != '0000-00-00 00:00:00') ? date('Y-m-d', strtotime($data['EndDate'])) : '';
                ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">วันที่เริ่มแสดงผล</label>
                        <div class="relative">
                            <input type="text" name="StartDate" value="<?php echo $startDateVal; ?>" class="datepicker w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <i class="ph ph-calendar text-lg"></i>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">วันที่สิ้นสุดแสดงผล</label>
                        <div class="relative">
                            <input type="text" name="EndDate" value="<?php echo $endDateVal; ?>" class="datepicker w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <i class="ph ph-calendar text-lg"></i>
                            </div>
                        </div>
                    </div>
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
document.getElementById('popupImage').addEventListener('change', function(e) {
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
    document.getElementById('popupImage').value = '';
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('imagePreview').src = '';
    document.getElementById('deleteImageFlag').value = '1';
}
</script>

<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script>
flatpickr(".datepicker", {
    dateFormat: "Y-m-d",
    altInput: true,
    altFormat: "d/m/Y",
    locale: "th",
    allowInput: true
});
</script>

<?php include_once('../../includes/footer.php'); ?>
