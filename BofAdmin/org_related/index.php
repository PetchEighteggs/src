<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = 'จัดการหน่วยงานที่เกี่ยวข้อง';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$canAdd    = $userPermissions['add']    ?? true;
$canEdit   = $userPermissions['edit']   ?? true;
$canDelete = $userPermissions['delete'] ?? true;

$searchKeyword = $_GET['keyword'] ?? '';
$searchStatus  = $_GET['status']  ?? '';

// Fetch summary
$summary = ['total' => 0, 'active_count' => 0, 'inactive_count' => 0];
$itemList = [];

try {
    if (isset($DB)) {
        $sqlBase = "SELECT * FROM org_related WHERE DelData = 'N'";
        $params = [];

        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (Title_TH LIKE :kw1 OR Title_EN LIKE :kw2)";
            $params[':kw1'] = "%$searchKeyword%";
            $params[':kw2'] = "%$searchKeyword%";
        }

        $stmtSum = $DB->prepare("SELECT COUNT(*) as total,
            SUM(CASE WHEN IsActive='Y' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN IsActive!='Y' THEN 1 ELSE 0 END) as inactive_count
            FROM ($sqlBase) t");
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        $sql = $sqlBase;
        if ($searchStatus === 'active')   $sql .= " AND IsActive = 'Y'";
        elseif ($searchStatus === 'inactive') $sql .= " AND IsActive = 'N'";

        $page   = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sql, $params, $page, 25, 'IsActive ASC, OrderNo ASC, RelatedID DESC');
        $itemList = $result['data'];
    }
} catch (PDOException $e) { $dbError = $e->getMessage(); }
?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="filterStatus('')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">รายการทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-buildings text-3xl"></i>
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
        <div class="relative z-10 w-14 h-14 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-minus-circle text-3xl"></i>
        </div>
    </div>
</div>

<div class="glass-panel p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-100">
        <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-buildings text-primary"></i>
                จัดการหน่วยงานที่เกี่ยวข้อง
            </h2>
           
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($canEdit): ?>
            <a href="sort.php" class="px-4 py-2 bg-gray-50 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-100 font-medium transition-colors flex items-center gap-2">
                <i class="ph ph-arrows-down-up text-lg"></i> จัดเรียงลำดับ
            </a>
            <?php endif; ?>
            <?php if ($canAdd): ?>
            <a href="form.php" class="btn-primary h-10 px-6 flex items-center justify-center gap-2 rounded-lg font-medium transition-colors">
                <i class="ph ph-plus-circle text-lg"></i> เพิ่มรายการ
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search Box -->
    <form id="filterForm" method="GET" action="index.php" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
        <input type="hidden" name="status" id="statusFilterInput" value="<?php echo htmlspecialchars($searchStatus); ?>">
        <div class="flex gap-4">
            <div class="flex-1 relative">
              <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ค้นหาชื่อหน่วยงาน..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary bg-white h-10">
            </div>
            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium transition-colors h-10 flex items-center gap-2">
                ค้นหา
            </button>
            <a href="index.php" id="clearFiltersBtn" class="<?php echo $searchKeyword !== '' ? '' : 'hidden'; ?> px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors h-[42px] flex items-center">
                ล้างค่า
            </a>
        </div>
    </form>

    <form id="orgRelatedActionForm" action="action.php" method="POST" class="hidden">
        <?php echo bofSecurityCsrfField(); ?>
        <input type="hidden" name="action" id="orgRelatedActionName" value="">
        <input type="hidden" name="id" id="orgRelatedActionId" value="">
        <input type="hidden" name="ids" id="orgRelatedActionIds" value="">
    </form>
    
    <?php if (defined('IS_AJAX')) { ob_clean(); } ?>
    <div id="orgRelatedTableContainer" class="space-y-6">
    <!-- Bulk Actions -->
    <?php if ($canEdit || $canDelete): ?>
    <div class="flex items-center gap-3 mb-6 hidden bg-gray-50 p-4 rounded-xl border border-gray-200" id="bulkActionContainer">
        <span class="text-sm font-medium text-gray-700">จัดการรายการที่เลือก:</span>
        <select id="bulkActionSelect" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none bg-white h-10 min-w-[180px]">
            <option value="">-- เลือกการจัดการ --</option>
            <?php if ($canEdit): ?>
            <option value="active">เปิดใช้งาน</option>
            <option value="inactive">ปิดใช้งาน</option>
            <?php endif; ?>
            <?php if ($canDelete): ?>
            <option value="delete">ลบข้อมูล</option>
            <?php endif; ?>
        </select>
        <button type="button" onclick="executeBulkAction()" class="btn-primary px-6 h-10 text-sm flex items-center gap-2">
            <i class="ph ph-check"></i> นำไปใช้
        </button>
        <span class="text-sm text-gray-500 ml-2" id="bulkCountText"></span>
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

    <!-- Table -->
    <?php if (isset($dbError)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm">
        <i class="ph ph-warning-circle mr-2"></i>Database Error: <?php echo htmlspecialchars($dbError); ?>
    </div>
    <?php endif; ?>

    <div class="overflow-x-auto rounded-xl border border-gray-100">
        <table class="min-w-full bg-white">
            <thead>
                <tr class="bg-gray-50/80 text-gray-600 text-sm border-b border-gray-100">
                    <?php if ($canEdit || $canDelete): ?>
                    <th class="py-4 px-4 font-semibold w-12 text-center">
                        <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer" onclick="toggleSelectAll(this)">
                    </th>
                    <?php endif; ?>
                    <th class="py-4 px-4 font-semibold w-24 text-center">โลโก้หน่วยงาน</th>
                    <th class="py-4 px-6 font-semibold text-left">ชื่อหน่วยงาน</th>
                    <th class="py-4 px-4 font-semibold w-28 text-center">สถานะ</th>
                    <?php if ($canEdit || $canDelete): ?>
                    <th class="py-4 px-4 font-semibold w-28 text-center">จัดการ</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($itemList)): ?>
                <tr>
                    <td colspan="<?php echo ($canEdit || $canDelete) ? 6 : 4; ?>" class="py-12 text-center text-gray-400">
                        <i class="ph ph-buildings text-4xl mb-3 block text-gray-300"></i>
                        ยังไม่มีข้อมูลหน่วยงานที่เกี่ยวข้อง
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($itemList as $row): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors group">
                        <?php if ($canEdit || $canDelete): ?>
                        <td class="py-4 px-4 text-center">
                            <input type="checkbox" name="row_ids[]" value="<?php echo $row['RelatedID']; ?>" 
                                   class="row-checkbox rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer"
                                   onclick="updateBulkDeleteBtn()">
                        </td>
                        <?php endif; ?>
                        <td class="py-4 px-4 text-center">
                            <div class="w-20 h-14 rounded-lg bg-gray-100 overflow-hidden border border-gray-200 mx-auto flex items-center justify-center p-1">
                                <?php if (!empty($row['Image'])): ?>
                                <img src="<?php echo $ImgPath . 'org_related/' . htmlspecialchars($row['Image']); ?>" 
                                     class="max-w-full max-h-full object-contain cursor-pointer hover:opacity-80 transition-opacity"
                                     onclick="previewImage('<?php echo $ImgPath . 'org_related/' . htmlspecialchars($row['Image']); ?>')"
                                     alt="Logo">
                                <?php else: ?>
                                <i class="ph ph-image text-2xl text-gray-300"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-4 px-6">
                            <div class="font-medium text-gray-800 line-clamp-2">
                                <?php echo htmlspecialchars($row['Title_TH']); ?>
                            </div>
                            <?php if (!empty($row['Title_EN'])): ?>
                            <div class="text-xs text-gray-400 mt-1 line-clamp-1"><?php echo htmlspecialchars($row['Title_EN']); ?></div>
                            <?php endif; ?>
                        </td>
                        
                        <td class="py-4 px-4 text-center">
                            <?php if (($row['IsActive'] ?? 'Y') == 'Y'): ?>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-green-50 text-green-600 border border-green-200">
                                <i class="ph ph-circle-fill text-[8px]"></i> เปิดใช้งาน
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200">
                                <i class="ph ph-circle-fill text-[8px]"></i> ปิดใช้งาน
                            </span>
                            <?php endif; ?>
                        </td>
                        <?php if ($canEdit || $canDelete): ?>
                        <td class="py-4 px-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <?php if ($canEdit): ?>
                                <a href="form.php?id=<?php echo $row['RelatedID']; ?>"
                                   class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors"
                                   title="แก้ไข">
                                    <i class="ph ph-pencil-simple text-lg"></i>
                                </a>
                                <?php endif; ?>
																<button type="button" onclick="openHistoryModal(<?php echo $row['RelatedID']; ?>, 'org_related')" class="w-8 h-8 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center hover:bg-gray-200 transition-colors" title="ประวัติการใช้งาน">
                                  <i class="ph ph-clock-counter-clockwise text-lg"></i>
                                </button>
                                <?php if ($canDelete): ?>
                                <button onclick="deleteItem(<?php echo $row['RelatedID']; ?>)"
                                        class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center transition-colors"
                                        title="ลบ">
                                    <i class="ph ph-trash text-lg"></i>
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
    <?php if (!empty($itemList) && isset($result['pagination'])): ?>
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-gray-100 gap-4">
        <div class="text-sm text-gray-500">
            แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo $result['pagination']['total_pages']; ?> (ทั้งหมด <?php echo number_format($result['pagination']['total']); ?> รายการ)
        </div>
        <div class="flex gap-2 flex-wrap items-center">
            <?php
            $totalPages = $result['pagination']['total_pages'];
            $startPage  = max(1, $page - 2);
            $endPage    = min($totalPages, $page + 2);
            $baseUrl    = "index.php?keyword=" . urlencode($searchKeyword) . "&status=" . urlencode($searchStatus);
            ?>
            <?php if ($page > 1): ?>
            <a href="<?php echo $baseUrl; ?>&page=1" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าแรก">
                <i class="ph ph-caret-double-left text-sm"></i>
            </a>
            <a href="<?php echo $baseUrl; ?>&page=<?php echo $page - 1; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200">
                <i class="ph ph-caret-left text-sm"></i>
            </a>
            <?php endif; ?>
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="<?php echo $baseUrl; ?>&page=<?php echo $i; ?>" 
               class="w-8 h-8 flex items-center justify-center rounded-lg border text-sm font-medium transition-colors <?php echo $i == $page ? 'bg-primary text-white border-primary shadow-sm shadow-primary/30' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="<?php echo $baseUrl; ?>&page=<?php echo $page + 1; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200">
                <i class="ph ph-caret-right text-sm"></i>
            </a>
            <a href="<?php echo $baseUrl; ?>&page=<?php echo $totalPages; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าสุดท้าย">
                <i class="ph ph-caret-double-right text-sm"></i>
            </a>
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

<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="fixed inset-0 bg-black/85 flex items-center justify-center z-[9999] hidden backdrop-blur-md" onclick="closeImagePreview()">
    <button onclick="closeImagePreview()" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20">
        <i class="ph ph-x text-2xl"></i>
    </button>
    <div class="relative max-w-[90vw] max-h-[85vh] p-2" onclick="event.stopPropagation()">
        <img id="previewModalImg" src="" alt="" class="max-w-full max-h-[80vh] rounded-xl shadow-2xl object-contain bg-white">
    </div>
</div>

<script>
function filterStatus(val) {
    document.getElementById('statusFilterInput').value = val;
    document.getElementById('filterForm').submit();
}
function previewImage(src) {
    document.getElementById('previewModalImg').src = src;
    document.getElementById('imagePreviewModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeImagePreview() {
    document.getElementById('imagePreviewModal').classList.add('hidden');
    document.body.style.overflow = '';
}
function toggleSelectAll(source) {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = source.checked);
    updateBulkDeleteBtn();
}
function updateBulkDeleteBtn() {
    let count = document.querySelectorAll('.row-checkbox:checked').length;
    let container = document.getElementById('bulkActionContainer');
    let countText = document.getElementById('bulkCountText');
    if (count > 0) {
        container.classList.remove('hidden'); container.classList.add('flex');
        countText.innerHTML = '(เลือก ' + count + ' รายการ)';
    } else {
        container.classList.add('hidden'); container.classList.remove('flex');
    }
}
function executeBulkAction() {
    let boxes = document.querySelectorAll('.row-checkbox:checked');
    if (!boxes.length) return;
    let action = document.getElementById('bulkActionSelect').value;
    if (!action) { AdminDialog.fire('แจ้งเตือน', 'กรุณาเลือกการจัดการ', 'warning'); return; }
    let actionMap = { delete: 'bulk_delete', active: 'bulk_active', inactive: 'bulk_inactive' };
    let msgs = { delete: 'ลบทิ้ง', active: 'เปิดใช้งาน', inactive: 'ปิดใช้งาน' };
    AdminDialog.fire({
        title: 'ยืนยัน?', text: 'คุณต้องการ' + msgs[action] + ' ' + boxes.length + ' รายการ',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#005A9C', cancelButtonColor: '#d33',
        confirmButtonText: 'ตกลง', cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if (r.isConfirmed) {
            let ids = Array.from(boxes).map(cb => cb.value).join(',');
            document.getElementById('orgRelatedActionName').value = actionMap[action];
            document.getElementById('orgRelatedActionId').value = '';
            document.getElementById('orgRelatedActionIds').value = ids;
            document.getElementById('orgRelatedActionForm').submit();
        }
    });
}
function deleteItem(id) {
    AdminDialog.fire({
        title: 'ยืนยันการลบ?', text: 'คุณต้องการลบทิ้งข้อมูลนี้ใช่หรือไม่?', icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#aaa',
        confirmButtonText: 'ยืนยันการลบ', cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('orgRelatedActionName').value = 'delete';
            document.getElementById('orgRelatedActionId').value = id;
            document.getElementById('orgRelatedActionIds').value = '';
            document.getElementById('orgRelatedActionForm').submit();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // AJAX Table Loading
    const tableContainer = document.getElementById('orgRelatedTableContainer');
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
            });
        }

        tableContainer.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (link && link.href && link.href.includes('index.php')) {
                // Ignore delete links
                if(link.getAttribute('onclick') && link.getAttribute('onclick').includes('confirm(')) return;
                e.preventDefault();
                loadPage(link.href);
            }
        });

        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            const clearFiltersBtn = document.getElementById('clearFiltersBtn');
            const updateClearButton = function() {
                const keyword = filterForm.elements.namedItem('keyword');
                const hasKeyword = keyword && keyword.value.trim() !== '';
                if (clearFiltersBtn) clearFiltersBtn.classList.toggle('hidden', !hasKeyword);
            };

            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                updateClearButton();
                const formData = new FormData(filterForm);
                const searchParams = new URLSearchParams(formData);
                const url = 'index.php?' + searchParams.toString();
                loadPage(url);
            });
        }
        
        window.filterStatus = function(statusVal) {
            const statusInput = document.getElementById('statusFilterInput');
            if (statusInput) statusInput.value = statusVal;
            if (filterForm) filterForm.dispatchEvent(new Event('submit'));
        };
    }
});
</script>

<?php include_once('../includes/history_modal.php'); ?>
<?php include_once('../includes/footer.php'); ?>
