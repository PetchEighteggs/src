<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการเมนูย่อย';
include_once('../../includes/header.php');

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
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'menus', $permRequired)) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = 'index.php';</script>";
    exit;
}

// Default Data
$data = [
    'SubMenuID' => 0,
    'MenuID' => 0,
    'SubMenuNameTH' => '',
    'SubMenuNameEN' => '',
    'URL' => '',
    'Target' => '_self',
    'IsActive' => 'Y',
    'OrderNo' => '0'
];

if ($id > 0) {
    try {
        if (isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM microsite_submenus WHERE SubMenuID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
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

// Fetch Main Menus
$mainMenus = [];
try {
    if (isset($DB)) {
        $stmt = $DB->prepare("SELECT MenuID, MenuNameTH FROM microsite_menus WHERE DeptID = ? AND (DelData IS NULL OR DelData = 'N') ORDER BY OrderNo ASC");
        $stmt->execute([$deptID]);
        $mainMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

?>

<form action="action.php" method="POST" class="space-y-6" onsubmit="showLoading()">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="SubMenuID" value="<?php echo $data['SubMenuID']; ?>">

    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <?php echo $action == 'add' ? 'เพิ่มข้อมูลเมนูย่อย' : 'แก้ไขข้อมูลเมนูย่อย'; ?>
                    </h2>
                    <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? ''); ?></span></div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-655">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="IsActive" value="N">
                    <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo ($data['IsActive'] == 'Y') ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
                <div>
									<div class="mb-4">
											<label class="block text-sm font-semibold text-gray-700 mb-2">ชื่อเมนูหลัก <span class="text-red-500">*</span></label>
											<select name="MenuID" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
													<option value="">-- เลือกเมนูหลัก --</option>
													<?php foreach ($mainMenus as $mm): ?>
															<option value="<?php echo $mm['MenuID']; ?>" <?php echo ($data['MenuID'] == $mm['MenuID']) ? 'selected' : ''; ?>>
																	<?php echo htmlspecialchars($mm['MenuNameTH']); ?>
															</option>
													<?php endforeach; ?>
											</select>
									</div>

									<div class="mb-4">
											<label class="block text-sm font-semibold text-gray-700 mb-2">ชื่อเมนูย่อย (<?php echo $Lang1 ?? 'TH';?>) <span class="text-red-500">*</span></label>
											<input type="text" name="SubMenuNameTH" value="<?php echo htmlspecialchars($data['SubMenuNameTH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
									</div>
                </div>
                <div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">ชื่อเมนูย่อย (<?php echo $Lang2 ?? 'EN';?>)</label>
                        <input type="text" name="SubMenuNameEN" value="<?php echo htmlspecialchars($data['SubMenuNameEN']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                    </div>
                </div>
            </div>

            <!-- Right Column (Sidebar) -->
            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ph ph-info"></i> ข้อมูลทั่วไป</h3>
                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-1">ลิงก์ปลายทาง (URL) <span class="text-red-500">*</span></label>
                        <input type="text" name="URL" id="urlInput" value="<?php echo htmlspecialchars($data['URL']); ?>" placeholder="https://" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white" required>
                        <p class="text-xs text-gray-400 mt-1">หมายเหตุ : กรุณา URL นำหน้าด้วย "https://" เช่น https://www.dgr.go.th เป็นต้น</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="border-t border-gray-100 pt-6 mt-8 flex justify-end gap-3">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-655 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 h-12 text-base font-semibold shadow-lg shadow-primary/30 flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </div>
</form>
<?php include_once('../../includes/footer.php'); ?>