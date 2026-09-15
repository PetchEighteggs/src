<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการข่าวสาร';
include_once('../includes/header.php');

$id = $_GET['id'] ?? null;
$newsData = [
	'Title_TH' => '', 'Title_EN' => '',
	'Excerpt_TH' => '', 'Excerpt_EN' => '',
	'Content_TH' => '', 'Content_EN' => '',
	'Category_TH' => '', 'CategoryID' => '',
	'CoverImage' => '', 'DateStr' => date('Y-m-d'),
	'Publisher' => '', 'Slug' => '', 'IsActive' => 'Y'
];
$galleries = [];
$attachments = [];
$categories = [];

try {
    if(isset($DB)) {
      $stmtCat = $DB->query("SELECT * FROM news_category WHERE DelData = 'N' AND IsActive = 'Y' ORDER BY OrderNo ASC, CategoryID DESC");
      $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

if ($id) {
    try {
			if(isset($DB)) {
				$stmt = $DB->prepare("
					SELECT n.*, 
					c.Name as CreatorName,
					u.Name as UpdaterName
					FROM news n
					LEFT JOIN staff c ON (n.CreateBy = c.StaffID OR n.CreateBy = c.Username)
					LEFT JOIN staff u ON (n.UpdateBy = u.StaffID OR n.UpdateBy = u.Username)
					WHERE n.NewsID = :id LIMIT 1
					");
				$stmt->execute([':id' => $id]);
				$res = $stmt->fetch(PDO::FETCH_ASSOC);
				if($res) $newsData = $res;
				
				$stmtGal = $DB->prepare("SELECT * FROM news_gallery WHERE NewsID = :id AND DelData = 'N' ORDER BY OrderNo ASC, GalleryID ASC");
				$stmtGal->execute([':id' => $id]);
				$galleries = $stmtGal->fetchAll(PDO::FETCH_ASSOC);
				
				$stmtAtt = $DB->prepare("SELECT * FROM news_attachments WHERE NewsID = :id AND DelData = 'N' ORDER BY OrderNo ASC, AttachmentID ASC");
				$stmtAtt->execute([':id' => $id]);
				$attachments = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
			}
    } catch(PDOException $e) {}
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
		<div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
			<a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
				<i class="ph ph-arrow-left text-xl"></i>
			</a>
			<h2 class="text-xl font-semibold text-gray-800 flex-1">
				<?php echo $id ? 'แก้ไขข่าวสาร' : 'เพิ่มข่าวสาร'; ?>
			</h2>
			<div class="flex items-center gap-3">
				<span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
				<label class="relative inline-flex items-center cursor-pointer">
						<input type="hidden" name="IsActive" value="N">
						<input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $newsData['IsActive'] == 'Y' ? 'checked' : ''; ?>>
						<div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
				</label>
			</div>
		</div>
		<input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
		<?php if($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>
		<?php if($id): ?><input type="hidden" name="old_cover" value="<?php echo htmlspecialchars($newsData['CoverImage']); ?>"><?php endif; ?>

		<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
				<div class="md:col-span-2 space-y-6">
					<div>
						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">หัวข้อ (<?php echo $Lang1; ?>)  <span class="text-red-500">*</span></label>
							<input type="text" name="Title_TH" value="<?php echo htmlspecialchars($newsData['Title_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
						</div>
						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">หัวข้อ (<?php echo $Lang2; ?>)</label>
							<input type="text" name="Title_EN" value="<?php echo htmlspecialchars($newsData['Title_EN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
						</div>
						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">คำโปรย (<?php echo $Lang1; ?>) </label>
							<textarea name="Excerpt_TH" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($newsData['Excerpt_TH']); ?></textarea>
						</div>
						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">คำโปรย (<?php echo $Lang2; ?>)</label>
							<textarea name="Excerpt_EN" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($newsData['Excerpt_EN']); ?></textarea>
						</div>
						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">รายละเอียด (<?php echo $Lang1; ?>) </label>
							<textarea name="Content_TH" id="editor_th" class="ckeditor_br"><?php echo htmlspecialchars($newsData['Content_TH']); ?></textarea>
						</div>
						<div class="mb-4">
							<label class="block text-gray-700 font-medium mb-2">รายละเอียด (<?php echo $Lang2; ?>)</label>
							<textarea name="Content_EN" id="editor_en" class="ckeditor_br"><?php echo htmlspecialchars($newsData['Content_EN']); ?></textarea>
						</div>
					</div>
		    </div>	
				<div class="space-y-6">
						<div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
								<h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-info"></i> ข้อมูลทั่วไป</h3>
								<div class="mb-4">
									<label class="block text-sm text-gray-600 mb-1">Slug (URL ภาษาอังกฤษ)</label>
									<input type="text" name="Slug" value="<?php echo htmlspecialchars($newsData['Slug']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm" placeholder="eg. news-title-2026">
									<p class="text-xs text-gray-500 mt-1">ใช้สำหรับสร้าง URL (SEO) หากเว้นว่างระบบจะสร้างอัตโนมัติ</p>
								</div>
								<div class="mb-4">
									<label class="block text-sm text-gray-600 mb-1">หมวดหมู่ข่าว</label>
									<select name="CategoryID" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm">
										<option value="">-- เลือกหมวดหมู่ --</option>
										<?php foreach($categories as $cat): ?>
												<option value="<?php echo $cat['CategoryID']; ?>" <?php echo $newsData['CategoryID'] == $cat['CategoryID'] ? 'selected' : ''; ?>>
														<?php echo htmlspecialchars($cat['CategoryName_TH']); ?>
												</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="mb-4">
									<label class="block text-sm text-gray-600 mb-1">วันที่ประกาศ</label>
									<div class="relative">
										<input type="text" id="datepicker" name="DateStr" value="<?php echo htmlspecialchars($newsData['DateStr']); ?>" class="w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
										<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
											<i class="ph ph-calendar text-lg"></i>
										</div>
									</div>
								</div>
						</div>
						<div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
								<h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพหน้าปก</h3>
								<div class="mb-3" id="previewContainer" style="<?php echo $newsData['CoverImage'] ? '' : 'display: none;'; ?>">
										<div class="w-full h-64 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 cursor-pointer relative group">
											<img id="imagePreview" src="<?php echo $newsData['CoverImage'] ? $ImgPath . 'news/' . htmlspecialchars($newsData['CoverImage']) : ''; ?>" alt="Preview" class="w-full h-full object-cover" onclick="openImageModal(this.src)">
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
						<div class="bg-gray-50 p-4 rounded-xl border border-gray-200 mt-6">
											<div class="flex items-center justify-between mb-4">
													<h3 class="font-semibold text-gray-700 flex items-center gap-2"><i class="ph ph-images"></i> รูปภาพแกลเลอรี่</h3>
													<?php if(!empty($galleries)): ?>
													<button type="button" class="text-xs bg-blue-100 text-blue-700 px-3 py-1.5 rounded-full hover:bg-blue-200 flex items-center gap-1 font-medium transition-colors" onclick="openManageGalleryModal()">
															<i class="ph ph-squares-four"></i> จัดการรูปภาพ
													</button>
													<?php endif; ?>
											</div>
										
										
								
										<?php if(!empty($galleries)): ?>
										<div class="flex flex-wrap gap-2 pb-2 mb-4" id="galleryPreviewList">
												<?php foreach($galleries as $idx => $gal): ?>
												<div class="relative flex-shrink-0" id="gal-<?php echo $gal['GalleryID']; ?>">
														<?php 
														$safeGalPath = ltrim(str_replace('uploads/images/', '', $gal['ImagePath']), '/');
														?>
														<img src="<?php echo $ImgPath; ?>news/gallery/<?php echo htmlspecialchars($safeGalPath); ?>" class="w-20 h-16 object-cover rounded-md border border-gray-200 cursor-pointer" onclick="openGalleryModal(<?php echo $idx; ?>, true)">
												</div>
												<?php endforeach; ?>
										</div>
										<?php endif; ?>
										<input type="hidden" name="delete_galleries" id="delete_galleries" value="">
										<input type="hidden" name="gallery_order" id="gallery_order" value="">
								
										<input type="file" id="GalleryInput" name="Gallery[]" multiple accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
										<p class="text-xs text-gray-400 mt-2">อัปโหลดได้หลายไฟล์ (รองรับ JPG, PNG)</p>
										<div id="galleryPreviewContainer" class="flex overflow-x-auto gap-2 mt-4 custom-scrollbar pb-2"></div>
						      </div>

										<div class="bg-gray-50 p-4 rounded-xl border border-gray-200 mt-6">
											<h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-paperclip"></i> เอกสารแนบ</h3>
											
											<?php if(!empty($attachments)): ?>
											<div class="space-y-2 mb-4" id="attachmentsSortable">
												<?php foreach($attachments as $att): 
													$pkId = $att['AttachID'] ?? $att['AttachmentID'] ?? '';
												?>
												<div class="flex items-center justify-between p-2 bg-white border border-gray-200 rounded-lg text-sm cursor-move group/att" data-id="<?php echo $pkId; ?>" id="att-<?php echo $pkId; ?>">
														<div class="flex items-center gap-2 flex-1 min-w-0">
																<i class="ph ph-dots-six-vertical text-gray-400 group-hover:text-gray-600 flex-shrink-0"></i>
																<i class="ph ph-file-pdf text-red-500 text-lg flex-shrink-0"></i>
																<a href="<?php echo $FilePath; ?>news/<?php echo htmlspecialchars($att['FilePath']); ?>" target="_blank" class="block truncate hover:text-blue-600 hover:underline"><?php echo htmlspecialchars($att['FileName_TH']); ?></a>
														</div>
														<div class="flex items-center gap-3 flex-shrink-0">
																<a href="<?php echo $FilePath; ?>news/<?php echo htmlspecialchars($att['FilePath']); ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="ดูไฟล์">
																		<i class="ph ph-eye text-lg"></i>
																</a>
																<button type="button" class="text-red-500 hover:text-red-700" onclick="deleteAttachment(this, <?php echo $pkId; ?>)" title="ลบไฟล์">
																		<i class="ph ph-trash text-lg"></i>
																</button>
														</div>
												</div>
												<?php endforeach; ?>
											</div>
											<?php endif; ?>
											<input type="hidden" name="delete_attachments" id="delete_attachments" value="">
											<input type="hidden" name="attachments_order" id="attachments_order" value="">
											
											<input type="file" id="AttachmentsInput" name="Attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
											<p class="text-xs text-gray-400 mt-2">อัปโหลดได้หลายไฟล์ (PDF, Word, Excel)</p>
											<div id="attachmentsPreviewContainer" class="space-y-2 mt-4"></div>
										</div>
				</div>
		</div>

		<div class="mt-8 pt-6 border-t border-gray-100 flex justify-end gap-4">
			<a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
			<button type="submit" class="btn-primary px-8 py-3 text-lg"><i class="ph ph-floppy-disk mr-2"></i> บันทึกข้อมูล</button>
		</div>
	</form>
</div>

<?php include_once('../assets/modules/ckeditor.php'); ?>
<!-- Manage Gallery Modal -->
<div id="manageGalleryModal" class="fixed inset-0 z-[99] bg-black/60 hidden flex items-center justify-center backdrop-blur-md px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-2xl">
            <div>
                <h3 class="text-xl font-bold text-gray-800">จัดการรูปภาพแกลเลอรี่</h3>
                <p class="text-sm text-gray-500 mt-1">ลากเพื่อสลับตำแหน่ง หรือกดกากบาทเพื่อลบรูปภาพ</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-full w-8 h-8 flex items-center justify-center transition-colors" onclick="closeManageGalleryModal()">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 custom-scrollbar bg-gray-50">
            <div id="manageGalleryGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <?php if(!empty($galleries)): ?>
                    <?php foreach($galleries as $idx => $gal): ?>
                    <div class="relative group cursor-move aspect-video rounded-xl overflow-hidden border-2 border-transparent hover:border-blue-400 hover:shadow-lg transition-all" data-id="<?php echo $gal['GalleryID']; ?>" id="manage-gal-<?php echo $gal['GalleryID']; ?>">
                        <?php 
                        $safeGalPathManage = ltrim(str_replace('uploads/images/', '', $gal['ImagePath']), '/');
                        ?>
                        <img src="<?php echo $ImgPath; ?>news/gallery/<?php echo htmlspecialchars($safeGalPathManage); ?>" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                        <button type="button" class="absolute top-2 right-2 bg-red-500/90 hover:bg-red-600 text-white rounded-full w-7 h-7 flex items-center justify-center shadow-sm opacity-0 group-hover:opacity-100 transition-all scale-95 group-hover:scale-100" onclick="markDeleteGallery(<?php echo $gal['GalleryID']; ?>, this)">
                            <i class="ph ph-trash text-sm"></i>
                        </button>
                        <div class="absolute bottom-2 left-2 bg-black/50 text-white text-[10px] px-2 py-0.5 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                            <i class="ph ph-arrows-out-cardinal"></i> ลาก
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if(empty($galleries)): ?>
                <div class="text-center py-12 text-gray-400">
                    <i class="ph ph-images text-4xl mb-2"></i>
                    <p>ยังไม่มีรูปภาพในแกลเลอรี่</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-white rounded-b-2xl">
            <button type="button" class="px-5 py-2 text-gray-600 font-medium hover:bg-gray-100 rounded-lg transition-colors" onclick="closeManageGalleryModal()">ยกเลิก</button>
            <button type="button" class="bg-blue-600 text-white px-6 py-2 font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-colors" onclick="saveManageGalleryModal()">ยืนยัน</button>
        </div>
    </div>
</div>

<!-- Image Viewer Modal (Lightbox) -->
<div id="imageModal" class="fixed inset-0 z-[9999] bg-black/80 hidden flex items-center justify-center backdrop-blur-md" onclick="closeImageModal(event)">
    <button type="button" onclick="document.getElementById('imageModal').classList.add('hidden')" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center hover:scale-105 transition-all shadow-lg border border-white/10 focus:outline-none">
        <i class="ph ph-x text-2xl font-bold"></i>
    </button>
    <button id="modalPrev" class="absolute left-4 text-white text-5xl hover:text-gray-300 transition-colors hidden" onclick="prevModalImage(event)">&#10094;</button>
    <img id="modalImage" src="" class="max-w-[85vw] max-h-[85vh] object-contain rounded-lg shadow-2xl">
    <button id="modalNext" class="absolute right-4 text-white text-5xl hover:text-gray-300 transition-colors hidden" onclick="nextModalImage(event)">&#10095;</button>
</div>

<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
// Datepicker Initialization
flatpickr("#datepicker", {
    dateFormat: "d/m/Y",
    locale: "th",
    allowInput: true
});

document.getElementById('CoverImageInput').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('previewContainer').style.display = 'block';
            document.getElementById('deleteCoverImageFlag').value = '0';
        }
        reader.readAsDataURL(file);
    }
});

function openImageModal(src) {
    if (src && src.trim() !== '') {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').classList.remove('hidden');
    }
}

function removePreviewImage(e) {
    e.stopPropagation(); // prevent modal from opening
    document.getElementById('CoverImageInput').value = '';
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('imagePreview').src = '';
    document.getElementById('deleteCoverImageFlag').value = '1';
}

let tempDeletedGalleries = [];

function openManageGalleryModal() {
    document.getElementById('manageGalleryModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // prevent scrolling behind
}

function closeManageGalleryModal() {
    document.getElementById('manageGalleryModal').classList.add('hidden');
    document.body.style.overflow = '';
    
    // Restore deleted items visually in modal since user canceled
    tempDeletedGalleries.forEach(id => {
        let el = document.getElementById('manage-gal-' + id);
        if (el) el.style.display = '';
    });
    tempDeletedGalleries = [];
    
    // Restore order in modal to match the main preview
    let grid = document.getElementById('manageGalleryGrid');
    let previewList = document.getElementById('galleryPreviewList');
    if(grid && previewList) {
        previewList.querySelectorAll('div[id^="gal-"]').forEach(previewEl => {
            let id = previewEl.dataset?.id || previewEl.id.replace('gal-', '');
            let modalEl = document.getElementById('manage-gal-' + id);
            if(modalEl) grid.appendChild(modalEl); // Append in original order
        });
    }
}

function markDeleteGallery(id, btn) {
    tempDeletedGalleries.push(id);
    btn.closest('.group').style.display = 'none';
}

function saveManageGalleryModal() {
    // 1. Update delete input
    let delInput = document.getElementById('delete_galleries');
    let existingDeleted = delInput.value ? delInput.value.split(',') : [];
    
    tempDeletedGalleries.forEach(id => {
        if(!existingDeleted.includes(id.toString())) existingDeleted.push(id);
        
        // Remove from main preview
        let previewEl = document.getElementById('gal-' + id);
        if (previewEl) previewEl.remove();
        
        // Remove permanently from modal grid so it doesn't come back on next open
        let modalEl = document.getElementById('manage-gal-' + id);
        if (modalEl) modalEl.remove();
    });
    delInput.value = existingDeleted.join(',');
    tempDeletedGalleries = [];
    
    // 2. Update order input
    let order = [];
    let previewList = document.getElementById('galleryPreviewList');
    document.querySelectorAll('#manageGalleryGrid .cursor-move').forEach(el => {
        if (el.style.display !== 'none' && el.dataset.id) {
            order.push(el.dataset.id);
            // Reorder main preview
            let previewEl = document.getElementById('gal-' + el.dataset.id);
            if(previewEl && previewList) {
                previewList.appendChild(previewEl); // Append in new order
            }
        }
    });
    document.getElementById('gallery_order').value = order.join(',');
    
    // 3. Close modal
    document.getElementById('manageGalleryModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function deleteAttachment(btn, id) {
    let el = btn.closest('#att-'+id);
    if (!el) el = document.getElementById('att-'+id);
    if (el) el.remove();
    let delInput = document.getElementById('delete_attachments');
    let vals = delInput.value ? delInput.value.split(',') : [];
    vals.push(id);
    delInput.value = vals.join(',');
}

// Multiple Image Preview
let currentGalleryImages = [];
let currentGalleryIndex = 0;

document.getElementById('GalleryInput').addEventListener('change', function(e) {
    var container = document.getElementById('galleryPreviewContainer');
    container.innerHTML = ''; 
    currentGalleryImages = [];
    var files = e.target.files;
    
    // Also include existing galleries in the array if needed, but for simplicity we'll just handle new ones, or we can merge.
    // To keep it simple, we'll let existing images open their own modal and new images open theirs.
    
    for (var i = 0; i < files.length; i++) {
        var file = files[i];
        if (file) {
            (function(f, index) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    currentGalleryImages[index] = e.target.result;
                    var div = document.createElement('div');
                    div.className = 'relative group flex-shrink-0';
                    div.innerHTML = '<img src="'+e.target.result+'" class="w-32 h-24 object-cover rounded-md border border-gray-200 cursor-pointer" onclick="openGalleryModal('+index+', false)">' +
                                    '<div class="absolute bottom-0 inset-x-0 bg-black bg-opacity-60 text-white text-[10px] truncate px-1 py-0.5 rounded-b-md" title="'+f.name+'">'+f.name+'</div>';
                    container.appendChild(div);
                };
                reader.readAsDataURL(f);
            })(file, i);
        }
    }
});

// Existing Gallery images array for Lightbox
let existingGalleryImages = [
    <?php foreach($galleries as $gal): ?>
    "<?php echo $ImgPath; ?>news/gallery/<?php echo htmlspecialchars($gal['ImagePath']); ?>",
    <?php endforeach; ?>
];

function openGalleryModal(index, isExisting) {
    currentGalleryIndex = index;
    let imagesArray = isExisting ? existingGalleryImages : currentGalleryImages;
    if (imagesArray.length === 0) return;
    
    document.getElementById('modalImage').src = imagesArray[currentGalleryIndex];
    document.getElementById('imageModal').classList.remove('hidden');
    
    // Show/hide arrows
    document.getElementById('modalPrev').classList.toggle('hidden', imagesArray.length <= 1);
    document.getElementById('modalNext').classList.toggle('hidden', imagesArray.length <= 1);
    
    // Store which array we are currently viewing
    document.getElementById('imageModal').dataset.isExisting = isExisting;
}

function nextModalImage(e) {
    e.stopPropagation();
    let isExisting = document.getElementById('imageModal').dataset.isExisting === 'true';
    let imagesArray = isExisting ? existingGalleryImages : currentGalleryImages;
    currentGalleryIndex = (currentGalleryIndex + 1) % imagesArray.length;
    document.getElementById('modalImage').src = imagesArray[currentGalleryIndex];
}

function prevModalImage(e) {
    e.stopPropagation();
    let isExisting = document.getElementById('imageModal').dataset.isExisting === 'true';
    let imagesArray = isExisting ? existingGalleryImages : currentGalleryImages;
    currentGalleryIndex = (currentGalleryIndex - 1 + imagesArray.length) % imagesArray.length;
    document.getElementById('modalImage').src = imagesArray[currentGalleryIndex];
}

function closeImageModal(e) {
    if (e.target.id === 'imageModal') {
        e.target.classList.add('hidden');
    }
}


// Multiple Files Preview
document.getElementById('AttachmentsInput').addEventListener('change', function(e) {
    var container = document.getElementById('attachmentsPreviewContainer');
    container.innerHTML = '';
    var files = e.target.files;
    
    if (files.length > 0) {
        // Add a clear all button
        var clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'text-xs text-red-500 hover:text-red-700 mb-2 flex items-center gap-1';
        clearBtn.innerHTML = '<i class="ph ph-trash"></i> ลบไฟล์ที่เลือกทั้งหมด';
        clearBtn.onclick = function() {
            document.getElementById('AttachmentsInput').value = '';
            container.innerHTML = '';
        };
        container.appendChild(clearBtn);
    }

    for (var i = 0; i < files.length; i++) {
        var file = files[i];
        if (file) {
            let objUrl = URL.createObjectURL(file);
            var div = document.createElement('div');
            div.className = 'flex items-center justify-between p-2 bg-blue-50 border border-blue-100 rounded-lg text-sm text-blue-800';
            div.innerHTML = '<div class="flex items-center gap-2 truncate">' +
                            '<i class="ph ph-file-plus text-lg"></i>' +
                            '<a href="'+objUrl+'" target="_blank" class="truncate hover:underline" title="'+file.name+'">'+file.name+'</a>' +
                            '</div>' +
                            '<a href="'+objUrl+'" target="_blank" class="text-blue-500 hover:text-blue-700" title="ดูไฟล์"><i class="ph ph-eye text-lg"></i></a>';
            container.appendChild(div);
        }
    }
});

// Initialize Sortable for management modal and attachments
if (document.getElementById('manageGalleryGrid')) {
    Sortable.create(document.getElementById('manageGalleryGrid'), {
        animation: 150,
        handle: '.cursor-move',
        ghostClass: 'opacity-50'
    });
}

if (document.getElementById('attachmentsSortable')) {
    Sortable.create(document.getElementById('attachmentsSortable'), {
        animation: 150,
        handle: '.cursor-move',
        onEnd: function () {
            let order = [];
            document.querySelectorAll('#attachmentsSortable .cursor-move').forEach(el => {
                if(el.dataset.id) order.push(el.dataset.id);
            });
            document.getElementById('attachments_order').value = order.join(',');
        }
    });
}
</script>
<?php include_once('../includes/footer.php'); ?>
