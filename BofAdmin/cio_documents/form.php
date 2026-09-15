<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการเอกสารดาวน์โหลด CIO';
include_once('../includes/header.php');

$id = $_GET['id'] ?? null;
$docData = [
    'CioID' => '',
    'Title_TH' => '', 'Title_EN' => '',
    'FilePath' => '', 'FileType' => '', 'FileSize' => 0,
    'IsActive' => 'Y'
];



// Fetch CIO Executives for Dropdown
$executives = [];
try {
    if (isset($DB)) {
        $stmtExec = $DB->query("SELECT CioID, Prefix_TH, FirstName_TH, LastName_TH, Position_TH FROM cio_executives WHERE DelData = 'N' AND IsActive = 'Y' ORDER BY OrderNo ASC, CioID DESC");
        $executives = $stmtExec->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

if ($id) {
    try {
        if(isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM cio_documents WHERE DocumentID = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if($res) $docData = $res;
        }
    } catch(PDOException $e) {}
}
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <h2 class="text-xl font-semibold text-gray-800 flex-1">
                <?php echo $id ? 'แก้ไขเอกสารดาวน์โหลด CIO' : 'เพิ่มเอกสารดาวน์โหลด CIO'; ?>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo $docData['IsActive'] == 'Y' ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>
        <input type="hidden" name="action" value="<?php echo $id ? 'edit' : 'add'; ?>">
        <?php if($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>
        <?php if($id): ?>
            <input type="hidden" name="old_file" value="<?php echo htmlspecialchars($docData['FilePath']); ?>">
            <input type="hidden" name="old_file_type" value="<?php echo htmlspecialchars($docData['FileType']); ?>">
            <input type="hidden" name="old_file_size" value="<?php echo htmlspecialchars($docData['FileSize']); ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">ชื่อเอกสาร (<?php echo $Lang1; ?>) <span class="text-red-500">*</span></label>
                        <input type="text" name="Title_TH" value="<?php echo htmlspecialchars($docData['Title_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">ชื่อเอกสาร (<?php echo $Lang2; ?>)</label>
                        <input type="text" name="Title_EN" value="<?php echo htmlspecialchars($docData['Title_EN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">

                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">ผู้บริหาร CIO</label>
                        <select name="CioID" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                            <option value="">เลือก</option>
                            <?php foreach ($executives as $exec): ?>
                                <option value="<?php echo $exec['CioID']; ?>" <?php echo $docData['CioID'] == $exec['CioID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exec['Prefix_TH'] . ' ' . $exec['FirstName_TH'] . ' ' . $exec['LastName_TH'] . ' (' . $exec['Position_TH'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-paperclip"></i>  เอกสารแนบ</h3>
                    
                    <?php if(!empty($docData['FilePath'])): ?>
                    <div class="mb-4 space-y-2">
                        <div class="flex items-center justify-between p-2 bg-white border border-gray-200 rounded-lg text-sm group" id="currentFileBox">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <i class="ph ph-file-pdf text-red-500 text-lg flex-shrink-0"></i>
                                <a href="<?php echo $FilePath . 'cio_documents/' . htmlspecialchars($docData['FilePath']); ?>" target="_blank" class="block truncate hover:text-blue-600 hover:underline"><?php echo htmlspecialchars($docData['FilePath']); ?></a>
                                <span class="text-xs text-gray-500 ml-2">(<?php echo round($docData['FileSize'] / 1024 / 1024, 2); ?> MB)</span>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <a href="<?php echo $FilePath . 'cio_documents/' . htmlspecialchars($docData['FilePath']); ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="ดูไฟล์">
                                    <i class="ph ph-eye text-lg"></i>
                                </a>
                                <button type="button" class="text-red-500 hover:text-red-700" onclick="removeFile()" title="ลบไฟล์">
                                    <i class="ph ph-trash text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <input type="hidden" name="deleteFileFlag" id="deleteFileFlag" value="0">
                    
                    <div class="mt-2">

                        <input type="file" name="FilePath" id="FilePathInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar" <?php echo !$id ? 'required' : ''; ?> class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">รองรับไฟล์: PDF, Word, Excel, PowerPoint, ZIP (ขนาดไม่เกิน 50MB)</p>
                        <div id="attachmentsPreviewContainer" class="space-y-2 mt-4"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-3 pt-6 border-t border-gray-100">
            <a href="index.php" class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                ยกเลิก
            </a>
            <button type="submit" class="btn-primary px-8 py-2.5 flex items-center gap-2">
                <i class="ph ph-floppy-disk text-lg"></i>
                บันทึกข้อมูล
            </button>
        </div>
    </form>
</div>

<script>
function showLoading() {
    Swal.fire({
        title: 'กำลังบันทึกข้อมูล...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
}

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
        if (result.isConfirmed) {
            const currentFileBox = document.getElementById('currentFileBox');
            if (currentFileBox) {
                currentFileBox.style.display = 'none';
            }
            document.getElementById('deleteFileFlag').value = '1';
            document.getElementById('FilePathInput').setAttribute('required', 'required');
        }
    });
}

document.getElementById('FilePathInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const container = document.getElementById('attachmentsPreviewContainer');
    container.innerHTML = '';
    
    if (file) {
        if (file.size > 50 * 1024 * 1024) { // 50MB limit
            Swal.fire('ขนาดไฟล์เกินกำหนด', 'กรุณาอัพโหลดไฟล์ขนาดไม่เกิน 50MB', 'warning');
            this.value = '';
            return;
        }
        document.getElementById('deleteFileFlag').value = '0';
        const currentFileBox = document.getElementById('currentFileBox');
        if (currentFileBox) {
            currentFileBox.style.display = 'none'; // hide old file box when new file is selected
        }
        
        let objUrl = URL.createObjectURL(file);
        var div = document.createElement('div');
        div.className = 'flex items-center justify-between p-2 bg-blue-50 border border-blue-100 rounded-lg text-sm text-blue-800';
        div.innerHTML = '<div class="flex items-center gap-2 flex-1 min-w-0">' +
                        '<i class="ph ph-file-plus text-lg flex-shrink-0"></i>' +
                        '<a href="'+objUrl+'" target="_blank" class="block truncate hover:underline" title="'+file.name+'">'+file.name+'</a>' +
                        '</div>' +
                        '<div class="flex items-center gap-3 flex-shrink-0">' +
                        '<a href="'+objUrl+'" target="_blank" class="text-blue-500 hover:text-blue-700" title="ดูไฟล์"><i class="ph ph-eye text-lg"></i></a>' +
                        '<button type="button" class="text-red-500 hover:text-red-700" onclick="clearNewFile()" title="ลบไฟล์ที่เลือก"><i class="ph ph-trash text-lg"></i></button>' +
                        '</div>';
        container.appendChild(div);
    }
});

function clearNewFile() {
    document.getElementById('FilePathInput').value = '';
    document.getElementById('attachmentsPreviewContainer').innerHTML = '';
    const currentFileBox = document.getElementById('currentFileBox');
    if (currentFileBox && document.getElementById('deleteFileFlag').value === '0') {
        currentFileBox.style.display = 'flex';
    }
}
</script>

<?php include_once('../includes/footer.php'); ?>
