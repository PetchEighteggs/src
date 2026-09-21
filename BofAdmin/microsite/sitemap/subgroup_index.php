<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'แผนผังเว็บไซต์';
include_once('../../includes/header.php');
require_once('../../includes/action_security.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>alert('กรุณาเลือกหน่วยงานก่อนจัดการข้อมูล'); window.location.href = '../index.php';</script>";
    exit;
}

$groupID = intval($_GET['group_id'] ?? 0);

// Fetch Group Info if group_id is provided
$groupTitleTH = 'ทั้งหมด';
if ($groupID > 0) {
    try {
			$stmtG = $DB->prepare("SELECT Title_TH FROM microsite_sitemap_groups WHERE GroupID = ? AND DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
			$stmtG->execute([$groupID, $deptID]);
			$groupData = $stmtG->fetch(PDO::FETCH_ASSOC);
			if ($groupData) {
				$groupTitleTH = $groupData['Title_TH'];
			}
    } catch (PDOException $e) {}
}

$allGroups = [];
try {
    $stmtAllG = $DB->prepare("SELECT GroupID, Title_TH FROM microsite_sitemap_groups WHERE DeptID = ? AND (DelData IS NULL OR DelData = 'N') ORDER BY OrderNo ASC, GroupID ASC");
    $stmtAllG->execute([$deptID]);
    $allGroups = $stmtAllG->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Check Permissions
$userGroupID = $_SESSION['GroupID'] ?? '';
$canView = false; $canAdd = false; $canEdit = false; $canDelete = false;

if ($userGroupID === 'A') {
    $canView = $canAdd = $canEdit = $canDelete = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $canView   = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'sitemap', 'CanView');
    $canAdd    = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'sitemap', 'CanAdd');
    $canEdit   = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'sitemap', 'CanEdit');
    $canDelete = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'sitemap', 'CanDelete');
}

if (!$canView) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = '../index.php';</script>";
    exit;
}

$searchKeyword = $_GET['keyword'] ?? '';
$searchStatus  = $_GET['status'] ?? '';
$summary = ['total' => 0, 'active_count' => 0, 'inactive_count' => 0];
$itemList = [];

try {
    if (isset($DB)) {
        $sqlBase = "SELECT s.*, g.Title_TH as GroupNameTH FROM microsite_sitemap_subgroups s LEFT JOIN microsite_sitemap_groups g ON s.GroupID = g.GroupID WHERE s.DeptID = :deptId AND (s.DelData IS NULL OR s.DelData = 'N')";
        $params  = [':deptId' => $deptID];

        if ($groupID > 0) {
            $sqlBase .= " AND s.GroupID = :groupId";
            $params[':groupId'] = $groupID;
        }

        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (s.Title_TH LIKE :keyword1 OR s.Title_EN LIKE :keyword2 OR s.URL_TH LIKE :keyword3 OR s.URL_EN LIKE :keyword4)";
            $params[':keyword1'] = "%$searchKeyword%";
            $params[':keyword2'] = "%$searchKeyword%";
            $params[':keyword3'] = "%$searchKeyword%";
            $params[':keyword4'] = "%$searchKeyword%";
        }

        $stmtSum = $DB->prepare("SELECT COUNT(*) as total,
            SUM(CASE WHEN IsActive = 'Y' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN IsActive != 'Y' THEN 1 ELSE 0 END) as inactive_count
            FROM ($sqlBase) as t");
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        if ($searchStatus === 'active') {
            $sqlBase .= " AND s.IsActive = 'Y'";
        } elseif ($searchStatus === 'inactive') {
            $sqlBase .= " AND s.IsActive != 'Y'";
        }

        $page   = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sqlBase, $params, $page, 25, 's.OrderNo ASC, s.SubGroupID ASC');
        $itemList = $result['data'];
    }
} catch (PDOException $e) { $dbError = $e->getMessage(); }
?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="window.location.href='subgroup_index.php?group_id=<?php echo $groupID; ?>'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center transition-all duration-300">
        <div><div class="text-sm text-gray-500 font-medium mb-1">รายการทั้งหมด</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600"><i class="ph ph-list text-3xl"></i></div>
    </div>
    <div onclick="window.location.href='subgroup_index.php?group_id=<?php echo $groupID; ?>&status=active'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'active' ? 'border-green-500 ring-2 ring-green-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center transition-all duration-300">
        <div><div class="text-sm text-green-600 font-medium mb-1">เปิดใช้งาน (Active)</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['active_count'] ?? 0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-500"><i class="ph ph-check-circle text-3xl"></i></div>
    </div>
    <div onclick="window.location.href='subgroup_index.php?group_id=<?php echo $groupID; ?>&status=inactive'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'inactive' ? 'border-amber-500 ring-2 ring-amber-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center transition-all duration-300">
        <div><div class="text-sm text-amber-600 font-medium mb-1">ปิดการใช้งาน (Inactive)</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['inactive_count'] ?? 0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-amber-50 flex items-center justify-center text-amber-500"><i class="ph ph-minus-circle text-3xl"></i></div>
    </div>
</div>

<form id="sitemapSubgroupActionForm" action="subgroup_action.php" method="POST" class="hidden">
    <?php echo bofSecurityCsrfField(); ?>
    <input type="hidden" name="action" id="sitemapSubgroupActionName" value="">
    <input type="hidden" name="group_id" value="<?php echo (int)$groupID; ?>">
    <input type="hidden" name="id" id="sitemapSubgroupActionId" value="">
    <input type="hidden" name="ids" id="sitemapSubgroupActionIds" value="">
</form>

<div class="glass-panel p-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-100 pb-4 mb-6 gap-4">
        <div class="flex items-center gap-4">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
              <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div>
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">จัดการเมนูย่อย </h2>
                <div class="text-sm text-gray-500 mt-1">หมวดหลัก: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($groupTitleTH); ?></span></div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($canAdd): ?>
            <a href="subgroup_form.php?group_id=<?php echo $groupID; ?>" class="btn-primary px-4 h-10 flex items-center gap-2 text-sm">
                <i class="ph ph-plus-circle text-lg"></i> เพิ่มเมนูย่อย
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search Box -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
        <form method="GET" action="" class="flex flex-wrap gap-4">
            <div class="flex-1 relative">
              <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ค้นหาชื่อรายการ, URL..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary bg-white h-10">
            </div>
						<div class="w-64">
							<select name="group_id" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary bg-white h-10">
								<option value="">-- ทุกหมวดหลัก --</option>
								<?php foreach ($allGroups as $g): ?>
								<option value="<?php echo $g['GroupID']; ?>" <?php echo $groupID == $g['GroupID'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($g['Title_TH']); ?>
								</option>
								<?php endforeach; ?>
							</select>
            </div>
            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium transition-colors h-10">ค้นหา</button>
            <?php if($searchKeyword != '' || $groupID > 0): ?>
              <a href="subgroup_index.php" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-10">ล้างค่า</a>
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
                    <th>ชื่อเมนูย่อย</th>
                    <th width="200">ชื่อเมนูหลัก</th>
                    <th width="120">สถานะ</th>
                    <th width="120" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($dbError)): ?>
                <tr><td colspan="7" class="py-12 text-center text-red-500">Database Error: <?php echo htmlspecialchars($dbError); ?></td></tr>
                <?php elseif (empty($itemList)): ?>
                <tr>
									<td colspan="7" class="py-12 text-center text-gray-400">
										<i class="ph ph-tree-structure text-4xl mb-2 block"></i> ยังไม่มีข้อมูลเมนูย่อย
									</td>
                </tr>
                <?php else: foreach ($itemList as $item): ?>
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="text-center">
                      <input type="checkbox" name="bulk_ids[]" value="<?php echo $item['SubGroupID']; ?>" class="item-checkbox rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                    </td>
                    <td class="font-semibold text-gray-800">
                      <?php echo htmlspecialchars($item['Title_TH'] ?? '-'); ?>
                    </td>
                    <td class="text-gray-600 text-sm"><?php echo htmlspecialchars($item['GroupNameTH'] ?? '-'); ?></td>
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
                            <a href="subgroup_form.php?group_id=<?php echo $groupID; ?>&id=<?php echo $item['SubGroupID']; ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="แก้ไข">
                                <i class="ph ph-pencil-simple text-lg"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button type="button" onclick="confirmDelete(<?php echo $item['SubGroupID']; ?>)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition-colors" title="ลบ">
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

    <!-- Pagination -->
    <?php if (isset($result['pagination']) && $result['pagination']['total_pages'] > 1): ?>
    <div class="mt-6 flex justify-between items-center">
        <div class="text-sm text-gray-500">
            แสดงหน้า <?php echo $page ?? 1; ?> จาก <?php echo $result['pagination']['total_pages']; ?> (รวม <?php echo $result['pagination']['total']; ?> รายการ)
        </div>
        <div class="flex gap-2">
            <?php for ($i = 1; $i <= $result['pagination']['total_pages']; $i++): ?>
                <a href="subgroup_index.php?group_id=<?php echo $groupID; ?>&page=<?php echo $i; ?>&status=<?php echo urlencode($searchStatus ?? ''); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == ($page ?? 1) ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
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

function confirmDelete(id) {
    Swal.fire({ title: 'ยืนยันการลบ?', text: 'คุณต้องการลบทิ้งข้อมูลนี้ใช่หรือไม่?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#aaa', confirmButtonText: 'ยืนยันการลบ', cancelButtonText: 'ยกเลิก' }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('sitemapSubgroupActionName').value = 'delete';
            document.getElementById('sitemapSubgroupActionId').value = id;
            document.getElementById('sitemapSubgroupActionIds').value = '';
            document.getElementById('sitemapSubgroupActionForm').submit();
        }
    });
}

function executeBulkAction() {
    const action = document.getElementById('bulkAction').value;
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    if (!action) { Swal.fire({ icon: 'warning', title: 'กรุณาเลือกการจัดการ' }); return; }
    if (checkedBoxes.length === 0) { Swal.fire({ icon: 'warning', title: 'กรุณาเลือกรายการ' }); return; }
    const confirmText = action === 'bulk_delete' ? 'ยืนยันการลบข้อมูลที่เลือก?' : (action === 'bulk_active' ? 'ยืนยันการเปิดใช้งาน?' : 'ยืนยันการปิดใช้งาน?');
    Swal.fire({ title: confirmText, text: `จำนวน ${checkedBoxes.length} รายการ`, icon: action === 'bulk_delete' ? 'warning' : 'question', showCancelButton: true, confirmButtonColor: action === 'bulk_delete' ? '#d33' : '#3085d6', cancelButtonColor: '#aaa', confirmButtonText: action === 'bulk_delete' ? 'ยืนยันการลบ' : 'ตกลง', cancelButtonText: 'ยกเลิก' }).then((result) => {
        if (result.isConfirmed) {
            let ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
            document.getElementById('sitemapSubgroupActionName').value = action;
            document.getElementById('sitemapSubgroupActionId').value = '';
            document.getElementById('sitemapSubgroupActionIds').value = ids;
            document.getElementById('sitemapSubgroupActionForm').submit();
        }
    });
}
</script>

<?php include_once('../../includes/footer.php'); ?>
