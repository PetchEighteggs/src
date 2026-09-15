<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = 'โครงสร้างและตำแหน่งข้าราชการ';
include_once('../includes/header.php');

$searchKeyword = $_GET['keyword'] ?? '';
$searchDeptID = $_GET['dept_id'] ?? '';
$searchStatus = $_GET['status'] ?? '';

// Fetch Data
$itemsList = [];
$departments = [];

try {
    if (isset($DB)) {
        // Fetch departments for filter
        $stmtDept = $DB->query("SELECT d.DeptID, d.ShortNameTH, d.NameTH 
                                FROM microsite_departments d
                                WHERE d.IsActive = 'Y' 
                                AND EXISTS (SELECT 1 FROM org_positions p WHERE p.DeptID = d.DeptID AND p.DelData = 'N')
                                ORDER BY d.OrderNo ASC, d.NameTH ASC");
        $departments = $stmtDept->fetchAll(PDO::FETCH_ASSOC);

        $sqlBase = "SELECT p.PositionID, p.DeptID, p.Name_TH, p.Name_EN, p.OrderNo, p.IsActive, 
        d.ShortNameTH as DeptShortName, d.NameTH as DeptName 
				FROM org_positions p
				LEFT JOIN microsite_departments d ON p.DeptID = d.DeptID
				WHERE p.DelData = 'N'";
        $params = [];
        
        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (p.Name_TH LIKE :keyword1 OR p.Name_EN LIKE :keyword2)";
            $params[':keyword1'] = "%$searchKeyword%";
            $params[':keyword2'] = "%$searchKeyword%";
        }

        if (!empty($searchDeptID)) {
            $sqlBase .= " AND p.DeptID = :dept_id";
            $params[':dept_id'] = $searchDeptID;
        }

        // Calculate summary
        $summarySql = "SELECT COUNT(*) as total, 
                       SUM(CASE WHEN IsActive = 'Y' THEN 1 ELSE 0 END) as active_count,
                       SUM(CASE WHEN IsActive != 'Y' THEN 1 ELSE 0 END) as inactive_count 
                       FROM ($sqlBase) as t";
        $stmtSum = $DB->prepare($summarySql);
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        // Apply status filter
        $sql = $sqlBase;
        if ($searchStatus === 'active') {
            $sql .= " AND p.IsActive = 'Y'";
        } else if ($searchStatus === 'inactive') {
            $sql .= " AND p.IsActive = 'N'";
        }

        $page = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sql, $params, $page, 25, 'd.OrderNo ASC, p.OrderNo ASC, p.PositionID ASC');
        $itemsList = $result['data'];
    }
} catch (PDOException $e) {}
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div id="cardTotal" onclick="filterStatus('')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">ตำแหน่งทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-users-three text-3xl"></i>
        </div>
        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-blue-50/50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
    </div>
    
    <div id="cardActive" onclick="filterStatus('active')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'active' ? 'border-green-500 ring-2 ring-green-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-green-600 font-medium mb-1">เปิดใช้งาน (Active)</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['active_count'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-500 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-check-circle text-3xl"></i>
        </div>
        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-green-50/50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
    </div>
    
    <div id="cardInactive" onclick="filterStatus('inactive')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'inactive' ? 'border-amber-500 ring-2 ring-amber-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-amber-600 font-medium mb-1">ปิดการใช้งาน (Inactive)</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['inactive_count'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-amber-50 flex items-center justify-center text-amber-550 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-minus-circle text-3xl"></i>
        </div>
        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-amber-50/50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
    </div>
</div>

<div class="glass-panel p-6">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
          <i class="ph ph-user-list text-primary"></i> โครงสร้างและตำแหน่งข้าราชการ
        </h2>
        
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($userPermissions['edit']): ?>
            <a href="sort.php" class="px-4 py-2 bg-gray-50 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-100 font-medium transition-colors flex items-center gap-2">
                <i class="ph ph-arrows-down-up text-lg"></i> จัดเรียงลำดับ
            </a>
            <?php endif; ?>
            <?php if ($userPermissions['add']): ?>
            <a href="form.php<?php echo !empty($searchDeptID) ? '?dept_id='.$searchDeptID : ''; ?>" class="btn-primary flex items-center gap-2">
              <i class="ph ph-plus-circle"></i> เพิ่มข้อมูล
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search & Filter Form -->
    <form id="filterForm" method="GET" action="index.php" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 flex flex-col sm:flex-row gap-4">
        <input type="hidden" name="status" id="statusFilterInput" value="<?php echo htmlspecialchars($searchStatus); ?>">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหาตำแหน่ง</label>
            <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ชื่อตำแหน่ง..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div class="sm:w-64">
            <label class="block text-sm font-medium text-gray-700 mb-1">หน่วยงาน</label>
            <select name="dept_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">-- ทั้งหมด --</option>
                <?php foreach($departments as $d): ?>
                    <?php $deptNameDisplay = !empty($d['ShortNameTH']) ? $d['ShortNameTH'] : $d['NameTH']; ?>
                    <option value="<?php echo $d['DeptID']; ?>" <?php echo $searchDeptID == $d['DeptID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($deptNameDisplay); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6">ค้นหา</button>
            <?php if($searchKeyword != '' || $searchDeptID != ''): ?>
                <a href="index.php" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-[42px]">ล้างค่า</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Bulk Actions -->
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
        <?php if (!empty($searchStatus)): ?>
        <div class="flex items-center justify-between bg-blue-50 border border-blue-100 p-4 rounded-xl text-sm text-blue-800 shadow-sm transition-all duration-300">
            <div class="flex items-center gap-2">
                <i class="ph ph-funnel text-lg"></i>
                <span>กำลังแสดงผลข้อมูลเฉพาะสถานะ: <strong><?php echo $searchStatus === 'active' ? 'เปิดใช้งาน (Active)' : 'ปิดการใช้งาน (Inactive)'; ?></strong></span>
            </div>
            <button type="button" onclick="filterStatus('')" class="text-blue-600 hover:text-blue-800 font-semibold flex items-center gap-1 hover:underline">
                <i class="ph ph-x-circle text-lg"></i> ล้างการกรอง
            </button>
        </div>
        <?php endif; ?>
        
        <div class="overflow-x-auto">
            <table class="premium-table min-w-full">
            <thead>
                <tr>
                    <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                    <th width="5%">
                        <div class="flex items-center justify-center">
                            <input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="toggleSelectAll(this)">
                        </div>
                    </th>
                    <?php endif; ?>
                    <th width="35%" class="text-left">ชื่อตำแหน่ง</th>
                    <th width="25%" class="text-left">สังกัดหน่วยงาน</th>
                    <th width="10%" class="text-left">สถานะ</th>
                    <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                    <th width="15%">
                        <div class="text-center w-full">จัดการ</div>
                    </th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($itemsList)): ?>
                <tr>
                    <td colspan="6" class="text-center py-8 text-gray-500">
                        <i class="ph ph-folder-open text-4xl mb-2 text-gray-300"></i>
                        <p>ยังไม่มีข้อมูลตำแหน่ง</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($itemsList as $row): ?>
                    <tr>
                        <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                        <td>
                          <div class="flex items-center justify-center">
                            <input type="checkbox" name="item_ids[]" value="<?php echo $row['PositionID']; ?>" class="row-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="updateBulkDeleteBtn()">
                          </div>
                        </td>
                        <?php endif; ?>
                        <td class="font-semibold text-gray-800">
													<?php echo htmlspecialchars($row['Name_TH']); ?>
													<?php if(!empty($row['Name_EN'])): ?>
														<div class="text-xs text-gray-400 font-normal mt-0.5"><?php echo htmlspecialchars($row['Name_EN']); ?></div>
													<?php endif; ?>
                        </td>
                        <td class="text-gray-600 text-sm">
													<?php 
														$deptDisplay = !empty($row['DeptShortName']) ? $row['DeptShortName'] : $row['DeptName'];
														echo htmlspecialchars($deptDisplay ?? '- ไม่ระบุ -'); 
													?>
                        </td>
                        <td>
													<?php if(($row['IsActive'] ?? 'Y') == 'Y'): ?>
														<span class="inline-flex px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium border border-green-200 whitespace-nowrap">เปิดใช้งาน</span>
													<?php else: ?>
														<span class="inline-flex px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium border border-gray-200 whitespace-nowrap">ปิดการใช้งาน</span>
													<?php endif; ?>
                        </td>
                        <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                        <td>
                            <div class="flex items-center justify-center gap-2">
                                <?php if ($userPermissions['edit']): ?>
                                <a href="form.php?id=<?php echo $row['PositionID']; ?>" class="w-8 h-8 rounded bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="แก้ไข">
                                    <i class="ph ph-pencil-simple"></i>
                                </a>
                                <?php endif; ?>
                                <button type="button" onclick="openHistoryModal(<?php echo $row['PositionID']; ?>, 'org_positions')" class="w-8 h-8 rounded bg-gray-50 text-gray-600 flex items-center justify-center hover:bg-gray-200 transition-colors" title="ประวัติการใช้งาน">
                                    <i class="ph ph-clock-counter-clockwise"></i>
                                </button>
                                <?php if ($userPermissions['delete']): ?>
                                <button onclick="deleteItem(<?php echo $row['PositionID']; ?>)" class="w-8 h-8 rounded bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition-colors" title="ลบ">
                                    <i class="ph ph-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if (isset($result['pagination']) && $result['pagination']['total_pages'] > 0): ?>
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-gray-100 gap-4">
            <div class="text-sm text-gray-500">
                แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo $result['pagination']['total_pages']; ?> (ทั้งหมด <?php echo $result['pagination']['total']; ?> รายการ)
            </div>
            <?php if ($result['pagination']['total_pages'] > 1): 
                $qParams = [];
                if ($searchKeyword) $qParams['keyword'] = $searchKeyword;
                if ($searchDeptID) $qParams['dept_id'] = $searchDeptID;
                if ($searchStatus) $qParams['status'] = $searchStatus;
                $qString = http_build_query($qParams);
                $qPrefix = $qString ? '&' . $qString : '';
            ?>
            <div class="flex gap-2 flex-wrap items-center">
                <!-- First Page -->
                <?php if ($page > 1): ?>
                    <a href="index.php?page=1<?php echo $qPrefix; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าแรก"><i class="ph ph-caret-double-left text-sm"></i></a>
                    <a href="index.php?page=<?php echo $page - 1; ?><?php echo $qPrefix; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าก่อนหน้า"><i class="ph ph-caret-left text-sm"></i></a>
                <?php else: ?>
                    <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed" title="หน้าแรก"><i class="ph ph-caret-double-left text-sm"></i></span>
                    <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed" title="หน้าก่อนหน้า"><i class="ph ph-caret-left text-sm"></i></span>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php 
                $totalPages = $result['pagination']['total_pages'];
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="index.php?page=<?php echo $i; ?><?php echo $qPrefix; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == $page ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:text-primary transition-colors'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <!-- Next Page -->
                <?php if ($page < $totalPages): ?>
                    <a href="index.php?page=<?php echo $page + 1; ?><?php echo $qPrefix; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าถัดไป"><i class="ph ph-caret-right text-sm"></i></a>
                    <a href="index.php?page=<?php echo $totalPages; ?><?php echo $qPrefix; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าสุดท้าย"><i class="ph ph-caret-double-right text-sm"></i></a>
                <?php else: ?>
                    <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed" title="หน้าถัดไป"><i class="ph ph-caret-right text-sm"></i></span>
                    <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed" title="หน้าสุดท้าย"><i class="ph ph-caret-double-right text-sm"></i></span>
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
    const tableContainer = document.getElementById('dataTableContainer');
    if (!tableContainer) return;
    
    function loadPage(url) {
        tableContainer.style.opacity = '0.5';
        tableContainer.style.pointerEvents = 'none';

        const ajaxUrl = new URL(url, window.location.origin);
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
            
            const urlObj = new URL(url, window.location.origin);
            const status = urlObj.searchParams.get('status') || '';
            const statusInput = document.getElementById('statusFilterInput');
            if (statusInput) statusInput.value = status;
            
            document.querySelectorAll('[id^="card"]').forEach(card => {
                card.classList.remove('border-primary', 'ring-2', 'ring-primary/20', 'scale-[1.02]', 'border-green-500', 'ring-green-100', 'border-amber-500', 'ring-amber-100');
                card.classList.add('border-gray-100', 'opacity-80', 'hover:opacity-100', 'hover:scale-[1.01]');
            });
            
            let activeCard;
            if (status === 'active') {
                activeCard = document.getElementById('cardActive');
                if (activeCard) {
                    activeCard.classList.add('border-green-500', 'ring-2', 'ring-green-100', 'scale-[1.02]');
                    activeCard.classList.remove('border-gray-100', 'opacity-80', 'hover:opacity-100', 'hover:scale-[1.01]');
                }
            } else if (status === 'inactive') {
                activeCard = document.getElementById('cardInactive');
                if (activeCard) {
                    activeCard.classList.add('border-amber-500', 'ring-2', 'ring-amber-100', 'scale-[1.02]');
                    activeCard.classList.remove('border-gray-100', 'opacity-80', 'hover:opacity-100', 'hover:scale-[1.01]');
                }
            } else {
                activeCard = document.getElementById('cardTotal');
                if (activeCard) {
                    activeCard.classList.add('border-primary', 'ring-2', 'ring-primary/20', 'scale-[1.02]');
                    activeCard.classList.remove('border-gray-100', 'opacity-80', 'hover:opacity-100', 'hover:scale-[1.01]');
                }
            }
            
            history.pushState(null, '', url);
        })
        .catch(err => {
            console.error('AJAX load error:', err);
            window.location.href = url;
        });
    }

    tableContainer.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (link && link.href) {
            if (link.href.includes('org_positions/index.php')) {
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
    
    window.filterStatus = function(statusVal) {
			const statusInput = document.getElementById('statusFilterInput');
			if (statusInput) {
					statusInput.value = statusVal;
			}
			if (filterForm) {
				filterForm.dispatchEvent(new Event('submit'));
			}
    };
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
        confirmMsg = 'คุณต้องการปิดการใช้งานข้อมูลที่เลือกจำนวน ' + checkedBoxes.length + ' รายการใช่หรือไม่?';
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

function deleteItem(id) {
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

<?php include_once('../includes/history_modal.php'); ?>
<?php include_once('../includes/footer.php'); ?>
