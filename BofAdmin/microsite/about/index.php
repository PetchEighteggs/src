<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'เกี่ยวกับหน่วยงาน';
include_once('../../includes/header.php');
require_once('../../includes/action_security.php');

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
    $canView = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'about', 'CanView');
    $canAdd = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'about', 'CanAdd');
    $canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'about', 'CanEdit');
    $canDelete = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'about', 'CanDelete');
}

if (!$canView) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (View Permission Required)'); window.location.href = '../index.php';</script>";
    exit;
}

$searchKeyword = trim($_GET['keyword'] ?? '');
$searchStatus = $_GET['status'] ?? '';
$searchGroup = (int)($_GET['group'] ?? 0);

$stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID = ?");
$stmtSlug->execute([$deptID]);
$deptSlug = $stmtSlug->fetchColumn();

$groups = [];
$stmtGroups = $DB->prepare("SELECT GroupID, GroupNameTH FROM microsite_about_groups WHERE DeptID = ? AND COALESCE(DelData, 'N') <> 'Y' ORDER BY GroupNameTH ASC");
$stmtGroups->execute([$deptID]);
$groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

// Fetch Data
$itemList = [];
try {
    if (isset($DB)) {
        $sqlBase = "SELECT a.AboutID, a.Title_TH, a.CreateDate, a.Image, a.IsActive, g.GroupNameTH 
				FROM microsite_about a 
				LEFT JOIN microsite_about_groups g ON a.GroupID = g.GroupID AND COALESCE(g.DelData, 'N') <> 'Y'
				WHERE a.DeptID = :deptId AND COALESCE(a.DelData, 'N') <> 'Y'";
        $params = [':deptId' => $deptID];
        
        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (a.Title_TH LIKE :keyword1 OR a.Title_EN LIKE :keyword2)";
            $params[':keyword1'] = "%$searchKeyword%";
            $params[':keyword2'] = "%$searchKeyword%";
        }

        if ($searchGroup > 0) {
            $sqlBase .= " AND a.GroupID = :group_id";
            $params[':group_id'] = $searchGroup;
        }

        $stmtSum = $DB->prepare("SELECT COUNT(*) as total, 
				SUM(CASE WHEN IsActive = 'Y' THEN 1 ELSE 0 END) as active_count,
				SUM(CASE WHEN IsActive != 'Y' THEN 1 ELSE 0 END) as inactive_count 
				FROM ($sqlBase) as t");
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        if ($searchStatus === 'active') {
            $sqlBase .= " AND a.IsActive = 'Y'";
        } elseif ($searchStatus === 'inactive') {
            $sqlBase .= " AND a.IsActive != 'Y'";
        }

        $sql = $sqlBase;

        $page = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sql, $params, $page, 25, 'a.OrderNo ASC, a.CreateDate DESC');
        $itemList = $result['data'];
    }
} catch (PDOException $e) {}
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="window.location.href='index.php'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">รายการทั้งหมด</div>
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

<form id="aboutActionForm" action="action.php" method="POST" class="hidden">
    <?php echo bofSecurityCsrfField(); ?>
    <input type="hidden" name="action" id="aboutActionName" value="">
    <input type="hidden" name="id" id="aboutActionId" value="">
    <input type="hidden" name="ids" id="aboutActionIds" value="">
</form>

<div class="glass-panel p-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-buildings text-primary"></i>
								
                เกี่ยวกับหน่วยงาน
            </h2>
            <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? ''); ?></span></div>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($canEdit): ?>
            <a href="sort.php" class="px-4 py-2 bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 rounded-lg font-medium transition-colors flex items-center justify-center gap-2 h-10">
                <i class="ph ph-arrows-down-up text-lg"></i> จัดเรียงลำดับ
            </a>
            <?php endif; ?>
            <a href="group.php" class="px-4 h-10 border border-primary/20 text-primary bg-white hover:bg-primary hover:text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                <i class="ph ph-folder-open"></i> จัดการหมวดหมู่
            </a>
            <?php if ($canAdd): ?>
            <a href="form.php" class="btn-primary px-4 h-10 flex items-center gap-2">
                <i class="ph ph-plus-circle text-lg"></i> เพิ่มข้อมูล
            </a>
            <?php endif; ?>
        </div>
    </div>

    <form method="GET" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($searchStatus, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(220px,0.45fr)_auto] gap-4 items-end">
            <div>
                <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ชื่อหัวข้อ..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <select name="group" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none bg-white">
                    <option value="">-- ทุกหมวดหมู่ --</option>
                    <?php foreach ($groups as $group): ?>
                    <option value="<?php echo (int)$group['GroupID']; ?>" <?php echo $searchGroup === (int)$group['GroupID'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($group['GroupNameTH'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6"> ค้นหา</button>
                <?php if ($searchKeyword !== '' || $searchGroup > 0): ?>
                <a href="index.php?status=<?php echo urlencode($searchStatus); ?>" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-[42px]">ล้างค่า</a>
                <?php endif; ?>
            </div>
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

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/80 text-gray-650 text-sm border-b border-gray-100">
                    <th class="py-4 px-4 font-semibold text-gray-600 w-16 text-center">
                        <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                    </th>
                    <th class="py-4 px-4 font-semibold text-gray-600">ชื่อเกี่ยวกับหน่วยงาน </th>
                    <th class="py-4 px-4 font-semibold text-gray-600 w-48">หมวดหมู่</th>
                    <th class="py-4 px-4 font-semibold text-gray-600 w-28 text-center">สถานะ</th>
                    <th class="py-4 px-4 font-semibold text-gray-600 w-24 text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($itemList)): ?>
                <tr>
                    <td colspan="6" class="py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center justify-center">
                            <i class="ph ph-empty text-4xl text-gray-300 mb-2"></i>
                            <p>ยังไม่มีข้อมูลเกี่ยวกับหน่วยงาน</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($itemList as $index => $item): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-3 px-4 text-center">
                            <input type="checkbox" name="bulk_ids[]" value="<?php echo $item['AboutID']; ?>" class="item-checkbox rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                        </td>
                        
                        <td class="py-3 px-4">
                            <div class="font-medium text-gray-800line-clamp-2"><?php echo htmlspecialchars($item['Title_TH']); ?></div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="text-gray-600"><?php echo htmlspecialchars($item['GroupNameTH'] ?? '-'); ?></div>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <?php if ($item['IsActive'] == 'Y'): ?>
                                <span class="inline-flex px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium border border-green-200 whitespace-nowrap">เปิดใช้งาน</span>
                            <?php else: ?>
                                <span class="inline-flex px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium border border-gray-200 whitespace-nowrap">ปิดใช้งาน</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <?php if ($canEdit): ?>
                                <a href="form.php?id=<?php echo $item['AboutID']; ?>" 
                                   class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors"
                                   title="แก้ไข">
                                    <i class="ph ph-pencil-simple text-lg"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                <button type="button" 
                                        onclick="deleteAbout(<?php echo (int)$item['AboutID']; ?>)"
                                        class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition-colors"
                                        title="ลบ">
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
                <a href="index.php?page=1&status=<?php echo urlencode($searchStatus ?? ''); ?>&keyword=<?php echo urlencode($searchKeyword ?? ''); ?>&group=<?php echo (int)$searchGroup; ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าแรก">
                    <i class="ph ph-caret-double-left text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($searchStatus ?? ''); ?>&keyword=<?php echo urlencode($searchKeyword ?? ''); ?>&group=<?php echo (int)$searchGroup; ?>" 
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
                <a href="index.php?page=<?php echo $i; ?>&status=<?php echo urlencode($searchStatus ?? ''); ?>&keyword=<?php echo urlencode($searchKeyword ?? ''); ?>&group=<?php echo (int)$searchGroup; ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == $page ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:text-primary transition-colors'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <!-- Next Page -->
            <?php if ($page < $totalPages): ?>
                <a href="index.php?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($searchStatus ?? ''); ?>&keyword=<?php echo urlencode($searchKeyword ?? ''); ?>&group=<?php echo (int)$searchGroup; ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าถัดไป">
                    <i class="ph ph-caret-right text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $totalPages; ?>&status=<?php echo urlencode($searchStatus ?? ''); ?>&keyword=<?php echo urlencode($searchKeyword ?? ''); ?>&group=<?php echo (int)$searchGroup; ?>" 
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

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const bulkActionBar = document.getElementById('bulkActionBar');

    function updateCount() {
        const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
        if (selectedCount) selectedCount.textContent = checkedBoxes.length;
        
        if (bulkActionBar) {
            if (checkedBoxes.length > 0) {
                bulkActionBar.classList.remove('hidden');
            } else {
                bulkActionBar.classList.add('hidden');
            }
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
            document.getElementById('aboutActionName').value = action;
            document.getElementById('aboutActionId').value = '';
            document.getElementById('aboutActionIds').value = ids;
            document.getElementById('aboutActionForm').submit();
        }
    });
}

function deleteAbout(id) {
    AdminDialog.fire({ title: 'ยืนยันการลบ?', text: 'ข้อมูลนี้จะถูกลบ', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#aaa', confirmButtonText: 'ยืนยันการลบ', cancelButtonText: 'ยกเลิก' }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('aboutActionName').value = 'delete';
            document.getElementById('aboutActionId').value = id;
            document.getElementById('aboutActionIds').value = '';
            document.getElementById('aboutActionForm').submit();
        }
    });
}

function confirmDeleteUrl(url, text = 'คุณต้องการลบทิ้งข้อมูลนี้ใช่หรือไม่?') {
    AdminDialog.fire({
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
