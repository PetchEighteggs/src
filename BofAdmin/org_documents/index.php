<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$serviceTypes = [
    'policy' => 'นโยบายและยุทธศาสตร์',
    'digital_plan' => 'แผนปฏิบัติการดิจิทัล',
    'annual_plan' => 'แผนปฏิบัติราชการประจำปี',
    'budget' => 'งบประมาณรายจ่ายประจำปี',
    'report' => 'คำรับรอง/รายงานผล'
];
$serviceType = $_GET['type'] ?? 'policy';
$serviceName = $serviceTypes[$serviceType] ?? 'จัดการเอกสารองค์กร';

$pageTitle = $serviceName;
include_once('../includes/header.php');

$canAdd = $userPermissions['add'] ?? true;
$canEdit = $userPermissions['edit'] ?? true;
$canDelete = $userPermissions['delete'] ?? true;

$searchStatus = $_GET['status'] ?? '';
$searchKeyword = trim($_GET['keyword'] ?? '');
$searchGroup = $_GET['group'] ?? '';

// Group filter removed

$summary = ['total' => 0, 'active_count' => 0, 'inactive_count' => 0];
try {
    $stmtSum = $DB->prepare("SELECT COUNT(*) as total, 
		SUM(CASE WHEN IsActive = 'Y' THEN 1 ELSE 0 END) as active_count,
		SUM(CASE WHEN IsActive != 'Y' THEN 1 ELSE 0 END) as inactive_count 
		FROM org_docs WHERE ServiceType = ? AND DelData = 'N'");
    $stmtSum->execute([$serviceType]);
    $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$items = [];
$page = $_GET['page'] ?? 1;
try {
    $sqlBase = "SELECT d.DocID, d.TitleTH, d.File, d.IsActive, d.OrderNo, 
		g.GroupNameTH as Category_TH, 
		(SELECT COUNT(*) FROM org_doc_attachments a WHERE a.DocID = d.DocID) as AttachCount
		FROM org_docs d
		LEFT JOIN org_doc_groups g ON d.GroupID = g.GroupID
		WHERE d.ServiceType = :stype AND (d.DelData = 'N' OR d.DelData IS NULL)";
    $params = [':stype' => $serviceType];
    
    if ($searchStatus === 'active') {
        $sqlBase .= " AND d.IsActive = 'Y'";
    } elseif ($searchStatus === 'inactive') {
        $sqlBase .= " AND d.IsActive != 'Y'";
    }
    
    if ($searchKeyword !== '') {
        $sqlBase .= " AND (d.TitleTH LIKE :kw1 OR d.TitleEN LIKE :kw2)";
        $params[':kw1'] = "%$searchKeyword%";
        $params[':kw2'] = "%$searchKeyword%";
    }

    
    $result = paginateWithCount($DB, $sqlBase, $params, $page, 25, 'd.OrderNo DESC, d.DocID DESC');
    $items = $result['data'];
} catch (PDOException $e) { echo "PDO ERROR: " . $e->getMessage(); }
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="window.location.href='index.php?type=<?php echo urlencode($serviceType); ?>'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">รายการทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-list text-3xl"></i>
        </div>
    </div>
    
    <div onclick="window.location.href='index.php?type=<?php echo urlencode($serviceType); ?>&status=active'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'active' ? 'border-green-500 ring-2 ring-green-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-green-600 font-medium mb-1">เปิดใช้งาน (Active)</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['active_count'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-500 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-check-circle text-3xl"></i>
        </div>
    </div>
    
    <div onclick="window.location.href='index.php?type=<?php echo urlencode($serviceType); ?>&status=inactive'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'inactive' ? 'border-amber-500 ring-2 ring-amber-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
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
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-100 pb-4 mb-6 gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
							<i class="ph ph-files text-primary"></i>
              <?php echo $serviceName; ?>
            </h2>
        </div>
        <div class="flex gap-2 items-center">
            <?php if ($canEdit || $canAdd): ?>
            <?php endif; ?>
            <?php if ($canAdd): ?>
            <a href="form.php?type=<?php echo urlencode($serviceType); ?>" class="btn-primary px-4 py-2 flex items-center gap-2 text-sm whitespace-nowrap">
                <i class="ph ph-plus-circle text-lg"></i>เพิ่มข้อมูล
            </a>
            <?php endif; ?>
        </div>
    </div>
    <form id="filterForm" method="GET" action="index.php" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 flex flex-col sm:flex-row gap-4">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($serviceType); ?>">
        <input type="hidden" name="status" id="statusFilterInput" value="<?php echo htmlspecialchars($searchStatus); ?>">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหาหัวข้อ</label>
            <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ชื่อหัวข้อ..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6">
              ค้นหา
            </button>
            <a href="index.php?type=<?php echo urlencode($serviceType); ?>" id="clearFiltersBtn" class="<?php echo $searchKeyword !== '' ? '' : 'hidden'; ?> px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors h-[42px] flex items-center">
                ล้างค่า
            </a>
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

    <?php if (defined('IS_AJAX')) { ob_clean(); } ?>
    <div id="groundwaterTableContainer" class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="premium-table min-w-full">
                <thead>
                    <tr>
                        <th class="w-12 text-center">
                          <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                        </th>
                        <th>ชื่อหัวข้อ</th>
                        <th class="w-32 text-center">สถานะ</th>
                        <th class="w-32 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr>
											<td colspan="6" class="text-center py-8 text-gray-500">
												<i class="ph ph-folder-open text-4xl mb-3 block"></i> ยังไม่มีข้อมูล
											</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($items as $row): ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="bulk_ids[]" value="<?php echo $row['DocID']; ?>" class="item-checkbox rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                            </td>
                            <td>
                                <div class="font-medium text-gray-800 line-clamp-1 mb-1">
                                    <?php echo htmlspecialchars($row['TitleTH']); ?>
                                </div>
                            </td>
                           
                            <td>
                                <?php if ($row['IsActive'] == 'Y'): ?>
                                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-green-50 text-green-600 border border-green-200">
                                    เปิดใช้งาน</span>
                                <?php else: ?>
                                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200">
                                    ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if ($canEdit): ?>
                                    <a href="form.php?type=<?php echo urlencode($serviceType); ?>&id=<?php echo $row['DocID']; ?>" 
                                       class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors tooltip"
                                       title="แก้ไข"><i class="ph ph-pencil-simple text-lg"></i>
                                    </a>
                                    <?php endif; ?>
																		<button type="button" onclick="openHistoryModal(<?php echo $row['DocID']; ?>, 'org_docs')" class="w-8 h-8 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center hover:bg-gray-200 transition-colors tooltip" title="ประวัติการใช้งาน">
                                      <i class="ph ph-clock-counter-clockwise text-lg"></i>
                                    </button>
                                    <?php if ($canDelete): ?>
                                    <button type="button" onclick="deleteService(<?php echo $row['DocID']; ?>, '<?php echo htmlspecialchars($serviceType); ?>')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center transition-colors tooltip" title="ลบ">
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
    <?php if (!empty($items) && isset($result['pagination'])): ?>
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-gray-100 gap-4">
        <div class="text-sm text-gray-500">
            แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo $result['pagination']['total_pages']; ?> (ทั้งหมด <?php echo $result['pagination']['total']; ?> รายการ)
        </div>
        <?php if ($result['pagination']['total_pages'] > 1): ?>
        <div class="flex gap-2 flex-wrap items-center">
            <!-- First Page -->
            <?php if ($page > 1): ?>
                <a href="index.php?page=1&type=<?php echo urlencode($serviceType); ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าแรก">
                    <i class="ph ph-caret-double-left text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $page - 1; ?>&type=<?php echo urlencode($serviceType); ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
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
                <a href="index.php?page=<?php echo $i; ?>&type=<?php echo urlencode($serviceType); ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == $page ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:text-primary transition-colors'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            <!-- Next Page -->
            <?php if ($page < $totalPages): ?>
                <a href="index.php?page=<?php echo $page + 1; ?>&type=<?php echo urlencode($serviceType); ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าถัดไป">
                    <i class="ph ph-caret-right text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $totalPages; ?>&type=<?php echo urlencode($serviceType); ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
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
    <?php
    if (defined('IS_AJAX')) {
      $html = ob_get_clean();
      echo $html;
      exit;
    }
    ?>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function updateCount() {
        const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
        const selectedCount = document.getElementById('selectedCount');
        const bulkActionBar = document.getElementById('bulkActionBar');
        
        if (selectedCount) selectedCount.textContent = checkedBoxes.length;
        
        if (bulkActionBar) {
            if (checkedBoxes.length > 0) {
                bulkActionBar.classList.remove('hidden');
                bulkActionBar.style.display = 'flex';
            } else {
                bulkActionBar.classList.add('hidden');
                bulkActionBar.style.display = 'none';
            }
        }
    }

    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'selectAll') {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = e.target.checked;
            });
            updateCount();
        } else if (e.target && e.target.classList.contains('item-checkbox')) {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.item-checkbox');
            
            if (!e.target.checked && selectAll) {
                selectAll.checked = false;
            } else if (document.querySelectorAll('.item-checkbox:checked').length === checkboxes.length && selectAll) {
                selectAll.checked = true;
            }
            updateCount();
        }
    });

    // AJAX Table Loading
    const tableContainer = document.getElementById('groundwaterTableContainer');
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
            if (link && link.href && link.href.includes('org_documents/index.php')) {
                // Ignore delete links which use confirm()
                if(link.getAttribute('onclick') && link.getAttribute('onclick').includes('confirm(')) return;
                e.preventDefault();
                loadPage(link.href);
            }
        });

        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            const keywordInput = filterForm.querySelector('input[name="keyword"]');
            const clearFiltersBtn = document.getElementById('clearFiltersBtn');
            const updateClearButton = function() {
                if (keywordInput && clearFiltersBtn) {
                    clearFiltersBtn.classList.toggle('hidden', keywordInput.value.trim() === '');
                }
            };

            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                updateClearButton();
                const formData = new FormData(filterForm);
                const searchParams = new URLSearchParams(formData);
                const url = 'index.php?' + searchParams.toString();
                loadPage(url);
            });
            // Removed auto-submit on group change
            // const filterGroupSel = filterForm.querySelector('select[name="group"]');
            // if (filterGroupSel) {
            //     filterGroupSel.addEventListener('change', function() {
            //         filterForm.dispatchEvent(new Event('submit'));
            //     });
            // }
        }
        
        window.filterStatus = function(statusVal) {
            const statusInput = document.getElementById('statusFilterInput');
            if (statusInput) statusInput.value = statusVal;
            if (filterForm) filterForm.dispatchEvent(new Event('submit'));
        };
    }
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
        confirmButtonText: 'ตกลง',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            let ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
            const currentUrl = new URL(window.location.href);
            const type = currentUrl.searchParams.get('type') || 'policy';
            window.location.href = 'action.php?action=' + action + '&type=' + type + '&ids=' + ids;
        }
    });
}

function deleteService(id, type) {
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
            window.location.href = 'action.php?action=delete&type=' + type + '&id=' + id;
        }
    });
}
</script>
<?php include_once('../includes/history_modal.php'); ?>
<?php include_once('../includes/footer.php'); ?>
