<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการ Intro';
include_once('../../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>alert('กรุณาเลือกหน่วยงานก่อนจัดการข้อมูล'); window.location.href = '../index.php';</script>";
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
    $canView = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'intro', 'CanView');
    $canAdd = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'intro', 'CanAdd');
    $canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'intro', 'CanEdit');
    $canDelete = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'intro', 'CanDelete');
}

if (!$canView) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (View Permission Required)'); window.location.href = '../index.php';</script>";
    exit;
}

$searchStatus = $_GET['status'] ?? '';
$searchKeyword = trim($_GET['keyword'] ?? '');
$summary = ['total' => 0, 'active_count' => 0, 'inactive_count' => 0];
try {
    $stmtSum = $DB->prepare("SELECT COUNT(*) as total, 
    SUM(CASE WHEN IsActive = 'Y' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN IsActive != 'Y' THEN 1 ELSE 0 END) as inactive_count 
    FROM microsite_intros WHERE DeptID = ? AND COALESCE(DelData, 'N') <> 'Y'");
    $stmtSum->execute([$deptID]);
    $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$items = [];
try {
    $sql = "SELECT * FROM microsite_intros WHERE DeptID = :deptId AND COALESCE(DelData, 'N') <> 'Y'";
    $params = [':deptId' => $deptID];

    if ($searchKeyword !== '') {
        $sql .= " AND (Title_TH LIKE :keyword_th OR Title_EN LIKE :keyword_en)";
        $params[':keyword_th'] = "%{$searchKeyword}%";
        $params[':keyword_en'] = "%{$searchKeyword}%";
    }
    
    if ($searchStatus === 'active') {
        $sql .= " AND IsActive = 'Y'";
    } elseif ($searchStatus === 'inactive') {
        $sql .= " AND IsActive != 'Y'";
    }
    $sql .= " ORDER BY OrderNo ASC, IntroID DESC";
    
    $stmt = $DB->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID = ?");
$stmtSlug->execute([$deptID]);
$deptSlug = $stmtSlug->fetchColumn();
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div onclick="window.location.href='index.php'" class="cursor-pointer bg-white p-6 rounded-2xl shadow-sm border <?php echo $searchStatus === '' ? 'border-primary ring-2 ring-primary/20 scale-[1.02]' : 'border-gray-100 opacity-80 hover:opacity-100 hover:scale-[1.01]'; ?> flex justify-between items-center relative overflow-hidden group transition-all duration-300">
        <div class="relative z-10">
            <div class="text-sm text-gray-500 font-medium mb-1">รายการทั้งหมด</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total'] ?? 0); ?></div>
        </div>
        <div class="relative z-10 w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform duration-300">
            <i class="ph ph-list text-3xl"></i>
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
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-100 pb-4 mb-6 gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-presentation-chart text-primary"></i>
                จัดการ Intro
            </h2>
            <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? ''); ?></span></div>
        </div>
        <?php if ($canAdd): ?>
        <a href="form.php" class="btn-primary px-4 py-2 flex items-center gap-2 text-sm whitespace-nowrap">
            <i class="ph ph-plus-circle text-lg"></i>
            เพิ่ม Intro ใหม่
        </a>
        <?php endif; ?>
    </div>

    <form method="GET" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($searchStatus, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="flex flex-col md:flex-row md:items-end gap-4">
            <div class="flex-1">
                <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ชื่อหัวข้อ..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6"> ค้นหา</button>
                <?php if ($searchKeyword !== ''): ?>
                <a href="index.php?status=<?php echo urlencode($searchStatus); ?>" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-[42px]">ล้างค่า</a>
                <?php endif; ?>
            </div>
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

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 text-gray-655 text-sm border-b border-gray-100">
                        <th class="py-4 px-6 font-semibold w-12 text-center">
                            <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                        </th>
                        <th class="py-4 px-6 font-semibold w-40">รูปภาพ</th>
                        <th class="py-4 px-6 font-semibold">หัวข้อ</th>
                        <th class="py-4 px-6 font-semibold w-32 text-center">สถานะ</th>
                        <th class="py-4 px-6 font-semibold w-32 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="4" class="py-12 text-center text-gray-400">
                            <i class="ph ph-folder-open text-4xl mb-3 block"></i>
                            ยังไม่มีข้อมูล Intro
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($items as $row): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <td class="py-4 px-6 text-center">
                                <input type="checkbox" name="bulk_ids[]" value="<?php echo $row['IntroID']; ?>" class="item-checkbox rounded border-gray-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                            </td>
                            <td class="py-4 px-6">
                                <?php if (!empty($row['Image'])): ?>
                                    <div class="w-24 h-16 rounded-lg overflow-hidden border border-gray-200 bg-gray-50">
                                        <img src="<?php echo $ImgPath; ?>microsite/<?php echo $deptSlug; ?>/<?php echo htmlspecialchars($row['Image']); ?>" class="w-full h-full object-cover">
                                    </div>
                                <?php else: ?>
                                    <div class="w-24 h-16 rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center text-gray-300">
                                        <i class="ph ph-image text-2xl"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6">
                                <div class="font-semibold text-gray-800 mb-1 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($row['Title_TH']); ?></div>
                                <?php if (!empty($row['URL'])): ?>
                                    <a href="<?php echo htmlspecialchars($row['URL']); ?>" target="_blank" class="text-sm text-blue-500 hover:underline"><i class="ph ph-link"></i> ลิงก์แนบ</a>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <?php if ($row['IsActive'] == 'Y'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-green-50 text-green-600 border border-green-200">
                                      เปิดใช้งาน
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200">
                                      ปิดใช้งาน
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if ($canEdit): ?>
                                    <a href="form.php?id=<?php echo $row['IntroID']; ?>" 
                                       class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors tooltip"
                                       title="แก้ไข">
                                        <i class="ph ph-pencil-simple text-lg"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                    <a href="action.php?action=delete&id=<?php echo $row['IntroID']; ?>" 
                                       onclick="confirmDeleteUrl(this.href, 'คุณต้องการลบทิ้งข้อมูลนี้ใช่หรือไม่?'); return false;"
                                       class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center transition-colors tooltip"
                                       title="ลบ">
                                        <i class="ph ph-trash text-lg"></i>
                                    </a>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const bulkActionBar = document.getElementById('bulkActionBar');

    function updateCount() {
        const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
        if (selectedCount) selectedCount.textContent = checkedBoxes.length;
        
        if (bulkActionBar) {
            if (checkedBoxes.length > 0) {
                bulkActionBar.classList.remove('hidden');
            } else {
                bulkActionBar.classList.add('hidden');
            }
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateCount();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!this.checked && selectAll) {
                selectAll.checked = false;
            } else if (document.querySelectorAll('.item-checkbox:checked').length === checkboxes.length && selectAll) {
                selectAll.checked = true;
            }
            updateCount();
        });
    });
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
        confirmButtonText: action === 'bulk_delete' ? 'ยืนยันการลบ' : 'ตกลง',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            let ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
            window.location.href = 'action.php?action=' + action + '&ids=' + ids;
        }
    });
}

function confirmDeleteUrl(url, text = 'คุณต้องการลบทิ้งข้อมูลนี้ใช่หรือไม่?') {
    AdminDialog.fire({
        title: 'ยืนยันการลบ?',
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'ยืนยันการลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}
</script>

<?php include_once('../../includes/footer.php'); ?>
