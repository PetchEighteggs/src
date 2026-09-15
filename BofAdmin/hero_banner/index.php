<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = "จัดการแบนเนอร์หน้าแรก";
include_once('../includes/header.php');

$search = mosGetParam($_FORM, 'search', '');
$searchStatus = $_GET['status'] ?? '';

try {
    if (isset($DB)) {
        $sqlBase = "SELECT * FROM hero_banners WHERE DelData = 'N'";
        $params = [];

        if ($search != '') {
            $sqlBase .= " AND (Title_TH LIKE :search OR Title_EN LIKE :search OR Template LIKE :search)";
            $params[':search'] = "%$search%";
        }

        // Calculate summary cards
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
            $sql .= " AND IsActive = 'Y'";
        } else if ($searchStatus === 'inactive') {
            $sql .= " AND IsActive = 'N'";
        }

        $page = mosGetParam($_FORM, 'page', 1);
        $result = paginateWithCount($DB, $sql, $params, $page, 15, 'IsActive ASC, OrderNo ASC, BannerID DESC');
    }
} catch (PDOException $e) {}
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div id="cardTotal" onclick="filterStatus('')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">แบนเนอร์ทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-image text-3xl"></i>
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
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
            <i class="ph ph-image text-primary"></i> แบนเนอร์หน้าแรก
        </h2>
        <div class="flex flex-wrap items-center gap-3">
            <a href="config.php" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200 px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2">
                <i class="ph ph-gear"></i> ตั้งค่าเลย์เอาต์
            </a>
            <?php if (!empty($userPermissions['edit'])): ?>
                <a href="sort.php" class="px-4 py-2 bg-gray-50 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-100 font-medium transition-colors flex items-center gap-2">
                    <i class="ph ph-arrows-down-up text-lg"></i>
                    จัดเรียงลำดับ
                </a>
            <?php endif; ?>
            <?php if (!empty($userPermissions['add'])): ?>
                <a href="form.php" class="btn-primary flex items-center gap-2">
                    <i class="ph ph-plus-circle"></i>
                    เพิ่มแบนเนอร์
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search Box -->
    <div class="bg-gray-50 p-4 rounded-xl mb-6 border border-gray-100">
        <form id="filterForm" method="GET" action="index.php" class="flex gap-4">
            <input type="hidden" name="status" id="statusFilterInput" value="<?php echo htmlspecialchars($searchStatus); ?>">
            <div class="flex-1 relative">
                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ค้นหาแบนเนอร์..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
            </div>
            <button type="submit" class="btn-primary px-6">ค้นหา</button>
            <?php if($search != ''): ?>
                <a href="index.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium transition-colors">ล้างค่า</a>
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
            <option value="inactive">ปิดการใช้งาน</option>
            <option value="delete">ลบข้อมูล</option>
        </select>
        <button type="button" onclick="executeBulkAction()" class="btn-primary px-6 h-[42px] text-sm font-medium flex items-center justify-center">นำไปใช้</button>
        <span class="text-sm text-gray-500 ml-2" id="bulkCountText"></span>
    </div>
    <?php endif; ?>

    <?php if (defined('IS_AJAX')) { ob_clean(); } ?>
    <div id="bannersTableContainer" class="space-y-6">
        <?php if (!empty($searchStatus)): ?>
        <div class="flex items-center justify-between bg-blue-50 border border-blue-100 p-4 rounded-xl text-sm text-blue-800 shadow-sm transition-all duration-300">
            <div class="flex items-center gap-2">
                <i class="ph ph-funnel text-lg"></i>
                <span>กำลังแสดงผลแบนเนอร์เฉพาะสถานะ: <strong><?php echo $searchStatus === 'active' ? 'เปิดใช้งาน (Active)' : 'ปิดการใช้งาน (Inactive)'; ?></strong></span>
            </div>
            <button type="button" onclick="filterStatus('')" class="text-blue-600 hover:text-blue-800 font-semibold flex items-center gap-1 hover:underline">
                <i class="ph ph-x-circle text-lg"></i> ล้างการกรอง
            </button>
        </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="premium-table table-fixed min-w-[900px]">
                <colgroup>
                    <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                    <col style="width: 4.5rem;">
                    <?php endif; ?>
                    <col style="width: 9.5rem;">
                    <col>
                    <col style="width: 8rem;">
                    <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                    <col style="width: 10rem;">
                    <?php endif; ?>
                </colgroup>
                <thead>
                    <tr>
                        <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                        <th class="text-center" style="text-align: center;">
                            <input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="toggleSelectAll(this)">
                        </th>
                        <?php endif; ?>
                        <th class="text-left" style="text-align: left;">รูปแบนเนอร์</th>
                        <th class="text-left" style="text-align: left;">หัวข้อ (<?php echo $Lang1; ?>)</th>
                        <th class="text-left" style="text-align: left;">สถานะ</th>
                        <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                        <th class="text-center" style="text-align: center;">จัดการ</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($result['data']) && count($result['data']) > 0): ?>
                        <?php foreach ($result['data'] as $row): ?>
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                                <td class="text-center">
                                    <input type="checkbox" name="ids[]" value="<?php echo $row['BannerID']; ?>" class="row-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="updateBulkDeleteBtn()">
                                </td>
                                <?php endif; ?>
                                <td class="text-left">
                                    <div class="w-24 h-14 rounded-lg bg-gray-100 overflow-hidden border border-gray-200">
                                        <?php if (!empty($row['Image'])): ?>
                                            <img src="<?php echo $ImgPath . 'hero_banner/'; ?><?php echo htmlspecialchars($row['Image']); ?>" class="w-full h-full object-cover cursor-pointer" onclick="previewImage(this.src)" alt="Banner Image">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-gray-400 bg-gray-50 text-xs">ไม่มีรูป</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-left">
                                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($row['Title_TH']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['Title_EN'] ?? ''); ?></div>
                                    <?php if(!empty($row['PublishStart']) || !empty($row['PublishEnd'])): ?>
                                        <div class="text-[10px] text-gray-400 mt-1 flex items-center gap-1">
                                            <i class="ph ph-calendar"></i>
                                            แสดงผล: <?php echo !empty($row['PublishStart']) ? date('d/m/Y', strtotime($row['PublishStart'])) : '-'; ?> ถึง <?php echo !empty($row['PublishEnd']) ? date('d/m/Y', strtotime($row['PublishEnd'])) : '-'; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-left">
                                    <?php if($row['IsActive'] == 'Y'): ?>
                                        <span class="inline-flex px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold border border-green-200">เปิดใช้งาน</span>
                                    <?php else: ?>
                                        <span class="inline-flex px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold border border-gray-200">ปิดการใช้งาน</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($userPermissions['edit'] || $userPermissions['delete']): ?>
                                <td class="text-center">
                                    <div class="flex justify-center gap-2">
                                        <?php if ($userPermissions['edit']): ?>
                                        <a href="form.php?id=<?php echo $row['BannerID']; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors" title="แก้ไข">
                                            <i class="ph ph-pencil-simple"></i>
                                        </a>
                                        <?php endif; ?>
                                        <button type="button" onclick="openHistoryModal(<?php echo $row['BannerID']; ?>, 'hero_banner')" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-200 transition-colors" title="ประวัติการใช้งาน">
                                            <i class="ph ph-clock-counter-clockwise"></i>
                                        </button>
                                        <?php if ($userPermissions['delete']): ?>
                                        <button onclick="deleteBanner(<?php echo $row['BannerID']; ?>)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors" title="ลบ">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo ($userPermissions['edit'] || $userPermissions['delete']) ? 5 : 3; ?>" class="text-center py-12 text-gray-400">
                                <i class="ph ph-image-square text-4xl mb-2 text-gray-300"></i>
                                <p>ไม่พบข้อมูลแบนเนอร์</p>
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
    const tableContainer = document.getElementById('bannersTableContainer');
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
            if (link.href.includes('hero_banner/index.php')) {
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
        confirmMsg = 'คุณต้องการลบทิ้งแบนเนอร์ที่เลือกจำนวน ' + checkedBoxes.length + ' รายการใช่หรือไม่?';
        actionStr = 'bulk_delete';
    } else if (action === 'active') {
        confirmMsg = 'คุณต้องการเปิดใช้งานแบนเนอร์ที่เลือกจำนวน ' + checkedBoxes.length + ' รายการใช่หรือไม่?';
        actionStr = 'bulk_active';
    } else if (action === 'inactive') {
        confirmMsg = 'คุณต้องการระงับการใช้งานแบนเนอร์ที่เลือกจำนวน ' + checkedBoxes.length + ' รายการใช่หรือไม่?';
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

function deleteBanner(id) {
    AdminDialog.fire({
        title: 'ยืนยันการลบข้อมูล?',
        text: 'คุณต้องการลบแบนเนอร์สไลด์นี้ใช่หรือไม่?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ตกลง',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'action.php?action=delete&id=' + id;
        }
    });
}

// Image Preview controller
function previewImage(src) {
    const modal = document.getElementById('imagePreviewModal');
    const img = document.getElementById('previewModalImg');
    if (modal && img) {
        img.src = src;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeImagePreview() {
    const modal = document.getElementById('imagePreviewModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}
</script>

<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="fixed inset-0 bg-black/85 flex items-center justify-center z-[9999] hidden backdrop-blur-md transition-all duration-300" onclick="closeImagePreview()">
    <button type="button" onclick="closeImagePreview()" class="absolute top-6 right-6 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center hover:scale-105 transition-all shadow-lg border border-white/10 focus:outline-none">
        <i class="ph ph-x text-2xl font-bold"></i>
    </button>
    <div class="relative max-w-[90vw] max-h-[85vh] p-2" onclick="event.stopPropagation()">
        <img id="previewModalImg" src="" alt="Image Preview" class="max-w-full max-h-[80vh] rounded-xl shadow-2xl border border-white/10 object-contain bg-white/5">
    </div>
</div>

<?php include_once('../includes/history_modal.php'); ?>

<?php include_once('../includes/footer.php'); ?>
