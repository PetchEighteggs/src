<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการกลุ่มย่อยประกอบกิจการน้ำบาดาล';
include_once('../includes/header.php');


$groupID = $_GET['group_id'] ?? 0;



if (empty($groupID)) {
    echo "<script>alert('กรุณาเลือกหมวดหมู่ก่อนจัดการกลุ่มย่อย'); window.location.href = 'group.php';</script>";
    exit;
}

$canAdd = $userPermissions['add'] ?? true;
$canEdit = $userPermissions['edit'] ?? true;
$canDelete = $userPermissions['delete'] ?? true;

// Fetch Group Info
$stmtGroup = $DB->prepare("SELECT GroupNameTH FROM groundwater_doc_groups WHERE GroupID = ?");
$stmtGroup->execute([$groupID]);
$groupName = $stmtGroup->fetchColumn();

$searchKeyword = $_GET['keyword'] ?? '';
$searchStatus  = $_GET['status'] ?? '';
$summary = ['total' => 0, 'active_count' => 0, 'inactive_count' => 0];
$itemList = [];

try {
    if (isset($DB)) {
        $sqlBase = "SELECT * FROM groundwater_doc_subgroups WHERE GroupID = :groupId";
        $params  = [':groupId' => $groupID];

        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (SubGroupNameTH LIKE :keyword1 OR SubGroupNameEN LIKE :keyword2)";
            $params[':keyword1'] = "%$searchKeyword%";
            $params[':keyword2'] = "%$searchKeyword%";
        }

        $stmtSum = $DB->prepare("SELECT COUNT(*) as total,
            SUM(CASE WHEN IsActive = 'Y' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN IsActive != 'Y' THEN 1 ELSE 0 END) as inactive_count
            FROM ($sqlBase) as t");
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        if ($searchStatus === 'active') {
            $sqlBase .= " AND IsActive = 'Y'";
        } elseif ($searchStatus === 'inactive') {
            $sqlBase .= " AND IsActive != 'Y'";
        }

        $page   = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sqlBase, $params, $page, 25, 'OrderNo ASC, SubGroupID DESC');
        $itemList = $result['data'];
    }
} catch (PDOException $e) { $dbError = $e->getMessage(); }
?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="window.location.href='subgroup.php?group_id=<?php echo $groupID; ?>'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center transition-all duration-300">
        <div><div class="text-sm text-gray-500 font-medium mb-1">รายการทั้งหมด</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600"><i class="ph ph-list text-3xl"></i></div>
    </div>
    <div onclick="window.location.href='subgroup.php?group_id=<?php echo $groupID; ?>&status=active'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'active' ? 'border-green-500 ring-2 ring-green-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center transition-all duration-300">
        <div><div class="text-sm text-green-600 font-medium mb-1">เปิดใช้งาน (Active)</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['active_count'] ?? 0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-500"><i class="ph ph-check-circle text-3xl"></i></div>
    </div>
    <div onclick="window.location.href='subgroup.php?group_id=<?php echo $groupID; ?>&status=inactive'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'inactive' ? 'border-amber-500 ring-2 ring-amber-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center transition-all duration-300">
        <div><div class="text-sm text-amber-600 font-medium mb-1">ปิดการใช้งาน (Inactive)</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['inactive_count'] ?? 0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-amber-50 flex items-center justify-center text-amber-500"><i class="ph ph-minus-circle text-3xl"></i></div>
    </div>
</div>

<div class="glass-panel p-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-100">
        <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-folder-notch-open text-primary"></i> จัดการกลุ่มย่อยประกอบกิจการน้ำบาดาล
            </h2>
            <div class="text-sm text-gray-500 mt-1">หมวดหมู่: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($groupName ?? ''); ?></span></div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($canAdd): ?>
            <a href="subgroup_form.php?group_id=<?php echo $groupID; ?>" class="btn-primary h-10 px-6 flex items-center justify-center gap-2 rounded-lg font-medium transition-colors">
                <i class="ph ph-plus-circle text-lg"></i> เพิ่มกลุ่มย่อย
            </a>
            <?php endif; ?>
            <a href="group.php" class="px-6 h-10 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                <i class="ph ph-arrow-left"></i> ย้อนกลับ
            </a>
        </div>
    </div>

    <!-- Search Box -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
        <form method="GET" action="" class="flex gap-4">
            <input type="hidden" name="group_id" value="<?php echo $groupID; ?>">
            <div class="flex-1 relative">
                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ค้นหากลุ่มย่อย..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary bg-white h-10">
            </div>
            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium transition-colors h-10 flex items-center justify-center">ค้นหา</button>
            <?php if($searchKeyword != ''): ?>
                <a href="subgroup.php?group_id=<?php echo $groupID; ?>" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-10">ล้างค่า</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Bulk Action Toolbar -->
    <?php if ($canEdit || $canDelete): ?>
    <div id="bulkActionBar" class="bg-white p-4 rounded-xl border border-gray-100 flex flex-wrap items-center gap-4 mb-6 shadow-sm hidden transition-all duration-300">
        <label class="text-sm font-semibold text-gray-700">จัดการรายการที่เลือก:</label>
        <select id="bulkAction" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary">
            <option value="">-- เลือกการจัดการ --</option>
            <?php if ($canEdit): ?>
            <option value="bulk_active">เปิดใช้งาน</option>
            <option value="bulk_inactive">ปิดใช้งาน</option>
            <?php endif; ?>
            <?php if ($canDelete): ?>
            <option value="bulk_delete">ลบข้อมูล</option>
            <?php endif; ?>
        </select>
        <button type="button" onclick="executeBulkAction()" class="btn-primary px-4 py-2 text-sm rounded-lg">นำไปใช้</button>
        <span class="text-sm text-gray-500">(เลือก <span id="selectedCount">0</span> รายการ)</span>
    </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="premium-table">
            <thead>
                <tr>
                    <th width="50" class="text-center">
                        <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                    </th>
                    <th>ชื่อกลุ่มย่อย</th>
                    <th width="120">สถานะ</th>
                    <th width="120" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($dbError)): ?>
                <tr><td colspan="4" class="py-12 text-center text-red-500 font-medium">Database Error: <?php echo htmlspecialchars($dbError); ?></td></tr>
                <?php elseif (empty($itemList)): ?>
                <tr><td colspan="4" class="py-12 text-center text-gray-500">ยังไม่มีข้อมูลกลุ่มย่อย</td></tr>
                <?php else: foreach ($itemList as $item): ?>
                <tr>
                    <td class="text-center">
                        <input type="checkbox" name="bulk_ids[]" value="<?php echo $item['SubGroupID']; ?>" class="item-checkbox rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                    </td>
                    <td class="font-semibold text-gray-800"><?php echo htmlspecialchars($item['SubGroupNameTH']); ?></td>
                    <td>
                        <?php if ($item['IsActive'] == 'Y'): ?>
                            <span class="inline-flex px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium border border-green-200 whitespace-nowrap">เปิดใช้งาน</span>
                        <?php else: ?>
                            <span class="inline-flex px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium border border-gray-200 whitespace-nowrap">ปิดใช้งาน</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="flex justify-center gap-2">
                            <?php if ($canEdit): ?>
                            <a href="subgroup_form.php?id=<?php echo $item['SubGroupID']; ?>&group_id=<?php echo $groupID; ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="แก้ไข">
                                <i class="ph ph-pencil-simple text-lg"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button type="button" onclick="if(confirm('ยืนยันการลบ?')) window.location.href='subgroup_action.php?action=delete&id=<?php echo $item['SubGroupID']; ?>&group_id=<?php echo $groupID; ?>'" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition-colors" title="ลบ">
                                <i class="ph ph-trash text-lg"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const bulkActionBar = document.getElementById('bulkActionBar');

    function updateCount() {
        const checked = document.querySelectorAll('.item-checkbox:checked');
        if (selectedCount) selectedCount.textContent = checked.length;
        if (bulkActionBar) bulkActionBar.classList.toggle('hidden', checked.length === 0);
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateCount();
        });
    }
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (selectAll) selectAll.checked = document.querySelectorAll('.item-checkbox:checked').length === checkboxes.length;
            updateCount();
        });
    });
});

function executeBulkAction() {
    const action = document.getElementById('bulkAction').value;
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    if (!action) { Swal.fire({ icon: 'warning', title: 'กรุณาเลือกการจัดการ' }); return; }
    if (checkedBoxes.length === 0) { Swal.fire({ icon: 'warning', title: 'กรุณาเลือกรายการ' }); return; }
    const confirmText = action === 'bulk_delete' ? 'ยืนยันการลบข้อมูลที่เลือก?' : (action === 'bulk_active' ? 'ยืนยันการเปิดใช้งาน?' : 'ยืนยันการปิดใช้งาน?');
    Swal.fire({ title: confirmText, text: `จำนวน ${checkedBoxes.length} รายการ`, icon: action === 'bulk_delete' ? 'warning' : 'question', showCancelButton: true, confirmButtonColor: action === 'bulk_delete' ? '#d33' : '#3085d6', cancelButtonColor: '#aaa', confirmButtonText: 'ตกลง', cancelButtonText: 'ยกเลิก' }).then((result) => {
        if (result.isConfirmed) {
            let ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
            window.location.href = 'subgroup_action.php?action=' + action + '&ids=' + ids + '&group_id=<?php echo $groupID; ?>';
        }
    });
}
</script>

<?php include_once('../includes/footer.php'); ?>
