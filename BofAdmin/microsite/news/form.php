<?php
define('_VALID_ACCESS', 1);
$pageTitle = isset($_GET['id']) ? 'แก้ไขข่าวสาร' : 'เพิ่มข่าวใหม่';
include_once('../../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>window.location.href = '../index.php';</script>";
    exit;
}

$id = $_GET['id'] ?? null;

// Permission Check
$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = false;
if ($userGroupID === 'A') {
    $hasAccess = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    if ($id) {
        $hasAccess = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'news', 'CanEdit');
    } else {
        $hasAccess = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'news', 'CanAdd');
    }
}

if (!$hasAccess) {
    $errMsg = $id ? 'คุณไม่มีสิทธิ์แก้ไขข้อมูล' : 'คุณไม่มีสิทธิ์เพิ่มข้อมูล';
    echo "<script>alert('$errMsg'); window.location.href = 'index.php';</script>";
    exit;
}
$newsData = [
    'Title_TH' => '', 'Title_EN' => '',
    'Content_TH' => '', 'Content_EN' => '',
    'Detail_TH' => '', 'Detail_EN' => '',
    'Image' => '', 'PublishDate' => date('Y-m-d'),
    'StartDate' => '', 'EndDate' => '',
    'IsActive' => 'Y'
];
$files = [];

if ($id) {
    try {
        if(isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM microsite_news WHERE NewsID = :id AND DeptID = :deptId AND COALESCE(DelData, 'N') <> 'Y' LIMIT 1");
            $stmt->execute([':id' => $id, ':deptId' => $deptID]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if($res) {
                $newsData = $res;
                if(empty($newsData['PublishDate'])) $newsData['PublishDate'] = date('Y-m-d');
            } else {
                echo "<script>alert('ไม่พบข้อมูลข่าวสาร'); window.location.href = 'index.php';</script>";
                exit;
            }
            
            // Files
            $stmtFiles = $DB->prepare("SELECT * FROM microsite_news_files WHERE NewsID = :id AND COALESCE(DelData, 'N') <> 'Y'");
            $stmtFiles->execute([':id' => $id]);
            $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(PDOException $e) {}
}

// Fetch Groups
$groups = [];
try {
    if(isset($DB)) {
        $stmtGroups = $DB->prepare("SELECT GroupID, GroupNameTH FROM microsite_news_groups WHERE DeptID = ? AND (DelData != 'Y' OR DelData IS NULL) ORDER BY OrderNo ASC");
        $stmtGroups->execute([$deptID]);
        $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {}

// Get Slug for image path
$stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID = ?");
$stmtSlug->execute([$deptID]);
$deptSlug = $stmtSlug->fetchColumn();
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
    <input type="hidden" name="action" value="save">
    <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
        <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <h2 class="text-xl font-semibold text-gray-800 flex-1">
            <?php echo $id ? 'แก้ไขข่าวสาร' : 'เพิ่มข่าวใหม่'; ?>
            <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700">
                    <?php echo htmlspecialchars($_SESSION['ActiveDeptName']); ?>
                </span>
            </div>
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
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id ?? ''); ?>">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
                <div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">หัวข้อ (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                        <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($newsData['Title_TH'] ?? ''); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">หัวข้อ (<?php echo $Lang2;?>)</label>
                        <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($newsData['Title_EN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">รายละเอียดย่อ (<?php echo $Lang1;?>)</label>
                        <textarea name="Content_TH" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($newsData['Content_TH'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">รายละเอียดย่อ (<?php echo $Lang2;?>)</label>
                        <textarea name="Content_EN" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($newsData['Content_EN'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">เนื้อหาข่าว (<?php echo $Lang1;?>)</label>
                        <textarea name="Detail_TH" class="ckeditor_full"><?php echo htmlspecialchars($newsData['Detail_TH'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">เนื้อหาข่าว (<?php echo $Lang2;?>)</label>
                        <textarea name="Detail_EN" class="ckeditor_full"><?php echo htmlspecialchars($newsData['Detail_EN'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-info"></i> ข้อมูลทั่วไป</h3>
                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-1">หมวดหมู่ <span class="text-red-500">*</span></label>
                        <select name="GroupID" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white" required>
                            <option value="0">-- เลือกหมวดหมู่ --</option>
                            <?php foreach($groups as $g): ?>
                            <option value="<?php echo $g['GroupID']; ?>" <?php echo ($newsData['GroupID'] ?? 0) == $g['GroupID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g['GroupNameTH']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-1">วันที่ประกาศ <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="text" name="PublishDate" value="<?php echo !empty($newsData['PublishDate']) && $newsData['PublishDate'] != '0000-00-00' ? date('d/m/Y', strtotime($newsData['PublishDate'])) : date('d/m/Y'); ?>" class="datepicker w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <i class="ph ph-calendar text-lg"></i>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-1">วันที่เริ่มแสดงผล</label>
                        <div class="relative">
                            <input type="text" name="StartDate" value="<?php echo !empty($newsData['StartDate']) && $newsData['StartDate'] != '0000-00-00 00:00:00' ? date('d/m/Y', strtotime($newsData['StartDate'])) : ''; ?>" class="datepicker w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <i class="ph ph-calendar text-lg"></i>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-1">วันที่สิ้นสุดแสดงผล</label>
                        <div class="relative">
                            <input type="text" name="EndDate" value="<?php echo !empty($newsData['EndDate']) && $newsData['EndDate'] != '0000-00-00 00:00:00' ? date('d/m/Y', strtotime($newsData['EndDate'])) : ''; ?>" class="datepicker w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <i class="ph ph-calendar text-lg"></i>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-1">วิดีโอ (YouTube URL)</label>
                        <input type="text" name="FileVDO" value="<?php echo htmlspecialchars($newsData['FileVDO'] ?? ''); ?>" placeholder="https://youtube.com/..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white">
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-image"></i> รูปภาพประกอบ</h3>
                    <div class="mb-3" id="previewContainer" style="<?php echo !empty($newsData['Image']) ? '' : 'display: none;'; ?>">
                        <div class="w-full h-48 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 relative group cursor-pointer">
                            <img id="imagePreview" src="<?php echo !empty($newsData['Image']) ? $ImgPath.'microsite/'.$deptSlug.'/'.htmlspecialchars($newsData['Image']) : ''; ?>" class="w-full h-full object-cover" onclick="openImageModal(this.src)">
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                            </div>
                            <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10" onclick="removePreviewImage(event)" title="ลบรูปภาพ">
                                <i class="ph ph-x font-bold"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="delete_image" id="deleteCoverImageFlag" value="0">
                    
                    <div class="mt-2">
                        <input type="file" name="Image" id="CoverImageInput" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">ขนาดที่แนะนำ: <?php echo CONF_IMG_REC_W; ?>x<?php echo CONF_IMG_REC_H; ?> px</p>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 mt-6">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-paperclip"></i> เอกสารแนบ</h3>
                    
                    <?php if(!empty($files)): ?>
                    <div class="space-y-2 mb-4">
                        <?php foreach($files as $f): ?>
                        <div class="flex items-center justify-between p-2 bg-white border border-gray-200 rounded-lg text-sm group" id="file-<?php echo $f['FileID']; ?>">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <i class="ph ph-file-text text-red-500 text-lg flex-shrink-0"></i>
                                <a href="<?php echo $ImgPath; ?>microsite/<?php echo $deptSlug; ?>/<?php echo htmlspecialchars($f['FileName']); ?>" target="_blank" class="block truncate hover:text-blue-600 hover:underline"><?php echo htmlspecialchars($f['FileOriginalName']); ?></a>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <a href="<?php echo $ImgPath; ?>microsite/<?php echo $deptSlug; ?>/<?php echo htmlspecialchars($f['FileName']); ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="ดูไฟล์">
                                    <i class="ph ph-eye text-lg"></i>
                                </a>
                                <button type="button" class="text-red-500 hover:text-red-700" onclick="deleteAttachment(<?php echo $f['FileID']; ?>)" title="ลบไฟล์">
                                    <i class="ph ph-trash text-lg"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div id="hidden_delete_files_container"></div>
                    <input type="file" id="FilesInput" name="Files[]" multiple class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-400 mt-2">อัปโหลดได้หลายไฟล์ (PDF, Word, Excel)</p>
                    <div id="filesPreviewContainer" class="space-y-2 mt-4"></div>
                </div>
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 py-3 text-lg"><i class="ph ph-floppy-disk mr-2"></i> บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<?php include_once('../../assets/modules/ckeditor.php'); ?>

<!-- Image Viewer Modal -->
<div id="imageModal" class="fixed inset-0 z-[9999] bg-black/80 hidden flex items-center justify-center backdrop-blur-md" onclick="closeImageModal(event)">
    <button type="button" onclick="document.getElementById('imageModal').classList.add('hidden')" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center hover:scale-105 transition-all shadow-lg border border-white/10 focus:outline-none">
      <i class="ph ph-x text-2xl font-bold"></i>
    </button>
    <img id="modalImage" src="" class="max-w-[85vw] max-h-[85vh] object-contain rounded-lg shadow-2xl">
</div>

<script>
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
    document.getElementById('CoverImageInput').value = '';
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('imagePreview').src = '';
    document.getElementById('deleteCoverImageFlag').value = '1';
}

function deleteAttachment(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: 'ยืนยันการลบไฟล์เอกสารนี้?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'ยืนยันการลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('file-'+id).style.display = 'none';
            let container = document.getElementById('hidden_delete_files_container');
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_files[]';
            input.value = id;
            container.appendChild(input);
        }
    });
}

document.getElementById('FilesInput').addEventListener('change', function(e) {
    var container = document.getElementById('filesPreviewContainer');
    container.innerHTML = '';
    var files = e.target.files;
    
    if (files.length > 0) {
        // Add a clear all button
        var clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'text-xs text-red-500 hover:text-red-700 mb-2 flex items-center gap-1';
        clearBtn.innerHTML = '<i class="ph ph-trash"></i> ลบไฟล์ที่เลือกทั้งหมด';
        clearBtn.onclick = function() {
            document.getElementById('FilesInput').value = '';
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
</script>

<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
// Datepicker Initialization
flatpickr(".datepicker", {
    dateFormat: "d/m/Y",
    locale: "th",
    allowInput: true
});
</script>

<?php include_once('../../includes/footer.php'); ?>

