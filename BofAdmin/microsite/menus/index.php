<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการเมนูเว็บไซต์';
include_once('../../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;

if (empty($deptID)) {
    echo "<script>alert('กรุณาเลือกหน่วยงานก่อนจัดการข้อมูล'); window.location.href = '../index.php';</script>";
    exit;
}

// Check Permissions
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
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (View Permission Required)'); window.location.href = '../index.php';</script>";
    exit;
}

$searchKeyword = $_GET['keyword'] ?? '';
$searchStatus = $_GET['status'] ?? '';

// Fetch Data
$menuList = [];
try {
    if (isset($DB)) {
        $sqlBase = "SELECT * FROM microsite_menus WHERE DeptID = :deptId AND (DelData IS NULL OR DelData = 'N')";
        $params = [':deptId' => $deptID];
        
        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (MenuNameTH LIKE :keyword OR MenuNameEN LIKE :keyword)";
            $params[':keyword'] = "%$searchKeyword%";
        }

        // Summary
        $stmtSum = $DB->prepare("SELECT COUNT(*) as total, 
				SUM(CASE WHEN IsActive = 'Y' THEN 1 ELSE 0 END) as active_count,
				SUM(CASE WHEN IsActive != 'Y' THEN 1 ELSE 0 END) as inactive_count 
				FROM ($sqlBase) as t");
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        $sql = $sqlBase;
        if ($searchStatus === 'active') {
            $sql .= " AND IsActive = 'Y'";
        } else if ($searchStatus === 'inactive') {
            $sql .= " AND IsActive = 'N'";
        }
        
        // Pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $countStmt = $DB->prepare("SELECT COUNT(*) FROM ($sql) as c");
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);
        
        $sql .= " ORDER BY OrderNo DESC, MenuID DESC LIMIT $limit OFFSET $offset";
        $stmt = $DB->prepare($sql);
        $stmt->execute($params);
        $menuList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo "<div style='color:red; font-weight:bold; padding: 20px;'>DB Error: " . $e->getMessage() . "</div>";
}
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="window.location.href='index.php'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">เมนู ทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-list text-3xl"></i>
        </div>
    </div>
    
    <div onclick="window.location.href='index.php?status=active'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'active' ? 'border-green-500 ring-2 ring-green-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-green-600 font-medium mb-1">เปิดใช้งาน (Active)</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['active_count'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-500 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-check-circle text-3xl"></i>
        </div>
    </div>
    
    <div onclick="window.location.href='index.php?status=inactive'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'inactive' ? 'border-amber-500 ring-2 ring-amber-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-amber-600 font-medium mb-1">ปิดการใช้งาน (Inactive)</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['inactive_count'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-amber-50 flex items-center justify-center text-amber-550 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-minus-circle text-3xl"></i>
        </div>
    </div>
</div>

<div class="glass-panel p-6">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4 border-b border-gray-100 pb-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-list text-primary"></i>
                จัดการเมนูเว็บไซต์
            </h2>
            <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? ''); ?></span></div>
        </div>
        
        <div class="flex items-center gap-3">
					<a href="../submenus/index.php" class="px-4 py-2 border border-primary/20 text-primary bg-white hover:bg-primary hover:text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
							<i class="ph ph-list-dashes"></i> จัดการเมนูย่อย
					</a>
					<?php if ($canAdd): ?>
					<a href="form.php?action=add" class="btn-primary px-5 py-2 text-sm font-semibold flex items-center gap-2">
							<i class="ph ph-plus-circle text-lg"></i> เพิ่มเมนูใหม่
					</a>
					<?php endif; ?>
        </div>
    </div>
    <form method="GET" action="index.php" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 flex flex-col sm:flex-row gap-4">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($searchStatus); ?>">
        <div class="flex-1">
            <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ชื่อเมนู..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6">ค้นหา</button>
            <?php if($searchKeyword != ''): ?>
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
								<th class="py-4 px-6 font-semibold w-5/12">ชื่อเมนู</th>
								<th class="py-4 px-6 font-semibold w-32 text-center">สถานะ</th>
								<th class="py-4 px-6 font-semibold w-32 text-center">จัดการ</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100 bg-white">
							<?php if (empty($menuList)): ?>
							<tr>
									<td colspan="6" class="text-center py-8 text-gray-500">
											<i class="ph ph-folder-open text-4xl mb-2 text-gray-300 block"></i>
											<p>ยังไม่มีข้อมูล เมนูเว็บไซต์</p>
									</td>
							</tr>
							<?php else: ?>
								<?php 
									foreach ($menuList as $row): 
								?>
								<tr class="hover:bg-gray-50/50 transition-colors group">
									<td class="py-4 px-6 text-center">
										<input type="checkbox" name="bulk_ids[]" value="<?php echo $row['MenuID']; ?>" class="item-checkbox rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
									</td>	
								<td class="py-4 px-6">
									<div class="font-medium text-gray-800 text-base mb-1">
										<?php echo htmlspecialchars($row['MenuNameTH'] ?? ''); ?>
									</div>
									<?php if (!empty($row['MenuNameEN'])): ?>
									<?php endif; ?>
									<div class="text-sm text-gray-500 mt-1 flex items-center gap-1 truncate max-w-sm">
										<i class="ph ph-link text-gray-400"></i>
										<?php echo htmlspecialchars($row['URL']); ?>
									</div>
								</td>
									<td class="py-4 px-6 text-center">
											<?php if(($row['IsActive'] ?? 'Y') == 'Y'): ?>
												<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-green-50 text-green-600 border border-green-200">
													เปิดใช้งาน
												</span>
											<?php else: ?>
												<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200">
													ปิดใช้งาน
												</span>
											<?php endif; ?>
									</td>
									<td class="py-4 px-6 text-center">
										<div class="flex items-center justify-center gap-2">
                      <?php if ($canEdit): ?>
											<a href="form.php?id=<?php echo $row['MenuID']; ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors tooltip" title="แก้ไข">
												<i class="ph ph-pencil-simple text-lg"></i>
											</a>
												<?php endif; ?>
												<?php if ($canDelete): ?>
													<button onclick="deleteData(<?php echo $row['MenuID']; ?>)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center transition-colors tooltip" title="ลบ">
														<i class="ph ph-trash text-lg"></i>
													</button>
                      <?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalRecords > 0): ?>
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-gray-100 gap-4">
        <div class="text-sm text-gray-500">
            แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo $totalPages; ?> (ทั้งหมด <?php echo $totalRecords; ?> รายการ)
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="flex gap-2 flex-wrap items-center">
            <!-- First Page -->
            <?php if ($page > 1): ?>
                <a href="index.php?page=1&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าแรก">
                    <i class="ph ph-caret-double-left text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $page - 1; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าก่อนหน้า">
                    <i class="ph ph-caret-left text-sm"></i>
                </a>
            <?php else: ?>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed" title="หน้าแรก">
                    <i class="ph ph-caret-double-left text-sm"></i>
                </span>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed" title="หน้าก่อนหน้า">
                    <i class="ph ph-caret-left text-sm"></i>
                </span>
            <?php endif; ?>

            <!-- Page Numbers -->
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="index.php?page=<?php echo $i; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == $page ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:text-primary transition-colors'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <!-- Next Page -->
            <?php if ($page < $totalPages): ?>
                <a href="index.php?page=<?php echo $page + 1; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าถัดไป">
                    <i class="ph ph-caret-right text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $totalPages; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าสุดท้าย">
                    <i class="ph ph-caret-double-right text-sm"></i>
                </a>
            <?php else: ?>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed" title="หน้าถัดไป">
                    <i class="ph ph-caret-right text-sm"></i>
                </span>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed" title="หน้าสุดท้าย">
                    <i class="ph ph-caret-double-right text-sm"></i>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
        const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
        selectedCount.textContent = checkedBoxes.length;
        
        if (checkedBoxes.length > 0) {
            bulkActionBar.classList.remove('hidden');
        } else {
            bulkActionBar.classList.add('hidden');
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateCount();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!this.checked && selectAll) {
                selectAll.checked = false;
            } else if (document.querySelectorAll('.item-checkbox:checked').length === checkboxes.length && selectAll) {
                selectAll.checked = true;
            }
            updateCount();
        });
    });
});

function executeBulkAction() {
    const action = document.getElementById('bulkAction').value;
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    
    if (!action) {
        AdminDialog.fire({
            icon: 'warning',
            title: 'กรุณาเลือกการจัดการ',
            text: 'โปรดเลือกรูปแบบการจัดการที่ต้องการก่อน'
        });
        return;
    }

    if (checkedBoxes.length === 0) {
        AdminDialog.fire({
            icon: 'warning',
            title: 'กรุณาเลือกรายการ',
            text: 'โปรดเลือกรายการที่ต้องการจัดการอย่างน้อย 1 รายการ'
        });
        return;
    }

    let actionStr = '';
    let confirmText = '';
    let confirmColor = '#3085d6';

    switch (action) {
        case 'bulk_active':
            actionStr = 'เปิดใช้งาน';
            confirmText = 'ยืนยันการเปิดใช้งานรายการที่เลือก?';
            break;
        case 'bulk_inactive':
            actionStr = 'ปิดใช้งาน';
            confirmText = 'ยืนยันการปิดใช้งานรายการที่เลือก?';
            break;
        case 'bulk_delete':
            actionStr = 'ลบข้อมูล';
            confirmText = 'ยืนยันการลบข้อมูลที่เลือก? (ไม่สามารถกู้คืนได้)';
            confirmColor = '#d33';
            break;
    }

    AdminDialog.fire({
        title: confirmText,
        text: `จำนวน ${checkedBoxes.length} รายการ`,
        icon: action === 'bulk_delete' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#aaa',
        confirmButtonText: action === 'bulk_delete' ? 'ยืนยันการลบ' : 'ตกลง',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            let ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
            window.location.href = 'action.php?action=' + action + '&ids=' + ids;
        }
    });
}

function deleteData(id) {
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
            window.location.href = 'action.php?action=delete&id=' + id;
        }
    });
}
</script>
<?php include_once('../../includes/footer.php'); ?>