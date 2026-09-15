<?php
$pageTitle = "กลุ่มผู้ใช้งาน";
include_once('../includes/header.php');
?>

<!-- Summary Badges -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Groups Card -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-center relative overflow-hidden group">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">กลุ่มผู้ใช้งานทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800">
                <?php 
                try {
                    $stmtCount = $DB->prepare("SELECT COUNT(*) FROM staff_set_group WHERE DelData = 'N'");
                    $stmtCount->execute();
                    echo number_format($stmtCount->fetchColumn());
                } catch(PDOException $e) { echo 0; }
                ?>
            </div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-users-three text-3xl"></i>
        </div>
        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-blue-50/50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
    </div>

    <!-- Active Users Card -->
    <a href="../users/index.php" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-center relative overflow-hidden group hover:shadow-md transition-shadow">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">ผู้ใช้งานระบบ</div>
            <div class="text-3xl font-bold text-gray-800">
                <?php 
                try {
                    $stmtUserCount = $DB->prepare("SELECT COUNT(*) FROM staff WHERE DelData = 'N' AND Status = 'Y'");
                    $stmtUserCount->execute();
                    echo number_format($stmtUserCount->fetchColumn());
                } catch(PDOException $e) { echo 0; }
                ?>
            </div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-user-check text-3xl"></i>
        </div>
        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-green-50/50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
    </a>

    <!-- Suspended Users Card -->
    <a href="../users/index.php?status=N" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-center relative overflow-hidden group hover:shadow-md transition-shadow">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">ระงับการใช้งาน</div>
            <div class="text-3xl font-bold text-gray-800">
                <?php 
                try {
                    $stmtSuspendedCount = $DB->prepare("SELECT COUNT(*) FROM staff WHERE DelData = 'N' AND Status = 'N'");
                    $stmtSuspendedCount->execute();
                    echo number_format($stmtSuspendedCount->fetchColumn());
                } catch(PDOException $e) { echo 0; }
                ?>
            </div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-red-50 flex items-center justify-center text-red-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-user-minus text-3xl"></i>
        </div>
        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-red-50/50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
    </a>
</div>

<!-- Bulk Actions -->
<?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
<div class="flex items-center gap-3 mb-6 hidden bg-gray-50 p-4 rounded-xl border border-gray-200" id="bulkActionContainer">
    <span class="text-sm font-medium text-gray-700">จัดการรายการที่เลือก:</span>
    <select id="bulkActionSelect" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-blue-500 bg-white h-[42px] min-w-[200px]">
        <option value="">-- เลือกการจัดการ --</option>
        <option value="active">เปิดใช้งาน</option>
        <option value="inactive">ระงับการใช้งาน</option>
        <option value="delete">ลบข้อมูล</option>
    </select>
    <button type="button" onclick="executeBulkAction()" class="btn-primary px-6 h-[42px] text-sm font-medium flex items-center justify-center">นำไปใช้</button>
    <span class="text-sm text-gray-500 ml-2" id="bulkCountText"></span>
</div>
<?php endif; ?>

<div class="glass-panel p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-gray-800"><i class="ph ph-users-three text-primary mr-2"></i> จัดการกลุ่มผู้ใช้งาน (Roles)</h2>
        <?php if ($userPermissions['add']): ?>
        <a href="form.php" class="btn-primary">
            <i class="ph ph-plus"></i> เพิ่มกลุ่มผู้ใช้งาน
        </a>
        <?php endif; ?>
    </div>
    <div class="overflow-x-auto">
        <table id="dataTable" class="w-full text-left border-collapse min-w-[600px]">
        <thead>
            <tr class="bg-gray-50 text-gray-600 text-sm border-b border-gray-200">
                <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                <th class="p-4 font-medium w-16 text-center">
                    <input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="toggleSelectAll(this)">
                </th>
                <?php endif; ?>
                <th class="p-4 font-medium">ชื่อกลุ่มผู้ใช้งาน</th>
                <th class="p-4 font-medium">การกำหนดค่า</th>
                <th class="p-4 font-medium w-40 text-center">สถานะ</th>
                <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                <th class="p-4 font-medium w-40 text-center">จัดการ</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody class="text-sm text-gray-700">
            <?php
            try {
                $stmt = $DB->prepare("SELECT * FROM staff_set_group WHERE DelData = 'N' ORDER BY SetOrder ASC, SetGroupID ASC");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $statusClass = $row['Approved'] == 'Y' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                    $statusText = $row['Approved'] == 'Y' ? 'เปิดใช้งาน' : 'ระงับ';
            ?>
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                <td class="p-4 text-center">
                    <input type="checkbox" name="ids[]" value="<?php echo $row['SetGroupID']; ?>" class="row-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="updateBulkDeleteBtn()">
                </td>
                <?php endif; ?>
                <td class="p-4 font-medium text-primary"><?php echo htmlspecialchars($row['SetGroupName']); ?></td>
                <td class="p-4 text-xs text-gray-600 leading-relaxed">
                    <?php
                    $stmtPerm = $DB->prepare("
                        SELECT g.SectionGroupName, s.SectionName, p.CanApprove, p.CanAdd, p.CanEdit, p.CanDelete 
                        FROM staff_permission p 
                        JOIN bof_section s ON p.SectionID = s.SectionID
                        JOIN bof_section_group g ON s.SectionGroupID = g.SectionGroupID
                        WHERE p.SetGroupID = ? AND (p.CanApprove = 'Y' OR p.CanAdd = 'Y' OR p.CanEdit = 'Y' OR p.CanDelete = 'Y')
                        ORDER BY g.SectionGroupOrder, s.SectionOrder
                    ");
                    $stmtPerm->execute([$row['SetGroupID']]);
                    $perms = $stmtPerm->fetchAll(PDO::FETCH_ASSOC);
                    
                    $currentGroup = '';
                    foreach ($perms as $p) {
                        if ($currentGroup != $p['SectionGroupName']) {
                            echo '<div class="text-gray-800 font-semibold mt-2">' . htmlspecialchars($p['SectionGroupName']) . '</div>';
                            $currentGroup = $p['SectionGroupName'];
                        }
                        
                        $actions = [];
                        if($p['CanApprove']=='Y') $actions[] = '<i class="ph ph-eye text-blue-500" title="ดู"></i>';
                        if($p['CanAdd']=='Y') $actions[] = '<i class="ph ph-plus-circle text-green-500" title="เพิ่ม"></i>';
                        if($p['CanEdit']=='Y') $actions[] = '<i class="ph ph-pencil-simple text-amber-500" title="แก้ไข"></i>';
                        if($p['CanDelete']=='Y') $actions[] = '<i class="ph ph-trash text-red-500" title="ลบ"></i>';
                        
                        echo '<div class="pl-3 flex items-center gap-2">- ' . htmlspecialchars($p['SectionName']) . ' ' . implode(' ', $actions) . '</div>';
                    }
                    if (count($perms) == 0) {
                        echo '<span class="text-gray-400">ยังไม่มีการกำหนดสิทธิ์</span>';
                    }
                    ?>
                </td>
                <td class="p-4 text-center">
                    <span class="inline-flex whitespace-nowrap px-3 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                        <?php echo $statusText; ?>
                    </span>
                </td>
                <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                <td class="p-4 text-center space-x-2">
                    <?php if ($userPermissions['edit']): ?>
                    <a href="form.php?id=<?php echo $row['SetGroupID']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors tooltip" data-tip="แก้ไข">
                        <i class="ph ph-pencil-simple text-lg"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($userPermissions['delete']): ?>
                    <button onclick="confirmDelete(<?php echo $row['SetGroupID']; ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors tooltip" data-tip="ลบ">
                        <i class="ph ph-trash text-lg"></i>
                    </button>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php } } catch(PDOException $e) {} ?>
        </tbody>
    </table>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#dataTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" },
        "pageLength": 25
    });
});
function toggleSelectAll(source) {
    let checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBulkDeleteBtn();
}

function updateBulkDeleteBtn() {
    let checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
    let container = document.getElementById('bulkActionContainer');
    let countText = document.getElementById('bulkCountText');
    if (checkedCount > 0) {
        container.classList.remove('hidden');
        container.classList.add('flex');
        countText.innerHTML = '(เลือก ' + checkedCount + ' รายการ)';
    } else {
        container.classList.add('hidden');
        container.classList.remove('flex');
    }
}

function executeBulkAction() {
    let checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) return;
    
    let action = document.getElementById('bulkActionSelect').value;
    if (action === '') {
        AdminDialog.fire('แจ้งเตือน', 'กรุณาเลือกการจัดการที่ต้องการ', 'warning');
        return;
    }
    
    let confirmMsg = '';
    let actionStr = '';
    if (action === 'delete') {
        confirmMsg = 'คุณต้องการลบทิ้งข้อมูลที่เลือกจำนวน ' + checkedBoxes.length + ' รายการใช่หรือไม่?';
        actionStr = 'bulk_delete';
    } else if (action === 'active') {
        confirmMsg = 'คุณต้องการเปิดใช้งานข้อมูลที่เลือกจำนวน ' + checkedBoxes.length + ' รายการใช่หรือไม่?';
        actionStr = 'bulk_active';
    } else if (action === 'inactive') {
        confirmMsg = 'คุณต้องการระงับการใช้งานข้อมูลที่เลือกจำนวน ' + checkedBoxes.length + ' รายการใช่หรือไม่?';
        actionStr = 'bulk_inactive';
    }
    
    AdminDialog.fire({
        title: 'ยืนยันการทำรายการ?',
        text: confirmMsg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#005A9C',
        cancelButtonColor: '#d33',
        confirmButtonText: 'ตกลง',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            let ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
            window.location.href = 'action.php?action=' + actionStr + '&ids=' + ids;
        }
    });
}

function confirmDelete(id) {
    AdminDialog.fire({
        title: 'ยืนยันการลบข้อมูล?',
        text: "คุณไม่สามารถกู้คืนข้อมูลนี้ได้!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'action.php?action=delete&id=' + id;
        }
    });
}
</script>

<?php include_once('../includes/footer.php'); ?>
