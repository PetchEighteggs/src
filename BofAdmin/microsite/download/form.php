<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการดาวน์โหลด';
include_once('../../includes/header.php');
require_once('../../includes/action_security.php');

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
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'download', $permRequired)) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = 'index.php';</script>";
    exit;
}

$data = [
    'DownloadID' => 0,
    'GroupID' => 0,
    'SubGroupID' => 0,
    'Title_TH' => '',
    'Title_EN' => '',
    'File' => '',
    'IsActive' => 'Y',
    'OrderNo' => '0'
];

if ($id > 0) {
    try {
        if (isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM microsite_download WHERE DownloadID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
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
        $stmtGroups = $DB->prepare("SELECT GroupID, GroupNameTH FROM microsite_download_groups WHERE DeptID = ? AND IsActive = 'Y' AND COALESCE(DelData, 'N') <> 'Y' ORDER BY OrderNo ASC");
        $stmtGroups->execute([$deptID]);
        $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {}

// Fetch Subgroups
$subgroups = [];
try {
    if(isset($DB)) {
        $stmtSubGroups = $DB->prepare("
            SELECT s.SubGroupID, s.GroupID, s.SubGroupNameTH 
            FROM microsite_download_subgroups s
            JOIN microsite_download_groups g ON s.GroupID = g.GroupID
            WHERE g.DeptID = ? AND s.IsActive = 'Y'
                AND COALESCE(g.DelData, 'N') <> 'Y' AND COALESCE(s.DelData, 'N') <> 'Y'
            ORDER BY s.OrderNo ASC
        ");
        $stmtSubGroups->execute([$deptID]);
        $subgroups = $stmtSubGroups->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {}
?>

<form action="action.php" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="showLoading()">
    <?php echo bofSecurityCsrfField(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="DownloadID" value="<?php echo $data['DownloadID']; ?>">

    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        
                        <?php echo $action == 'add' ? 'เพิ่มข้อมูลไดาวน์โหลดใหม่' : 'แก้ไขข้อมูลไฟล์ดาวน์โหลด'; ?>
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
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-paperclip"></i>เอกสารแนบ</h3>
                     
                    <?php if(!empty($data['File'])): ?>
                    <div class="space-y-2 mb-4">
                        <div id="currentFileBox" class="flex items-center justify-between p-2 bg-white border border-gray-200 rounded-lg text-sm group">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <i class="ph ph-file-pdf text-red-500 text-lg flex-shrink-0"></i>
                                <a href="<?php echo $FilePath; ?>microsite/<?php echo rawurlencode($deptSlug); ?>/<?php echo rawurlencode($data['File']); ?>" target="_blank" class="block truncate hover:text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($data['File'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <a href="<?php echo $FilePath; ?>microsite/<?php echo rawurlencode($deptSlug); ?>/<?php echo rawurlencode($data['File']); ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="ดูไฟล์">
                                    <i class="ph ph-eye text-lg"></i>
                                </a>
                                <button type="button" class="text-red-500 hover:text-red-700" onclick="removeFile()" title="ลบไฟล์">
                                    <i class="ph ph-trash text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <input type="hidden" name="delete_file" id="deleteFileFlag" value="0">
                    
                    <div class="mt-2">
                        <input type="file" name="UploadFile" id="downloadFile" accept=".pdf,.doc,.docx,.xls,.xlsx" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">รองรับไฟล์ (PDF, Word, Excel)</p>
                        <div id="filePreviewContainer" class="space-y-2 mt-4"></div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">หมวดหมู่ <span class="text-red-500">*</span></label>
                        <select name="GroupID" id="GroupID" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                            <option value="">-- เลือกหมวดหมู่ --</option>
                            <?php foreach($groups as $g): ?>
                            <option value="<?php echo $g['GroupID']; ?>" <?php echo ($data['GroupID'] ?? 0) == $g['GroupID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g['GroupNameTH']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">หมวดหมู่ย่อย</label>
                        <select name="SubGroupID" id="SubGroupID" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                            <option value="0">-- เลือกหมวดหมู่ย่อย --</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อไฟล์ (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                    <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($data['Title_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อไฟล์ (<?php echo $Lang2;?>)</label>
                    <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($data['Title_EN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">รายละเอียด (<?php echo $Lang1;?>)</label>
                    <textarea name="Detail_TH" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none"><?php echo htmlspecialchars($data['Detail_TH'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">รายละเอียด (<?php echo $Lang2;?>)</label>
                    <textarea name="Detail_EN" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none"><?php echo htmlspecialchars($data['Detail_EN'] ?? ''); ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">วันที่เริ่มแสดงผล</label>
                        <div class="relative">
                            <input type="text" name="StartDate" value="<?php echo !empty($data['StartDate']) && $data['StartDate'] != '0000-00-00 00:00:00' ? date('d/m/Y', strtotime($data['StartDate'])) : ''; ?>" class="datepicker w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <i class="ph ph-calendar text-lg"></i>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">วันที่สิ้นสุดแสดงผล</label>
                        <div class="relative">
                            <input type="text" name="EndDate" value="<?php echo !empty($data['EndDate']) && $data['EndDate'] != '0000-00-00 00:00:00' ? date('d/m/Y', strtotime($data['EndDate'])) : ''; ?>" class="datepicker w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <i class="ph ph-calendar text-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="border-t border-gray-100 pt-6 mt-8 flex justify-end gap-3">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-650 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 h-12 text-base font-semibold shadow-lg shadow-primary/30 flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </form>
</div>

<script>
const downloadFileInput = document.getElementById('downloadFile');
const deleteFileFlag = document.getElementById('deleteFileFlag');
const filePreviewContainer = document.getElementById('filePreviewContainer');
let selectedFileUrl = null;

function removeFile() {
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
        if (!result.isConfirmed) return;

        const currentFileBox = document.getElementById('currentFileBox');
        if (currentFileBox) currentFileBox.style.display = 'none';
        deleteFileFlag.value = '1';
    });
}

downloadFileInput.addEventListener('change', function (event) {
    const file = event.target.files && event.target.files[0];
    filePreviewContainer.innerHTML = '';
    if (!file) return;

    if (selectedFileUrl) URL.revokeObjectURL(selectedFileUrl);
    selectedFileUrl = URL.createObjectURL(file);

    const currentFileBox = document.getElementById('currentFileBox');
    if (currentFileBox) currentFileBox.style.display = 'none';

    const previewRow = document.createElement('div');
    previewRow.className = 'flex items-center justify-between p-2 bg-blue-50 border border-blue-100 rounded-lg text-sm text-blue-800';

    const fileInfo = document.createElement('div');
    fileInfo.className = 'flex items-center gap-2 flex-1 min-w-0';
    fileInfo.innerHTML = '<i class="ph ph-file-plus text-lg flex-shrink-0"></i>';

    const fileLink = document.createElement('a');
    fileLink.href = selectedFileUrl;
    fileLink.target = '_blank';
    fileLink.className = 'block truncate hover:underline';
    fileLink.title = file.name;
    fileLink.textContent = file.name;
    fileInfo.appendChild(fileLink);

    const actions = document.createElement('div');
    actions.className = 'flex items-center gap-3 flex-shrink-0';
    actions.innerHTML =
        '<a href="' + selectedFileUrl + '" target="_blank" class="text-blue-500 hover:text-blue-700" title="ดูไฟล์"><i class="ph ph-eye text-lg"></i></a>' +
        '<button type="button" class="text-red-500 hover:text-red-700" onclick="clearNewFile()" title="ลบไฟล์ที่เลือก"><i class="ph ph-trash text-lg"></i></button>';

    previewRow.appendChild(fileInfo);
    previewRow.appendChild(actions);
    filePreviewContainer.appendChild(previewRow);
    deleteFileFlag.value = '0';
});

function clearNewFile() {
    downloadFileInput.value = '';
    filePreviewContainer.innerHTML = '';

    const currentFileBox = document.getElementById('currentFileBox');
    if (currentFileBox && deleteFileFlag.value === '0') {
        currentFileBox.style.display = 'flex';
    }

    if (selectedFileUrl) {
        URL.revokeObjectURL(selectedFileUrl);
        selectedFileUrl = null;
    }
}
</script>

<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
    // Subgroup Dynamic Dropdown
    const allSubgroups = <?php echo json_encode($subgroups ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const initialSubGroupId = <?php echo (int)($data['SubGroupID'] ?? 0); ?>;

    function updateSubgroupDropdown(keepInitialSelection = false) {
        const groupSelect = document.getElementById('GroupID');
        const subGroupSelect = document.getElementById('SubGroupID');
        if (!groupSelect || !subGroupSelect) return;

        const groupId = Number(groupSelect.value);
        const matchingSubgroups = Array.isArray(allSubgroups)
            ? allSubgroups.filter((subgroup) => Number(subgroup.GroupID) === groupId)
            : [];

        subGroupSelect.replaceChildren();

        const placeholder = document.createElement('option');
        placeholder.value = '0';
        placeholder.textContent = groupId > 0
            ? '-- เลือกหมวดหมู่ย่อย --'
            : '-- กรุณาเลือกหมวดหมู่ก่อน --';
        subGroupSelect.appendChild(placeholder);

        matchingSubgroups.forEach((subgroup) => {
            const option = document.createElement('option');
            option.value = subgroup.SubGroupID;
            option.textContent = subgroup.SubGroupNameTH;
            option.selected = keepInitialSelection && Number(subgroup.SubGroupID) === initialSubGroupId;
            subGroupSelect.appendChild(option);
        });

        subGroupSelect.disabled = groupId <= 0 || matchingSubgroups.length === 0;
    }
    
    document.addEventListener("DOMContentLoaded", function() {
        const groupSelect = document.getElementById('GroupID');
        if (groupSelect) {
            groupSelect.addEventListener('change', () => updateSubgroupDropdown(false));
            updateSubgroupDropdown(true);
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

<?php include_once('../../includes/footer.php'); ?>
