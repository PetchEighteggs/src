<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'โครงสร้างและตำแหน่งข้าราชการ';
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
    'PositionID' => 0,
    'DeptID' => $_GET['dept_id'] ?? 0,
    'Name_TH' => '',
    'Name_EN' => '',
    'IsActive' => 'Y',
    'OrderNo' => '0'
];

if ($id > 0) {
    try {
        if (isset($DB)) {
            $stmt = $DB->prepare("SELECT * FROM org_positions WHERE PositionID = ?");
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

// Fetch Departments
$departments = [];
try {
    if(isset($DB)) {
        $stmtDept = $DB->query("SELECT DeptID, ShortNameTH, NameTH FROM microsite_departments WHERE IsActive = 'Y' ORDER BY NameTH ASC");
        $departments = $stmtDept->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {}
?>

<form action="action.php" method="POST" class="space-y-6" onsubmit="showLoading()">
    <?php echo bofSecurityCsrfField(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="PositionID" value="<?php echo $data['PositionID']; ?>">

    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php<?php echo $data['DeptID'] ? '?dept_id='.$data['DeptID'] : ''; ?>" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <?php echo $action == 'add' ? 'เพิ่มข้อมูลโครงสร้างและตำแหน่งข้าราชการ' : 'แก้ไขข้อมูลโครงสร้างและตำแหน่งข้าราชการ'; ?>
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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-5 bg-gray-50 p-6 rounded-xl border border-gray-200">
                <h3 class="font-semibold text-gray-700 border-b border-gray-200 pb-2 mb-4 flex items-center gap-2">
                    <i class="ph ph-info"></i> ข้อมูลตำแหน่ง
                </h3>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-2">ชื่อตำแหน่ง (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                    <input type="text" name="Name_TH" value="<?php echo htmlspecialchars($data['Name_TH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-2">ชื่อตำแหน่ง (<?php echo $Lang2;?>)</label>
                    <input type="text" name="Name_EN" value="<?php echo htmlspecialchars($data['Name_EN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
            </div>

            <div class="space-y-5 bg-gray-50 p-6 rounded-xl border border-gray-200">
                <h3 class="font-semibold text-gray-700 border-b border-gray-200 pb-2 mb-4 flex items-center gap-2">
                    <i class="ph ph-buildings"></i>หน่วยงาน
                </h3>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-2">หน่วยงาน <span class="text-red-500">*</span></label>
                    <select name="DeptID" required class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">-- เลือกหน่วยงาน --</option>
                        <?php foreach($departments as $d): ?>
                            <?php $deptNameDisplay = !empty($d['ShortNameTH']) ? $d['ShortNameTH'] : $d['NameTH']; ?>
                            <option value="<?php echo $d['DeptID']; ?>" <?php echo ($data['DeptID'] == $d['DeptID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($deptNameDisplay); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <input type="hidden" name="OrderNo" value="<?php echo htmlspecialchars($data['OrderNo']); ?>">
            </div>
        </div>
        
        <div class="border-t border-gray-100 pt-6 mt-8 flex justify-end gap-3">
            <a href="index.php<?php echo $data['DeptID'] ? '?dept_id='.$data['DeptID'] : ''; ?>" class="px-6 py-3 bg-gray-100 text-gray-650 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 h-12 text-base font-semibold shadow-lg shadow-primary/30 flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </div>
</form>
<?php include_once('../includes/footer.php'); ?>
