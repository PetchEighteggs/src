<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = 'จัดการข้อมูลคณะกรรมการจริยธรรมประจำกรม';
include_once('../includes/header.php');

$searchKeyword = $_GET['keyword'] ?? '';
$searchStatus = $_GET['status'] ?? '';

// Query personnel
$personnelList = [];
$total = 0;
$summary = ['total' => 0, 'active_count' => 0, 'inactive_count' => 0];

try {
    if (isset($DB)) {
        $summarySql = "SELECT COUNT(EthicsID) as total,
				COUNT(CASE WHEN IsActive = 'Y' THEN EthicsID END) as active_count
				FROM org_ethics_committee 
				WHERE DelData = 'N'";
        $stmtSum = $DB->prepare($summarySql);
        $stmtSum->execute();
        $sumRes = $stmtSum->fetch(PDO::FETCH_ASSOC);
        $summary['total'] = $sumRes['total'] ?? 0;
        $summary['active_count'] = $sumRes['active_count'] ?? 0;
        $summary['inactive_count'] = $summary['total'] - $summary['active_count'];

        $sqlBase = "SELECT EthicsID, NameTH, NameEN, PositionTH, PositionEN, Image, IsActive, OrderNo FROM org_ethics_committee WHERE DelData = 'N'";
        $params = [];
        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (NameTH LIKE :kw1 OR NameEN LIKE :kw2 OR PositionTH LIKE :kw3)";
            $params[':kw1'] = $params[':kw2'] = $params[':kw3'] = "%$searchKeyword%";
        }
        
        if ($searchStatus !== '') {
            if ($searchStatus === 'inactive') {
                $sqlBase .= " AND IsActive = 'N' ";
            } elseif ($searchStatus === 'active') {
                $sqlBase .= " AND IsActive = 'Y' ";
            }
        }
        
        $page   = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sqlBase, $params, $page, 25, 'OrderNo ASC');
        $personnelList = $result['data'];
        $total = $result['pagination']['total'] ?? 0;
    }
} catch (PDOException $e) {}

?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div id="cardTotal" onclick="filterStatus('')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">คณะกรรมการจริยธรรมประจำกรมทั้งหมด</div>
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
          <i class="ph ph-users-three text-primary"></i> ข้อมูลคณะกรรมการจริยธรรมประจำกรม
        </h2>
        <?php if ($userPermissions['add'] || $userPermissions['edit']): ?>
        <div class="flex gap-2">
            <a href="sort.php" class="px-4 py-2 bg-gray-50 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-100 font-medium transition-colors flex items-center gap-2">
                <i class="ph ph-arrows-down-up"></i> จัดเรียงลำดับ
            </a>
            <?php if ($userPermissions['add']): ?>
            <a href="form.php" class="btn-primary">
                <i class="ph ph-plus-circle"></i> เพิ่มข้อมูลใหม่
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php
    $isFiltered = ($searchKeyword !== '' || $searchStatus !== '');
    ?>
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
        <form id="filterForm" method="GET" action="index.php" class="space-y-4">
            <input type="hidden" name="status" id="statusFilterInput" value="<?php echo htmlspecialchars($searchStatus); ?>">
            <div class="flex flex-col md:flex-row gap-3 items-center">
                <!-- Keyword Search -->
                <div class="flex-1 relative w-full">
                    <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ค้นหาชื่อ, ตำแหน่ง, ปี พ.ศ..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm">
                </div>
                
                <!-- Action Buttons -->
                <div class="flex gap-2 w-full md:w-auto shrink-0">
                    <button type="submit" class="btn-primary px-6 py-2 rounded-lg text-sm font-medium flex items-center justify-center gap-1.5 cursor-pointer h-[42px] whitespace-nowrap">
                        ค้นหา
                    </button>
                    <a href="index.php" id="clearFiltersBtn" class="<?php echo $isFiltered ? '' : 'hidden'; ?> px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors h-[42px] flex items-center">ล้างค่า</a>
                </div>
            </div>
        </form>
    </div>
    
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
    <div id="pastExecutivesTableContainer" class="space-y-6">
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
								<th width="5%">
									<input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="toggleSelectAll(this)">
								</th>
								<?php endif; ?>
								<th width="8%">รูปภาพ</th>
								<th width="25%">ชื่อ-นามสกุล</th>
								<th width="25%">ตำแหน่ง</th>
								<th width="8%">สถานะ</th>
								<?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
								<th width="10%">จัดการ</th>
								<?php endif; ?>
							</tr>
            </thead>
            <tbody>
                <?php if (empty($personnelList)): ?>
                <tr>
                    <td colspan="7" class="text-center py-8 text-gray-500">
                        <i class="ph ph-users text-4xl mb-2 text-gray-300"></i>
                        <p>ยังไม่มีข้อมูลคณะกรรมการจริยธรรมประจำกรม</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($personnelList as $row): ?>
                    <tr>
                        <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                        <td>
                            <input type="checkbox" name="ids[]" value="<?php echo $row['EthicsID']; ?>" class="row-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="updateBulkDeleteBtn()">
                        </td>
                        <?php endif; ?>
                        <td>
                            <div class="w-12 h-12 rounded-full bg-gray-100 overflow-hidden border border-gray-200">
                                <?php if (!empty($row['Image'])): ?>
                                <?php 
                                  $imgUrl = $ImgPath . 'ethics_committee/' . $row['Image'];
                                ?>
                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="w-full  object-cover cursor-pointer hover:opacity-80" onclick="previewImage('<?php echo htmlspecialchars($imgUrl); ?>')">
                                <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400"><i class="ph ph-user text-xl"></i></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($row['NameTH']); ?></div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($row['NameEN'] ?? ''); ?></div>
                        </td>
                        <td>
                            <div class="font-medium text-gray-700 text-sm"><?php echo htmlspecialchars($row['PositionTH']); ?></div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($row['PositionEN'] ?? ''); ?></div>
                        </td>
                        <td>
                            <?php if(($row['IsActive'] ?? 'N') == 'Y'): ?>
                                <span class="inline-flex px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium border border-green-200 whitespace-nowrap">เปิดใช้งาน</span>
                            <?php else: ?>
                                <span class="inline-flex px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium border border-gray-200 whitespace-nowrap">ปิดการใช้งาน</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                        <td>
                            <div class="flex items-center justify-center gap-2">
                                <?php if ($userPermissions['edit']): ?>
                                <a href="form.php?id=<?php echo $row['EthicsID']; ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="แก้ไข">
                                    <i class="ph ph-pencil-simple"></i>
                                </a>
                                <?php endif; ?>
																<button type="button" onclick="openHistoryModal(<?php echo $row['EthicsID']; ?>, 'ethics_committee')" class="w-8 h-8 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center hover:bg-gray-200 transition-colors" title="ประวัติการใช้งาน">
                                    <i class="ph ph-clock-counter-clockwise"></i>
                                </button>
                                <?php if ($userPermissions['delete']): ?>
                                <button type="button" onclick="deleteItem(<?php echo $row['EthicsID']; ?>)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition-colors" title="ลบ">
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
    <?php if (isset($result['pagination'])): ?>
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-gray-100 gap-4">
        <div class="text-sm text-gray-500">
            แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo $result['pagination']['total_pages']; ?> (ทั้งหมด <?php echo $result['pagination']['total']; ?> รายการ)
        </div>
        <?php if ($result['pagination']['total_pages'] > 1): 
            $qParams = [];
            if ($searchKeyword) $qParams['keyword'] = $searchKeyword;
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
function filterStatus(statusVal) {
    const statusInput = document.getElementById('statusFilterInput');
    if (statusInput) {
        statusInput.value = statusVal;
    }
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.dispatchEvent(new Event('submit'));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // AJAX Table Loading
    const tableContainer = document.getElementById('pastExecutivesTableContainer');
    if (tableContainer) {
        function loadPage(url) {
            tableContainer.style.opacity = '0.5';
            tableContainer.style.pointerEvents = 'none';
            const ajaxUrl = new URL(url, window.location.origin + window.location.pathname);
            ajaxUrl.searchParams.set('ajax', '1');
            
            fetch(ajaxUrl.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.text())
            .then(html => {
                tableContainer.innerHTML = html;
                tableContainer.style.opacity = '1';
                tableContainer.style.pointerEvents = 'auto';
                
                const urlObj = new URL(url, window.location.origin);
                const status = urlObj.searchParams.get('status') || '';
                const statusInput = document.getElementById('statusFilterInput');
                if (statusInput) statusInput.value = status;
            });
        }

        tableContainer.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (link && link.href && link.href.includes('index.php')) {
                if(link.getAttribute('onclick') && link.getAttribute('onclick').includes('confirm(')) return;
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
                loadPage(url);
            });
        }
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
        if (container) {
            container.classList.remove('hidden');
            container.classList.add('flex');
        }
        if (countText) countText.innerHTML = '(เลือก ' + checkedCount + ' รายการ)';
    } else {
        if (container) {
            container.classList.add('hidden');
            container.classList.remove('flex');
        }
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
        confirmMsg = 'คุณต้องการเปิดใช้งานที่เลือก ' + checkedBoxes.length + ' รายการใช่หรือไม่?';
        actionStr = 'bulk_active';
    } else if (action === 'inactive') {
        confirmMsg = 'คุณต้องการปิดการใช้งานที่เลือก ' + checkedBoxes.length + ' รายการใช่หรือไม่?';
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
        title: 'ยืนยันการลบข้อมูล?',
        text: "ข้อมูลนี้จะถูกลบออกจากระบบ!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `action.php?action=delete&id=${id}`;
        }
    });
}
function previewImage(src) {
    AdminDialog.fire({
        imageUrl: src,
        imageAlt: 'Preview',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
            image: 'max-h-[80vh] object-contain rounded-lg'
        }
    });
}
</script>
<?php include_once('../includes/history_modal.php'); ?>
<?php include_once('../includes/footer.php'); ?>

