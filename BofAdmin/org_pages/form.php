<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'แก้ไขหน้าเนื้อหาองค์กร';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

$pageData = [];
try {
    if (isset($DB)) {
			$stmt = $DB->prepare("SELECT * FROM org_pages WHERE PageID = ? LIMIT 1");
			$stmt->execute([$id]);
			$pageData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

$attachments = [];
try {
    if (isset($DB)) {
			$stmtAtt = $DB->prepare("SELECT * FROM org_pages_attachments WHERE PageID = ? AND DelData = 'N' ORDER BY OrderNo ASC, AttachmentID ASC");
			$stmtAtt->execute([$id]);
			$attachments = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

$timelines = [];
try {
    if (isset($DB)) {
			$stmtTL = $DB->prepare("SELECT * FROM org_pages_timelines WHERE PageID = ? AND DelData = 'N' ORDER BY OrderNo ASC, TimelineID ASC");
			$stmtTL->execute([$id]);
			$timelines = $stmtTL->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

if (!$pageData) { header("Location: index.php"); exit; }

$hasFile = $pageData['HasFile'] ?? 'N';
$canFile = $pageData['CanFile'] ?? 'Y';
$hasTimeline = $pageData['HasTimeline'] ?? 'N';
$canTimeline = (trim($pageData['Title_TH'] ?? '') === 'ประวัติความเป็นมา') ? 'Y' : 'N';
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
        <?php echo bofSecurityCsrfField(); ?>
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
					<a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
						<i class="ph ph-arrow-left text-xl"></i>
					</a>
					<h2 class="text-xl font-semibold text-gray-800 flex-1">
						แก้ไขเนื้อหา: <span class="text-primary"><?php echo htmlspecialchars($pageData['Title_TH']); ?></span>
					</h2>
        </div>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <div class="mb-6">
          <label class="block text-gray-700 font-medium mb-2">เนื้อหา (ภาษาไทย)</label>
          <textarea name="Content_TH" id="editor_th" class="ckeditor_full w-full"><?php echo htmlspecialchars($pageData['Content_TH'] ?? ''); ?></textarea>
        </div>
        <div class="mb-6">
          <label class="block text-gray-700 font-medium mb-2">เนื้อหา (ภาษาอังกฤษ)</label>
          <textarea name="Content_EN" id="editor_en" class="ckeditor_full w-full"><?php echo htmlspecialchars($pageData['Content_EN'] ?? ''); ?></textarea>
        </div>
        <?php if ($canTimeline === 'Y'): ?>
        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6">
            <div class="flex items-center justify-between mb-4">
							<h3 class="font-semibold text-gray-700 flex items-center gap-2"><i class="ph ph-clock"></i> ข้อมูลไทม์ไลน์</h3>
							<label class="relative inline-flex items-center cursor-pointer">
								<input type="checkbox" id="toggleTimelines" name="HasTimeline" value="Y" class="sr-only peer" <?php echo $hasTimeline === 'Y' ? 'checked' : ''; ?> onchange="toggleTimelinesSection()">
								<div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
								<span class="ml-3 text-sm font-medium text-gray-700">ต้องการแสดงข้อมูลแบบไทม์ไลน์</span>
							</label>
            </div>
            <div id="timelinesSection" style="<?php echo $hasTimeline === 'Y' ? '' : 'display: none;'; ?>">
							<div class="space-y-4 mb-4" id="timelinesSortable">
								<?php if(!empty($timelines)): ?>
									<?php foreach($timelines as $tl): ?>
									<div class="p-4 bg-white border border-gray-200 rounded-lg group timeline-item" data-id="<?php echo $tl['TimelineID']; ?>" id="tl-<?php echo $tl['TimelineID']; ?>">
											<input type="hidden" name="TimelineIDs[]" value="<?php echo $tl['TimelineID']; ?>">
											<div class="flex items-center justify-between mb-2 border-b border-gray-100 pb-2">
													<div class="flex items-center gap-2">
															<i class="ph ph-dots-six-vertical text-gray-400 hover:text-gray-600 cursor-move text-lg" title="ลากเพื่อจัดลำดับ"></i>
															<span class="font-medium text-gray-600 timeline-number-label">ไทม์ไลน์</span>
													</div>
													<div class="flex items-center gap-2">
															<label class="relative inline-flex items-center cursor-pointer mr-2">
																<input type="hidden" name="Timeline_IsHeader[]" value="<?php echo isset($tl['IsHeader']) ? $tl['IsHeader'] : 'N'; ?>" id="is-header-hidden-<?php echo $tl['TimelineID']; ?>">
																<input type="checkbox" class="sr-only peer" <?php echo (isset($tl['IsHeader']) && $tl['IsHeader'] === 'Y') ? 'checked' : ''; ?> onchange="toggleTimelineHeaderMode('<?php echo $tl['TimelineID']; ?>', this)">
																<div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
																<span class="ml-2 text-xs font-medium text-gray-500">ตั้งเป็นหัวข้อหลัก</span>
															</label>
															<button type="button" class="text-gray-400 hover:text-blue-500 p-1 flex items-center justify-center transition-colors" onclick="toggleTimelineBody('<?php echo $tl['TimelineID']; ?>')" title="ย่อ/ขยาย">
																	<i class="ph ph-caret-right text-lg" id="icon-toggle-<?php echo $tl['TimelineID']; ?>"></i>
															</button>
													</div>
											</div>
											<div id="body-tl-<?php echo $tl['TimelineID']; ?>" class="timeline-body mt-3" style="display: none;">
													<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
															<div class="timeline-fields-year-<?php echo $tl['TimelineID']; ?>" style="<?php echo (isset($tl['IsHeader']) && $tl['IsHeader'] === 'Y') ? 'display:none;' : ''; ?>">
																	<label class="block text-gray-600 text-sm mb-1">ปี (ภาษาไทย)</label>
																	<input type="text" name="Timeline_Year_TH[]" value="<?php echo htmlspecialchars($tl['Year_TH'] ?? ''); ?>" placeholder="เช่น พ.ศ. 2450" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm">
															</div>
															<div class="timeline-fields-year-<?php echo $tl['TimelineID']; ?>" style="<?php echo (isset($tl['IsHeader']) && $tl['IsHeader'] === 'Y') ? 'display:none;' : ''; ?>">
																	<label class="block text-gray-600 text-sm mb-1">ปี (ภาษาอังกฤษ)</label>
																	<input type="text" name="Timeline_Year_EN[]" value="<?php echo htmlspecialchars($tl['Year_EN'] ?? ''); ?>" placeholder="e.g. 1907" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm">
															</div>
															<div>
																	<label class="block text-gray-600 text-sm mb-1">หัวข้อ (ภาษาไทย)</label>
																	<input type="text" name="Timeline_Title_TH[]" value="<?php echo htmlspecialchars($tl['Title_TH'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm">
															</div>
															<div>
																	<label class="block text-gray-600 text-sm mb-1">หัวข้อ (ภาษาอังกฤษ)</label>
																	<input type="text" name="Timeline_Title_EN[]" value="<?php echo htmlspecialchars($tl['Title_EN'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm">
															</div>
													</div>
													<div class="grid grid-cols-1 md:grid-cols-2 gap-4 timeline-fields-desc-<?php echo $tl['TimelineID']; ?>" style="<?php echo (isset($tl['IsHeader']) && $tl['IsHeader'] === 'Y') ? 'display:none;' : ''; ?>">
															<div>
																	<label class="block text-gray-600 text-sm mb-1">รายละเอียด (ภาษาไทย)</label>
																	<textarea name="Timeline_Desc_TH[]" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm"><?php echo htmlspecialchars($tl['Description_TH'] ?? ''); ?></textarea>
															</div>
															<div>
																	<label class="block text-gray-600 text-sm mb-1">รายละเอียด (ภาษาอังกฤษ)</label>
																	<textarea name="Timeline_Desc_EN[]" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm"><?php echo htmlspecialchars($tl['Description_EN'] ?? ''); ?></textarea>
															</div>
													</div>
													<div class="flex justify-end mt-4 pt-4 border-t border-gray-100">
															<button type="button" class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded bg-red-50 text-red-500 hover:bg-red-100 transition-colors" onclick="deleteTimeline('<?php echo $tl['TimelineID']; ?>')" title="ลบรายการ">
																	<i class="ph ph-trash text-lg"></i>
															</button>
													</div>
											</div>
									</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
							<input type="hidden" name="delete_timelines" id="delete_timelines" value="">
							<button type="button" onclick="addTimelineItem()" class="px-4 py-2 bg-blue-50 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors text-sm flex items-center justify-center w-full mt-2 border-dashed">
								<i class="ph ph-plus mr-2"></i> เพิ่มไทม์ไลน์ใหม่
							</button>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($canFile === 'Y'): ?>
        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-700 flex items-center gap-2"><i class="ph ph-paperclip"></i> เอกสารแนบ</h3>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="toggleAttachments" name="HasFile" value="Y" class="sr-only peer" <?php echo $hasFile === 'Y' ? 'checked' : ''; ?> onchange="toggleAttachmentsSection()">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    <span class="ml-3 text-sm font-medium text-gray-700">ต้องการแนบไฟล์เอกสาร</span>
                </label>
            </div>
            
            <div id="attachmentsSection" style="<?php echo $hasFile === 'Y' ? '' : 'display: none;'; ?>">
                <?php if(!empty($attachments)): ?>
                <div class="space-y-2 mb-4" id="attachmentsSortable">
                    <?php foreach($attachments as $att): ?>
                    <div class="flex items-center justify-between p-2 bg-white border border-gray-200 rounded-lg text-sm cursor-move group" data-id="<?php echo $att['AttachmentID']; ?>" id="att-<?php echo $att['AttachmentID']; ?>">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <i class="ph ph-dots-six-vertical text-gray-400 group-hover:text-gray-600 flex-shrink-0"></i>
                            <?php 
                                $ext = strtolower($att['FileType'] ?? '');
                                $imageExts = ['jpg', 'jpeg', 'gif', 'png', 'svg', 'webp'];
                                
                                $fileIcon = 'ph-file text-gray-500';
                                if ($ext === 'pdf') {
                                    $fileIcon = 'ph-file-pdf text-red-500';
                                } elseif (in_array($ext, ['doc', 'docx'])) {
                                    $fileIcon = 'ph-file-doc text-blue-600';
                                } elseif (in_array($ext, ['xls', 'xlsx'])) {
                                    $fileIcon = 'ph-file-xls text-green-600';
                                } elseif (in_array($ext, $imageExts)) {
                                    $fileIcon = 'ph-image text-blue-500';
                                }
                                
                                if (in_array($ext, $imageExts)) {
                                    $safeFilePath = $ImgPath . 'structure/' . ltrim($att['FilePath'], '/');
                                } else {
                                    $safeFilePath = $FilePath . 'structure/' . ltrim($att['FilePath'], '/');
                                }
                            ?>
                            <i class="ph <?php echo $fileIcon; ?> text-lg flex-shrink-0"></i>
                            <a href="<?php echo $safeFilePath; ?>" target="_blank" class="block truncate hover:text-blue-600 hover:underline"><?php echo htmlspecialchars($att['FileName_TH'] ?? basename($att['FilePath'])); ?></a>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <a href="<?php echo $safeFilePath; ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="ดูไฟล์">
                                <i class="ph ph-eye text-lg"></i>
                            </a>
                            <button type="button" class="text-red-500 hover:text-red-700" onclick="deleteAttachment(<?php echo $att['AttachmentID']; ?>)" title="ลบไฟล์">
                                <i class="ph ph-trash text-lg"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <input type="hidden" name="delete_attachments" id="delete_attachments" value="">
                <input type="hidden" name="attachments_order" id="attachments_order" value="">
                
                <input type="file" id="AttachmentsInput" name="Attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="text-xs text-gray-400 mt-2">อัปโหลดได้หลายไฟล์ (PDF, JPG, PNG)</p>
                <div id="attachmentsPreviewContainer" class="space-y-2 mt-4"></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex gap-3 pt-4 border-t border-gray-100 justify-end">
            <a href="index.php" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition-colors font-medium flex items-center justify-center">
                <i class="ph ph-x mr-2"></i> ยกเลิก
            </a>
            <?php if ($userPermissions['edit']): ?>
            <button type="submit" class="btn-primary px-8 py-3 flex items-center justify-center">
                <i class="ph ph-floppy-disk mr-2"></i> บันทึกข้อมูล
            </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php include_once('../assets/modules/ckeditor.php'); ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
function toggleAttachmentsSection() {
    const isChecked = document.getElementById('toggleAttachments').checked;
    document.getElementById('attachmentsSection').style.display = isChecked ? 'block' : 'none';
}

function deleteAttachment(id) {
    document.getElementById('att-' + id).style.display = 'none';
    let delInput = document.getElementById('delete_attachments');
    let delVals = delInput.value ? delInput.value.split(',') : [];
    delVals.push(id);
    delInput.value = delVals.join(',');
}

function removeSelectedFile(index) {
    const input = document.getElementById('AttachmentsInput');
    const dt = new DataTransfer();
    for(let i = 0; i < input.files.length; i++) {
        if(i !== index) dt.items.add(input.files[i]);
    }
    input.files = dt.files;
    input.dispatchEvent(new Event('change'));
}

function toggleTimelinesSection() {
    const isChecked = document.getElementById('toggleTimelines').checked;
    document.getElementById('timelinesSection').style.display = isChecked ? 'block' : 'none';
}

function toggleTimelineBody(id) {
    const body = document.getElementById('body-tl-' + id);
    const icon = document.getElementById('icon-toggle-' + id);
    if (body.style.display === 'none') {
        body.style.display = 'block';
        icon.classList.remove('ph-caret-right');
        icon.classList.add('ph-caret-down');
    } else {
        body.style.display = 'none';
        icon.classList.remove('ph-caret-down');
        icon.classList.add('ph-caret-right');
    }
}

function updateTimelineNumbers() {
    const labels = document.querySelectorAll('#timelinesSortable .timeline-number-label');
    labels.forEach((label, index) => {
        label.innerText = `ไทม์ไลน์ที่ ${index + 1}`;
    });
}

function toggleTimelineHeaderMode(id, el) {
    const isHeader = el.checked;
    document.getElementById('is-header-hidden-' + id).value = isHeader ? 'Y' : 'N';
    
    const yearFields = document.querySelectorAll('.timeline-fields-year-' + id);
    yearFields.forEach(field => {
        field.style.display = isHeader ? 'none' : 'block';
    });
    
    const descField = document.querySelector('.timeline-fields-desc-' + id);
    if (descField) {
        descField.style.display = isHeader ? 'none' : 'grid'; // it's a grid container
    }
}

function deleteTimeline(id) {
    document.getElementById('tl-' + id).remove();
    if (id && !id.toString().startsWith('new_')) {
        let delInput = document.getElementById('delete_timelines');
        let delVals = delInput.value ? delInput.value.split(',') : [];
        delVals.push(id);
        delInput.value = delVals.join(',');
    }
    updateTimelineNumbers();
}

let newTimelineCounter = 1;
function addTimelineItem() {
    const container = document.getElementById('timelinesSortable');
    const tempId = 'new_' + newTimelineCounter++;
    const html = `
        <div class="p-4 bg-white border border-gray-200 rounded-lg group timeline-item" data-id="${tempId}" id="tl-${tempId}">
            <input type="hidden" name="TimelineIDs[]" value="new">
            <div class="flex items-center justify-between mb-2 border-b border-gray-100 pb-2">
                <div class="flex items-center gap-2">
                    <i class="ph ph-dots-six-vertical text-gray-400 hover:text-gray-600 cursor-move text-lg" title="ลากเพื่อจัดลำดับ"></i>
                    <span class="font-medium text-blue-500 timeline-number-label">ไทม์ไลน์ใหม่</span>
                </div>
                <div class="flex items-center gap-2">
                    <label class="relative inline-flex items-center cursor-pointer mr-2">
                        <input type="hidden" name="Timeline_IsHeader[]" value="N" id="is-header-hidden-${tempId}">
                        <input type="checkbox" class="sr-only peer" onchange="toggleTimelineHeaderMode('${tempId}', this)">
                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                        <span class="ml-2 text-xs font-medium text-gray-500">ตั้งเป็นหัวข้อหลัก</span>
                    </label>
                    <button type="button" class="text-gray-400 hover:text-blue-500 p-1 flex items-center justify-center transition-colors" onclick="toggleTimelineBody('${tempId}')" title="ย่อ/ขยาย">
                        <i class="ph ph-caret-down text-lg" id="icon-toggle-${tempId}"></i>
                    </button>
                </div>
            </div>
            <div id="body-tl-${tempId}" class="timeline-body mt-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="timeline-fields-year-${tempId}">
                        <label class="block text-gray-600 text-sm mb-1">ปี (ภาษาไทย)</label>
                        <input type="text" name="Timeline_Year_TH[]" placeholder="เช่น พ.ศ. 2450" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm">
                    </div>
                    <div class="timeline-fields-year-${tempId}">
                        <label class="block text-gray-600 text-sm mb-1">ปี (ภาษาอังกฤษ)</label>
                        <input type="text" name="Timeline_Year_EN[]" placeholder="e.g. 1907" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm">
                    </div>
                    <div>
                        <label class="block text-gray-600 text-sm mb-1">หัวข้อ (ภาษาไทย)</label>
                        <input type="text" name="Timeline_Title_TH[]" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm">
                    </div>
                    <div>
                        <label class="block text-gray-600 text-sm mb-1">หัวข้อ (ภาษาอังกฤษ)</label>
                        <input type="text" name="Timeline_Title_EN[]" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 timeline-fields-desc-${tempId}">
                    <div>
                        <label class="block text-gray-600 text-sm mb-1">รายละเอียด (ภาษาไทย)</label>
                        <textarea name="Timeline_Desc_TH[]" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-600 text-sm mb-1">รายละเอียด (ภาษาอังกฤษ)</label>
                        <textarea name="Timeline_Desc_EN[]" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm"></textarea>
                    </div>
                </div>
                <div class="flex justify-end mt-4 pt-4 border-t border-gray-100">
                    <button type="button" class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded bg-red-50 text-red-500 hover:bg-red-100 transition-colors" onclick="deleteTimeline('${tempId}')" title="ลบรายการ">
                        <i class="ph ph-trash text-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    updateTimelineNumbers();
}

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('attachmentsSortable')) {
        Sortable.create(document.getElementById('attachmentsSortable'), {
            animation: 150,
            ghostClass: 'bg-blue-50',
            handle: '.cursor-move',
            onEnd: function() {
                let order = [];
                document.querySelectorAll('#attachmentsSortable .cursor-move').forEach(el => {
                    // Check if it's the actual wrapper element, not just the handle icon
                    const parent = el.closest('.group');
                    if (parent && parent.style.display !== 'none' && !order.includes(parent.dataset.id)) {
                        order.push(parent.dataset.id);
                    }
                });
                document.getElementById('attachments_order').value = order.join(',');
            }
        });
    }

    if (document.getElementById('timelinesSortable')) {
        Sortable.create(document.getElementById('timelinesSortable'), {
            animation: 150,
            handle: '.cursor-move',
            ghostClass: 'bg-blue-50',
            onEnd: function() {
                updateTimelineNumbers();
            }
        });
        updateTimelineNumbers();
    }

    document.getElementById('AttachmentsInput').addEventListener('change', function(e) {
        var container = document.getElementById('attachmentsPreviewContainer');
        container.innerHTML = '';
        var files = e.target.files;
        if(files && files.length > 0) {
            for(var i = 0; i < files.length; i++) {
                var file = files[i];
                container.innerHTML += `
                    <div class="flex items-center gap-2 p-2 bg-blue-50 border border-blue-100 rounded-lg text-sm text-blue-700">
                        <i class="ph ph-file-pdf text-red-500"></i>
                        <span class="truncate">${file.name}</span>
                        <span class="text-xs text-blue-400">(${(file.size/1024/1024).toFixed(2)} MB)</span>
                        <button type="button" class="ml-auto text-red-400 hover:text-red-600 p-1" onclick="removeSelectedFile(${i})" title="ยกเลิกไฟล์นี้">
                            <i class="ph ph-x"></i>
                        </button>
                    </div>
                `;
            }
        }
    });
});
</script>
<?php include_once('../includes/footer.php'); ?>
