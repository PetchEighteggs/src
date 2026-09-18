<?php
$pageTitle = "ฟอร์มข้อมูลกลุ่มผู้ใช้งาน";
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$id = mosGetParam($_FORM, 'id', '');
$row = [
    'SetGroupID' => '',
    'SetGroupName' => '',
    'SetOrder' => '9999',
    'Approved' => 'Y'
];

$permissions = [];
if ($id != '') {
    $stmt = $DB->prepare("SELECT * FROM staff_set_group WHERE SetGroupID = ? AND DelData = 'N'");
    $stmt->execute([$id]);
    if ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row = $data;
    }
    
    // Fetch permissions
    $stmt = $DB->prepare("SELECT * FROM staff_permission WHERE SetGroupID = ?");
    $stmt->execute([$id]);
    $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($perms as $p) {
        $permissions[$p['SectionID']] = $p;
    }
}
?>

<div class="glass-panel p-6">
    <form action="action.php" method="POST" id="mainForm" onsubmit="showLoading()">
        <?php echo bofSecurityCsrfField(); ?>
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <h2 class="text-xl font-bold text-gray-800 flex-1">
                <?php echo $id == '' ? 'เพิ่มข้อมูลกลุ่มผู้ใช้งาน' : 'แก้ไขข้อมูลกลุ่มผู้ใช้งาน'; ?>
            </h2>
            
            <!-- Status Toggle -->
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="Approved" value="N">
                    <input type="checkbox" name="Approved" value="Y" class="sr-only peer" <?php echo $row['Approved'] == 'Y' ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>
        
        <input type="hidden" name="action" value="<?php echo $id == '' ? 'add' : 'edit'; ?>">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
        
        <div class="space-y-6">
            
            <!-- Name Panel -->
            <div class="glass-panel p-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อ <span class="text-red-500">*</span></label>
                <input type="text" name="SetGroupName" value="<?php echo htmlspecialchars($row['SetGroupName']); ?>" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary bg-white">
            </div>

            <!-- Access Control List Panel -->
            <div class="glass-panel p-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-gray-500 text-sm border-b border-gray-100">
                                <th class="py-3 font-medium">Access Control List</th>
                                <th class="py-3 font-medium text-center w-16">ดู</th>
                                <th class="py-3 font-medium text-center w-16">เพิ่ม</th>
                                <th class="py-3 font-medium text-center w-16">แก้ไข</th>
                                <th class="py-3 font-medium text-center w-16">ลบ</th>
                                <th class="py-3 font-medium text-center w-16">All</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php
                            $stmtGroup = $DB->prepare("SELECT * FROM bof_section_group WHERE SectionGroupShow = 'Y' ORDER BY SectionGroupOrder ASC");
                            $stmtGroup->execute();
                            $groups = $stmtGroup->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($groups as $grp) {
                                echo '<tr class="border-b border-gray-50">';
                                echo '<td colspan="6" class="py-3 font-bold text-gray-800">' . htmlspecialchars($grp['SectionGroupName']) . '</td>';
                                echo '</tr>';
                                
                                $stmtSec = $DB->prepare("SELECT * FROM bof_section WHERE SectionGroupID = ? AND SectionShow = 'Y' ORDER BY SectionOrder ASC");
                                $stmtSec->execute([$grp['SectionGroupID']]);
                                $sections = $stmtSec->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($sections as $sec) {
                                    $sId = $sec['SectionID'];
                                    $p = $permissions[$sId] ?? ['CanApprove' => 'N', 'CanAdd' => 'N', 'CanEdit' => 'N', 'CanDelete' => 'N'];
                                    
                                    // Normally 'view' doesn't exist in schema, we will use CanApprove as View.
                                    $isView = $p['CanApprove'] == 'Y';
                                    $isAdd = $p['CanAdd'] == 'Y';
                                    $isEdit = $p['CanEdit'] == 'Y';
                                    $isDel = $p['CanDelete'] == 'Y';
                                    $isAll = $isView && $isAdd && $isEdit && $isDel;
                                    
                                    echo '<tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">';
                                    echo '<td class="py-3 pl-4 text-gray-600">- ' . htmlspecialchars($sec['SectionName']) . '</td>';
                                    
                                    // Checkboxes
                                    echo '<td class="py-3 text-center"><input type="checkbox" name="perm['.$sId.'][view]" value="Y" class="w-4 h-4 text-blue-600 rounded border-gray-300 perm-view-'.$sId.' row-chk-'.$sId.'" '.($isView ? 'checked' : '').' onclick="checkAllRow('.$sId.')"></td>';
                                    echo '<td class="py-3 text-center"><input type="checkbox" name="perm['.$sId.'][add]" value="Y" class="w-4 h-4 text-blue-600 rounded border-gray-300 perm-add-'.$sId.' row-chk-'.$sId.'" '.($isAdd ? 'checked' : '').' onclick="checkAllRow('.$sId.')"></td>';
                                    echo '<td class="py-3 text-center"><input type="checkbox" name="perm['.$sId.'][edit]" value="Y" class="w-4 h-4 text-blue-600 rounded border-gray-300 perm-edit-'.$sId.' row-chk-'.$sId.'" '.($isEdit ? 'checked' : '').' onclick="checkAllRow('.$sId.')"></td>';
                                    echo '<td class="py-3 text-center"><input type="checkbox" name="perm['.$sId.'][del]" value="Y" class="w-4 h-4 text-blue-600 rounded border-gray-300 perm-del-'.$sId.' row-chk-'.$sId.'" '.($isDel ? 'checked' : '').' onclick="checkAllRow('.$sId.')"></td>';
                                    echo '<td class="py-3 text-center"><input type="checkbox" class="w-4 h-4 text-blue-600 rounded border-gray-300 chk-all-'.$sId.'" '.($isAll ? 'checked' : '').' onclick="toggleAllRow(this, '.$sId.')"></td>';
                                    
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
            
        <div class="border-t border-gray-100 pt-6 flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 py-3 text-lg"><i class="ph ph-floppy-disk mr-2"></i> บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<script>
function toggleAllRow(source, sId) {
    let checkboxes = document.querySelectorAll('.row-chk-' + sId);
    checkboxes.forEach(cb => cb.checked = source.checked);
}
function checkAllRow(sId) {
    let checkboxes = document.querySelectorAll('.row-chk-' + sId);
    let allChecked = true;
    checkboxes.forEach(cb => {
        if (!cb.checked) allChecked = false;
    });
    document.querySelector('.chk-all-' + sId).checked = allChecked;
}
</script>

<?php include_once('../includes/footer.php'); ?>
