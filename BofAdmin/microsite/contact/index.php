<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'ติดต่อหน่วยงาน';
include_once('../../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>alert('กรุณาเลือกหน่วยงานก่อนจัดการข้อมูล'); window.location.href = '../index.php';</script>";
    exit;
}

// Check Permissions
$userGroupID = $_SESSION['GroupID'] ?? '';
$canView = false; $canAdd = false; $canEdit = false; $canDelete = false;

if ($userGroupID === 'A') {
    $canView = $canAdd = $canEdit = $canDelete = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $canView   = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'contact', 'CanView');
    $canAdd    = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'contact', 'CanAdd');
    $canEdit   = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'contact', 'CanEdit');
    $canDelete = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'contact', 'CanDelete');
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
        // Fetch groups for display
        $groups = [];
        $stmtGrp = $DB->prepare("SELECT GroupID, GroupNameTH FROM microsite_contact_groups WHERE DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
        $stmtGrp->execute([$deptID]);
        while ($g = $stmtGrp->fetch(PDO::FETCH_ASSOC)) {
            $groups[$g['GroupID']] = $g['GroupNameTH'];
        }

        $stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID = ?");
        $stmtSlug->execute([$deptID]);
        $deptSlug = $stmtSlug->fetchColumn();

        $sqlBase = "SELECT * FROM microsite_contact WHERE DeptID = :deptId AND (DelData IS NULL OR DelData = 'N')";
        $params  = [':deptId' => $deptID];

        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (Name LIKE :keyword1 OR Tel LIKE :keyword2 OR Email LIKE :keyword3)";
            $params[':keyword1'] = "%$searchKeyword%";
            $params[':keyword2'] = "%$searchKeyword%";
            $params[':keyword3'] = "%$searchKeyword%";
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
        $result = paginateWithCount($DB, $sqlBase, $params, $page, 25, 'ContactID DESC');
        $itemList = $result['data'];
    }
} catch (PDOException $e) { $dbError = $e->getMessage(); }
?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="window.location.href='index.php'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center transition-all duration-300">
        <div><div class="text-sm text-gray-500 font-medium mb-1">รายการทั้งหมด</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600"><i class="ph ph-list text-3xl"></i></div>
    </div>
    <div onclick="window.location.href='index.php?status=active'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'active' ? 'border-green-500 ring-2 ring-green-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center transition-all duration-300">
        <div><div class="text-sm text-green-600 font-medium mb-1">เปิดใช้งาน (Active)</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['active_count'] ?? 0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-500"><i class="ph ph-check-circle text-3xl"></i></div>
    </div>
    <div onclick="window.location.href='index.php?status=inactive'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'inactive' ? 'border-amber-500 ring-2 ring-amber-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center transition-all duration-300">
        <div><div class="text-sm text-amber-600 font-medium mb-1">ปิดการใช้งาน (Inactive)</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['inactive_count'] ?? 0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-amber-50 flex items-center justify-center text-amber-500"><i class="ph ph-minus-circle text-3xl"></i></div>
    </div>
</div>

<div class="glass-panel p-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-100 pb-4 mb-6 gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-phone text-primary"></i> จัดการข้อมูลติดต่อหน่วยงาน
            </h2>
            <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? ''); ?></span></div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($canEdit): ?>
            <a href="group.php" class="px-4 h-10 border border-primary/20 text-primary bg-white hover:bg-primary hover:text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2 text-sm">
                <i class="ph ph-folder-open"></i> จัดการกลุ่มติดต่อหน่วยงาน
            </a>
            <?php endif; ?>
            <?php /* if ($canAdd): ?>
            <a href="form.php" class="btn-primary px-4 h-10 flex items-center gap-2 text-sm">
                <i class="ph ph-plus-circle text-lg"></i> เพิ่มข้อมูลติดต่อ
            </a>
            <?php endif; */ ?>
        </div>
    </div>

    <!-- Search Box -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
        <form method="GET" action="" class="flex gap-4">
            <div class="flex-1 relative">
              <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ค้นหาชื่อ, อีเมล..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary bg-white h-10">
            </div>
            <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6">ค้นหา</button>
            <?php if($searchKeyword != ''): ?>
                <a href="index.php" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-10">ล้างค่า</a>
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
                    <th>ชื่อ-สกุล</th>
                    <th>กลุ่มติดต่อหน่วยงาน</th>
                    <th width="160">อีเมล</th>
                    <th width="120" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($dbError)): ?>
                <tr><td colspan="6" class="py-12 text-center text-red-500 font-medium">Database Error: <?php echo htmlspecialchars($dbError); ?></td></tr>
                <?php elseif (empty($itemList)): ?>
                <tr>
                    <td colspan="6" class="py-12 text-center text-gray-400">
                        <i class="ph ph-phone-slash text-4xl mb-2 block"></i>
                        ยังไม่มีข้อมูลติดต่อหน่วยงาน
                    </td>
                </tr>
                <?php else: foreach ($itemList as $item): ?>
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="text-center">
                        <input type="checkbox" name="bulk_ids[]" value="<?php echo $item['ContactID']; ?>" class="item-checkbox rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                    </td>
                    <td>
                        <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($item['Name'] ?? '-'); ?></div>
                        <?php if (!empty($item['Title'])): ?>
                        <div class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($item['Title']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-gray-600">
                        <?php 
                        $gid = $item['GroupID'] ?? 0;
                        echo htmlspecialchars($groups[$gid] ?? ($gid > 0 ? "กลุ่ม #$gid" : '-'));
                        ?>
                    </td>
                    <td class="text-sm">
                        <?php if (!empty($item['Email'])): ?>
                        <div class="text-blue-500 text-xs mt-0.5"><?php echo htmlspecialchars($item['Email']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="flex justify-center gap-2">
                            <?php if ($canView): ?>
                            <a href="view.php?id=<?php echo $item['ContactID']; ?>" class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center hover:bg-green-100 transition-colors" title="ดูรายละเอียด">
                                <i class="ph ph-eye text-lg"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button type="button" onclick="confirmDelete(<?php echo $item['ContactID']; ?>)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition-colors" title="ลบ">
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
    <?php if (isset($result['pagination']) && $result['pagination']['total'] > 0): ?>
    <?php 
    $totalPages = $result['pagination']['total_pages'];
    $totalRecords = $result['pagination']['total'];
    $page = $page ?? 1;
    ?>
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-gray-100 gap-4">
        <div class="text-sm text-gray-500">
            แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo $totalPages; ?> (ทั้งหมด <?php echo $totalRecords; ?> รายการ)
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="flex gap-2 flex-wrap items-center">
            <!-- First Page -->
            <?php if ($page > 1): ?>
                <a href="index.php?page=1&status=<?php echo urlencode($searchStatus ?? ''); ?>&keyword=<?php echo urlencode($searchKeyword ?? ''); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าแรก">
                    <i class="ph ph-caret-double-left text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($searchStatus ?? ''); ?>&keyword=<?php echo urlencode($searchKeyword ?? ''); ?>" 
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
                <a href="index.php?page=<?php echo $i; ?>&status=<?php echo urlencode($searchStatus ?? ''); ?>&keyword=<?php echo urlencode($searchKeyword ?? ''); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == $page ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:text-primary transition-colors'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <!-- Next Page -->
            <?php if ($page < $totalPages): ?>
                <a href="index.php?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($searchStatus ?? ''); ?>&keyword=<?php echo urlencode($searchKeyword ?? ''); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าถัดไป">
                    <i class="ph ph-caret-right text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $totalPages; ?>&status=<?php echo urlencode($searchStatus ?? ''); ?>&keyword=<?php echo urlencode($searchKeyword ?? ''); ?>" 
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

function executeBulkAction() {
    const action = document.getElementById('bulkAction').value;
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    if (!action) { AdminDialog.fire({ icon: 'warning', title: 'กรุณาเลือกการจัดการ' }); return; }
    if (checkedBoxes.length === 0) { AdminDialog.fire({ icon: 'warning', title: 'กรุณาเลือกรายการ' }); return; }
    const confirmText = action === 'bulk_delete' ? 'ยืนยันการลบข้อมูลที่เลือก?' : (action === 'bulk_active' ? 'ยืนยันการเปิดใช้งาน?' : 'ยืนยันการปิดใช้งาน?');
    AdminDialog.fire({ title: confirmText, text: `จำนวน ${checkedBoxes.length} รายการ`, icon: action === 'bulk_delete' ? 'warning' : 'question', showCancelButton: true, confirmButtonColor: action === 'bulk_delete' ? '#d33' : '#3085d6', cancelButtonColor: '#aaa', confirmButtonText: action === 'bulk_delete' ? 'ยืนยันการลบ' : 'ตกลง', cancelButtonText: 'ยกเลิก' }).then((result) => {
        if (result.isConfirmed) {
            let ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
            window.location.href = 'action.php?action=' + action + '&ids=' + ids;
        }
    });
}
</script>

<?php include_once('../../includes/footer.php'); ?>
