<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการเมนูเว็บไซต์';
include_once('../../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>window.location.href = '../index.php';</script>";
    exit;
}

$id = $_GET['id'] ?? 0;
$action = $id > 0 ? 'edit' : 'add';

$activeDeptSlug = $_SESSION['ActiveDeptSlug'] ?? '';
if (empty($activeDeptSlug)) {
    $stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID = ?");
    $stmtSlug->execute([$deptID]);
    $activeDeptSlug = $stmtSlug->fetchColumn();
    if (empty($activeDeptSlug)) $activeDeptSlug = 'main';
}

// Check Permissions
$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = false;
if ($userGroupID === 'A') {
    $hasAccess = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $permRequired = (!empty($id)) ? 'CanEdit' : 'CanAdd';
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'menus', $permRequired)) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = 'index.php';</script>";
    exit;
}

// Default Data
$data = [
    'MenuID' => 0,
    'MenuNameTH' => '',
    'MenuNameEN' => '',
    'Image' => '',
    'URL' => '',
    'Target' => '_self',
    'IsActive' => 'Y',
    'OrderNo' => '0',
    'ParentID' => 0,
    'SubID' => 0,
    'ModuleType' => '',
    'ModuleRefID' => 0
];

if ($id > 0) {
    try {
			if (isset($DB)) {
				$stmt = $DB->prepare("SELECT * FROM microsite_menus WHERE MenuID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
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
?>

<form action="action.php" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="showLoading()">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="MenuID" value="<?php echo $data['MenuID']; ?>">
    <input type="hidden" name="SubID" id="subIdInput" value="<?php echo $data['SubID']; ?>">

    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <?php echo $action == 'add' ? 'เพิ่มเมนูเว็บไซต์' : 'แก้ไขเมนูเว็บไซต์'; ?>
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
							<div>
								<div class="mb-4">
									<label class="block text-gray-700 font-medium mb-2">ชื่อเมนูหลัก (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
									<input type="text" name="MenuNameTH" value="<?php echo htmlspecialchars($data['MenuNameTH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
								</div>
							</div>
							<div>
								<div class="mb-4">
									<label class="block text-gray-700 font-medium mb-2">ชื่อเมนูหลัก (<?php echo $Lang2;?>)</label>
									<input type="text" name="MenuNameEN" value="<?php echo htmlspecialchars($data['MenuNameEN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
								</div>
							</div>
            </div>

            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-info"></i> ข้อมูลทั่วไป</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-1">การเชื่อมโยงโมดูลอ้างอิง</label>
                        <select name="ModuleType" id="ModuleType" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white mb-3" onchange="toggleUrlRequirement()">
                            <option value="" <?php echo $data['ModuleType'] == '' ? 'selected' : ''; ?>>ไม่มี</option>
                            <option value="about" <?php echo $data['ModuleType'] == 'about' ? 'selected' : ''; ?>>เกี่ยวกับหน่วยงาน</option>
                            <option value="news" <?php echo $data['ModuleType'] == 'news' ? 'selected' : ''; ?>>ข่าวประชาสัมพันธ์</option>
                            <option value="multimedia" <?php echo $data['ModuleType'] == 'multimedia' ? 'selected' : ''; ?>>สื่อมัลติมีเดีย</option>
                            <option value="download" <?php echo $data['ModuleType'] == 'download' ? 'selected' : ''; ?>>ดาวน์โหลด</option>
                            <option value="gallery" <?php echo $data['ModuleType'] == 'gallery' ? 'selected' : ''; ?>>อัลบั้มภาพ</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-1">ลิงก์ปลายทาง (URL) <span class="text-red-500" id="urlReqStar">*</span></label>
                        <input type="text" name="URL" id="URL" placeholder="https://" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white resize-none" value="<?php echo htmlspecialchars($data['URL']); ?>" required>
                        <p class="text-xs text-gray-400 mt-1">หมายเหตุ : กรุณา URL นำหน้าด้วย "https://" เช่น https://www.dgr.go.th เป็นต้น</p>
                    </div>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพประกอบ</h3>
                    <div class="mb-3" id="previewContainerImage" style="<?php echo !empty($data['Image']) ? '' : 'display: none;'; ?>">
                        <div class="w-full h-48 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 relative group cursor-pointer">
                            <img id="previewImg" src="<?php echo !empty($data['Image']) ? $ImgPath.'microsite/'.htmlspecialchars($activeDeptSlug).'/'.htmlspecialchars($data['Image']) : ''; ?>" class="w-full h-full object-cover">
                            <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10" onclick="removeImage(event)" title="ลบรูปภาพ">
                                <i class="ph ph-x font-bold"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="delete_image" id="deleteImage" value="0">
                    <div class="mt-2">
                        <input type="file" name="Image" id="imageFile" accept="image/jpeg,image/png,image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">หมายเหตุ : ขนาดแนะนำ 800x600 px  (.jpg, .png)</p>
                    </div>
                </div>

            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 py-3 text-lg flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </div>
</form>

<script>
// Image preview handler
const imageFileInput = document.getElementById('imageFile');
const previewImg = document.getElementById('previewImg');
const previewContainerImage = document.getElementById('previewContainerImage');
const deleteImageInput = document.getElementById('deleteImage');

if(imageFileInput) {
    imageFileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewContainerImage.style.display = 'block';
                deleteImageInput.value = '0';
            }
            reader.readAsDataURL(file);
        } else {
            previewContainerImage.style.display = 'none';
        }
    });
}

function removeImage(e) {
    e.stopPropagation();
    previewContainerImage.style.display = 'none';
    deleteImageInput.value = '1';
    imageFileInput.value = '';
}

function toggleUrlRequirement() {
    const moduleType = document.getElementById('ModuleType').value;
    const urlInput = document.getElementById('URL');
    const urlReqStar = document.getElementById('urlReqStar');
    
    if (!moduleType) {
        urlInput.required = true;
        urlReqStar.style.display = 'inline';
    } else {
        urlInput.required = false;
        urlReqStar.style.display = 'none';
    }
}

// Initial load for edit mode
document.addEventListener('DOMContentLoaded', () => {
    toggleUrlRequirement();
});
</script>
<?php include_once('../../includes/footer.php'); ?>