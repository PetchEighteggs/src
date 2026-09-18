<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการข้อมูลสถิติ CSR';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$search = trim((string)mosGetParam($_GET, 'search', ''));
$status = (string)mosGetParam($_GET, 'status', '');
$page = max(1, (int)mosGetParam($_GET, 'page', 1));
$result = ['data' => [], 'pagination' => ['total' => 0, 'total_pages' => 0]];

// ดึงข้อมูล Config ส่วนหัว
$config = [];
try {
    $stmt = $DB->prepare("SELECT * FROM csr_config LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$config) {
        $config = ['Eyebrow_TH' => '', 'Heading_TH' => '', 'Summary_TH' => ''];
    }
} catch (PDOException $e) {
    error_log('csr_stats config error: ' . $e->getMessage());
}

$sql = "SELECT c.* FROM csr_stats c WHERE c.DelData = 'N'";
$params = [];
if ($search !== '') {
    $sql .= ' AND (c.Title_TH LIKE :search OR c.ValueText LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

try {
    $listSql = $sql;
    if ($status === 'active') {
        $listSql .= " AND c.IsActive = 'Y'";
    } elseif ($status === 'inactive') {
        $listSql .= " AND c.IsActive = 'N'";
    }
    
    // Check if paginateWithCount exists, if not fallback to manual pagination
    if (function_exists('paginateWithCount')) {
        $result = paginateWithCount($DB, $listSql, $params, $page, 15, 'c.OrderNo ASC, c.StatID ASC');
    } else {
        // Fallback in case function is missing
        $stmt = $DB->prepare($listSql . " ORDER BY c.OrderNo ASC, c.StatID ASC");
        $stmt->execute($params);
        $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result['pagination'] = ['total' => count($result['data']), 'total_pages' => 1];
    }
} catch (PDOException $e) {
    error_log('csr_stats index: ' . $e->getMessage());
}

function csrStatsQuery(array $overrides = [])
{
    $query = array_merge([
        'search' => $_GET['search'] ?? '',
        'status' => $_GET['status'] ?? '',
        'page' => $_GET['page'] ?? 1,
    ], $overrides);
    return http_build_query(array_filter($query, function ($value) {
        return $value !== '' && $value !== null;
    }));
}
?>

<?php if (!empty($_GET['message'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    AdminDialog.fire({
        icon: <?php echo json_encode(($_GET['result'] ?? '') === 'success' ? 'success' : 'error'); ?>,
        text: <?php echo json_encode((string)$_GET['message'], JSON_UNESCAPED_UNICODE); ?>,
        timer: 2200,
        showConfirmButton: false
    });
});
</script>
<?php endif; ?>

<div class="glass-panel p-6 mb-8">
    <div class="flex items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold text-gray-800">ตั้งค่าข้อความส่วนหัว</h2>
        </div>
        <?php if (!empty($userPermissions['edit'])): ?>
        <button type="button" id="btnEditConfig" onclick="toggleConfigEdit()" class="btn-primary px-4 py-2"><i class="ph ph-pencil-simple mr-2"></i> แก้ไข</button>
        <?php endif; ?>
    </div>
    
    <!-- View Mode -->
    <div id="configViewMode" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="flex flex-col">
                <label class="block text-sm font-semibold text-gray-700 mb-1">ข้อความเล็กเหนือหัวข้อ (<?php echo $Lang1;?>)</label>
                <div class="text-gray-900 bg-gray-50 px-4 py-3 rounded-lg border border-gray-100 min-h-[42px] flex-1"><?php echo nl2br(htmlspecialchars($config['Eyebrow_TH'] ?? 'ไม่ระบุ')); ?></div>
            </div>
            <div class="flex flex-col">
                <label class="block text-sm font-semibold text-gray-700 mb-1">ข้อความเล็กเหนือหัวข้อ (<?php echo $Lang2;?>)</label>
                <div class="text-gray-900 bg-gray-50 px-4 py-3 rounded-lg border border-gray-100 min-h-[42px] flex-1"><?php echo nl2br(htmlspecialchars($config['Eyebrow_EN'] ?? 'ไม่ระบุ')); ?></div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="flex flex-col">
                <label class="block text-sm font-semibold text-gray-700 mb-1">หัวข้อหลัก (<?php echo $Lang1;?>)</label>
                <div class="text-gray-900 bg-gray-50 px-4 py-3 rounded-lg border border-gray-100 min-h-[42px] flex-1"><?php echo nl2br(htmlspecialchars($config['Heading_TH'] ?? 'ไม่ระบุ')); ?></div>
            </div>
            <div class="flex flex-col">
                <label class="block text-sm font-semibold text-gray-700 mb-1">หัวข้อหลัก (<?php echo $Lang2;?>)</label>
                <div class="text-gray-900 bg-gray-50 px-4 py-3 rounded-lg border border-gray-100 min-h-[42px] flex-1"><?php echo nl2br(htmlspecialchars($config['Heading_EN'] ?? 'ไม่ระบุ')); ?></div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="flex flex-col">
                <label class="block text-sm font-semibold text-gray-700 mb-1">คำอธิบายเพิ่มเติม (<?php echo $Lang1;?>)</label>
                <div class="text-gray-900 bg-gray-50 px-4 py-3 rounded-lg border border-gray-100 min-h-[42px] flex-1"><?php echo nl2br(htmlspecialchars($config['Summary_TH'] ?? 'ไม่ระบุ')); ?></div>
            </div>
            <div class="flex flex-col">
                <label class="block text-sm font-semibold text-gray-700 mb-1">คำอธิบายเพิ่มเติม (<?php echo $Lang2;?>)</label>
                <div class="text-gray-900 bg-gray-50 px-4 py-3 rounded-lg border border-gray-100 min-h-[42px] flex-1"><?php echo nl2br(htmlspecialchars($config['Summary_EN'] ?? 'ไม่ระบุ')); ?></div>
            </div>
        </div>
    </div>
    
    <form action="action.php" method="post" id="configForm" onsubmit="showLoading()" class="hidden">
        <?php echo bofSecurityCsrfField(); ?>
        <input type="hidden" name="action" value="update_config">
        <div class="space-y-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">ข้อความเล็กเหนือหัวข้อ (<?php echo $Lang1;?>)</label>
                    <input type="text" name="Eyebrow_TH" value="<?php echo htmlspecialchars($config['Eyebrow_TH'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">ข้อความเล็กเหนือหัวข้อ (<?php echo $Lang2;?>)</label>
                    <input type="text" name="Eyebrow_EN" value="<?php echo htmlspecialchars($config['Eyebrow_EN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">หัวข้อหลัก (<?php echo $Lang1;?>)</label>
                    <textarea name="Heading_TH" rows="3" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary flex-1"><?php echo htmlspecialchars($config['Heading_TH'] ?? ''); ?></textarea>
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">หัวข้อหลัก (<?php echo $Lang2;?>)</label>
                    <textarea name="Heading_EN" rows="3" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary flex-1"><?php echo htmlspecialchars($config['Heading_EN'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">คำอธิบายเพิ่มเติม (<?php echo $Lang1;?>)</label>
                    <textarea name="Summary_TH" rows="2" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary flex-1"><?php echo htmlspecialchars($config['Summary_TH'] ?? ''); ?></textarea>
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">คำอธิบายเพิ่มเติม (<?php echo $Lang2;?>)</label>
                    <textarea name="Summary_EN" rows="2" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary flex-1"><?php echo htmlspecialchars($config['Summary_EN'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <?php if (!empty($userPermissions['edit'])): ?>
        <div class="flex justify-end pt-4 border-t border-gray-100 gap-3">
            <button type="button" onclick="toggleConfigEdit()" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium">ยกเลิก</button>
            <button type="submit" class="btn-primary px-6"><i class="ph ph-floppy-disk mr-2"></i> บันทึก</button>
        </div>
        <?php endif; ?>
    </form>
</div>
<div class="glass-panel p-6">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2"><i class="ph ph-chart-bar text-primary"></i> จัดการรายการสถิติ</h2>
        <div class="flex flex-wrap gap-3">
            <?php if (!empty($userPermissions['edit'])): ?>
            <a href="sort.php" class="px-4 py-2 bg-gray-50 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-100 font-medium flex items-center gap-2"><i class="ph ph-arrows-down-up"></i> จัดเรียงลำดับ</a>
            <?php endif; ?>
            <?php if (!empty($userPermissions['add'])): ?>
            <a href="form.php" class="btn-primary"><i class="ph ph-plus-circle"></i> เพิ่มสถิติ</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-100">
        <table class="premium-table">
            <thead><tr>
				<th>รายการสถิติ</th>
                <th>ตัวเลขหลัก</th>
                <th>ตัวเลขรอง</th>
                <th>หน่วย</th>
				<th width="12%">สถานะ</th>
                <?php if (!empty($userPermissions['edit']) || !empty($userPermissions['delete'])): ?>
				<th width="12%" class="text-center">จัดการ</th>
				<?php endif; ?>
            </tr></thead>
            <tbody>
            <?php if (!empty($result['data'])): foreach ($result['data'] as $row): ?>
                <tr>
                    <td>
                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars((string)$row['Title_TH']); ?></div>
                    </td>
                    <td><span class="text-lg font-bold text-primary"><?php echo htmlspecialchars((string)$row['ValueText']); ?></span></td>
                    <td><span class="text-gray-500"><?php echo htmlspecialchars((string)$row['SubValueText']); ?></span></td>
                    <td><?php echo htmlspecialchars((string)$row['Unit_TH']); ?></td>
                    <td><?php if ($row['IsActive'] === 'Y'): ?><span class="inline-flex px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold border border-green-200">เปิดใช้งาน</span><?php else: ?><span class="inline-flex px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold border border-gray-200">ปิดใช้งาน</span><?php endif; ?></td>
                    <?php if (!empty($userPermissions['edit']) || !empty($userPermissions['delete'])): ?><td><div class="flex justify-center gap-2">
                        <?php if (!empty($userPermissions['edit'])): ?><a href="form.php?StatID=<?php echo (int)$row['StatID']; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100" title="แก้ไข"><i class="ph ph-pencil-simple"></i></a><?php endif; ?>
                        <button type="button" onclick="openHistoryModal(<?php echo (int)$row['StatID']; ?>, 'csr_stats')" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-200" title="ประวัติการใช้งาน"><i class="ph ph-clock-counter-clockwise"></i></button>
                        <?php if (!empty($userPermissions['delete'])): ?><button type="button" onclick="removeStat(<?php echo (int)$row['StatID']; ?>)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100" title="ลบ"><i class="ph ph-trash"></i></button><?php endif; ?>
                    </div></td><?php endif; ?>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="<?php echo (!empty($userPermissions['edit']) || !empty($userPermissions['delete'])) ? 6 : 5; ?>" class="text-center py-12 text-gray-400"><i class="ph ph-chart-bar text-4xl text-gray-300"></i><p class="mt-2">ไม่พบข้อมูลสถิติ</p></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<form action="action.php" method="post" id="deleteStatForm" class="hidden">
    <?php echo bofSecurityCsrfField(); ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="StatID" id="deleteStatId" value="">
</form>

<script>
function toggleConfigEdit() {
    const viewMode = document.getElementById('configViewMode');
    const formMode = document.getElementById('configForm');
    const btnEdit = document.getElementById('btnEditConfig');
    
    if (formMode.classList.contains('hidden')) {
        viewMode.classList.add('hidden');
        formMode.classList.remove('hidden');
        if (btnEdit) btnEdit.classList.add('hidden');
    } else {
        viewMode.classList.remove('hidden');
        formMode.classList.add('hidden');
        if (btnEdit) btnEdit.classList.remove('hidden');
    }
}

function removeStat(id) {
    AdminDialog.fire({title:'ยืนยันการลบ?', text:'รายการจะถูกซ่อนด้วย Soft delete และไม่ถูกลบออกจากฐานข้อมูล', icon:'warning', showCancelButton:true, confirmButtonColor:'#dc2626', confirmButtonText:'ยืนยันการลบ', cancelButtonText:'ยกเลิก'}).then(result => {
        if (result.isConfirmed) {
            document.getElementById('deleteStatId').value = id;
            document.getElementById('deleteStatForm').submit();
        }
    });
}
</script>

<?php include_once('../includes/history_modal.php'); ?>
<?php include_once('../includes/footer.php'); ?>
