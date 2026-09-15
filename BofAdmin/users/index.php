<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = "จัดการผู้ใช้งานระบบ";
include_once('../includes/header.php');

$search = mosGetParam($_FORM, 'search', '');
$status = mosGetParam($_FORM, 'status', '');
$filterGroup = mosGetParam($_FORM, 'group_id', '');
$filterSetGroup = mosGetParam($_FORM, 'set_group_id', '');
$filterDept = mosGetParam($_FORM, 'dept_id', '');

// Fetch option data
$setGroups = [];
try {
    $stmtGroup = $DB->prepare("SELECT SetGroupID, SetGroupName FROM staff_set_group WHERE DelData = 'N' AND Approved = 'Y' ORDER BY SetOrder ASC, SetGroupID ASC");
    $stmtGroup->execute();
    $setGroups = $stmtGroup->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

$depts = [];
try {
    $stmtDept = $DB->prepare("SELECT DeptID, NameTH FROM microsite_departments WHERE IsActive = 'Y' ORDER BY NameTH ASC");
    $stmtDept->execute();
    $depts = $stmtDept->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

$sql = "SELECT s.* FROM staff s WHERE s.DelData = 'N'";
$params = [];

if ($search != '') {
    $sql .= " AND (s.Username LIKE :search OR s.Name LIKE :search OR s.Email LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($status != '') {
    $sql .= " AND s.Status = :status";
    $params[':status'] = $status;
}
if ($filterGroup != '') {
    $sql .= " AND s.GroupID = :group_id";
    $params[':group_id'] = $filterGroup;
}
if ($filterSetGroup != '') {
    $sql .= " AND s.SetGroupID = :set_group_id";
    $params[':set_group_id'] = $filterSetGroup;
}
if ($filterDept != '') {
    $sql .= " AND EXISTS (SELECT 1 FROM staff_microsite_access a WHERE a.StaffID = s.StaffID AND a.DeptID = :dept_id)";
    $params[':dept_id'] = $filterDept;
}

$page = mosGetParam($_FORM, 'page', 1);
$result = paginateWithCount($DB, $sql, $params, $page, 20, 's.StaffID DESC');

$queryParams = [
    'search' => $search,
    'status' => $status,
    'group_id' => $filterGroup,
    'set_group_id' => $filterSetGroup,
    'dept_id' => $filterDept
];
$queryParamsFiltered = array_filter($queryParams, function($val) {
    return $val !== '';
});
$queryString = http_build_query($queryParamsFiltered);
$queryStringPrefix = $queryString !== '' ? '&' . $queryString : '';

$isFiltered = ($search !== '' || $status !== '' || $filterGroup !== '' || $filterSetGroup !== '' || $filterDept !== '');
$isAdvancedActive = ($filterGroup !== '' || $filterSetGroup !== '' || $filterDept !== '');
?>

<div class="glass-panel">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-gray-800">
					<i class="ph ph-user-gear"></i> ผู้ใช้งานระบบ</h2>
        <a href="form.php" class="btn-primary">
            <i class="ph ph-plus"></i> เพิ่มผู้ใช้งาน
        </a>
    </div>

    <!-- Search Box & Filters -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
        <form id="filterForm" method="GET" action="index.php" class="space-y-4">
            <!-- Row 1: Basic Filters -->
            <div class="flex flex-col md:flex-row gap-3 items-center">
                <!-- Keyword Search -->
                <div class="flex-1 relative w-full">
                    <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ค้นหาชื่อผู้ใช้, ชื่อ, อีเมล..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm">
                </div>
                
                <!-- Status dropdown -->
                <div class="w-full md:w-48">
                    <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm bg-white cursor-pointer">
                        <option value="">-- สถานะทั้งหมด --</option>
                        <option value="Y" <?php echo $status === 'Y' ? 'selected' : ''; ?>>ใช้งาน</option>
                        <option value="N" <?php echo $status === 'N' ? 'selected' : ''; ?>>ระงับการใช้งาน</option>
                    </select>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex gap-2 w-full md:w-auto shrink-0">
                    <button type="button" id="toggleAdvanced" class="px-4 py-2 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-100 font-medium text-sm transition-colors border border-gray-200 flex items-center justify-center gap-1.5 cursor-pointer h-[42px] whitespace-nowrap">
                        <i class="ph ph-sliders-horizontal"></i>
                        <span>ตัวกรองขั้นสูง</span>
                    </button>
                    <button type="submit" class="btn-primary px-6 py-2 rounded-lg text-sm font-medium flex items-center justify-center gap-1.5 cursor-pointer h-[42px] whitespace-nowrap">
                        ค้นหา
                    </button>
                    <a href="index.php" id="clearFiltersBtn" class="<?php echo $isFiltered ? '' : 'hidden'; ?> px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors">ล้างค่า</a>
                </div>
            </div>
            
            <!-- Row 2: Collapsible Advanced Filters -->
            <div id="advancedFiltersPanel" class="<?php echo $isAdvancedActive ? '' : 'hidden'; ?> border-t border-gray-100 pt-4 mt-2">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Access Level -->
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">ระดับการเข้าถึง</label>
                        <select name="group_id" id="group_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm bg-white cursor-pointer">
                            <option value="">-- ทุกระดับการเข้าถึง --</option>
                            <option value="A" <?php echo $filterGroup === 'A' ? 'selected' : ''; ?>>ผู้ดูแลระบบสูงสุด</option>
                            <option value="S" <?php echo $filterGroup === 'S' ? 'selected' : ''; ?>>เจ้าหน้าที่ทั่วไป</option>
                        </select>
                    </div>
                    
                    <!-- Set Group -->
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">กลุ่มผู้ใช้งานระบบ</label>
                        <select name="set_group_id" id="set_group_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm bg-white cursor-pointer">
                            <option value="">-- ทุกกลุ่มผู้ใช้งาน --</option>
                            <?php foreach($setGroups as $sg): ?>
                                <option value="<?php echo htmlspecialchars($sg['SetGroupID']); ?>" <?php echo $filterSetGroup == $sg['SetGroupID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sg['SetGroupName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Department -->
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">หน่วยงาน</label>
                        <select name="dept_id" id="dept_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm bg-white cursor-pointer">
                            <option value="">-- ทุกหน่วยงาน --</option>
                            <?php foreach($depts as $d): ?>
                                <option value="<?php echo htmlspecialchars($d['DeptID']); ?>" <?php echo $filterDept == $d['DeptID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['NameTH']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Bulk Actions -->
    <div class="flex items-center gap-3 mb-6 hidden bg-gray-50 p-4 rounded-xl border border-gray-200" id="bulkActionContainer">
        <span class="text-sm font-medium text-gray-700">จัดการรายการที่เลือก:</span>
        <select id="bulkActionSelect" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-blue-500 bg-white h-[42px] min-w-[200px]">
            <option value="">-- เลือกการจัดการ --</option>
            <option value="active">เปิดใช้งาน</option>
            <option value="inactive">ระงับการใช้งาน</option>
            <option value="delete">ลบทิ้ง</option>
        </select>
        <button type="button" onclick="executeBulkAction()" class="btn-primary px-6 h-[42px] text-sm font-medium flex items-center justify-center">นำไปใช้</button>
        <span class="text-sm text-gray-500 ml-2" id="bulkCountText"></span>
    </div>

    <?php if (defined('IS_AJAX')) { ob_clean(); } ?>
    <div id="usersTableContainer" class="space-y-6">
        <div class="overflow-x-auto">
        <table class="premium-table">
            <thead>
                <tr>
                    <th width="5%" class="text-center">
                        <input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="toggleSelectAll(this)">
                    </th>
                    <th>ชื่อผู้ใช้งาน</th>
                    <th>ชื่อ - นามสกุล</th>
										<th>ระดับการเข้าถึง</th>
                    <th>ข้อมูลการติดต่อ</th>
                    <th width="100">สถานะ</th>
                    <th width="150" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($result['data']) > 0): ?>
                    <?php foreach ($result['data'] as $index => $row): ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="ids[]" value="<?php echo $row['StaffID']; ?>" class="row-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="updateBulkDeleteBtn()">
                            </td>
                            <td>
                                <div class="font-semibold text-gray-800 font-medium">
                                  <?php echo htmlspecialchars($row['Username']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-gray-800 font-medium"><?php echo htmlspecialchars($row['Name']); ?></div>
                                <?php if ($row['GroupID'] == 'S'): ?>
                                    <?php
                                    try {
                                        $stmtAcc = $DB->prepare("
                                            SELECT d.NameTH 
                                            FROM staff_microsite_access a 
                                            JOIN microsite_departments d ON a.DeptID = d.DeptID 
                                            WHERE a.StaffID = ?
                                        ");
                                        $stmtAcc->execute([$row['StaffID']]);
                                        $depts = $stmtAcc->fetchAll(PDO::FETCH_COLUMN);
                                        if (count($depts) > 0) {
                                            echo '<div class="text-xs text-gray-500 mt-1.5 flex flex-wrap gap-1 items-center">';
																						echo '<span class="text-gray-400">หน่วยงาน:</span>';
                                            foreach($depts as $deptName) {
                                              echo '<span class="inline-flex px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded text-[10px] font-medium border border-blue-100">'.htmlspecialchars($deptName).'</span>';
                                            }
                                          echo '</div>';
                                        } else {
                                            //echo '<div class="text-xs text-red-500 mt-1.5 flex items-center gap-1"><i class="ph ph-warning"></i> ยังไม่กำหนดสิทธิ์เข้าถึงหน่วยงาน</div>';
                                        }
                                    } catch(PDOException $e) {}
                                    ?>
                                <?php endif; ?>
                            </td>

														<td>
															<div class="text-xs text-gray-500 mt-1.5 flex flex-wrap gap-1 items-center">
																<?php echo $row['GroupID'] == 'A' ? 'ผู้ดูแลระบบสูงสุด' : 'เจ้าหน้าที่ทั่วไป'; ?>
															</div>
														</td>

                            <td class="text-sm">
                                <?php if($row['Tel']): ?>
                                <div class="flex items-center gap-2 text-gray-600 mb-1">
                                    <i class="ph ph-phone"></i> <?php echo htmlspecialchars($row['Tel']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if($row['Email']): ?>
                                <div class="flex items-center gap-2 text-gray-600">
                                    <i class="ph ph-envelope"></i> <?php echo htmlspecialchars($row['Email']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['Status'] == 'Y'): ?>
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium border border-green-200">ใช้งาน</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-medium border border-red-200">ระงับ</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="flex justify-center gap-2">
                                    <a href="form.php?id=<?php echo $row['StaffID']; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors" title="แก้ไข">
                                        <i class="ph ph-pencil-simple"></i>
                                    </a>
                                    <button type="button" onclick="openHistoryModal(<?php echo $row['StaffID']; ?>, 'users')" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-200 transition-colors" title="ประวัติการใช้งาน">
                                        <i class="ph ph-clock-counter-clockwise"></i>
                                    </button>
                                    <a href="action.php?action=delete&id=<?php echo $row['StaffID']; ?>" onclick="confirmLink(event, this.href, 'ยืนยันการลบรายการนี้หรือไม่?');" class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors" title="ลบ">
                                        <i class="ph ph-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-8 text-gray-500">
                            <i class="ph ph-users text-4xl mb-2 text-gray-300"></i>
                            <p>ไม่พบข้อมูลผู้ใช้งาน</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

        <!-- Pagination -->
        <?php if (isset($result['pagination'])): ?>
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-gray-100 gap-4">
            <div class="text-sm text-gray-500">
                แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo $result['pagination']['total_pages']; ?> (ทั้งหมด <?php echo $result['pagination']['total']; ?> รายการ)
            </div>
            <?php if ($result['pagination']['total_pages'] > 1): ?>
            <div class="flex gap-2 flex-wrap items-center">
                <!-- First Page -->
                <?php if ($page > 1): ?>
                    <a href="index.php?page=1<?php echo $queryStringPrefix; ?>" 
                       class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าแรก">
                        <i class="ph ph-caret-double-left text-sm"></i>
                    </a>
                    <a href="index.php?page=<?php echo $page - 1; ?><?php echo $queryStringPrefix; ?>" 
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
                    <a href="index.php?page=<?php echo $i; ?><?php echo $queryStringPrefix; ?>" 
                       class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == $page ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:text-primary transition-colors'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <!-- Next Page -->
                <?php if ($page < $totalPages): ?>
                    <a href="index.php?page=<?php echo $page + 1; ?><?php echo $queryStringPrefix; ?>" 
                       class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าถัดไป">
                        <i class="ph ph-caret-right text-sm"></i>
                    </a>
                    <a href="index.php?page=<?php echo $totalPages; ?><?php echo $queryStringPrefix; ?>" 
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
    <?php
    if (defined('IS_AJAX')) {
        $html = ob_get_clean();
        echo $html;
        exit;
    }
    ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableContainer = document.getElementById('usersTableContainer');
    if (!tableContainer) return;
    
    function updateClearButtonVisibility() {
        const search = document.querySelector('input[name="search"]')?.value.trim() || '';
        const status = document.querySelector('select[name="status"]')?.value || '';
        const groupId = document.querySelector('select[name="group_id"]')?.value || '';
        const setGroupId = document.querySelector('select[name="set_group_id"]')?.value || '';
        const deptId = document.querySelector('select[name="dept_id"]')?.value || '';
        
        const isFiltered = (search !== '' || status !== '' || groupId !== '' || setGroupId !== '' || deptId !== '');
        const clearBtn = document.getElementById('clearFiltersBtn');
        if (clearBtn) {
            if (isFiltered) {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
            }
        }
    }

    function loadPage(url) {
        tableContainer.style.opacity = '0.5';
        tableContainer.style.pointerEvents = 'none';

        const ajaxUrl = new URL(url, window.location.href);
        ajaxUrl.searchParams.set('ajax', '1');

        fetch(ajaxUrl.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(html => {
            tableContainer.innerHTML = html;
            tableContainer.style.opacity = '1';
            tableContainer.style.pointerEvents = 'auto';
            
            const panel = document.querySelector('.glass-panel');
            if (panel) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            
            history.pushState(null, '', url);
            updateClearButtonVisibility();
        })
        .catch(err => {
            console.error('AJAX load error:', err);
            window.location.href = url;
        });
    }

    tableContainer.addEventListener('click', function(e) {
			const link = e.target.closest('a');
			if (link && link.href) {
				if (link.href.includes('users/index.php')) {
					e.preventDefault();
					loadPage(link.href);
				}
			}
    });

    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
					e.preventDefault();
					const formData = new FormData(filterForm);
					const searchParams = new URLSearchParams(formData);
					const url = 'index.php?' + searchParams.toString();
					loadPage(url);
        });
    }

    // Toggle Advanced Filters Panel
    const toggleBtn = document.getElementById('toggleAdvanced');
    const advancedPanel = document.getElementById('advancedFiltersPanel');
    if (toggleBtn && advancedPanel) {
        toggleBtn.addEventListener('click', function() {
            advancedPanel.classList.toggle('hidden');
        });
    }
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
</script>

<?php include_once('../includes/history_modal.php'); ?>

<?php include_once('../includes/footer.php'); ?>
