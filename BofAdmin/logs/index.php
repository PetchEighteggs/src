<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = "ประวัติการใช้งาน";
include_once('../includes/header.php');

// Retrieve all filters
$search = mosGetParam($_FORM, 'search', '');
$filterStaff = mosGetParam($_FORM, 'staff_id', '');
$filterGroup = mosGetParam($_FORM, 'section_group_id', '');
$filterSection = mosGetParam($_FORM, 'section_id', '');
$startDate = mosGetParam($_FORM, 'start_date', '');
$endDate = mosGetParam($_FORM, 'end_date', '');

// Fetch option data for filters
$staffs = [];
try {
    $staffStmt = $DB->prepare("SELECT StaffID, Username, Name FROM staff ORDER BY Name ASC");
    $staffStmt->execute();
    $staffs = $staffStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$secGroups = [];
try {
    $groupStmt = $DB->prepare("SELECT SectionGroupID, SectionGroupName FROM bof_section_group WHERE SectionGroupShow = 'Y' ORDER BY SectionGroupOrder ASC");
    $groupStmt->execute();
    $secGroups = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$sectionsList = [];
try {
    $sectionStmt = $DB->prepare("SELECT SectionID, SectionGroupID, SectionName FROM bof_section WHERE SectionShow = 'Y' ORDER BY SectionOrder ASC");
    $sectionStmt->execute();
    $sectionsList = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Construct dynamic SQL query
$sql = "SELECT l.*, s.Name as StaffName, s.Username as StaffUsername 
FROM bof_logs l 
LEFT JOIN staff s ON (l.StaffID = s.StaffID OR l.StaffID = s.Username) 
WHERE 1=1";
$params = [];

if ($search != '') {
    $sql .= " AND (l.LogAction LIKE :search OR l.CreateIP LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($filterStaff != '') {
    $sql .= " AND l.StaffID = :staff_id";
    $params[':staff_id'] = $filterStaff;
}
if ($filterGroup != '') {
    $sql .= " AND l.SectionGroupID = :group_id";
    $params[':group_id'] = $filterGroup;
}
if ($filterSection != '') {
    $sql .= " AND l.SectionID = :section_id";
    $params[':section_id'] = $filterSection;
}
$dbStartDate = '';
if ($startDate != '') {
    $parts = explode('/', $startDate);
    if (count($parts) === 3) {
        $dbStartDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    } else {
        $dbStartDate = $startDate;
    }
}

$dbEndDate = '';
if ($endDate != '') {
    $parts = explode('/', $endDate);
    if (count($parts) === 3) {
        $dbEndDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    } else {
        $dbEndDate = $endDate;
    }
}

if ($dbStartDate != '') {
    $sql .= " AND l.CreateDate >= :start_date";
    $params[':start_date'] = $dbStartDate . ' 00:00:00';
}
if ($dbEndDate != '') {
    $sql .= " AND l.CreateDate <= :end_date";
    $params[':end_date'] = $dbEndDate . ' 23:59:59';
}

$page = mosGetParam($_FORM, 'page', 1);
$result = paginateWithCount($DB, $sql, $params, $page, 50, 'LogID DESC');

// Helper to keep query parameters in pagination
$queryParams = [
    'search' => $search,
    'staff_id' => $filterStaff,
    'section_group_id' => $filterGroup,
    'section_id' => $filterSection,
    'start_date' => $startDate,
    'end_date' => $endDate
];
$queryParamsFiltered = array_filter($queryParams, function($val) {
    return $val !== '';
});
$queryString = http_build_query($queryParamsFiltered);
$queryStringPrefix = $queryString !== '' ? '&' . $queryString : '';

$isFiltered = ($search !== '' || $filterStaff !== '' || $filterGroup !== '' || $filterSection !== '' || $startDate !== '' || $endDate !== '');
$isAdvancedActive = ($filterGroup !== '' || $filterSection !== '' || $startDate !== '' || $endDate !== '');
?>

<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<div class="glass-panel">
    <div class="flex justify-between items-center mb-6">
			<h2 class="text-xl font-semibold text-gray-800">
			<i class="ph ph-list-magnifying-glass"></i> ประวัติการใช้งาน</h2>
    </div>

    <!-- Search Box & Filters -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
			<form id="filterForm" method="GET" action="index.php" class="space-y-4">
				<!-- Row 1: Basic Filters -->
				<div class="flex flex-col md:flex-row gap-3 items-center">
					<!-- Keyword Search -->
					<div class="flex-1 relative w-full">
						<i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
						<input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
							placeholder="ค้นหาการกระทำ หรือ IP Address..." 
							class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm">
					</div>
					
					<!-- Staff dropdown -->
					<div class="w-full md:w-64">
						<select name="staff_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm bg-white cursor-pointer">
							<option value="">-- ผู้ใช้งานทั้งหมด --</option>
							<?php foreach($staffs as $s): ?>
								<option value="<?php echo htmlspecialchars($s['StaffID']); ?>" <?php echo $filterStaff === $s['StaffID'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($s['Name'] . ' (' . $s['Username'] . ')'); ?>
								</option>
							<?php endforeach; ?>
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
					<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
						<!-- Section Group -->
						<div class="space-y-1">
							<label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">กลุ่มเมนู</label>
							<select name="section_group_id" id="groupSelect" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm bg-white cursor-pointer">
								<option value="">-- ทุกกลุ่มเมนู --</option>
								<?php foreach($secGroups as $sg): ?>
									<option value="<?php echo htmlspecialchars($sg['SectionGroupID']); ?>" <?php echo $filterGroup === $sg['SectionGroupID'] ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($sg['SectionGroupName']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<!-- Section (Submenu) -->
						<div class="space-y-1">
							<label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">เมนูย่อย</label>
							<select name="section_id" id="sectionSelect" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm bg-white cursor-pointer">
								<option value="">-- ทุกเมนูย่อย --</option>
								<?php foreach($sectionsList as $sec): ?>
									<option value="<?php echo htmlspecialchars($sec['SectionID']); ?>" 
											data-group="<?php echo htmlspecialchars($sec['SectionGroupID']); ?>" 
											<?php echo $filterSection === $sec['SectionID'] ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($sec['SectionName']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						
						<!-- Start Date -->
						<div class="space-y-1">
							<label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">ตั้งแต่วันที่</label>
							<div class="relative">
								<input type="text" name="start_date" id="start_date" value="<?php echo htmlspecialchars($startDate); ?>" 
											 class="w-full pl-3 pr-10 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
								<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
									<i class="ph ph-calendar text-lg"></i>
								</div>
							</div>
						</div>
						
						<!-- End Date -->
						<div class="space-y-1">
							<label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">ถึงวันที่</label>
							<div class="relative">
								<input type="text" name="end_date" id="end_date" value="<?php echo htmlspecialchars($endDate); ?>" 
											 class="w-full pl-3 pr-10 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary h-[42px] text-sm bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
								<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
									<i class="ph ph-calendar text-lg"></i>
								</div>
							</div>
						</div>
					</div>
				</div>
			</form>
    </div>

    <?php if (defined('IS_AJAX')) { ob_clean(); } ?>
    <div id="logsTableContainer" class="space-y-6">
        <div class="overflow-x-auto">
        <table class="premium-table text-sm">
            <thead>
                <tr>
									<th width="150">วันเวลา</th>
									<th>ผู้ใช้งาน</th>
									<th>การกระทำ</th>
									<th width="150">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($result['data']) > 0): ?>
                    <?php foreach ($result['data'] as $row): ?>
                        <tr>
													<td class="text-gray-500 whitespace-nowrap">
														<i class="ph ph-clock mr-1"></i> <?php echo date('d/m/Y H:i:s', strtotime($row['CreateDate'])); ?>
													</td>
													<td>
														<span class="font-semibold text-gray-800 bg-gray-100 px-2 py-1 rounded-md">
															<?php 
																if (!empty($row['StaffName'])) {
																	echo htmlspecialchars($row['StaffName']);
																} elseif (!empty($row['StaffUsername'])) {
																	echo htmlspecialchars($row['StaffUsername']);
																} else {
																	echo htmlspecialchars($row['StaffID']);
																}
															?>
														</span>
													</td>
													<td class="text-gray-700">
														<?php 
															$cleanAction = preg_replace('/\s*\(?ID:\s*\d+\)?/i', '', $row['LogAction']);
															echo htmlspecialchars($cleanAction); 
														?>
													</td>
													<td class="text-gray-500">
															<?php echo htmlspecialchars($row['CreateIP']); ?>
													</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
									<tr>
										<td colspan="4" class="text-center py-8 text-gray-500">
											<i class="ph ph-clock-counter-clockwise text-4xl mb-2 text-gray-300"></i>
											<p>ไม่มีประวัติการใช้งาน</p>
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
    const tableContainer = document.getElementById('logsTableContainer');
    if (!tableContainer) return;
    
    function updateClearButtonVisibility() {
        const search = document.querySelector('input[name="search"]')?.value.trim() || '';
        const staffId = document.querySelector('select[name="staff_id"]')?.value || '';
        const sectionGroupId = document.querySelector('select[name="section_group_id"]')?.value || '';
        const sectionId = document.querySelector('select[name="section_id"]')?.value || '';
        const startDate = document.querySelector('input[name="start_date"]')?.value.trim() || '';
        const endDate = document.querySelector('input[name="end_date"]')?.value.trim() || '';

        const isFiltered = (search !== '' || staffId !== '' || sectionGroupId !== '' || sectionId !== '' || startDate !== '' || endDate !== '');
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
            if (link.href.includes('logs/index.php')) {
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

    // Initialize Flatpickr with Thai locale
    if (typeof flatpickr !== 'undefined') {
        flatpickr("#start_date", {
            dateFormat: "d/m/Y",
            locale: "th",
            allowInput: true
        });
        flatpickr("#end_date", {
            dateFormat: "d/m/Y",
            locale: "th",
            allowInput: true
        });
    }

    // Cascading Dropdown for Section Group and Submenus
    const groupSelect = document.getElementById('groupSelect');
    const sectionSelect = document.getElementById('sectionSelect');
    if (groupSelect && sectionSelect) {
        const allOptions = Array.from(sectionSelect.options).slice(1);
        
        function filterSections() {
            const selectedGroupId = groupSelect.value;
            
            const selectedOption = sectionSelect.options[sectionSelect.selectedIndex];
            if (selectedOption && selectedOption.value !== '') {
                const optGroup = selectedOption.getAttribute('data-group');
                if (selectedGroupId !== '' && optGroup !== selectedGroupId) {
                    sectionSelect.value = '';
                }
            }
            
            sectionSelect.innerHTML = '<option value="">-- ทุกเมนูย่อย --</option>';
            allOptions.forEach(opt => {
                const optGroup = opt.getAttribute('data-group');
                if (selectedGroupId === '' || optGroup === selectedGroupId) {
                    sectionSelect.appendChild(opt.cloneNode(true));
                }
            });
        }
        
        groupSelect.addEventListener('change', filterSections);
        
        if (groupSelect.value !== '') {
            filterSections();
            const prevSectionVal = "<?php echo htmlspecialchars($filterSection); ?>";
            if (prevSectionVal !== '') {
                sectionSelect.value = prevSectionVal;
            }
        }
    }
});
</script>
<?php include_once('../includes/footer.php'); ?>