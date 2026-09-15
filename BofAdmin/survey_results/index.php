<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = 'จัดการข้อมูลผลแบบสอบถาม';
include_once('../includes/header.php');

$searchKeyword = $_GET['keyword'] ?? '';
$searchStatus = $_GET['status'] ?? '';

// Query Forms
$formList = [];
$total = 0;
$summary = ['total' => 0, 'active_count' => 0, 'inactive_count' => 0];

try {
    if (isset($DB)) {
        $summarySql = "SELECT COUNT(FormID) as total,
				COUNT(CASE WHEN IsActive = 'Y' THEN FormID END) as active_count
				FROM survey_form";
        $stmtSum = $DB->prepare($summarySql);
        $stmtSum->execute();
        $sumRes = $stmtSum->fetch(PDO::FETCH_ASSOC);
        $summary['total'] = $sumRes['total'] ?? 0;
        $summary['active_count'] = $sumRes['active_count'] ?? 0;
        $summary['inactive_count'] = $summary['total'] - $summary['active_count'];

        $sqlBase = "SELECT * FROM survey_form WHERE 1=1";
        $params = [];
        if (!empty($searchKeyword)) {
            $sqlBase .= " AND (FormName LIKE :kw1 OR Description LIKE :kw2)";
            $params[':kw1'] = $params[':kw2'] = "%$searchKeyword%";
        }
        
        if ($searchStatus !== '') {
            if ($searchStatus === 'inactive') {
                $sqlBase .= " AND IsActive = 'N' ";
            } elseif ($searchStatus === 'active') {
                $sqlBase .= " AND IsActive = 'Y' ";
            }
        }
        
        $page   = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sqlBase, $params, $page, 25, 'FormID DESC');
        $formList = $result['data'];
        $total = $result['pagination']['total'] ?? 0;
        
    }
} catch (PDOException $e) {}

if (defined('IS_AJAX')) {
    ob_end_clean();
}
?>

<?php if (!defined('IS_AJAX')): ?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div id="cardTotal" onclick="filterStatus('')" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">แบบฟอร์มทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-clipboard-text text-3xl"></i>
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
            <i class="ph ph-chart-bar text-primary"></i> จัดการข้อมูลผลแบบสอบถาม
        </h2>
    </div>

    <form id="filterForm" method="GET" action="index.php" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 flex flex-col md:flex-row gap-4">
        <input type="hidden" name="status" id="statusFilterInput" value="<?php echo htmlspecialchars($searchStatus); ?>">
        <div class="flex-1">
            <label for="searchInput" class="block text-sm font-medium text-gray-700 mb-1">ค้นหาแบบสอบถาม</label>
            <div class="relative">
                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="searchInput" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ชื่อแบบสอบถาม..."
                    class="w-full h-[42px] pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
            </div>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6">
              ค้นหา
            </button>
            <?php if ($searchKeyword !== '' || $searchStatus !== ''): ?>
                <a href="index.php" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-[42px]">
                    ล้างค่า
                </a>
            <?php endif; ?>
        </div>
    </form>

    <div id="dataContainer">
<?php endif; ?>

        <!-- Table -->
        <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm bg-white">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-medium">
                    <tr>
											<th class="px-6 py-4">ชื่อฟอร์ม</th>
											<th class="px-6 py-4">จำนวนคนตอบ</th>
											<th class="px-6 py-4">วันที่สร้าง</th>
											<th class="px-6 py-4">สถานะ</th>
											<th class="px-6 py-4 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (count($formList) > 0): ?>
                        <?php foreach ($formList as $row): 
                            // Get Submission Count
                            $subCount = 0;
                            try {
                                $stmtC = $DB->prepare("SELECT COUNT(*) FROM survey_submission WHERE FormID = ?");
                                $stmtC->execute([$row['FormID']]);
                                $subCount = $stmtC->fetchColumn();
                            } catch (PDOException $e) {}
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center flex-shrink-0">
                                            <i class="ph ph-clipboard-text text-xl text-indigo-600"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-800 line-clamp-1"><?php echo htmlspecialchars($row['FormName']); ?></div>
                                            <?php if($row['Description']): ?>
                                                <div class="text-xs text-gray-500 mt-0.5 line-clamp-1 max-w-md"><?php echo htmlspecialchars($row['Description']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                        <i class="ph ph-users"></i> <?php echo number_format($subCount); ?> ครั้ง
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500">
                                    <?php echo date('d/m/Y', strtotime($row['CreateDate'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium <?php echo $row['IsActive'] === 'Y' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'; ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?php echo $row['IsActive'] === 'Y' ? 'bg-green-500' : 'bg-gray-400'; ?>"></span>
                                        <?php echo $row['IsActive'] === 'Y' ? 'เปิดใช้งาน' : 'ปิดใช้งาน'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-center items-center gap-2">
                                        <a href="view.php?form_id=<?php echo $row['FormID']; ?>" 
                                           class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-200 text-gray-600 hover:bg-primary hover:text-white hover:border-primary flex items-center justify-center transition-all tooltip"
                                           data-tip="ดูผลลัพธ์">
                                            <i class="ph ph-chart-bar text-lg"></i>
                                        </a>
                                        <a href="export_excel.php?form_id=<?php echo $row['FormID']; ?>" target="_blank"
                                           class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-200 text-green-600 hover:bg-green-600 hover:text-white hover:border-green-600 flex items-center justify-center transition-all tooltip"
                                           data-tip="ดาวน์โหลด Excel">
                                            <i class="ph ph-file-xls text-lg"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                    <i class="ph ph-magnifying-glass text-2xl text-gray-400"></i>
                                </div>
                                <h3 class="text-base font-medium text-gray-900 mb-1">ไม่พบข้อมูล</h3>
                                <p class="text-sm text-gray-500">ไม่พบข้อมูลแบบสอบถามในระบบ</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total > 0 && isset($result['pagination'])): ?>
            <div class="mt-6">
                <?php echo $result['pagination']['html']; ?>
            </div>
        <?php endif; ?>

<?php if (!defined('IS_AJAX')): ?>
    </div>
</div>

<script>
    const filterForm = document.getElementById('filterForm');
    filterForm.addEventListener('submit', function(event) {
        event.preventDefault();
        loadData(1);
    });

    function filterStatus(status) {
        document.getElementById('statusFilterInput').value = status;
        loadData(1);
    }

    function loadData(page = 1) {
        const keyword = document.getElementById('searchInput').value;
        const status = document.getElementById('statusFilterInput').value;
        const container = document.getElementById('dataContainer');
        
        container.style.opacity = '0.5';
        
        fetch(`index.php?ajax=1&page=${page}&keyword=${encodeURIComponent(keyword)}&status=${status}`)
            .then(res => res.text())
            .then(html => {
                container.innerHTML = html;
                container.style.opacity = '1';
                // Update URL without reload
                const newUrl = new URL(window.location);
                newUrl.searchParams.set('page', page);
                if(keyword) newUrl.searchParams.set('keyword', keyword);
                else newUrl.searchParams.delete('keyword');
                if(status) newUrl.searchParams.set('status', status);
                else newUrl.searchParams.delete('status');
                window.history.pushState({}, '', newUrl);
            });
    }
</script>

<?php include_once('../includes/footer.php'); ?>
<?php endif; ?>
