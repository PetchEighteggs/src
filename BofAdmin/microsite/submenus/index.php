<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการเมนูย่อย';
include_once('../../includes/header.php');
require_once('../../includes/action_security.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>window.location.href = '../index.php';</script>";
    exit;
}

// Check Permissions (inherit from 'menus' since submenus is part of menus)
$userGroupID = $_SESSION['GroupID'] ?? '';
$canView = false;
$canAdd = false;
$canEdit = false;
$canDelete = false;

if ($userGroupID === 'A') {
    $canView = true;
    $canAdd = true;
    $canEdit = true;
    $canDelete = true;
} else if ($userGroupID === 'S') {
		$staffID = $_SESSION['StaffID'] ?? 0;
		require_once('../../includes/microsite_permission.php');
		$canView = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'menus', 'CanView');
		$canAdd = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'menus', 'CanAdd');
		$canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'menus', 'CanEdit');
		$canDelete = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'menus', 'CanDelete');
}

if (!$canView) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = '../index.php';</script>";
    exit;
}

$searchKeyword = $_GET['keyword'] ?? '';
$searchStatus = $_GET['status'] ?? '';
$searchMenuID = $_GET['menu_id'] ?? '';

// Fetch Main Menus for filter
$mainMenus = [];
try {
  if (isset($DB)) {
		$stmtMenu = $DB->prepare("SELECT MenuID, MenuNameTH FROM microsite_menus WHERE DeptID = ? AND (DelData IS NULL OR DelData = 'N') ORDER BY OrderNo ASC");
		$stmtMenu->execute([$deptID]);
		$mainMenus = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (PDOException $e) {}

// Fetch Data
$menuList = [];
try {
    if (isset($DB)) {
        $sqlBase = "SELECT sm.*, m.MenuNameTH as ParentMenuName 
				FROM microsite_submenus sm 
				LEFT JOIN microsite_menus m ON sm.MenuID = m.MenuID AND COALESCE(m.DelData, 'N') <> 'Y'
				WHERE sm.DeptID = :deptId AND (sm.DelData IS NULL OR sm.DelData = 'N')";
        $params = [':deptId' => $deptID];
        
        if (!empty($searchKeyword)) {
            $sqlBase .= " AND sm.SubMenuNameTH LIKE :keyword";
            $params[':keyword'] = "%$searchKeyword%";
        }
        
        if (!empty($searchMenuID)) {
            $sqlBase .= " AND sm.MenuID = :menuId";
            $params[':menuId'] = $searchMenuID;
        }

        // Summary
        $stmtSum = $DB->prepare("SELECT COUNT(*) as total, 
				SUM(CASE WHEN IsActive = 'Y' THEN 1 ELSE 0 END) as active_count,
				SUM(CASE WHEN IsActive != 'Y' THEN 1 ELSE 0 END) as inactive_count 
				FROM ($sqlBase) as t");
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        if (!empty($searchStatus)) {
					$sqlBase .= " AND sm.IsActive = :status";
					$params[':status'] = $searchStatus;
        }

        $sqlBase .= " ORDER BY sm.MenuID ASC, sm.OrderNo ASC, sm.SubMenuID ASC";

        // Pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $sqlCount = "SELECT COUNT(*) FROM ($sqlBase) as t";
        $stmtCount = $DB->prepare($sqlCount);
        $stmtCount->execute($params);
        $totalRows = $stmtCount->fetchColumn();
        $totalPages = ceil($totalRows / $limit);

        $sqlBase .= " LIMIT $limit OFFSET $offset";
        $stmt = $DB->prepare($sqlBase);
        $stmt->execute($params);
        $menuList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="window.location.href='index.php'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">เมนู ทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-list-dashes text-3xl"></i>
        </div>
    </div>
    
    <div onclick="window.location.href='index.php?status=Y'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'Y' ? 'border-green-500 ring-2 ring-green-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-green-600 font-medium mb-1">เปิดใช้งาน (Active)</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['active_count'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-500 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-check-circle text-3xl"></i>
        </div>
    </div>
    
    <div onclick="window.location.href='index.php?status=N'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'N' ? 'border-amber-500 ring-2 ring-amber-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-amber-600 font-medium mb-1">ปิดการใช้งาน (Inactive)</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['inactive_count'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-amber-50 flex items-center justify-center text-amber-550 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-minus-circle text-3xl"></i>
        </div>
    </div>
</div>

<form id="submenusActionForm" action="action.php" method="POST" class="hidden">
    <?php echo bofSecurityCsrfField(); ?>
    <input type="hidden" name="action" id="submenusActionName" value="">
    <input type="hidden" name="id" id="submenusActionId" value="">
    <input type="hidden" name="ids" id="submenusActionIds" value="">
</form>

<div class="glass-panel p-6">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4 border-b border-gray-100 pb-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-list-dashes text-primary"></i>
                จัดการเมนูย่อย
            </h2>
            <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? ''); ?></span></div>
        </div>
        
        <div class="flex items-center gap-3">
            <?php if ($canEdit): ?>
            <a href="../menus/sort.php" class="px-4 py-2 bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 rounded-lg font-medium transition-colors flex items-center justify-center gap-2 h-10">
                <i class="ph ph-arrows-down-up text-lg"></i> จัดเรียงลำดับ
            </a>
            <?php endif; ?>
            <?php if ($canAdd): ?>
            <a href="form.php?action=add" class="btn-primary h-10 px-6 flex items-center justify-center gap-2 rounded-lg font-medium transition-colors">
                <i class="ph ph-plus-circle text-lg"></i> เพิ่มเมนูย่อย
            </a>
            <?php endif; ?>
             <a href="../menus/index.php" class="px-6 h-10 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                <i class="ph ph-arrow-left"></i> ย้อนกลับ
            </a>
        </div>
    </div>
    <form method="GET" action="index.php" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 flex flex-col sm:flex-row gap-4">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($searchStatus); ?>">
        
        <div class="flex-1 flex gap-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหาชื่อเมนูย่อย</label>
                <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ชื่อเมนูย่อย..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div class="w-64">
                <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหาจากเมนูหลัก</label>
                <select name="menu_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary h-[42px]">
                    <option value="">-- ทั้งหมด --</option>
                    <?php foreach($mainMenus as $mm): ?>
                        <option value="<?php echo $mm['MenuID']; ?>" <?php echo $searchMenuID == $mm['MenuID'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($mm['MenuNameTH']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6">ค้นหา</button>
            <?php if (!empty($searchKeyword) || !empty($searchMenuID)): ?>
                <a href="index.php?status=<?php echo urlencode($searchStatus); ?>" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-[42px]">ล้างค่า</a>
            <?php endif; ?>
        </div>
    </form>

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

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
					<table class="w-full text-left border-collapse">
						<thead>
							<tr class="bg-gray-50/80 text-gray-650 text-sm border-b border-gray-100">
								<th class="py-4 px-6 font-semibold w-12 text-center">
									<input type="checkbox" id="selectAll" class="rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
								</th>
								<th class="py-4 px-6 font-semibold w-auto">ชื่อเมนูย่อย</th>
								<th class="py-4 px-6 font-semibold w-2/5">ชื่อเมนูหลัก</th>
								<th class="py-4 px-6 font-semibold w-32 text-center">สถานะ</th>
								<th class="py-4 px-6 font-semibold w-32 text-center">จัดการ</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100 bg-white">
							<?php if (empty($menuList)): ?>
							<tr>
								<td colspan="5" class="text-center py-8 text-gray-500">
									<i class="ph ph-folder-open text-4xl mb-2 text-gray-300 block"></i>
									<p>ยังไม่มีข้อมูล เมนูย่อย</p>
								</td>
							</tr>
							<?php else: ?>
								<?php foreach ($menuList as $row): ?>
								<tr class="hover:bg-gray-50/50 transition-colors group">
									<td class="py-4 px-6 text-center">
										<input type="checkbox" name="bulk_ids[]" value="<?php echo $row['SubMenuID']; ?>" class="item-checkbox rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
									</td>
									<td class="py-4 px-6">
										<div class="font-medium text-gray-800 text-base mb-1">
											<?php echo htmlspecialchars($row['SubMenuNameTH'] ?? ''); ?>
										</div>
										<?php if (!empty($row['SubMenuNameEN'])): ?>
										<div class="text-sm text-gray-500">
												<?php echo htmlspecialchars($row['SubMenuNameEN']); ?>
										</div>
										<?php endif; ?>
										<?php /* if(!empty($row['URL'] != "" && $row['URL'] != "#")): ?>
										<div class="text-sm text-gray-500 mt-1 flex items-center gap-1 truncate max-w-sm">
											<i class="ph ph-link text-gray-400"></i>
											<?php echo htmlspecialchars($row['URL']); ?>
										</div>
										<?php endif; ?> */ ?>
									</td>
									<td class="py-4 px-6">
										<div class="font-medium text-gray-800 text-base mb-1">
											<?php echo htmlspecialchars($row['ParentMenuName'] ?? '-'); ?>
										</div>
									</td>
									<td class="py-4 px-6 text-center">
										<?php if ($row['IsActive'] == 'Y'): ?>
											<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
												เปิดใช้งาน
											</span>
										<?php else: ?>
											<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
												ปิดใช้งาน
											</span>
										<?php endif; ?>
									</td>
									<td class="py-4 px-6 text-center">
											<div class="flex justify-center gap-2">
													<?php if ($canEdit): ?>
													<a href="form.php?id=<?php echo $row['SubMenuID']; ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="แก้ไข">
															<i class="ph ph-pencil-simple text-lg"></i>
													</a>
													<?php endif; ?>
													
													<?php if ($canDelete): ?>
													<a href="javascript:void(0);" onclick="confirmDelete(<?php echo $row['SubMenuID']; ?>)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition-colors" title="ลบ">
															<i class="ph ph-trash text-lg"></i>
													</a>
													<?php endif; ?>
											</div>
									</td>
								</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
        </div>
        
        <!-- Pagination -->
        <?php if (!empty($menuList) && $totalPages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-gray-50/50">
            <div class="text-sm text-gray-500">
                แสดง <?php echo $offset + 1; ?> ถึง <?php echo min($offset + $limit, $totalRows); ?> จาก <?php echo $totalRows; ?> รายการ
            </div>
            <div class="flex gap-1">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>&menu_id=<?php echo urlencode($searchMenuID); ?>" class="w-8 h-8 flex items-center justify-center rounded border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                        <i class="ph ph-caret-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>&menu_id=<?php echo urlencode($searchMenuID); ?>" 
                       class="w-8 h-8 flex items-center justify-center rounded border <?php echo $page == $i ? 'bg-primary text-white border-primary' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>&menu_id=<?php echo urlencode($searchMenuID); ?>" class="w-8 h-8 flex items-center justify-center rounded border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                        <i class="ph ph-caret-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Bulk Actions Handle
const selectAll = document.getElementById('selectAll');
const itemCheckboxes = document.querySelectorAll('.item-checkbox');
const bulkActionBar = document.getElementById('bulkActionBar');
const selectedCount = document.getElementById('selectedCount');

function updateBulkActionBar() {
    if (!bulkActionBar) return;
    
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    const count = checkedBoxes.length;
    
    selectedCount.textContent = count;
    
    if (count > 0) {
        bulkActionBar.classList.remove('hidden');
    } else {
        bulkActionBar.classList.add('hidden');
    }
    
    // Update selectAll checkbox state
    if (selectAll) {
        selectAll.checked = count > 0 && count === itemCheckboxes.length;
        selectAll.indeterminate = count > 0 && count < itemCheckboxes.length;
    }
}

if (selectAll) {
    selectAll.addEventListener('change', function() {
        itemCheckboxes.forEach(cb => {
            cb.checked = this.checked;
        });
        updateBulkActionBar();
    });
}

itemCheckboxes.forEach(cb => {
    cb.addEventListener('change', updateBulkActionBar);
});

function executeBulkAction() {
    const action = document.getElementById('bulkAction').value;
    if (!action) {
        alert('กรุณาเลือกการจัดการ');
        return;
    }

    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('กรุณาเลือกรายการที่ต้องการจัดการ');
        return;
    }

    let confirmMsg = '';
    if (action === 'bulk_delete') confirmMsg = 'คุณต้องการลบข้อมูลที่เลือกทั้งหมดใช่หรือไม่?';
    else if (action === 'bulk_active') confirmMsg = 'คุณต้องการเปิดใช้งานข้อมูลที่เลือกทั้งหมดใช่หรือไม่?';
    else if (action === 'bulk_inactive') confirmMsg = 'คุณต้องการปิดใช้งานข้อมูลที่เลือกทั้งหมดใช่หรือไม่?';

    AdminDialog.fire({
        title: action === 'bulk_delete' ? 'ยืนยันการลบ?' : 'ยืนยันการทำรายการ?',
        text: confirmMsg + ' (จำนวน ' + checkedBoxes.length + ' รายการ)',
        icon: action === 'bulk_delete' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'bulk_delete' ? '#d33' : '#3085d6',
        cancelButtonColor: '#aaa',
        confirmButtonText: action === 'bulk_delete' ? 'ยืนยันการลบ' : 'ตกลง',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            const ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
            document.getElementById('submenusActionName').value = action;
            document.getElementById('submenusActionId').value = '';
            document.getElementById('submenusActionIds').value = ids;
            document.getElementById('submenusActionForm').submit();
        }
    });
}

function confirmDelete(id) {
    AdminDialog.fire({
        title: 'ยืนยันการลบ?',
        text: 'คุณต้องการลบทิ้งข้อมูลนี้ใช่หรือไม่?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'ยืนยันการลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('submenusActionName').value = 'delete';
            document.getElementById('submenusActionId').value = id;
            document.getElementById('submenusActionIds').value = '';
            document.getElementById('submenusActionForm').submit();
        }
    });
}
</script>

<?php include_once('../../includes/footer.php'); ?>
