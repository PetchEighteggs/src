<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดการการ์ดหน้าหลัก';
include_once('../includes/header.php');

$search = trim((string)mosGetParam($_GET, 'search', ''));
$status = (string)mosGetParam($_GET, 'status', '');
$page = max(1, (int)mosGetParam($_GET, 'page', 1));
$result = ['data' => [], 'pagination' => ['total' => 0, 'total_pages' => 0]];
$summary = ['total' => 0, 'active_count' => 0, 'inactive_count' => 0];

$sql = "SELECT c.* FROM home_cards c WHERE c.DelData = 'N'";
$params = [];
if ($search !== '') {
    $sql .= ' AND (c.Slug LIKE :search OR c.Title_TH LIKE :search OR c.Title_EN LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

try {
    $summaryStmt = $DB->prepare("SELECT COUNT(*) AS total,
        SUM(CASE WHEN IsActive='Y' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN IsActive='N' THEN 1 ELSE 0 END) AS inactive_count
        FROM ({$sql}) filtered");
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: $summary;

    $listSql = $sql;
    if ($status === 'active') {
        $listSql .= " AND c.IsActive = 'Y'";
    } elseif ($status === 'inactive') {
        $listSql .= " AND c.IsActive = 'N'";
    }
    $result = paginateWithCount($DB, $listSql, $params, $page, 15, 'c.OrderNo ASC, c.CardID DESC');
} catch (PDOException $e) {
    error_log('home_cards index: ' . $e->getMessage());
}

function homeCardsQuery(array $overrides = [])
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

function homeCardsImageUrl($image, $imageBasePath)
{
    $image = trim((string)$image);
    if ($image === '') {
        return '';
    }
    return preg_match('/^(?:\/|https?:\/\/)/i', $image)
        ? $image
        : $imageBasePath . 'home_cards/' . $image;
}
?>
<link rel="stylesheet" href="../assets/css/bgColorClass.css">

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

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <a href="index.php?<?php echo homeCardsQuery(['status' => '', 'page' => 1]); ?>" class="bg-white p-6 rounded-2xl shadow-sm border <?php echo $status === '' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-100'; ?> flex justify-between items-center transition hover:-translate-y-0.5">
        <div><div class="text-sm text-gray-500 mb-1">รายการทั้งหมด</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format((int)$summary['total']); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center"><i class="ph ph-cards text-3xl"></i></div>
    </a>
    <a href="index.php?<?php echo homeCardsQuery(['status' => 'active', 'page' => 1]); ?>" class="bg-white p-6 rounded-2xl shadow-sm border <?php echo $status === 'active' ? 'border-green-500 ring-2 ring-green-100' : 'border-gray-100'; ?> flex justify-between items-center transition hover:-translate-y-0.5">
        <div><div class="text-sm text-green-600 mb-1">เปิดใช้งาน</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format((int)$summary['active_count']); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-green-50 text-green-600 flex items-center justify-center"><i class="ph ph-check-circle text-3xl"></i></div>
    </a>
    <a href="index.php?<?php echo homeCardsQuery(['status' => 'inactive', 'page' => 1]); ?>" class="bg-white p-6 rounded-2xl shadow-sm border <?php echo $status === 'inactive' ? 'border-amber-500 ring-2 ring-amber-100' : 'border-gray-100'; ?> flex justify-between items-center transition hover:-translate-y-0.5">
        <div><div class="text-sm text-amber-600 mb-1">ปิดใช้งาน</div><div class="text-3xl font-bold text-gray-800"><?php echo number_format((int)$summary['inactive_count']); ?></div></div>
        <div class="w-14 h-14 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center"><i class="ph ph-minus-circle text-3xl"></i></div>
    </a>
</div>

<div class="glass-panel p-6">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2"><i class="ph ph-cards text-primary"></i> จัดการการ์ดหน้าหลัก</h2>
        <div class="flex flex-wrap gap-3">
            <?php if (!empty($userPermissions['edit'])): ?>
            <a href="sort.php" class="px-4 py-2 bg-gray-50 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-100 font-medium flex items-center gap-2"><i class="ph ph-arrows-down-up"></i> จัดเรียงลำดับ</a>
            <?php endif; ?>
            <?php if (!empty($userPermissions['add'])): ?>
            <a href="form.php" class="btn-primary"><i class="ph ph-plus-circle"></i> เพิ่มการ์ดหน้าหลัก</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-gray-50 p-4 rounded-xl mb-6 border border-gray-100">
        <form method="get" action="index.php" class="flex flex-col md:flex-row gap-4">
            <div class="flex-1 relative">
                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ค้นหา" class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
            </div>
           
            <button type="submit" class="btn-primary px-6"><i class="ph ph-magnifying-glass"></i> ค้นหา</button>
            <?php if ($search !== '' || $status !== ''): ?><a href="index.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium text-center">ล้างค่า</a><?php endif; ?>
        </form>
    </div>

    <?php if (!empty($userPermissions['edit']) || !empty($userPermissions['delete'])): ?>
    <form id="bulkForm" action="action.php" method="post" class="hidden items-center flex-wrap gap-3 mb-5 bg-gray-50 p-4 rounded-xl border border-gray-200">
        <input type="hidden" name="ids" id="bulkIds">
        <select name="action" id="bulkAction" class="border border-gray-300 rounded-lg px-4 py-2 bg-white">
            <option value="">-- เลือกการจัดการ --</option>
            <?php if (!empty($userPermissions['edit'])): ?><option value="bulk_active">เปิดใช้งาน</option><option value="bulk_inactive">ปิดใช้งาน</option><?php endif; ?>
            <?php if (!empty($userPermissions['delete'])): ?><option value="bulk_delete">ลบข้อมูล</option><?php endif; ?>
        </select>
        <button type="button" onclick="submitBulk()" class="btn-primary px-5">นำไปใช้</button>
        <span id="selectedCount" class="text-sm text-gray-500"></span>
    </form>
    <?php endif; ?>

    <div class="overflow-x-auto rounded-xl border border-gray-100">
        <table class="premium-table">
            <thead><tr>
                <?php if (!empty($userPermissions['edit']) || !empty($userPermissions['delete'])): ?>
									<th width="5%" class="text-center">
									<input id="selectAll" type="checkbox" class="w-4 h-4" onchange="toggleAll(this)">
								</th><?php endif; ?>
				<th>ตัวอย่างการแสดงผล</th>
				<th width="12%">สถานะ</th>
                <?php if (!empty($userPermissions['edit']) || !empty($userPermissions['delete'])): ?>
									<th width="12%" class="text-center">จัดการ</th>
								<?php endif; ?>
            </tr></thead>
            <tbody>
            <?php if (!empty($result['data'])): foreach ($result['data'] as $row): ?>
                <tr>
                    <?php if (!empty($userPermissions['edit']) || !empty($userPermissions['delete'])): ?>
											<td class="text-center"><input type="checkbox" class="row-check w-4 h-4" value="<?php echo (int)$row['CardID']; ?>" onchange="updateBulk()">
										</td><?php endif; ?>
                    <td>
                        <?php $rowImageUrl = homeCardsImageUrl($row['Image'] ?? '', $ImgPath); ?>
                        <?php $isRight = ($row['Direction'] ?? 'left') === 'right'; ?>
                        <div class="<?php echo htmlspecialchars($row['BgClass']); ?> w-[380px] min-w-[380px] h-[120px] rounded-xl border border-gray-200 shadow-sm flex <?php echo $isRight ? 'flex-row-reverse text-right' : 'flex-row text-left'; ?> items-center gap-3 px-4 py-3 overflow-hidden">
                            <div class="w-[88px] h-[88px] flex-shrink-0 flex items-center justify-center">
                                <?php if ($rowImageUrl !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($rowImageUrl); ?>" alt="<?php echo htmlspecialchars($row['Title_TH']); ?>" class="w-full h-full object-contain">
                                <?php else: ?>
                                    <div class="w-full h-full rounded-xl border border-white/60 bg-white/30 flex items-center justify-center text-sm font-medium text-gray-500">ไม่มีรูป</div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-base font-bold text-gray-900 leading-snug whitespace-pre-line"><?php echo htmlspecialchars((string)$row['Title_TH']); ?></div>
                                <div class="text-xs text-gray-800 mt-1.5 leading-relaxed whitespace-pre-line"><?php echo htmlspecialchars((string)$row['Description_TH']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?php if ($row['IsActive'] === 'Y'): ?><span class="inline-flex px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold border border-green-200">เปิดใช้งาน</span><?php else: ?><span class="inline-flex px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold border border-gray-200">ปิดใช้งาน</span><?php endif; ?></td>
                    <?php if (!empty($userPermissions['edit']) || !empty($userPermissions['delete'])): ?><td><div class="flex justify-center gap-2">
                        <?php if (!empty($userPermissions['edit'])): ?><a href="form.php?CardID=<?php echo (int)$row['CardID']; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100" title="แก้ไข"><i class="ph ph-pencil-simple"></i></a><?php endif; ?>
                        <button type="button" onclick="openHistoryModal(<?php echo (int)$row['CardID']; ?>, 'home_cards')" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-200" title="ประวัติ"><i class="ph ph-clock-counter-clockwise"></i></button>
                        <?php if (!empty($userPermissions['delete'])): ?><button type="button" onclick="removeCard(<?php echo (int)$row['CardID']; ?>)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100" title="ลบ"><i class="ph ph-trash"></i></button><?php endif; ?>
                    </div></td><?php endif; ?>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="<?php echo (!empty($userPermissions['edit']) || !empty($userPermissions['delete'])) ? 4 : 3; ?>" class="text-center py-12 text-gray-400"><i class="ph ph-cards text-4xl text-gray-300"></i><p class="mt-2">ไม่พบข้อมูลการ์ดหน้าหลัก</p></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php $pagination = $result['pagination']; if ((int)$pagination['total_pages'] > 0): ?>
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4 bg-white p-4 rounded-xl border border-gray-100">
        <div class="text-sm text-gray-500">แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo (int)$pagination['total_pages']; ?> (ทั้งหมด <?php echo number_format((int)$pagination['total']); ?> รายการ)</div>
        <?php if ((int)$pagination['total_pages'] > 1): ?><div class="flex gap-2">
            <?php if ($page > 1): ?><a class="px-3 py-2 border rounded-lg hover:bg-gray-50" href="?<?php echo homeCardsQuery(['page' => $page - 1]); ?>">ก่อนหน้า</a><?php endif; ?>
            <?php if ($page < (int)$pagination['total_pages']): ?><a class="px-3 py-2 border rounded-lg hover:bg-gray-50" href="?<?php echo homeCardsQuery(['page' => $page + 1]); ?>">ถัดไป</a><?php endif; ?>
        </div><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function checkedIds() { return Array.from(document.querySelectorAll('.row-check:checked')).map(el => el.value); }
function updateBulk() {
    const ids = checkedIds();
    const form = document.getElementById('bulkForm');
    if (!form) return;
    form.classList.toggle('hidden', ids.length === 0);
    form.classList.toggle('flex', ids.length > 0);
    document.getElementById('selectedCount').textContent = ids.length ? `เลือก ${ids.length} รายการ` : '';
}
function toggleAll(source) { document.querySelectorAll('.row-check').forEach(el => el.checked = source.checked); updateBulk(); }
function removeCard(id) {
    AdminDialog.fire({title:'ยืนยันการลบ?', text:'รายการจะถูกซ่อนด้วย Soft delete และไม่ถูกลบออกจากฐานข้อมูล', icon:'warning', showCancelButton:true, confirmButtonColor:'#dc2626', confirmButtonText:'ยืนยันการลบ', cancelButtonText:'ยกเลิก'}).then(result => {
        if (result.isConfirmed) window.location.href = `action.php?action=delete&CardID=${id}`;
    });
}
function submitBulk() {
    const action = document.getElementById('bulkAction').value;
    const ids = checkedIds();
    if (!action || !ids.length) { AdminDialog.fire('กรุณาเลือกคำสั่งและรายการ', '', 'warning'); return; }
    const isDelete = action === 'bulk_delete';
    AdminDialog.fire({adminAction:action, title:'ยืนยันการดำเนินการ?', text:`ดำเนินการกับ ${ids.length} รายการ`, icon:isDelete?'warning':'question', showCancelButton:true, confirmButtonText:'ยืนยัน', cancelButtonText:'ยกเลิก'}).then(result => {
        if (result.isConfirmed) { document.getElementById('bulkIds').value = ids.join(','); document.getElementById('bulkForm').submit(); }
    });
}
</script>
<?php include_once('../includes/history_modal.php'); ?>
<?php include_once('../includes/footer.php'); ?>
