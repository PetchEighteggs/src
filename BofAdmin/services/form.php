<?php
define('_VALID_ACCESS', 1);
$serviceTypes = [
	'infographic' => 'สื่อประชาสัมพันธ์',
	'situation' => 'ข้อมูลสถานการณ์น้ำบาดาล',
	'reports' => 'รายงานเกี่ยวกับน้ำบาดาล',
	'mou' => 'บันทึกความเข้าใจ',
	'laws' => 'กฎหมายน้ำบาดาล',
	'downloads' => 'สื่อดาวน์โหลด',
	'standards' => 'มาตรฐานและราคากลางการเจาะน้ำบาดาล',
	'manuals' => 'คู่มือสำหรับประชาชน'
];
$serviceType = $_GET['type'] ?? 'situation';
$serviceName = $serviceTypes[$serviceType] ?? 'จัดการข้อมูลบริการ';
$pageTitle = $serviceName;
include_once('../includes/header.php');
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
	'File' => '',
	'IsActive' => 'Y',
	'OrderNo' => '0'
];

if ($id > 0) {
	try {
		if (isset($DB)) {
			$stmt = $DB->prepare("SELECT * FROM service_docs WHERE DocID = ?");
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

// Fetch Attachments
$attachments = [];
if ($id > 0) {
    try {
        if (isset($DB)) {
            $stmtAtt = $DB->prepare("SELECT * FROM service_doc_attachments WHERE DocID = ? ORDER BY OrderNo ASC");
            $stmtAtt->execute([$id]);
            $attachments = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {}
}

if ($id > 0 && !empty($data['ServiceType'])) {
    $serviceType = $data['ServiceType'];
    $serviceName = $serviceTypes[$serviceType] ?? $serviceName;
}
$isInfographic = $serviceType === 'infographic';
$serviceFilePath = $isInfographic ? $_Config_live_site . '/uploads/images/services/' : $FilePath . 'services/';
// Group removed as requested
?>

<form action="action.php" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="showLoading()">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="DocID" value="<?php echo $data['DocID']; ?>">
    <input type="hidden" name="ServiceType" value="<?php echo htmlspecialchars($serviceType); ?>">

    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php?type=<?php echo urlencode($serviceType); ?>" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        
                        <?php echo $action == 'add' ? 'เพิ่ม'.$serviceName : 'แก้ไข'.$serviceName; ?>
                    </h2>
                   
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
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-5">
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200 space-y-5">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">หัวข้อ (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                        <input type="text" name="TitleTH" value="<?php echo htmlspecialchars($data['TitleTH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">หัวข้อ (<?php echo $Lang2;?>)</label>
                        <input type="text" name="TitleEN" value="<?php echo htmlspecialchars($data['TitleEN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">รายละเอียด (<?php echo $Lang1;?>)</label>
                        <textarea name="DescriptionTH" class="ckeditor_br"><?php echo htmlspecialchars($data['DescriptionTH'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">รายละเอียด (<?php echo $Lang2;?>)</label>
                        <textarea name="DescriptionEN" class="ckeditor_br"><?php echo htmlspecialchars($data['DescriptionEN'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200" style="display: none;">
                    <input type="hidden" name="GroupID" value="0">
                </div>

                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-file-arrow-up"></i> <?php echo $isInfographic ? 'รูปภาพ' : 'ไฟล์แนบ'; ?> </h3>
                    
                    <?php if(!empty($attachments)): ?>
                    <div class="mb-4 space-y-2" id="currentFileContainer">
                        <?php foreach($attachments as $att): 
                            $pkId = $att['AttachID'] ?? $att['AttachmentID'] ?? '';
                        ?>
                        <?php if ($isInfographic): ?>
                        <div class="w-full h-64 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 cursor-pointer relative group" id="att_<?php echo $pkId; ?>">
                            <img src="<?php echo htmlspecialchars($serviceFilePath . $att['FilePath']); ?>" alt="ตัวอย่างรูปภาพ" class="w-full h-full object-contain" onclick="openServiceImageModal(this.src)" loading="lazy">
                            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                            </div>
                            <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10" onclick="removeAttachment(this, <?php echo $pkId; ?>)" aria-label="ลบรูปภาพ">
                                <i class="ph ph-x font-bold"></i>
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="flex items-center justify-between p-2 bg-white border border-gray-200 rounded-lg text-sm group/att" id="att_<?php echo $pkId; ?>">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <i class="ph ph-dots-six-vertical text-gray-400 group-hover/att:text-gray-600 flex-shrink-0"></i>
                                <?php
                                $ext = strtolower(pathinfo($att['FilePath'], PATHINFO_EXTENSION));
                                $icons = ['pdf' => 'ph-file-pdf text-red-500', 'doc' => 'ph-file-doc text-blue-600', 'docx' => 'ph-file-doc text-blue-600', 'xls' => 'ph-file-xls text-green-600', 'xlsx' => 'ph-file-xls text-green-600'];
                                $icon = $icons[$ext] ?? 'ph-file text-gray-500';
                                ?>
                                <i class="ph <?php echo $icon; ?> text-lg flex-shrink-0"></i>
                                <a href="<?php echo htmlspecialchars($serviceFilePath . $att['FilePath']); ?>" target="_blank" class="block truncate hover:text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($att['FilePath']); ?>
                                </a>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <a href="<?php echo htmlspecialchars($serviceFilePath . $att['FilePath']); ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="ดูไฟล์">
                                    <i class="ph ph-eye text-lg"></i>
                                </a>
                                <button type="button" 
                                        onclick="removeAttachment(this, <?php echo $pkId; ?>)"
                                        class="text-red-500 hover:text-red-700" title="ลบไฟล์">
                                    <i class="ph ph-trash text-lg"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div id="deleteAttachmentsContainer"></div>
                    
                    <div class="mt-2">
                        <input type="file" name="UploadFiles[]" id="downloadFile" accept="<?php echo $isInfographic ? '.jpg,.jpeg,.png' : '.pdf,.doc,.docx,.xls,.xlsx'; ?>" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">รองรับ <?php echo $isInfographic ? 'JPG, PNG' : 'PDF, Word, Excel'; ?> (ขนาดไฟล์ไม่เกิน <?php echo CONF_UPLOAD_MAX_MB; ?>MB)</p>
                        <?php if ($isInfographic): ?>
                        <p class="text-xs text-gray-400 mt-2">ขนาดรูปภาพที่แนะนำ: 1200x1200 px</p>
                        <?php endif; ?>
                    </div>
                    <div id="filePreviewContainer" class="mt-4 space-y-2"></div>
                </div>
            </div>
        </div>
        
        <div class="border-t border-gray-100 pt-6 mt-8 flex justify-end gap-3">
            <a href="index.php?type=<?php echo urlencode($serviceType); ?>" class="px-6 py-3 bg-gray-100 text-gray-650 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 h-12 text-base font-semibold shadow-lg shadow-primary/30 flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </form>
</div>

<?php if ($isInfographic): ?>
<div id="serviceImageModal" role="dialog" aria-modal="true" aria-label="ตัวอย่างรูปภาพ" class="fixed inset-0 z-[9999] bg-black/80 hidden flex items-center justify-center backdrop-blur-md" onclick="if (event.target === this) closeServiceImageModal()">
    <button type="button" id="serviceImageModalClose" onclick="closeServiceImageModal()" aria-label="ปิดตัวอย่างรูปภาพ" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center shadow-lg border border-white/10">
        <i class="ph ph-x text-2xl"></i>
    </button>
    <img id="serviceModalImage" src="" alt="ตัวอย่างรูปภาพ" class="max-w-[85vw] max-h-[85vh] object-contain rounded-lg shadow-2xl">
</div>
<?php endif; ?>
<script>
let serviceImagePreviousFocus = null;
function openServiceImageModal(src) {
    serviceImagePreviousFocus = document.activeElement;
    document.getElementById('serviceModalImage').src = src;
    document.getElementById('serviceImageModal').classList.remove('hidden');
    document.getElementById('serviceImageModalClose').focus();
}
function closeServiceImageModal() {
    document.getElementById('serviceImageModal').classList.add('hidden');
    if (serviceImagePreviousFocus) serviceImagePreviousFocus.focus();
}
document.addEventListener('keydown', function(event) {
    const modal = document.getElementById('serviceImageModal');
    if (modal && !modal.classList.contains('hidden')) {
        if (event.key === 'Escape') closeServiceImageModal();
        if (event.key === 'Tab') {
            event.preventDefault();
            document.getElementById('serviceImageModalClose').focus();
        }
    }
});
let servicePreviewUrls = [];
function clearServicePreview() {
    servicePreviewUrls.forEach(function(url) { URL.revokeObjectURL(url); });
    servicePreviewUrls = [];
    document.getElementById('filePreviewContainer').innerHTML = '';
}
function removeAttachment(btn, attId) {
    let el = btn.closest('#att_' + attId);
    if (!el) el = document.getElementById('att_' + attId);
    if (el) {
        el.remove();
    }
    
    let container = document.getElementById('deleteAttachmentsContainer');
    if (container) {
        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_attachments[]';
        input.value = attId;
        container.appendChild(input);
    }
}

document.getElementById('downloadFile').addEventListener('change', function(e) {
    var container = document.getElementById('filePreviewContainer');
    clearServicePreview();
    var files = e.target.files;

    if (<?php echo $isInfographic ? 'true' : 'false'; ?>) {
        Array.from(files).forEach(function(file) {
            const url = URL.createObjectURL(file);
            servicePreviewUrls.push(url);
            const card = document.createElement('div');
            card.className = 'w-full h-64 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 cursor-pointer relative group';
            card.innerHTML = '<div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"><i class="ph ph-magnifying-glass-plus text-white text-3xl"></i></div>';
            const preview = document.createElement('img');
            preview.src = url;
            preview.alt = file.name;
            preview.className = 'w-full h-full object-contain';
            preview.onclick = function() { openServiceImageModal(url); };
            card.prepend(preview);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10';
            remove.setAttribute('aria-label', 'ยกเลิกการเลือกรูปภาพ');
            remove.innerHTML = '<i class="ph ph-x font-bold"></i>';
            remove.onclick = function() {
                document.getElementById('downloadFile').value = '';
                clearServicePreview();
            };
            card.appendChild(remove);
            container.appendChild(card);
        });
        return;
    }
    
    if (files.length > 0) {
        // Add clear button
        var clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'text-xs text-red-500 hover:text-red-700 mb-2 flex items-center gap-1';
        clearBtn.innerHTML = '<i class="ph ph-trash"></i> ยกเลิกการเลือกไฟล์ทั้งหมด';
        clearBtn.onclick = function() {
            document.getElementById('downloadFile').value = '';
            container.innerHTML = '';
        };
        container.appendChild(clearBtn);

        for (var i = 0; i < files.length; i++) {
            let file = files[i];
            let objUrl = URL.createObjectURL(file);
            servicePreviewUrls.push(objUrl);
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
</script>

<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const groupSelect = document.getElementById('GroupID');
        if (groupSelect) {
        }
        
        // Datepicker Initialization
        if (typeof flatpickr !== 'undefined') {
            flatpickr(".datepicker", {
                dateFormat: "d/m/Y",
                locale: "th",
                allowInput: true
            });
        }
    });
</script>
<?php include_once('../assets/modules/ckeditor.php'); ?>
<?php include_once('../includes/footer.php'); ?>
