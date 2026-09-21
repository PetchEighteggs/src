<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการวีดิทัศน์';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$id = $_GET['id'] ?? 0;
$action = $id > 0 ? 'edit' : 'add';

$hasAccess = (!empty($id)) ? ($userPermissions['edit'] ?? true) : ($userPermissions['add'] ?? true);

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = 'index.php';</script>";
    exit;
}

$data = [
    'DocID' => 0,
    'GroupID' => 0,
    'TitleTH' => '',
    'TitleEN' => '',
    'DetailTH' => '',
    'DetailEN' => '',
    'URLTH' => '',
    'URLEN' => '',
    'CoverImage' => '',
    'IsActive' => 'Y',
    'OrderNo' => '0',
    'StartDate' => '',
    'EndDate' => ''
];

if ($id > 0) {
    try {
        if (isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM videos WHERE DocID = ?");
            $stmt->execute([$id]);
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

// Auto-check DelData in videos_groups
try {
    if(isset($DB)) {
        $checkDel = $DB->query("SHOW COLUMNS FROM `videos_groups` LIKE 'DelData'")->fetch();
        if (!$checkDel) {
            $DB->exec("ALTER TABLE `videos_groups` ADD COLUMN `DelData` char(1) DEFAULT 'N'");
        }
    }
} catch(PDOException $e) {}

// Fetch Groups
$groups = [];
try {
    if(isset($DB)) {
        $stmtGroups = $DB->prepare("SELECT GroupID, GroupNameTH FROM videos_groups WHERE IsActive = 'Y' AND (DelData = 'N' OR DelData IS NULL) ORDER BY OrderNo ASC");
        $stmtGroups->execute();
        $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {}
?>
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<form action="action.php" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="showLoading()">
    <?php echo bofSecurityCsrfField(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="DocID" value="<?php echo $data['DocID']; ?>">
    <input type="hidden" name="OrderNo" value="<?php echo (int)($data['OrderNo'] ?? 0); ?>">

    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <?php echo $action == 'add' ? 'เพิ่มวีดิทัศน์' : 'แก้ไขวีดิทัศน์'; ?>
                    </h2>
                </div>
            </div>
            
            <!-- Status Toggle -->
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo ($data['IsActive'] == 'Y') ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- RIGHT SIDEBAR -->
            <div class="order-2 lg:col-span-1 space-y-6">
                <!-- Cover Image -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพปก</h3>
                    
                    <div class="mb-4 text-center">
                        <div id="previewContainer" class="mb-3" style="<?php echo !empty($data['CoverImage']) ? '' : 'display: none;'; ?>">
                            <div class="relative inline-block w-full">
                                <img id="coverPreview" src="<?php echo !empty($data['CoverImage']) ? $ImgPath . 'videos/' . htmlspecialchars($data['CoverImage']) : ''; ?>" alt="Cover" class="w-full max-h-48 object-contain rounded-lg border border-gray-200 cursor-pointer" onclick="openImageModal(this.src)">
                                <button type="button" onclick="removeCoverImage(event)" class="absolute top-2 right-2 w-8 h-8 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center transition-transform hover:scale-110 shadow-lg z-10" title="ลบรูป">
                                    <i class="ph ph-x font-bold"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="deleteCoverImageFlag" id="deleteCoverImageFlag" value="0">
                        <input type="hidden" name="old_cover" value="<?php echo htmlspecialchars($data['CoverImage'] ?? ''); ?>">
                        <div class="mt-2">
                            <input type="file" name="CoverImage" id="coverImageInput" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" onchange="previewCoverImage(this)">
                            <p class="text-xs text-gray-400 mt-2 text-center">แนะนำขนาด 1280x720 px (JPG, PNG)</p>
                        </div>
                        <div id="newCoverPreview" class="mt-3 hidden">
                            <div class="relative inline-block w-full">
                                <img id="newCoverImg" src="" alt="Preview" class="w-full max-h-48 object-contain rounded-lg border border-blue-200">
                                <button type="button" onclick="clearCoverPreview()" class="absolute top-2 right-2 w-8 h-8 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center transition-transform hover:scale-110 shadow-lg z-10" title="ยกเลิกรูปนี้">
                                    <i class="ph ph-x font-bold"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
								<div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
									<label class="block text-gray-700 font-medium mb-2">หมวดหมู่ <span class="text-red-500">*</span></label>
									<select name="GroupID" id="GroupID" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
										<option value="0">-- เลือกหมวดหมู่ --</option>
										<?php foreach($groups as $g): ?>
										<option value="<?php echo $g['GroupID']; ?>" <?php echo ($data['GroupID'] ?? 0) == $g['GroupID'] ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars($g['GroupNameTH']); ?>
										</option>
										<?php endforeach; ?>
									</select>
								</div>
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-calendar-blank"></i> ระยะเวลา</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm text-gray-600 mb-2">วันที่เริ่มต้น</label>
                            <div class="relative">
                                <input type="text" id="startDate" name="StartDate" value="<?php echo !empty($data['StartDate']) && $data['StartDate'] != '0000-00-00 00:00:00' ? date('d/m/Y', strtotime($data['StartDate'])) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                    <i class="ph ph-calendar text-lg"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-2">วันที่สิ้นสุด</label>
                            <div class="relative">
                                <input type="text" id="endDate" name="EndDate" value="<?php echo !empty($data['EndDate']) && $data['EndDate'] != '0000-00-00 00:00:00' ? date('d/m/Y', strtotime($data['EndDate'])) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                    <i class="ph ph-calendar text-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                    
            </div>
            <div class="order-1 lg:col-span-2 space-y-5">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <div class="space-y-4">
											<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
													<div>
														<label class="block text-gray-700 font-medium mb-2">ชื่อวีดิทัศน์ (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
														<input type="text" name="TitleTH" value="<?php echo htmlspecialchars($data['TitleTH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
													</div>
													<div>
														<label class="block text-gray-700 font-medium mb-2">ชื่อวีดิทัศน์ (<?php echo $Lang2;?>)</label>
														<input type="text" name="TitleEN" value="<?php echo htmlspecialchars($data['TitleEN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
													</div>
											</div>
											<div>
												<label class="block text-gray-700 font-medium mb-2"> YouTube URL (<?php echo $Lang1;?>)</label>
												<input type="url" name="URLTH" value="<?php echo htmlspecialchars($data['URLTH'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="https://www.youtube.com/watch?v=...">
												<p class="text-xs text-gray-400 mt-1">วาง URL ของวีดีโอจาก YouTube เช่น https://www.youtube.com/watch?v=xxxxx</p>
											</div>
											<div>
												<label class="block text-gray-700 font-medium mb-2"> YouTube URL (<?php echo $Lang2;?>)</label>
												<input type="url" name="URLEN" value="<?php echo htmlspecialchars($data['URLEN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="https://www.youtube.com/watch?v=...">
											</div>
											<?php
											$youtubeId = '';
											if (!empty($data['URLTH'])) {
												preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w\-]+)/', $data['URLTH'], $matches);
												$youtubeId = $matches[1] ?? '';
											}
											?>
											<?php if (!empty($youtubeId)): ?>
											<div id="youtubePreviewSection">
												<label class="block text-gray-700 font-medium mb-2">ตัวอย่างวีดีโอ</label>
												<div class="aspect-video rounded-lg overflow-hidden border border-gray-200">
													<iframe id="youtubeFrame" src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtubeId); ?>" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
												</div>
											</div>
											<?php endif; ?>
											<div>
												<label class="block text-gray-700 font-medium mb-2">รายละเอียด (<?php echo $Lang1;?>)</label>
												<textarea name="DetailTH" rows="3" class="ckeditor_br w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($data['DetailTH'] ?? ''); ?></textarea>
											</div>
											<div>
												<label class="block text-gray-700 font-medium mb-2">รายละเอียด (<?php echo $Lang2;?>)</label>
												<textarea name="DetailEN" rows="3" class="ckeditor_br w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($data['DetailEN'] ?? ''); ?></textarea>
											</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="border-t border-gray-100 pt-6 mt-8 flex justify-end gap-3">
            <a href="index.php" class="px-6 h-12 border border-gray-300 text-gray-600 bg-white hover:bg-gray-50 rounded-lg font-medium transition-colors flex items-center gap-2">
                <i class="ph ph-x"></i> ยกเลิก
            </a>
            <button type="submit" class="btn-primary px-8 h-12 text-base font-semibold shadow-lg shadow-primary/30 flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </div>
</form>

<!-- Fullscreen Image Modal -->
<div id="imageModal" class="fixed inset-0 z-[100] bg-black/90 hidden flex items-center justify-center p-4 backdrop-blur-sm" onclick="closeImageModal(event)">
    <button type="button" class="absolute top-4 right-4 text-white/50 hover:text-white transition-colors" onclick="document.getElementById('imageModal').classList.add('hidden')">
        <i class="ph ph-x text-3xl"></i>
    </button>
    <img id="modalImage" src="" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl">
</div>

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

function removeCoverImage(e) {
    if (e) e.stopPropagation();
    document.getElementById('deleteCoverImageFlag').value = '1';
    let c = document.getElementById('previewContainer');
    if (c) { c.style.display = 'none'; }
    let img = document.getElementById('coverPreview');
    if (img) img.src = '';
    let input = document.getElementById('coverImageInput');
    if (input) input.value = '';
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

// YouTube URL live preview
document.querySelector('input[name="URLTH"]').addEventListener('change', function() {
    const url = this.value;
    let videoId = '';
    const match = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w\-]+)/);
    if (match) videoId = match[1];
    
    let section = document.getElementById('youtubePreviewSection');
    if (videoId) {
        if (!section) {
            section = document.createElement('div');
            section.id = 'youtubePreviewSection';
            section.innerHTML = `
                <label class="block text-gray-700 font-medium mb-2">ตัวอย่างวีดีโอ</label>
                <div class="aspect-video rounded-lg overflow-hidden border border-gray-200">
                    <iframe id="youtubeFrame" src="https://www.youtube.com/embed/${videoId}" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
                </div>
            `;
            this.closest('div').parentNode.insertBefore(section, this.closest('div').nextSibling.nextSibling);
        } else {
            const frame = section.querySelector('iframe');
            if (frame) frame.src = 'https://www.youtube.com/embed/' + videoId;
        }
    } else if (section) {
        section.remove();
    }
});
</script>

<!-- Flatpickr JS & Thai locale -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof flatpickr !== 'undefined') {
        flatpickr("#startDate", {
            dateFormat: "d/m/Y",
            locale: "th",
            allowInput: true
        });
        flatpickr("#endDate", {
            dateFormat: "d/m/Y",
            locale: "th",
            allowInput: true
        });
    }
});
</script>
<?php include_once('../assets/modules/ckeditor.php'); ?>
<?php include_once('../includes/footer.php'); ?>
