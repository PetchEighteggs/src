<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการหมวดหมู่เกี่ยวกับหน่วยงาน';
include_once('../../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>alert('กรุณาเลือกหน่วยงานก่อนจัดการข้อมูล'); window.location.href = '../index.php';</script>";
    exit;
}
// Permission Check
$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = false;
$canEdit = false;
$canAdd = false;
$canDelete = false;

if ($userGroupID === 'A') {
    $hasAccess = true;
    $canEdit = true;
    $canAdd = true;
    $canDelete = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'about', 'CanEdit');
    $canAdd = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'about', 'CanAdd');
    $canDelete = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'about', 'CanDelete');
    $hasAccess = ($canEdit || $canAdd);
}

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์จัดการหมวดหมู่เกี่ยวกับหน่วยงาน'); window.location.href = 'index.php';</script>";
    exit;
}
$searchKeyword = $_GET['keyword'] ?? '';

// Fetch Data
$itemList = [];
try {
    if (isset($DB)) {
        $sqlBase = "SELECT * FROM microsite_about_groups WHERE DeptID = :deptId AND (DelData IS NULL OR DelData = 'N')";
        $params = [':deptId' => $deptID];
        
        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (GroupNameTH LIKE :keyword1 OR GroupNameEN LIKE :keyword2)";
            $params[':keyword1'] = "%$searchKeyword%";
            $params[':keyword2'] = "%$searchKeyword%";
        }

        $searchStatus = $_GET['status'] ?? '';

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

        $sql = $sqlBase;
        $page = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sql, $params, $page, 25, 'OrderNo ASC, GroupID ASC');
        $itemList = $result['data'];
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="window.location.href='group.php'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">รายการทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-list text-3xl"></i>
        </div>
    </div>
    
    <div onclick="window.location.href='group.php?status=active'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'active' ? 'border-green-500 ring-2 ring-green-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-green-600 font-medium mb-1">เปิดใช้งาน (Active)</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['active_count'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-500 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-check-circle text-3xl"></i>
        </div>
    </div>
    
    <div onclick="window.location.href='group.php?status=inactive'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'inactive' ? 'border-amber-500 ring-2 ring-amber-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
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
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-100">
        <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-folder-open text-primary"></i>
                จัดการหมวดหมู่เกี่ยวกับหน่วยงาน
            </h2>
            <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? ''); ?></span></div>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($canEdit): ?>
            <a href="sort.php" class="px-4 py-2 bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 rounded-lg font-medium transition-colors flex items-center justify-center gap-2 h-10">
                <i class="ph ph-arrows-down-up text-lg"></i> จัดเรียงลำดับ
            </a>
            <?php endif; ?>
            <?php if ($canAdd): ?>
            <a href="group_form.php" class="btn-primary h-10 px-6 flex items-center justify-center gap-2 rounded-lg font-medium transition-colors">
                <i class="ph ph-plus-circle text-lg"></i> เพิ่มหมวดหมู่
            </a>
            <?php endif; ?>
            <a href="index.php" class="px-6 h-10 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                <i class="ph ph-arrow-left"></i> ย้อนกลับ
            </a>
        </div>
    </div>

    <!-- Search Box -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
        <form method="GET" action="" class="flex gap-4">
            <div class="flex-1 relative">
                <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ค้นหาหมวดหมู่..." class="w-full pl-5 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary bg-white h-10">
            </div>
            <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6">ค้นหา</button>
            <?php if($searchKeyword != ''): ?>
                <a href="group.php" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-10">ล้างค่า</a>
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
                    <th width="50" class="text-center"><input type="checkbox" id="selectAll" class="rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer"></th>
                    <th>ชื่อหมวดหมู่</th>
                    <th width="120">สถานะ</th>
                    <th width="150" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($dbError)): ?>
                <tr><td colspan="5" class="py-12 text-center text-red-500 font-medium">Database Error: <?php echo htmlspecialchars($dbError); ?></td></tr>
                <?php elseif (empty($itemList)): ?>
                <tr><td colspan="5" class="py-12 text-center text-gray-500">ยังไม่มีข้อมูลหมวดหมู่</td></tr>
                <?php else: foreach ($itemList as $index => $item): ?>
                <tr>
                    <td class="text-center"><input type="checkbox" name="bulk_ids[]" value="<?php echo $item['GroupID']; ?>" class="item-checkbox rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer"></td>
                    <td class="font-semibold text-gray-800"><?php echo htmlspecialchars($item['GroupNameTH']); ?></td>
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
                            <a href="group_form.php?id=<?php echo $item['GroupID']; ?>" class="w-8 h-8 rounded-lg bg-blue-55 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="แก้ไข">
                                <i class="ph ph-pencil-simple text-lg"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button type="button" onclick="confirmDeleteUrl('group_action.php?action=delete&id=<?php echo $item['GroupID']; ?>')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition-colors" title="ลบ">
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
    const bulkActionBar = document.getElementById('bulkActionBar');
    const selectedCountSpan = document.getElementById('selectedCount');

    function updateCount() {
        if (!bulkActionBar) return;
        const count = document.querySelectorAll('.item-checkbox:checked').length;
        if (selectedCountSpan) selectedCountSpan.textContent = count;
        
        if (count > 0) {
            bulkActionBar.classList.remove('hidden');
            // Force reflow
            void bulkActionBar.offsetWidth;
            bulkActionBar.style.opacity = '1';
            bulkActionBar.style.transform = 'translateY(0)';
        } else {
            bulkActionBar.style.opacity = '0';
            bulkActionBar.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                if (document.querySelectorAll('.item-checkbox:checked').length === 0) {
                    bulkActionBar.classList.add('hidden');
                }
            }, 300);
        }
    }

    // Initialize state
    if (bulkActionBar) {
        bulkActionBar.style.opacity = '0';
        bulkActionBar.style.transform = 'translateY(-10px)';
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
        Swal.fire({
            icon: 'warning',
            title: 'กรุณาเลือกการจัดการ',
            text: 'โปรดเลือกรูปแบบการจัดการที่ต้องการก่อน'
        });
        return;
    }

    if (checkedBoxes.length === 0) {
        Swal.fire({
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

    Swal.fire({
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
            window.location.href = 'group_action.php?action=' + action + '&ids=' + ids;
        }
    });
}

function confirmDeleteUrl(url, text = 'คุณต้องการลบทิ้งข้อมูลนี้ใช่หรือไม่?') {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'ยืนยันการลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}
</script>

<?php include_once('../../includes/footer.php'); ?>
