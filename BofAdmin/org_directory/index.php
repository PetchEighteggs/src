<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = 'จัดการนามสงเคราะห์';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$searchKeyword = $_GET['keyword'] ?? '';
$deptFilter    = $_GET['dept'] ?? '';

// ดึงหน่วยงานสำหรับ filter
$deptOptions = [];
try {
    if (isset($DB)) {
        $stmt = $DB->query("SELECT DeptID, NameTH FROM microsite_departments WHERE IsActive='Y' ORDER BY NameTH ASC");
        $deptOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

// Fetch summary
$summary = ['total' => 0, 'active_count' => 0, 'inactive_count' => 0];
$dirList = [];

try {
    if (isset($DB)) {
        $sqlBase = "SELECT p.DirID, p.Image, p.NameTH, p.PositionTH, p.IsActive, p.OrderNo, p.DeptID, d.NameTH as DeptName
				FROM org_directory p
				LEFT JOIN microsite_departments d ON p.DeptID = d.DeptID
				WHERE p.DelData = 'N'";
        $params  = [];
        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (p.NameTH LIKE :kw1 OR p.PositionTH LIKE :kw2 OR p.Phone LIKE :kw3)";
            $params[':kw1'] = $params[':kw2'] = $params[':kw3'] = "%$searchKeyword%";
        }
        if (!empty($deptFilter)) {
            $sqlBase .= " AND p.DeptID = :deptid";
            $params[':deptid'] = $deptFilter;
        }

        $stmtSum = $DB->prepare("SELECT COUNT(*) as total,
            SUM(CASE WHEN IsActive='Y' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN IsActive!='Y' THEN 1 ELSE 0 END) as inactive_count
            FROM ($sqlBase) t");
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        $searchStatus = $_GET['status'] ?? '';
        $sql = $sqlBase;
        if ($searchStatus === 'active')   $sql .= " AND p.IsActive = 'Y'";
        elseif ($searchStatus === 'inactive') $sql .= " AND p.IsActive = 'N'";

        $page   = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sql, $params, $page, 25, 'IsActive ASC, p.OrderNo ASC, d.NameTH ASC');
        $dirList = $result['data'];
    }
} catch (PDOException $e) {}
?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="filterStatus('')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo ($searchStatus??'')==='' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-100 opacity-80'; ?> flex justify-between items-center group transition-all duration-300">
        <div><div class="text-sm text-gray-500 mb-1">รายการทั้งหมด</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total']??0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform"><i class="ph ph-address-book text-3xl"></i></div>
    </div>
    <div onclick="filterStatus('active')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo ($searchStatus??'')==='active' ? 'border-green-500 ring-2 ring-green-100' : 'border-gray-100 opacity-80'; ?> flex justify-between items-center group transition-all duration-300">
        <div><div class="text-sm text-green-600 mb-1">เปิดใช้งาน</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['active_count']??0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-500 group-hover:scale-110 transition-transform"><i class="ph ph-check-circle text-3xl"></i></div>
    </div>
    <div onclick="filterStatus('inactive')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo ($searchStatus??'')==='inactive' ? 'border-amber-500 ring-2 ring-amber-100' : 'border-gray-100 opacity-80'; ?> flex justify-between items-center group transition-all duration-300">
        <div><div class="text-sm text-amber-600 mb-1">ปิดการใช้งาน</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['inactive_count']??0); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform"><i class="ph ph-minus-circle text-3xl"></i></div>
    </div>
</div>

<div class="glass-panel p-6">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
            <i class="ph ph-address-book text-primary"></i> นามสงเคราะห์
        </h2>
        <div class="flex flex-wrap items-center gap-3">
            <?php if (!empty($userPermissions['edit'])): ?>
                <a href="sort.php" class="px-4 py-2 bg-gray-50 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-100 font-medium transition-colors flex items-center gap-2">
                    <i class="ph ph-arrows-down-up text-lg"></i>
                    จัดเรียงลำดับ
                </a>
            <?php endif; ?>
            <?php if (!empty($userPermissions['add'])): ?>
                <a href="form.php" class="btn-primary"><i class="ph ph-plus-circle"></i> เพิ่มรายชื่อ</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search -->
    <form id="filterForm" method="GET" action="index.php" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 flex flex-col sm:flex-row gap-4">
        <input type="hidden" name="status" id="statusFilterInput" value="<?php echo htmlspecialchars($searchStatus??''); ?>">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหา</label>
            <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ชื่อ, ตำแหน่ง, เบอร์โทร..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div class="sm:w-64">
            <label class="block text-sm font-medium text-gray-700 mb-1">หน่วยงาน</label>
            <select name="dept" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">-- ทุกหน่วยงาน --</option>
                <?php foreach ($deptOptions as $dept): ?>
                <option value="<?php echo $dept['DeptID']; ?>" <?php echo $deptFilter == $dept['DeptID'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($dept['NameTH']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary h-[42px] px-6">ค้นหา</button>
            <a href="index.php" id="clearFiltersBtn" class="<?php echo ($searchKeyword || $deptFilter || $searchStatus) ? '' : 'hidden'; ?> px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors h-[42px] flex items-center">ล้างค่า</a>
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

    <form id="orgDirectoryActionForm" action="action.php" method="POST" class="hidden">
        <?php echo bofSecurityCsrfField(); ?>
        <input type="hidden" name="action" id="orgDirectoryActionName" value="">
        <input type="hidden" name="id" id="orgDirectoryActionId" value="">
        <input type="hidden" name="ids" id="orgDirectoryActionIds" value="">
    </form>

    <?php if (defined('IS_AJAX')) { ob_clean(); } ?>
    <div id="orgDirectoryTableContainer" class="space-y-6">
    <?php if (!empty($searchStatus)): ?>
    <div class="flex items-center justify-between bg-blue-50 border border-blue-100 p-4 rounded-xl text-sm text-blue-800 shadow-sm transition-all duration-300 mb-4">
        <div class="flex items-center gap-2">
          <i class="ph ph-funnel text-lg"></i>
          <span>กำลังแสดงผลเฉพาะสถานะ: <strong><?php echo $searchStatus === 'active' ? 'เปิดใช้งาน (Active)' : 'ปิดการใช้งาน (Inactive)'; ?></strong></span>
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
								<th width="5%" class="text-center">
									<input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="toggleSelectAll(this)">
								</th>
								<?php endif; ?>
								<th width="7%">รูปภาพ</th>
								<th width="22%">ชื่อ-นามสกุล</th>
								<th width="22%">ตำแหน่ง</th>
								<th width="18%">หน่วยงาน</th>
								<th width="7%">สถานะ</th>
								<?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
								<th width="7%" class="text-center">จัดการ</th>
								<?php endif; ?>
							</tr>
            </thead>
            <tbody>
                <?php if (empty($dirList)): ?>
                <tr>
									<td colspan="9" class="text-center py-8 text-gray-500">
										<i class="ph ph-address-book text-4xl mb-2 text-gray-300"></i>
										<p>ยังไม่มีข้อมูลนามสงเคราะห์</p>
									</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($dirList as $row): ?>
                    <tr>
                        <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                        <td>
                            <input type="checkbox" name="dir_ids[]" value="<?php echo $row['DirID']; ?>" class="row-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="updateBulkDeleteBtn()">
                        </td>
                        <?php endif; ?>
                        <td>
													<div class="w-12 h-12 rounded-full bg-gray-100 overflow-hidden border border-gray-200">
														<?php if (!empty($row['Image'])): ?>
                                                        <?php 
                                                        $safeImagePath = str_replace('uploads/images/', '', $row['Image']);
                                                        $safeImagePath = ltrim($safeImagePath, '/');
                                                        ?>
														<img src="<?php echo $ImgPath . htmlspecialchars($safeImagePath); ?>" class="w-full h-full object-cover cursor-pointer hover:opacity-80" onclick="previewImage('<?php echo $ImgPath . htmlspecialchars($safeImagePath); ?>')">
														<?php else: ?>
														<div class="w-full h-full flex items-center justify-center text-gray-400"><i class="ph ph-user text-xl"></i></div>
														<?php endif; ?>
													</div>
                        </td>
                        <td class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($row['NameTH']); ?></td>
                        <td class="text-sm text-gray-600"><?php echo htmlspecialchars($row['PositionTH'] ?? '—'); ?></td>
                        <td class="text-sm text-gray-600"><?php echo htmlspecialchars($row['DeptName'] ?? '—'); ?></td>
                        <td>
                            <?php if (($row['IsActive'] ?? 'Y') == 'Y'): ?>
                            <span class="inline-flex px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">เปิดใช้งาน</span>
                            <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs font-medium">ปิดใช้งาน</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                        <td>
													<div class="flex items-center justify-center gap-2">
														<?php if ($userPermissions['edit']): ?>
														<a href="form.php?id=<?php echo $row['DirID']; ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="แก้ไข">
																<i class="ph ph-pencil-simple"></i>
														</a>
														<?php endif; ?>
														<button type="button" onclick="openHistoryModal(<?php echo $row['DirID']; ?>, 'org_directory')" class="w-8 h-8 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center hover:bg-gray-200 transition-colors" title="ประวัติการใช้งาน">
                              <i class="ph ph-clock-counter-clockwise"></i>
                            </button>
														<?php if ($userPermissions['delete']): ?>
														<button type="button" onclick="deleteDir(<?php echo $row['DirID']; ?>)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition-colors" title="ลบ">
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
    <?php if (!empty($dirList) && isset($result['pagination'])): ?>
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-gray-100 gap-4">
        <div class="text-sm text-gray-500">
            แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo $result['pagination']['total_pages']; ?> (ทั้งหมด <?php echo $result['pagination']['total']; ?> รายการ)
        </div>
        
        <div class="flex gap-2 flex-wrap items-center">
            <!-- First Page -->
            <?php if ($page > 1): ?>
                <a href="index.php?page=1&keyword=<?php echo urlencode($searchKeyword); ?>&dept=<?php echo urlencode($deptFilter); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าแรก">
                    <i class="ph ph-caret-double-left text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $page - 1; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&dept=<?php echo urlencode($deptFilter); ?>&status=<?php echo urlencode($searchStatus); ?>" 
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
                <a href="index.php?page=<?php echo $i; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&dept=<?php echo urlencode($deptFilter); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == $page ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:text-primary transition-colors'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <!-- Next Page -->
            <?php if ($page < $totalPages): ?>
                <a href="index.php?page=<?php echo $page + 1; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&dept=<?php echo urlencode($deptFilter); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าถัดไป">
                    <i class="ph ph-caret-right text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $totalPages; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&dept=<?php echo urlencode($deptFilter); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าสุดท้าย">
                    <i class="ph ph-caret-double-right text-sm"></i>
                </a>
            <?php else: ?>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed" title="หน้าถัดไป">
                    <i class="ph ph-caret-right text-sm"></i>
                </span>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed" title="หน้าสุดท้าย">
                    <i class="ph ph-caret-double-right text-sm"></i>
<?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php
    if (defined('IS_AJAX')) {
        $html = ob_get_clean();
        echo $html;
        exit;
    }
    ?>
</div>
</div>

<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="fixed inset-0 bg-black/85 flex items-center justify-center z-[9999] hidden backdrop-blur-md" onclick="closeImagePreview()">
  <button onclick="closeImagePreview()" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20"><i class="ph ph-x text-2xl"></i></button>
  <div class="max-w-[90vw] max-h-[85vh] p-2" onclick="event.stopPropagation()">
    <img id="previewModalImg" src="" alt="" class="max-w-full max-h-[80vh] rounded-xl shadow-2xl object-contain">
  </div>
</div>

<script>
function previewImage(src) {
  document.getElementById('previewModalImg').src = src;
  document.getElementById('imagePreviewModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}
function closeImagePreview() {
  document.getElementById('imagePreviewModal').classList.add('hidden');
  document.body.style.overflow = '';
}
function deleteDir(id) {
	AdminDialog.fire({ title: 'ยืนยันการลบ?', text: 'ต้องการลบรายชื่อนี้ใช่หรือไม่?', icon: 'warning',
		showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#aaa',
		confirmButtonText: 'ยืนยันการลบ', cancelButtonText: 'ยกเลิก'
	}).then(r => {
		if (r.isConfirmed) {
            document.getElementById('orgDirectoryActionName').value = 'delete';
            document.getElementById('orgDirectoryActionId').value = id;
            document.getElementById('orgDirectoryActionIds').value = '';
            document.getElementById('orgDirectoryActionForm').submit();
        }
	});
}

document.addEventListener('DOMContentLoaded', () => {

    // AJAX Table Loading
    const tableContainer = document.getElementById('orgDirectoryTableContainer');
    if (tableContainer) {
        function loadPage(url) {
            tableContainer.style.opacity = '0.5';
            tableContainer.style.pointerEvents = 'none';
            const ajaxUrl = new URL(url, window.location.href);
            ajaxUrl.searchParams.set('ajax', '1');
            
            fetch(ajaxUrl.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.text())
            .then(html => {
                tableContainer.innerHTML = html;
                tableContainer.style.opacity = '1';
                tableContainer.style.pointerEvents = 'auto';
                
                const urlObj = new URL(url, window.location.href);
                const status = urlObj.searchParams.get('status') || '';
                const statusInput = document.getElementById('statusFilterInput');
                if (statusInput) statusInput.value = status;
            });
        }

        tableContainer.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (link && link.href && link.href.includes('org_directory/index.php')) {
                e.preventDefault();
                loadPage(link.href);
            }
        });

        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(filterForm);
                const searchParams = new URLSearchParams(formData);
                const url = 'index.php?' + searchParams.toString();
                
                const clearBtn = document.getElementById('clearFiltersBtn');
                if (clearBtn) {
                    let hasFilter = false;
                    for (let [key, value] of formData.entries()) {
                        if (value.trim() !== '') {
                            hasFilter = true;
                            break;
                        }
                    }
                    if (hasFilter) {
                        clearBtn.classList.remove('hidden');
                    } else {
                        clearBtn.classList.add('hidden');
                    }
                }
                
                loadPage(url);
            });
            /*
            const filterDeptSel = filterForm.querySelector('select[name="dept"]');
            if (filterDeptSel) {
                filterDeptSel.addEventListener('change', function() {
                    filterForm.dispatchEvent(new Event('submit'));
                });
            }
            */
        }
        
        window.filterStatus = function(statusVal) {
            const statusInput = document.getElementById('statusFilterInput');
            if (statusInput) statusInput.value = statusVal;
            if (filterForm) filterForm.dispatchEvent(new Event('submit'));
        };
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
            document.getElementById('orgDirectoryActionName').value = actionStr;
            document.getElementById('orgDirectoryActionId').value = '';
            document.getElementById('orgDirectoryActionIds').value = ids;
            document.getElementById('orgDirectoryActionForm').submit();
        }
    });
}
</script>
<?php include_once('../includes/history_modal.php'); ?>
<?php include_once('../includes/footer.php'); ?>
