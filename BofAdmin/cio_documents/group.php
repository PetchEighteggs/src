<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = 'จัดการกลุ่มเอกสาร CIO';
include_once('../includes/header.php');

$searchKeyword = $_GET['keyword'] ?? '';
$searchStatus = $_GET['status'] ?? '';
$dataList = [];

try {
    if (isset($DB)) {
        $sqlBase = "SELECT GroupID, GroupName_TH, GroupName_EN, OrderNo, CreateDate, IsActive 
                    FROM cio_document_groups 
                    WHERE DelData = 'N'";
        $params = [];
        
        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (GroupName_TH LIKE :keyword OR GroupName_EN LIKE :keyword)";
            $params[':keyword'] = "%$searchKeyword%";
        }

        $summarySql = "SELECT COUNT(*) as total, 
                       SUM(CASE WHEN IsActive = 'Y' THEN 1 ELSE 0 END) as active_count,
                       SUM(CASE WHEN IsActive != 'Y' THEN 1 ELSE 0 END) as inactive_count 
                       FROM ($sqlBase) as t";
        $stmtSum = $DB->prepare($summarySql);
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        $sql = $sqlBase;
        if ($searchStatus === 'active') {
            $sql .= " AND IsActive = 'Y'";
        } else if ($searchStatus === 'inactive') {
            $sql .= " AND IsActive = 'N'";
        }

        $page = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sql, $params, $page, 25, 'GroupID DESC');
        $dataList = $result['data'];
    }
} catch (PDOException $e) {}
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="filterStatus('')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">กลุ่มทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-folder text-3xl"></i>
        </div>
    </div>
    
    <div onclick="filterStatus('active')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'active' ? 'border-green-500 ring-2 ring-green-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-green-600 font-medium mb-1">เปิดใช้งาน (Active)</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['active_count'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-500 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-check-circle text-3xl"></i>
        </div>
    </div>
    
    <div onclick="filterStatus('inactive')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'inactive' ? 'border-amber-500 ring-2 ring-amber-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
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
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
        <div class="flex items-center gap-4">
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
              <i class="ph ph-folder-open text-primary"></i> กลุ่มเอกสาร CIO
            </h2>
        </div>
        
        <div class="flex gap-2 items-center">
            <?php if ($userPermissions['add']): ?>
            <a href="group_form.php" class="btn-primary">
              <i class="ph ph-plus-circle"></i> เพิ่มกลุ่ม
            </a>
            <?php endif; ?>
            <a href="index.php" class="px-6 h-10 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                <i class="ph ph-arrow-left"></i> ย้อนกลับ
            </a>
        </div>
    </div>

    <!-- Search Form -->
    <form id="filterForm" method="GET" action="group.php" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 flex flex-col sm:flex-row gap-4">
        <input type="hidden" name="status" id="statusFilterInput" value="<?php echo htmlspecialchars($searchStatus); ?>">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหา</label>
            <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6">
              ค้นหา
            </button>
            <?php if($searchKeyword != ''): ?>
                <a href="group.php" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-[42px]">
                    ล้างค่า
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
    <div class="flex items-center gap-3 mb-6 hidden bg-gray-50 p-4 rounded-xl border border-gray-200" id="bulkActionContainer">
        <span class="text-sm font-medium text-gray-700">จัดการรายการที่เลือก:</span>
        <select id="bulkActionSelect" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-blue-500 bg-white h-[42px] min-w-[200px]">
            <option value="">-- เลือกการจัดการ --</option>
            <option value="active">เปิดใช้งาน</option>
            <option value="inactive">ปิดใช้งาน</option>
            <option value="delete">ลบข้อมูล</option>
        </select>
        <button type="button" onclick="executeBulkAction()" class="btn-primary px-6 h-[42px] text-sm font-medium flex items-center justify-center">นำไปใช้</button>
        <span class="text-sm text-gray-500 ml-2" id="bulkCountText"></span>
    </div>
    <?php endif; ?>

    <?php if (defined('IS_AJAX')) { ob_clean(); } ?>
    <div id="dataTableContainer" class="space-y-6">
        <div class="overflow-x-auto">
            <table class="premium-table min-w-full">
                <thead>
                    <tr>
                        <th class="w-12 text-center">
                            <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        </th>
                        <th class="text-left">ชื่อกลุ่ม</th>
                        <th class="w-32 text-center">สถานะ</th>
                        <th class="w-32 text-center">วันที่สร้าง</th>
                        <th class="w-24 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataList)): ?>
                        <tr><td colspan="5" class="text-center py-8 text-gray-500">ไม่พบข้อมูล</td></tr>
                    <?php else: ?>
                        <?php foreach ($dataList as $row): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="text-center">
                                <input type="checkbox" class="row-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4" value="<?php echo $row['GroupID']; ?>">
                            </td>
                            <td class="font-medium text-gray-900">
                                <?php echo htmlspecialchars($row['GroupName_TH']); ?>
                            </td>
                            <td>
                              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $row['IsActive'] == 'Y' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $row['IsActive'] == 'Y' ? 'เปิดใช้งาน' : 'ปิดใช้งาน'; ?>
                              </span>
                            </td>
                            <td class="text-center text-sm text-gray-500">
                              <?php echo !empty($row['CreateDate']) ? date('d/m/Y', strtotime($row['CreateDate'])) : '-'; ?>
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if ($userPermissions['edit']): ?>
                                    <a href="group_form.php?id=<?php echo $row['GroupID']; ?>" class="w-8 h-8 rounded bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="แก้ไข">
                                      <i class="ph ph-pencil-simple"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($userPermissions['delete']): ?>
                                    <button type="button" onclick="deleteItem(<?php echo $row['GroupID']; ?>)" class="w-8 h-8 rounded bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition-colors" title="ลบข้อมูล">
                                      <i class="ph ph-trash"></i>
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

        <?php if (isset($result['pagination'])): ?>
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-gray-100 gap-4">
            <div class="text-sm text-gray-500">
                แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo $result['pagination']['total_pages']; ?> (ทั้งหมด <?php echo $result['pagination']['total']; ?> รายการ)
            </div>
            <?php if ($result['pagination']['total_pages'] > 1): ?>
            <div class="flex gap-2 flex-wrap items-center">
                <!-- First Page -->
                <?php if ($page > 1): ?>
                    <a href="group.php?page=1&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                       class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าแรก">
                        <i class="ph ph-caret-double-left text-sm"></i>
                    </a>
                    <a href="group.php?page=<?php echo $page - 1; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
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
                $totalPages = $result['pagination']['total_pages'];
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="group.php?page=<?php echo $i; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                       class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == $page ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:text-primary transition-colors'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <!-- Next Page -->
                <?php if ($page < $totalPages): ?>
                    <a href="group.php?page=<?php echo $page + 1; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                       class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าถัดไป">
                        <i class="ph ph-caret-right text-sm"></i>
                    </a>
                    <a href="group.php?page=<?php echo $totalPages; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
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
    <?php if (defined('IS_AJAX')) { exit; } ?>
</div>

<script>
let currentKeyword = '<?php echo $searchKeyword; ?>';
let currentStatus = '<?php echo $searchStatus; ?>';

function filterStatus(status) {
    document.getElementById('statusFilterInput').value = status;
    document.getElementById('filterForm').submit();
}

function showLoading() {
    Swal.fire({
        title: 'กำลังโหลด...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
}

// Bulk Actions
const selectAll = document.getElementById('selectAll');
const rowCheckboxes = document.querySelectorAll('.row-checkbox');
const bulkActionContainer = document.getElementById('bulkActionContainer');
const bulkCountText = document.getElementById('bulkCountText');

function updateBulkActionVisibility() {
    if(!bulkActionContainer) return;
    const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
    if (checkedCount > 0) {
        bulkActionContainer.classList.remove('hidden');
        bulkActionContainer.classList.add('flex');
        bulkCountText.textContent = `(เลือกแล้ว ${checkedCount} รายการ)`;
    } else {
        bulkActionContainer.classList.add('hidden');
        bulkActionContainer.classList.remove('flex');
    }
}

if(selectAll) {
    selectAll.addEventListener('change', (e) => {
        rowCheckboxes.forEach(cb => cb.checked = e.target.checked);
        updateBulkActionVisibility();
    });
}

rowCheckboxes.forEach(cb => {
    cb.addEventListener('change', () => {
        if(selectAll) {
            selectAll.checked = document.querySelectorAll('.row-checkbox:checked').length === rowCheckboxes.length;
        }
        updateBulkActionVisibility();
    });
});

function executeBulkAction() {
    const action = document.getElementById('bulkActionSelect').value;
    if (!action) {
        Swal.fire('กรุณาเลือกการจัดการ', '', 'warning');
        return;
    }

    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    const ids = Array.from(checkedBoxes).map(cb => cb.value);

    let confirmTitle = '';
    let confirmText = '';
    let confirmBtnText = '';
    let confirmBtnColor = '';

    if (action === 'delete') {
        confirmTitle = 'ยืนยันการลบข้อมูล';
        confirmText = `จำนวน ${ids.length} รายการ`;
        confirmBtnText = 'ตกลง';
        confirmBtnColor = '#ef4444';
    } else {
        const actionText = action === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
        confirmTitle = `ยืนยันการ${actionText}`;
        confirmText = `จำนวน ${ids.length} รายการ`;
        confirmBtnText = 'ตกลง';
        confirmBtnColor = '#3085d6';
    }

    Swal.fire({
        title: confirmTitle,
        text: confirmText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: confirmBtnColor,
        cancelButtonColor: '#aaa',
        confirmButtonText: confirmBtnText,
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `group_action.php?action=bulk_${action}&ids=${ids.join(',')}`;
        }
    });
}

function deleteItem(id) {
    Swal.fire({
        title: 'ยืนยันการลบข้อมูล',
        text: "คุณไม่สามารถกู้คืนข้อมูลนี้ได้!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `group_action.php?action=delete&id=${id}`;
        }
    });
}
</script>
<?php include_once('../includes/footer.php'); ?>
