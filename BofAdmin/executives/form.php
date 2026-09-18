<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการข้อมูลผู้บริหาร';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$id = $_GET['id'] ?? null;
$personData = [];
$personPositions = [];
$deptOptions = [];

try {
	if (isset($DB)) {
		// Fetch departments for dropdown
		$stmtD = $DB->query("SELECT DeptID, NameTH, DeptGroup FROM microsite_departments WHERE IsActive='Y' ORDER BY NameTH ASC");
		$deptOptions = $stmtD->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (PDOException $e) {}

if ($id) {
    try {
			if (isset($DB)) {
				// Fetch person
				$stmt = $DB->prepare("SELECT * FROM microsite_executives WHERE ExecID = ? AND DelData = 'N' LIMIT 1");
				$stmt->execute([$id]);
				$personData = $stmt->fetch(PDO::FETCH_ASSOC);
				
				// Fetch positions
				if ($personData) {
						$stmtPos = $DB->prepare("SELECT * FROM org_executive_positions WHERE ExecID = ? AND DelData = 'N' ORDER BY OrderNo ASC");
						$stmtPos->execute([$id]);
						$personPositions = $stmtPos->fetchAll(PDO::FETCH_ASSOC);
				}
			}
    } catch (PDOException $e) {}
    if (!$personData) { header("Location: index.php"); exit; }
}

?>
<div class="glass-panel p-6">
    <form action="action.php" method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
        <?php echo bofSecurityCsrfField(); ?>
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <h2 class="text-xl font-semibold text-gray-800">
                <?php echo $id ? 'แก้ไขข้อมูลผู้บริหาร' : 'เพิ่มข้อมูลผู้บริหาร'; ?>
            </h2>
        </div>

        <input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
        <?php if ($id): ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($personData['Image'] ?? ''); ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2 space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-user"></i> ข้อมูลส่วนตัว</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                          <label class="block text-gray-700 font-medium mb-2">ชื่อ-นามสกุล (<?php echo $Lang1; ?>) <span class="text-red-500">*</span></label>
                          <input type="text" name="NameTH" value="<?php echo htmlspecialchars($personData['NameTH'] ?? ''); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                          <label class="block text-gray-700 font-medium mb-2">ชื่อ-นามสกุล (<?php echo $Lang2; ?>)</label>
                          <input type="text" name="NameEN" value="<?php echo htmlspecialchars($personData['NameEN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                          <label class="block text-gray-700 font-medium mb-2">เบอร์โทรศัพท์</label>
                          <input type="text" name="Phone" value="<?php echo htmlspecialchars($personData['Phone'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                          <label class="block text-gray-700 font-medium mb-2">อีเมล</label>
                          <input type="email" name="Email" value="<?php echo htmlspecialchars($personData['Email'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-file-text"></i> รายละเอียด</h3>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">รายละเอียด (<?php echo $Lang1;?>)</label>
                        <textarea name="DetailTH" id="editor_th" class="ckeditor_br w-full"><?php echo htmlspecialchars($personData['DetailTH'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">รายละเอียด (<?php echo $Lang2;?>)</label>
                        <textarea name="DetailEN" id="editor_en" class="ckeditor_br w-full"><?php echo htmlspecialchars($personData['DetailEN'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
									<div class="flex justify-between items-center mb-4">
										<h3 class="font-semibold text-gray-700 flex items-center gap-2"><i class="ph ph-briefcase"></i> ตำแหน่งและสังกัด</h3>
										<button type="button" onclick="addPositionRow()" class="btn-primary text-sm px-3 py-1.5 flex items-center gap-1">
											<i class="ph ph-plus"></i> เพิ่มตำแหน่ง
										</button>
									</div>
									<div id="positionsContainer" class="space-y-4">
										<!-- Rows will be injected here by JS -->
									</div>
                    
									<div id="noPositionMsg" class="text-center py-4 text-gray-400 text-sm hidden">ยังไม่มีข้อมูลตำแหน่ง</div>
                </div>
            </div>
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพผู้บริหาร</h3>
                    <div class="mb-4">
                        <div class="mb-4 text-center">
                            <div id="previewContainer" class="mb-3" style="<?php echo !empty($personData['Image']) ? '' : 'display: none;'; ?>">
                                <div class="max-w-[250px] aspect-[250/287] mx-auto rounded-lg overflow-hidden border border-gray-200 bg-gray-50 cursor-pointer relative group">
                                    <img id="imagePreview" src="<?php echo !empty($personData['Image']) ? $ImgPath . 'executives/' . htmlspecialchars($personData['Image']) : ''; ?>" class="w-full h-full object-cover" onclick="openImageModal(this.src)">
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
                            <p class="text-xs text-gray-400 mt-1">ขนาดแนะนำ 300x300 พิกเซล (.jpg, .png)</p>
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

<?php include_once('../assets/modules/ckeditor.php'); ?>

<!-- Image Viewer Modal -->
<div id="imageModal" class="fixed inset-0 z-[9999] bg-black/80 hidden flex items-center justify-center backdrop-blur-md" onclick="closeImageModal(event)">
    <button type="button" onclick="document.getElementById('imageModal').classList.add('hidden')" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center hover:scale-105 transition-all shadow-lg border border-white/10 focus:outline-none">
        <i class="ph ph-x text-2xl font-bold"></i>
    </button>
    <img id="modalImage" src="" class="max-w-[85vw] max-h-[85vh] object-contain rounded-lg shadow-2xl">
</div>

<script>
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

const deptOptions = <?php echo json_encode($deptOptions); ?>;
const existingPositions = <?php echo json_encode($personPositions); ?>;
const positionsContainer = document.getElementById('positionsContainer');
const noPositionMsg = document.getElementById('noPositionMsg');
let posIndex = 0;

function addPositionRow(data = null) {
    const row = document.createElement('div');
    row.className = 'bg-white p-4 pr-12 rounded-lg border border-gray-200 relative group position-row';
    
    // Check what group is selected to filter depts
    let selectedGroup = '';
    let selectedDeptId = data ? data.DeptID : '';
    
    if (data) {
        if (data.ExecGroup) {
            selectedGroup = data.ExecGroup;
        } else if (data.DeptID == 0) {
            selectedGroup = 'dept'; // Fallback for old data
        } else {
            const d = deptOptions.find(opt => opt.DeptID == data.DeptID);
            if (d) selectedGroup = d.DeptGroup;
        }
    }
    
    row.innerHTML = `
        <button type="button" onclick="this.closest('.position-row').remove(); updateNoPosMsg();" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10" title="ลบตำแหน่งนี้">
            <i class="ph ph-x font-bold text-lg"></i>
        </button>
        
        <input type="hidden" name="Positions[${posIndex}][MapID]" value="${data ? data.MapID : ''}">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">สังกัด <span class="text-red-500">*</span></label>
                <select name="Positions[${posIndex}][Group]" class="w-full px-3 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-primary text-sm group-select" required onchange="updateDeptOptions(this, ${posIndex})">
                    <option value="">-- เลือกสังกัด --</option>
                    <option value="dept" ${selectedGroup === 'dept' ? 'selected' : ''}>ผู้บริหาร</option>
                    <option value="central" ${selectedGroup === 'central' ? 'selected' : ''}>ผู้บริหารส่วนกลาง</option>
                    <option value="regional" ${selectedGroup === 'regional' ? 'selected' : ''}>ผู้บริหารส่วนภูมิภาค</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">หน่วยงาน <span class="text-red-500">*</span></label>
                <select name="Positions[${posIndex}][DeptID]" class="w-full px-3 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-primary text-sm dept-select" required>
                    <option value="0">-- เลือกหน่วยงาน --</option>
                </select>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">ตำแหน่ง (ภาษาไทย) <span class="text-red-500">*</span></label>
                <input type="text" name="Positions[${posIndex}][PositionTH]" value="${data ? data.PositionTH : ''}" required class="w-full px-3 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-primary text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">ตำแหน่ง (ภาษาอังกฤษ)</label>
                <input type="text" name="Positions[${posIndex}][PositionEN]" value="${data ? (data.PositionEN || '') : ''}" class="w-full px-3 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-primary text-sm">
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex items-end pb-1">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="Positions[${posIndex}][IsActive]" value="N">
                        <input type="checkbox" name="Positions[${posIndex}][IsActive]" value="Y" class="sr-only peer" ${!data || data.IsActive === 'Y' ? 'checked' : ''}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                    </label>
                </div>
            </div>
        </div>
    `;
    
    positionsContainer.appendChild(row);
    
    // Trigger update dept options manually if data exists
    if (data) {
        const groupSelect = row.querySelector('.group-select');
        updateDeptOptions(groupSelect, posIndex, selectedDeptId);
    }
    
    posIndex++;
    updateNoPosMsg();
}

function updateDeptOptions(selectElement, index, selectedValue = '') {
    const deptSelect = selectElement.closest('.position-row').querySelector('.dept-select');
    const group = selectElement.value;
    
    deptSelect.innerHTML = '';
    
    if (group === 'dept' || group === 'past_exec') {
        deptSelect.innerHTML = '<option value="0">-- เลือกหน่วยงาน --</option>';
        deptSelect.value = "0";
        deptSelect.classList.add('bg-gray-100', 'pointer-events-none', 'text-gray-500');
    } else {
        deptSelect.classList.remove('bg-gray-100', 'pointer-events-none', 'text-gray-500');
        deptSelect.innerHTML = '<option value="">-- เลือกหน่วยงาน --</option>';
        
        deptOptions.forEach(d => {
            if (d.DeptGroup === group) {
                const selected = (selectedValue == d.DeptID) ? 'selected' : '';
                deptSelect.innerHTML += `<option value="${d.DeptID}" ${selected}>${d.NameTH}</option>`;
            }
        });
    }
}

function updateNoPosMsg() {
    if (positionsContainer.children.length === 0) {
        noPositionMsg.classList.remove('hidden');
    } else {
        noPositionMsg.classList.add('hidden');
    }
}

// Initialize
if (existingPositions.length > 0) {
    existingPositions.forEach(pos => addPositionRow(pos));
} else {
    // maybe don't add empty row by default, or add one if it's new
    updateNoPosMsg();
}
</script>

<?php include_once('../includes/footer.php'); ?>
