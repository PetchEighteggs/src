<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการปฏิทินกิจกรรม';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$id = $_GET['id'] ?? null;
$eventData = [
	'Title_TH' => '', 'Title_EN' => '',
	'Content_TH' => '', 'Content_EN' => '',
	'EventStartDate' => '', 'EventEndDate' => '',
	'TimeFrom' => '', 'TimeTo' => '',
	'Location_TH' => '', 'Location_EN' => '',
	'CoverImage' => '', 'IsActive' => 'Y'
];

if ($id) {
    try {
			if(isset($DB)) {
				$stmt = $DB->prepare("SELECT * FROM events WHERE EventID = :id LIMIT 1");
				$stmt->execute([':id' => $id]);
				$res = $stmt->fetch(PDO::FETCH_ASSOC);
				if($res) $eventData = $res;
			}
    } catch(PDOException $e) {}
}

$eventStartDateVal = '';
if (!empty($eventData['EventStartDate']) && $eventData['EventStartDate'] !== '0000-00-00') {
    $eventStartDateVal = date('d/m/Y', strtotime($eventData['EventStartDate']));
}
$eventEndDateVal = '';
if (!empty($eventData['EventEndDate']) && $eventData['EventEndDate'] !== '0000-00-00') {
    $eventEndDateVal = date('d/m/Y', strtotime($eventData['EventEndDate']));
}
?>

<style>
.custom-scrollbar::-webkit-scrollbar { height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
<div class="glass-panel p-6">
	<form action="action.php" method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
		<?php echo bofSecurityCsrfField(); ?>
		<div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
			<a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
				<i class="ph ph-arrow-left text-xl"></i>
			</a>
			<h2 class="text-xl font-semibold text-gray-800 flex-1">
				<?php echo $id ? 'แก้ไขกิจกรรม' : 'เพิ่มกิจกรรม'; ?>
			</h2>
			<div class="flex items-center gap-3">
				<span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
				<label class="relative inline-flex items-center cursor-pointer">
						<input type="hidden" name="IsActive" value="N">
						<input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $eventData['IsActive'] == 'Y' ? 'checked' : ''; ?>>
						<div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
				</label>
			</div>
		</div>
		<input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
		<?php if($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>
		<?php if($id): ?><input type="hidden" name="old_cover" value="<?php echo htmlspecialchars($eventData['CoverImage']); ?>"><?php endif; ?>

		<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
				<div class="md:col-span-2 space-y-6">
					<div>
						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">หัวข้อกิจกรรม (<?php echo $Lang1; ?>)  <span class="text-red-500">*</span></label>
							<input type="text" name="Title_TH" value="<?php echo htmlspecialchars($eventData['Title_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
						</div>
						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">หัวข้อกิจกรรม (<?php echo $Lang2; ?>)</label>
							<input type="text" name="Title_EN" value="<?php echo htmlspecialchars($eventData['Title_EN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
						</div>
						
						<div class="grid grid-cols-2 gap-4 mb-4">
							<div>
								<label class="block text-gray-700 font-medium mb-2">วันที่เริ่มกิจกรรม <span class="text-red-500">*</span></label>
								<div class="relative">
									<input type="text" id="event_start_date" name="EventStartDate" value="<?php echo htmlspecialchars($eventStartDateVal); ?>" required class="w-full pl-4 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
									<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
										<i class="ph ph-calendar text-lg"></i>
									</div>
								</div>
							</div>
							<div>
								<label class="block text-gray-700 font-medium mb-2">วันที่สิ้นสุดกิจกรรม</label>
								<div class="relative">
									<input type="text" id="event_end_date" name="EventEndDate" value="<?php echo htmlspecialchars($eventEndDateVal); ?>" class="w-full pl-4 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
									<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
										<i class="ph ph-calendar text-lg"></i>
									</div>
								</div>
							</div>
						</div>
						
						<div class="grid grid-cols-2 gap-4 mb-4">
							<div>
								<label class="block text-gray-700 font-medium mb-2">เวลาเริ่ม (เช่น 08:30)</label>
								<input type="text" name="TimeFrom" value="<?php echo htmlspecialchars($eventData['TimeFrom']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
							</div>
							<div>
								<label class="block text-gray-700 font-medium mb-2">เวลาสิ้นสุด (เช่น 16:00)</label>
								<input type="text" name="TimeTo" value="<?php echo htmlspecialchars($eventData['TimeTo']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
							</div>
						</div>

						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">สถานที่จัดกิจกรรม (<?php echo $Lang1; ?>)</label>
							<textarea name="Location_TH" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($eventData['Location_TH']); ?></textarea>
						</div>
						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">สถานที่จัดกิจกรรม (<?php echo $Lang2; ?>)</label>
							<textarea name="Location_EN" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($eventData['Location_EN']); ?></textarea>
						</div>

						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">รายละเอียดกิจกรรม (<?php echo $Lang1; ?>) </label>
							<textarea name="Content_TH" id="editor_th" class="ckeditor_br"><?php echo htmlspecialchars($eventData['Content_TH']); ?></textarea>
						</div>
						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">รายละเอียดกิจกรรม (<?php echo $Lang2; ?>)</label>
							<textarea name="Content_EN" id="editor_en" class="ckeditor_br"><?php echo htmlspecialchars($eventData['Content_EN']); ?></textarea>
						</div>
					</div>
		    </div>	
				<div class="space-y-6">
						<div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
								<h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพหน้าปก</h3>
								<div class="mb-3" id="previewContainer" style="<?php echo $eventData['CoverImage'] ? '' : 'display: none;'; ?>">
										<div class="w-full h-64 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 cursor-pointer relative group">
											<img id="imagePreview" src="<?php echo $eventData['CoverImage'] ? $ImgPath . 'events/' . htmlspecialchars($eventData['CoverImage']) : ''; ?>" alt="Preview" class="w-full h-full object-cover" onclick="openImageModal(this.src)">
											<div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
												<i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
											</div>
											<button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10" onclick="removePreviewImage(event)">
												<i class="ph ph-x font-bold"></i>
											</button>
										</div>
								</div>
								<input type="hidden" name="deleteCoverImageFlag" id="deleteCoverImageFlag" value="0">
								<div class="mt-2">
									<input type="file" name="CoverImage" id="CoverImageInput" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
									<p class="text-xs text-gray-400 mt-2">ขนาดที่แนะนำ: <?php echo CONF_IMG_REC_W; ?>x<?php echo CONF_IMG_REC_H; ?> px (รองรับ JPG, PNG)</p>
								</div>
						</div>
				</div>
		</div>

		<div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
				<a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center">
					<i class="ph ph-x mr-2"></i>ยกเลิก</a>
				<button type="submit" class="btn-primary px-8 py-3 text-lg">
						<i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
				</button>
		</div>
	</form>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-white/80 flex flex-col items-center justify-center z-[9999] hidden backdrop-blur-sm">
	<div class="w-16 h-16 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mb-4"></div>
	<div class="text-blue-800 font-medium text-lg animate-pulse">กำลังบันทึกข้อมูล...</div>
	<div class="text-sm text-gray-500 mt-2">กรุณารอสักครู่</div>
</div>

<!-- Modal for Image Preview -->
<div id="imageModal" class="fixed inset-0 bg-black/85 flex items-center justify-center z-[9999] hidden backdrop-blur-md transition-all duration-300" onclick="closeImageModal()">
	<button type="button" onclick="closeImageModal()" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center hover:scale-105 transition-all shadow-lg border border-white/10 focus:outline-none">
		<i class="ph ph-x text-2xl font-bold"></i>
	</button>
	<div class="relative max-w-[90vw] max-h-[90vh]" onclick="event.stopPropagation()">
		<img id="modalImage" src="" alt="Preview" class="max-w-full max-h-[85vh] rounded-xl shadow-2xl border border-white/10 object-contain bg-white/5">
	</div>
</div>

<?php include_once('../assets/modules/ckeditor.php'); ?>

<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Initialize flatpickr date pickers
	if (typeof flatpickr !== 'undefined') {
		flatpickr("#event_start_date", {
			dateFormat: "d/m/Y",
			locale: "th",
			allowInput: true
		});
		flatpickr("#event_end_date", {
			dateFormat: "d/m/Y",
			locale: "th",
			allowInput: true
		});
	}
	const coverInput = document.getElementById('CoverImageInput');
	const previewContainer = document.getElementById('previewContainer');
	const imagePreview = document.getElementById('imagePreview');
	const deleteFlag = document.getElementById('deleteCoverImageFlag');

	coverInput.addEventListener('change', function(e) {
			const file = this.files[0];
			if (file) {
					if (!file.type.match('image.*')) {
							Swal.fire('ข้อผิดพลาด', 'กรุณาเลือกไฟล์รูปภาพเท่านั้น', 'error');
							this.value = '';
							return;
					}
					const reader = new FileReader();
					reader.onload = function(e) {
							imagePreview.src = e.target.result;
							previewContainer.style.display = 'block';
							deleteFlag.value = '0';
					};
					reader.readAsDataURL(file);
			}
	});
});

function removePreviewImage(e) {
	e.stopPropagation(); // prevent modal from opening
	document.getElementById('CoverImageInput').value = '';
	document.getElementById('previewContainer').style.display = 'none';
	document.getElementById('imagePreview').src = '';
	document.getElementById('deleteCoverImageFlag').value = '1';
}

function openImageModal(src) {
	const modal = document.getElementById('imageModal');
	const modalImg = document.getElementById('modalImage');
	modalImg.src = src;
	modal.classList.remove('hidden');
	document.body.style.overflow = 'hidden';
}

function closeImageModal() {
	const modal = document.getElementById('imageModal');
	modal.classList.add('hidden');
	document.body.style.overflow = '';
}

function showLoading() {
	const loading = document.getElementById('loadingOverlay');
	loading.classList.remove('hidden');
	
	if (typeof CKEDITOR !== 'undefined') {
			for(var instanceName in CKEDITOR.instances) {
					CKEDITOR.instances[instanceName].updateElement();
			}
	}
}
</script>

<?php include_once('../includes/footer.php'); ?>

