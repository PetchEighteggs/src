<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = "จัดการหมวดหมู่ข่าวสาร";
include_once('../includes/header.php');

$search = mosGetParam($_FORM, 'search', '');
$searchStatus = $_GET['status'] ?? '';

try {
    if (isset($DB)) {
        $sqlBase = "SELECT c.*, (SELECT COUNT(*) FROM news n WHERE n.CategoryID = c.CategoryID AND n.DelData = 'N') as NewsCount 
                    FROM news_category c 
                    WHERE c.DelData = 'N'";
        $params = [];

        if ($search != '') {
            $sqlBase .= " AND (c.CategoryName_TH LIKE :search OR c.CategoryName_EN LIKE :search)";
            $params[':search'] = "%$search%";
        }

        // Calculate summary based on sqlBase (ignoring active/inactive status filters)
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
            $sql .= " AND c.IsActive = 'Y'";
        } else if ($searchStatus === 'inactive') {
            $sql .= " AND c.IsActive = 'N'";
        }

        $page = mosGetParam($_FORM, 'page', 1);
        $result = paginateWithCount($DB, $sql, $params, $page, 25, 'OrderNo ASC, CategoryID DESC');
    }
} catch (PDOException $e) {}
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div id="cardTotal" onclick="filterStatus('')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">หมวดหมู่ทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-folder-open text-3xl"></i>
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
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-gray-800"><i class="ph ph-folder-open text-primary mr-2"></i> หมวดหมู่ข่าวสาร</h2>
        <?php if ($userPermissions['add']): ?>
        <a href="form.php" class="btn-primary">
            <i class="ph ph-plus"></i> เพิ่มหมวดหมู่
        </a>
        <?php endif; ?>
    </div>

    <!-- Search Box -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
        <form id="filterForm" method="GET" action="index.php" class="flex gap-4">
            <input type="hidden" name="status" id="statusFilterInput" value="<?php echo htmlspecialchars($searchStatus); ?>">
            <div class="flex-1 relative">
                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ค้นหาหมวดหมู่ข่าว (TH/EN)..." class="w-full pl-5 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
            </div>
            <button type="submit" class="btn-primary px-6">ค้นหา</button>
            <?php if($search != ''): ?>
                <a href="index.php" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors">ล้างค่า</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Bulk Actions -->
    <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
    <div class="flex items-center gap-3 mb-6 hidden bg-gray-50 p-4 rounded-xl border border-gray-200" id="bulkActionContainer">
        <span class="text-sm font-medium text-gray-700">จัดการรายการที่เลือก:</span>
        <select id="bulkActionSelect" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-blue-500 bg-white h-[42px] min-w-[200px]">
            <option value="">-- เลือกการจัดการ --</option>
            <option value="active">เปิดใช้งาน</option>
            <option value="inactive">ระงับการใช้งาน</option>
            <option value="delete">ลบข้อมูล</option>
        </select>
        <button type="button" onclick="executeBulkAction()" class="btn-primary px-6 h-[42px] text-sm font-medium flex items-center justify-center">นำไปใช้</button>
        <span class="text-sm text-gray-500 ml-2" id="bulkCountText"></span>
    </div>
    <?php endif; ?>

    <?php if (defined('IS_AJAX')) { ob_clean(); } ?>
    <div id="categoriesTableContainer" class="space-y-6">
        <?php if (!empty($searchStatus)): ?>
        <div class="flex items-center justify-between bg-blue-50 border border-blue-100 p-4 rounded-xl text-sm text-blue-800 shadow-sm transition-all duration-300">
            <div class="flex items-center gap-2">
                <i class="ph ph-funnel text-lg"></i>
                <span>กำลังแสดงผลหมวดหมู่เฉพาะสถานะ: <strong><?php echo $searchStatus === 'active' ? 'เปิดใช้งาน (Active)' : 'ปิดการใช้งาน (Inactive)'; ?></strong></span>
            </div>
            <button type="button" onclick="filterStatus('')" class="text-blue-600 hover:text-blue-800 font-semibold flex items-center gap-1 hover:underline">
                <i class="ph ph-x-circle text-lg"></i> ล้างการกรอง
            </button>
        </div>
        <?php endif; ?>
        <div class="overflow-x-auto">
        <table class="premium-table">
            <thead>
                <tr>
                    <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                    <th width="5%" class="text-center">
                        <input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="toggleSelectAll(this)">
                    </th>
                    <?php endif; ?>
                    <th>ชื่อหมวดหมู่ </th>
                    <th class="text-center" width="150">จำนวนข่าวทั้งหมด</th>
                    <th width="100">สถานะ</th>
                    <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                    <th width="150" class="text-center">จัดการ</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($result['data']) > 0): ?>
                    <?php foreach ($result['data'] as $index => $row): ?>
                        <tr>
                            <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                            <td class="text-center">
                                <input type="checkbox" name="ids[]" value="<?php echo $row['CategoryID']; ?>" class="row-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="updateBulkDeleteBtn()">
                            </td>
                            <?php endif; ?>
                            <td>
                                <div class="font-semibold text-gray-800">
                                    <?php echo htmlspecialchars($row['CategoryName_TH']); ?>
                                </div>
                                <?php if($row['CategoryName_EN']): ?>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($row['CategoryName_EN']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                           
                            <td class="text-center">
                                <a href="../news/index.php?category=<?php echo $row['CategoryID']; ?>" class="inline-flex px-3 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 hover:text-blue-900 rounded-full text-xs font-semibold border border-blue-200 whitespace-nowrap transition-colors">
                                    <?php echo number_format($row['NewsCount']); ?> รายการ
                                </a>
                            </td>
                            <td>
                                <?php if($row['IsActive'] == 'Y'): ?>
                                    <span class="inline-flex px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium border border-green-200 whitespace-nowrap">เปิดใช้งาน</span>
                                <?php else: ?>
                                    <span class="inline-flex px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium border border-gray-200 whitespace-nowrap">ปิดการใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                            <td>
                                <div class="flex justify-center gap-2">
                                    <?php if ($userPermissions['edit']): ?>
                                    <a href="form.php?id=<?php echo $row['CategoryID']; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors" title="แก้ไข">
                                        <i class="ph ph-pencil-simple"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button type="button" onclick="openHistoryModal(<?php echo $row['CategoryID']; ?>, 'news_category')" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-200 transition-colors" title="ประวัติการใช้งาน">
                                        <i class="ph ph-clock-counter-clockwise"></i>
                                    </button>
                                    <?php if ($userPermissions['delete']): ?>
                                    <a href="action.php?action=delete&id=<?php echo $row['CategoryID']; ?>" onclick="confirmLink(event, this.href, 'ยืนยันการลบข้อมูลนี้หรือไม่?');" class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors" title="ลบ">
                                        <i class="ph ph-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-8 text-gray-500">ไม่พบข้อมูลหมวดหมู่ข่าว</td>
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
                    <a href="index.php?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                       class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าแรก">
                        <i class="ph ph-caret-double-left text-sm"></i>
                    </a>
                    <a href="index.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($searchStatus); ?>" 
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
                    <a href="index.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                       class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == $page ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:text-primary transition-colors'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <!-- Next Page -->
                <?php if ($page < $totalPages): ?>
                    <a href="index.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                       class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าถัดไป">
                        <i class="ph ph-caret-right text-sm"></i>
                    </a>
                    <a href="index.php?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($searchStatus); ?>" 
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
    const tableContainer = document.getElementById('categoriesTableContainer');
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
            
            // Update active states of cards based on URL status param
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

            const panel = document.querySelector('.glass-panel');
            if (panel) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
            // Support both absolute and relative clicks in local scope
            if (link.href.includes('news_category/index.php')) {
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
