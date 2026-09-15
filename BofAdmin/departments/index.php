<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = 'จัดการหน้าที่ความรับผิดชอบ';
include_once('../includes/header.php');

$searchKeyword = $_GET['keyword'] ?? '';
$groupFilter   = $_GET['deptgroup'] ?? '';

$deptList = [];
try {
    if (isset($DB)) {
        $sqlBase = "SELECT DeptID, Slug, Image, NameTH, NameEN, ShortNameTH, ShortNameEN, DeptGroup, DescriptionTH, DescriptionEN, UpdateDate
                    FROM microsite_departments WHERE IsActive = 'Y' AND DeptGroup != 'other' AND DescriptionTH IS NOT NULL AND DescriptionTH != ''";
        $params  = [];
        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (NameTH LIKE :kw1 OR NameEN LIKE :kw2 OR ShortNameTH LIKE :kw3 OR ShortNameEN LIKE :kw4)";
            $params[':kw1'] = $params[':kw2'] = $params[':kw3'] = $params[':kw4'] = "%$searchKeyword%";
        }
        if (!empty($groupFilter)) {
            $sqlBase .= " AND DeptGroup = :grp";
            $params[':grp'] = $groupFilter;
        }
        $page   = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sqlBase, $params, $page, 20, 'OrderNo_Org ASC, NameTH ASC');
        $deptList = $result['data'];
        
        // Summary Counts
        $sumSql = "SELECT 
            COUNT(*) as total, 
            SUM(CASE WHEN DeptGroup='central' THEN 1 ELSE 0 END) as central_count,
            SUM(CASE WHEN DeptGroup='regional' THEN 1 ELSE 0 END) as regional_count
            FROM microsite_departments
            WHERE IsActive = 'Y' AND DeptGroup != 'other' AND DescriptionTH IS NOT NULL AND DescriptionTH != ''";
        $sumStmt = $DB->prepare($sumSql);
        $sumStmt->execute();
        $summary = $sumStmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}
?>

<?php
$isAllActive = empty($groupFilter) ? 'border-primary ring-2 ring-primary/20' : 'border-gray-100 hover:border-primary/50';
$isCentralActive = $groupFilter === 'central' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-100 hover:border-primary/50';
$isRegionalActive = $groupFilter === 'regional' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-100 hover:border-primary/50';
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <a href="index.php" class="bg-white p-6 rounded-2xl shadow-sm border <?php echo $isAllActive; ?> flex justify-between items-center relative overflow-hidden transition-all group">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">หน่วยงานทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform">
            <i class="ph ph-buildings text-3xl"></i>
        </div>
    </a>
    
    <a href="index.php?deptgroup=central" class="bg-white p-6 rounded-2xl shadow-sm border <?php echo $isCentralActive; ?> flex justify-between items-center transition-all group">
        <div>
            <div class="text-sm text-gray-500 font-medium mb-1">ส่วนกลาง</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['central_count'] ?? 0); ?></div>
        </div>
        <div class="w-14 h-14 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-500 group-hover:scale-110 transition-transform">
            <i class="ph ph-map-pin text-3xl"></i>
        </div>
    </a>
    
    <a href="index.php?deptgroup=regional" class="bg-white p-6 rounded-2xl shadow-sm border <?php echo $isRegionalActive; ?> flex justify-between items-center transition-all group">
        <div>
            <div class="text-sm text-gray-500 font-medium mb-1">ส่วนภูมิภาค</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['regional_count'] ?? 0); ?></div>
        </div>
        <div class="w-14 h-14 rounded-full bg-teal-50 flex items-center justify-center text-teal-500 group-hover:scale-110 transition-transform">
            <i class="ph ph-map-trifold text-3xl"></i>
        </div>
    </a>
</div>

<div class="glass-panel p-6">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
            <i class="ph ph-clipboard-text text-primary"></i> จัดการหน้าที่ความรับผิดชอบ
        </h2>
        <div class="flex flex-wrap items-center gap-3">
            <?php if (!empty($userPermissions['edit'])): ?>
                <a href="sort.php" class="px-4 py-2 bg-gray-50 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-100 font-medium transition-colors flex items-center gap-2">
                    <i class="ph ph-arrows-down-up text-lg"></i>
                    จัดเรียงลำดับ
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search & Filter -->
    <form method="GET" action="index.php" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหาหน่วยงาน</label>
            <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ชื่อหน่วยงาน..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div class="sm:w-64">
            <label class="block text-sm font-medium text-gray-700 mb-1">หน่วยงาน</label>
            <select name="deptgroup" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">-- ทั้งหมด --</option>
                <option value="central"  <?php echo $groupFilter === 'central'  ? 'selected' : ''; ?>>ส่วนกลาง</option>
                <option value="regional" <?php echo $groupFilter === 'regional' ? 'selected' : ''; ?>>ส่วนภูมิภาค</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6">ค้นหา</button>
            <?php if ($searchKeyword || $groupFilter): ?>
            <a href="index.php" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-[42px]">ล้างค่า</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (defined('IS_AJAX')) { ob_clean(); } ?>
    <div id="departmentsTableContainer" class="space-y-6">
    <div class="overflow-x-auto">
        <table class="premium-table">
            <thead>
                <tr>
                    <th>ชื่อหน่วยงาน</th>
                    <th>กลุ่มหน่วยงาน</th>
                    <th>รายละเอียด</th>
                    <?php if ($userPermissions['edit']): ?>
                    <th width="120" class="text-center">จัดการ</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deptList)): ?>
                <tr>
                    <td colspan="5" class="text-center py-8 text-gray-500">
                        <i class="ph ph-folder-open text-4xl mb-2 text-gray-300"></i>
                        <p>ไม่พบข้อมูลหน่วยงาน</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($deptList as $i => $row): 
                        $imgUrl = !empty($row['Image']) ? $ImgPath."microsite/".$row['Slug']."/".$row['Image'] : "../../assets/images/logo.png";
                        $grp = $row['DeptGroup'] ?? '';
                        $groupLabel = $grp === 'central' ? 'ส่วนกลาง' : ($grp === 'regional' ? 'ส่วนภูมิภาค' : 'กรมทรัพยากรน้ำบาดาล');
                        $groupClass = $grp === 'central' ? 'bg-indigo-100 text-indigo-700 border-indigo-200' : ($grp === 'regional' ? 'bg-teal-100 text-teal-700 border-teal-200' : 'bg-orange-100 text-orange-700 border-orange-200');
                    ?>
                    <tr>
                        <td>
                          <div class="font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($row['ShortNameTH'] ? $row['ShortNameTH'] : $row['NameTH']); ?></div>
                        </td>
                        <td>
                            <span class="inline-flex px-2.5 py-1 rounded-md text-[11px] font-bold border <?php echo $groupClass; ?>">
                                <?php echo $groupLabel; ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                                $descRaw = trim(strip_tags(html_entity_decode($row['DescriptionTH'])));
                                $descSnippet = mb_strlen($descRaw, 'UTF-8') > 100 ? mb_substr($descRaw, 0, 100, 'UTF-8') . '...' : $descRaw;
                            ?>
                            <div class="text-xs text-gray-500 max-w-xs" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?php echo $descSnippet ? htmlspecialchars($descSnippet) : '<span class="text-gray-300 italic">ไม่มีข้อมูล</span>'; ?>
                            </div>
                        </td>
                        <?php if ($userPermissions['edit']): ?>
                        <td class="text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="form.php?id=<?php echo $row['DeptID']; ?>"
                                   class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="แก้ไข">
                                    <i class="ph ph-pencil-simple "></i>
                                </a>
                                <button type="button" onclick="openHistoryModal(<?php echo $row['DeptID']; ?>, 'departments')" class="w-8 h-8 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center hover:bg-gray-200 transition-colors" title="ประวัติการใช้งาน">
                                    <i class="ph ph-clock-counter-clockwise"></i>
                                </button>
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
    <?php if (($result['pagination']['total'] ?? 0) > 0): ?>
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-gray-100 gap-4">
        <div class="text-sm text-gray-500">
            แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo $result['pagination']['total_pages']; ?> (ทั้งหมด <?php echo $result['pagination']['total']; ?> รายการ)
        </div>
        <?php if ($result['pagination']['total_pages'] > 1): 
            $qParams = [];
            if ($searchKeyword) $qParams['keyword'] = $searchKeyword;
            if ($groupFilter) $qParams['deptgroup'] = $groupFilter;
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
document.addEventListener('DOMContentLoaded', function() {
    // AJAX Table Loading
    const tableContainer = document.getElementById('departmentsTableContainer');
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
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(filterForm);
                const searchParams = new URLSearchParams(formData);
                const url = 'index.php?' + searchParams.toString();
                loadPage(url);
            });
            const filterSelects = filterForm.querySelectorAll('select');
            filterSelects.forEach(sel => {
                sel.addEventListener('change', function() {
                    filterForm.dispatchEvent(new Event('submit'));
                });
            });
        }
    }
});
</script>

<?php include_once('../includes/history_modal.php'); ?>
<?php include_once('../includes/footer.php'); ?>

