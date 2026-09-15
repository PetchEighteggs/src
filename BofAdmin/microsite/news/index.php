<?php
try {
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการข่าวประชาสัมพันธ์';
include_once('../../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;

if (empty($deptID)) {
  echo "<script>alert('กรุณาเลือกหน่วยงานก่อนจัดกาข่าวประชาสัมพันธ์'); window.location.href = '../index.php';</script>";
  exit;
}

// Check Permissions
$userGroupID = $_SESSION['GroupID'] ?? '';
$canView = false;
$canAdd = false;
$canEdit = false;
$canDelete = false;

if ($userGroupID === 'A') {
	$canView = true;
	$canAdd = true;
	$canEdit = true;
	$canDelete = true;
} else if ($userGroupID === 'S') {
	$staffID = $_SESSION['StaffID'] ?? 0;
	require_once('../../includes/microsite_permission.php');
	$canView = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'news', 'CanView');
	$canAdd = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'news', 'CanAdd');
	$canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'news', 'CanEdit');
	$canDelete = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'news', 'CanDelete');
}

if (!$canView) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (View Permission Required)'); window.location.href = '../index.php';</script>";
    exit;
}

$searchKeyword = $_GET['keyword'] ?? '';
$searchStatus = $_GET['status'] ?? '';
$searchGroup = $_GET['group'] ?? '';

// Fetch News Data
$newsList = [];
$groupList = [];
try {
    if (isset($DB)) {
        $sqlBase = "SELECT n.NewsID, n.Title_TH, n.Title_EN, n.Image, n.IsActive, n.CreateDate, 
				g.GroupNameTH as Category_TH 
				FROM microsite_news n
				LEFT JOIN microsite_news_groups g ON n.GroupID = g.GroupID AND COALESCE(g.DelData, 'N') <> 'Y'
				WHERE n.DeptID = :deptId AND (n.DelData IS NULL OR n.DelData = 'N')";
        $params = [':deptId' => $deptID];

        // Fetch groups
        $stmtG = $DB->prepare("SELECT GroupID, GroupNameTH FROM microsite_news_groups WHERE DeptID = ? AND COALESCE(DelData, 'N') <> 'Y' ORDER BY GroupNameTH ASC");
        $stmtG->execute([$deptID]);
        $groupList = $stmtG->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($searchKeyword)) {
					$sqlBase .= " AND (n.Title_TH LIKE :keyword1 OR n.Title_EN LIKE :keyword2)";
					$params[':keyword1'] = "%$searchKeyword%";
					$params[':keyword2'] = "%$searchKeyword%";
        }
        
        if (!empty($searchGroup)) {
            $sqlBase .= " AND n.GroupID = :group";
            $params[':group'] = $searchGroup;
        }

        // Calculate summary
        $stmtSum = $DB->prepare("SELECT COUNT(*) as total, 
				SUM(CASE WHEN IsActive = 'Y' THEN 1 ELSE 0 END) as active_count,
				SUM(CASE WHEN IsActive != 'Y' THEN 1 ELSE 0 END) as inactive_count 
				FROM ($sqlBase) as t");
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        // Apply status filter
        $sql = $sqlBase;
        if ($searchStatus === 'active') {
            $sql .= " AND n.IsActive = 'Y'";
        } else if ($searchStatus === 'inactive') {
            $sql .= " AND n.IsActive = 'N'";
        }
        
        $page = $_GET['page'] ?? 1;
        $result = paginateWithCount($DB, $sql, $params, $page, 25, 'CreateDate DESC, NewsID DESC');
        $newsList = $result['data'];
    }
} catch (PDOException $e) {
    echo "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-4 text-sm'>";
    echo "<b>PDOException:</b> " . $e->getMessage();
    echo "</div>";
}
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="window.location.href='index.php'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
          <div class="text-sm text-gray-500 font-medium mb-1">ข่าวหน่วยงานทั้งหมด</div>
          <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
          <i class="ph ph-newspaper text-3xl"></i>
        </div>
    </div>
    
    <div onclick="window.location.href='index.php?status=active'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'active' ? 'border-green-500 ring-2 ring-green-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-green-600 font-medium mb-1">เปิดใช้งาน (Active)</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['active_count'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-500 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-check-circle text-3xl"></i>
        </div>
    </div>
    
    <div onclick="window.location.href='index.php?status=inactive'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === 'inactive' ? 'border-amber-500 ring-2 ring-amber-100 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
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
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4 border-b border-gray-100 pb-4">
        <div>
					<h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
						<i class="ph ph-newspaper text-primary"></i> ข่าวประชาสัมพันธ์
					</h2>
          <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName']); ?></span></div>
        </div>
        
        <div class="flex gap-2 items-center">
            <?php if ($canEdit || $canAdd): ?>
            <a href="group.php" class="px-4 py-2 border border-primary/20 text-primary bg-white hover:bg-primary hover:text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
              <i class="ph ph-folder-open"></i> จัดการหมวดหมู่ข่าว
            </a>
            <?php endif; ?>
            <?php if ($canAdd): ?>
            <a href="form.php" class="btn-primary">
              <i class="ph ph-plus-circle"></i> เพิ่มข่าวใหม่
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search Form -->
    <form method="GET" action="index.php" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 flex flex-col sm:flex-row gap-4">
			<input type="hidden" name="status" value="<?php echo htmlspecialchars($searchStatus); ?>">
			<div class="flex-1">
				<label class="block text-sm font-medium text-gray-700 mb-1">ค้นหาข่าว</label>
				<input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ชื่อหัวข้อ..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
			</div>
			<div class="w-full sm:w-64">
				<label class="block text-sm font-medium text-gray-700 mb-1">หมวดหมู่</label>
				<select name="group" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
					<option value="">ทั้งหมด</option>
					<?php foreach ($groupList as $g): ?>
						<option value="<?php echo $g['GroupID']; ?>" <?php echo $searchGroup == $g['GroupID'] ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars($g['GroupNameTH']); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="flex items-end gap-2">
				<button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6">ค้นหา</button>
				<?php if($searchKeyword != '' || $searchGroup != ''): ?>
					<a href="index.php?status=<?php echo urlencode($searchStatus); ?>" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-[42px]">ล้างค่า</a>
				<?php endif; ?>
			</div>
    </form>
    
    <!-- Bulk Action Toolbar -->
    <?php if ($canEdit || $canDelete): ?>
    <div id="bulkActionContainer" class="bg-white p-4 rounded-xl border border-gray-100 items-center gap-4 mb-6 shadow-sm hidden transition-all duration-300">
        <label class="text-sm font-semibold text-gray-700">จัดการรายการที่เลือก:</label>
        <select id="bulkActionSelect" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary">
            <option value="">-- เลือกการจัดการ --</option>
            <?php if ($canEdit): ?>
            <option value="active">เปิดใช้งาน</option>
            <option value="inactive">ปิดใช้งาน</option>
            <?php endif; ?>
            <?php if ($canDelete): ?>
            <option value="delete">ลบข้อมูล</option>
            <?php endif; ?>
        </select>
        <button type="button" onclick="executeBulkAction()" class="btn-primary px-4 py-2 text-sm rounded-lg">นำไปใช้</button>
        <span id="bulkCountText" class="text-sm text-gray-500">(เลือก 0 รายการ)</span>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
					<table class="w-full text-left border-collapse">
						<thead>
							<tr class="bg-gray-50/80 text-gray-655 text-sm border-b border-gray-100">
								<?php if ($canEdit || $canDelete): ?>
								<th class="py-4 px-6 font-semibold w-12 text-center">
									<input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="toggleSelectAll(this)">
								</th>
								<?php endif; ?>
								<th class="py-4 px-6 font-semibold">หัวข้อ</th>
								<th class="py-4 px-6 font-semibold">หมวดหมู่</th>
								<th class="py-4 px-6 font-semibold w-32 text-center">สถานะ</th>
								<th class="py-4 px-6 font-semibold w-32 text-center">วันที่สร้าง</th>
								<th class="py-4 px-6 font-semibold w-40 text-center">จัดการ</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100 bg-white">
							<?php if (empty($newsList)): ?>
							<tr>
								<td colspan="<?php echo ($canEdit || $canDelete) ? '7' : '6'; ?>" class="text-center py-8 text-gray-500">
									<i class="ph ph-folder-open text-4xl mb-2 text-gray-300 block"></i>
									<p>ยังไม่มีข้อมูลข่าวประชาสัมพันธ์</p>
								</td>
							</tr>
							<?php else: ?>
									<?php 
									// Fetch Dept Slug for image path
									$stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID = ?");
									$stmtSlug->execute([$deptID]);
									$deptSlug = $stmtSlug->fetchColumn();
									
									foreach ($newsList as $row): 
									?>
									<tr class="hover:bg-gray-50/50 transition-colors group">
											<?php if ($canEdit || $canDelete): ?>
											<td class="py-4 px-6 text-center">
												<input type="checkbox" name="news_ids[]" value="<?php echo $row['NewsID']; ?>" class="row-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onclick="updateBulkDeleteBtn()">
											</td>
											<?php endif; ?>
											<td class="py-4 px-6 font-semibold text-gray-700">
													<?php echo htmlspecialchars($row['Title_TH']); ?>
											</td>
											<td class="py-4 px-6 text-gray-600">
													<?php echo htmlspecialchars($row['Category_TH'] ?? '-'); ?>
											</td>
											<td class="py-4 px-6 text-center">
													<?php if(($row['IsActive'] ?? 'Y') == 'Y'): ?>
															<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-green-50 text-green-600 border border-green-200">
																	เปิดใช้งาน
															</span>
													<?php else: ?>
															<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200">
																	ปิดใช้งาน
															</span>
													<?php endif; ?>
											</td>
											<td class="py-4 px-6 text-sm text-gray-500 text-center">
													<?php echo date('d/m/Y', strtotime($row['CreateDate'] ?? date('Y-m-d'))); ?>
											</td>
											<td class="py-4 px-6 text-center">
													<div class="flex items-center justify-center gap-1.5">
														<?php if ($canEdit): ?>
														<a href="form.php?id=<?php echo $row['NewsID']; ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors tooltip" title="แก้ไข">
															<i class="ph ph-pencil-simple text-lg"></i>
														</a>
														<?php endif; ?>
														<?php if ($canDelete): ?>
														<button onclick="deleteNews(<?php echo $row['NewsID']; ?>)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center transition-colors tooltip" title="ลบ">
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
    <?php if (isset($result['pagination']) && $result['pagination']['total_pages'] > 1): ?>
    <div class="mt-6 flex justify-between items-center">
        <div class="text-sm text-gray-500">
            แสดงหน้า <?php echo $page; ?> จาก <?php echo $result['pagination']['total_pages']; ?>
        </div>
        <div class="flex gap-2">
            <?php for ($i = 1; $i <= $result['pagination']['total_pages']; $i++): ?>
                <a href="index.php?page=<?php echo $i; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&status=<?php echo urlencode($searchStatus); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == $page ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function deleteNews(id) {
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
        confirmButtonColor: action === 'delete' ? '#d33' : '#005A9C',
        cancelButtonColor: '#aaa',
        confirmButtonText: action === 'delete' ? 'ยืนยันการลบ' : 'ตกลง',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            let ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
            window.location.href = 'action.php?action=' + actionStr + '&ids=' + ids;
        }
    });
}
</script>

<?php include_once('../../includes/footer.php'); ?>
<?php 
} catch (Throwable $t) {
    echo "<h1>Fatal Error Caught:</h1>";
    echo "<pre>" . $t->getMessage() . "\n" . $t->getTraceAsString() . "</pre>";
    echo "Line: " . $t->getLine();
    echo "File: " . $t->getFile();
}
?>
