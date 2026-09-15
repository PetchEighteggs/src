<?php
define('_VALID_ACCESS', 1);
$pageTitle = isset($_GET['id']) ? 'แก้ไขเกี่ยวกับหน่วยงาน' : 'เพิ่มเกี่ยวกับหน่วยงาน';
include_once('../../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>alert('กรุณาเลือกหน่วยงานก่อนจัดการข้อมูล'); window.location.href='../index.php';</script>";
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = false;
if ($userGroupID === 'A') {
    $hasAccess = true;
} elseif ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $permission = $id > 0 ? 'CanEdit' : 'CanAdd';
    $hasAccess = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'about', $permission);
}

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href='index.php';</script>";
    exit;
}

$data = [
    'Title_TH' => '',
    'Title_EN' => '',
    'Detail_TH' => '',
    'Detail_EN' => '',
    'Image' => '',
    'IsActive' => 'Y',
    'OrderNo' => 1,
    'GroupID' => 0,
];
$groups = [];

try {
    $stmtGroups = $DB->prepare("SELECT GroupID, GroupNameTH, GroupNameEN FROM microsite_about_groups WHERE DeptID=? AND COALESCE(DelData, 'N') <> 'Y' AND IsActive='Y' ORDER BY OrderNo ASC, GroupID DESC");
    $stmtGroups->execute([$deptID]);
    $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

    if ($id > 0) {
        $stmt = $DB->prepare("SELECT * FROM microsite_about WHERE AboutID=? AND DeptID=? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmt->execute([$id, $deptID]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            echo "<script>alert('ไม่พบข้อมูลที่ต้องการแก้ไข'); window.location.href='index.php';</script>";
            exit;
        }
        $data = array_merge($data, $record);
    }
} catch (PDOException $e) {
    echo "<script>alert('ไม่สามารถโหลดข้อมูลได้'); window.location.href='index.php';</script>";
    exit;
}

$stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID=?");
$stmtSlug->execute([$deptID]);
$deptSlug = (string)$stmtSlug->fetchColumn();
$currentFileUrl = !empty($data['Image'])
    ? $FilePath . 'documents/microsite/' . rawurlencode($deptSlug) . '/' . rawurlencode($data['Image'])
    : '#';
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center mb-6 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-4 min-w-0 flex-1">
                <a href="index.php" class="w-10 h-10 shrink-0 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-xl"></i>
                </a>
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <?php echo $id > 0 ? 'แก้ไขเกี่ยวกับหน่วยงาน' : 'เพิ่มเกี่ยวกับหน่วยงาน'; ?>
                    </h2>
                    <p class="text-sm text-gray-500 mt-1 truncate">
                        หน่วยงาน:
                        <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 sm:justify-end">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo ($data['IsActive'] ?? 'Y') === 'Y' ? 'checked' : ''; ?>>
                    <span class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></span>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <section class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            หัวข้อ (<?php echo $Lang1; ?>) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($data['Title_TH'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">หัวข้อ (<?php echo $Lang2; ?>)</label>
                        <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($data['Title_EN'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            เนื้อหา (<?php echo $Lang1; ?>) <span class="text-red-500">*</span>
                        </label>
                        <textarea name="Detail_TH" class="ckeditor_full" rows="15"><?php echo htmlspecialchars($data['Detail_TH'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">เนื้อหา (<?php echo $Lang2; ?>)</label>
                        <textarea name="Detail_EN" class="ckeditor_full" rows="15"><?php echo htmlspecialchars($data['Detail_EN'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="ph ph-info"></i> ข้อมูลทั่วไป
                    </h3>

                    <label class="block text-sm text-gray-600 mb-1">
                        หมวดหมู่ <span class="text-red-500">*</span>
                    </label>
                    <select name="GroupID" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white">
                        <option value="">-- เลือกหมวดหมู่ --</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo (int)$group['GroupID']; ?>" <?php echo (int)($data['GroupID'] ?? 0) === (int)$group['GroupID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($group['GroupNameTH'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </section>

                <section class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="ph ph-paperclip"></i> เอกสารแนบ
                    </h3>

                    <?php if (!empty($data['Image'])): ?>
                    <div class="space-y-2 mb-4">
                        <div id="currentFileBox" class="flex items-center justify-between p-2 bg-white border border-gray-200 rounded-lg text-sm group">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <i class="ph ph-file-pdf text-red-500 text-lg flex-shrink-0"></i>
                                <a id="fileLink" href="<?php echo htmlspecialchars($currentFileUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="block truncate hover:text-blue-600 hover:underline">
                                    <span id="fileNameDisplay"><?php echo htmlspecialchars($data['Image'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <a id="fileViewLink" href="<?php echo htmlspecialchars($currentFileUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="ดูไฟล์">
                                    <i class="ph ph-eye text-lg"></i>
                                </a>
                                <button type="button" onclick="removeFile()" class="text-red-500 hover:text-red-700" title="ลบไฟล์">
                                    <i class="ph ph-trash text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <input type="hidden" name="delete_image" id="deleteImageFlag" value="0">
                    <div class="mt-2">
                        <input
                            type="file"
                            name="Image"
                            id="aboutFile"
                            accept=".pdf,.doc,.docx,.xls,.xlsx"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        >
                        <p class="text-xs text-gray-400 mt-2">รองรับไฟล์ (PDF, Word, Excel)</p>
                        <div id="attachmentsPreviewContainer" class="space-y-2 mt-4"></div>
                    </div>
                </section>
            </aside>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-100 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center">
                <i class="ph ph-x mr-2"></i> ยกเลิก
            </a>
            <button type="submit" class="btn-primary px-8 py-3 text-base font-semibold flex items-center justify-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </form>
</div>

<script>
const aboutFileInput = document.getElementById('aboutFile');
const deleteImageFlag = document.getElementById('deleteImageFlag');
const attachmentsPreviewContainer = document.getElementById('attachmentsPreviewContainer');
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
        deleteImageFlag.value = '1';
    });
}

aboutFileInput.addEventListener('change', function (event) {
    const file = event.target.files && event.target.files[0];
    attachmentsPreviewContainer.innerHTML = '';
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
    attachmentsPreviewContainer.appendChild(previewRow);
    deleteImageFlag.value = '0';
});

function clearNewFile() {
    aboutFileInput.value = '';
    attachmentsPreviewContainer.innerHTML = '';

    const currentFileBox = document.getElementById('currentFileBox');
    if (currentFileBox && deleteImageFlag.value === '0') {
        currentFileBox.style.display = 'flex';
    }

    if (selectedFileUrl) {
        URL.revokeObjectURL(selectedFileUrl);
        selectedFileUrl = null;
    }
}
</script>

<?php
include_once('../../assets/modules/ckeditor.php');
include_once('../../includes/footer.php');
?>
