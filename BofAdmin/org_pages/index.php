<?php
define('_VALID_ACCESS', 1);
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    define('IS_AJAX', true);
    ob_start();
}
$pageTitle = 'จัดการหน้าเนื้อหาองค์กร';
include_once('../includes/header.php');

$pages = [];
try {
    if (isset($DB)) {
        $stmt = $DB->query("SELECT PageID, PageSlug, Title_TH, Title_EN, UpdateDate FROM org_pages ORDER BY PageID ASC");
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}
?>

<div class="glass-panel p-6">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
			<h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
				<i class="ph ph-file-text text-primary"></i> จัดการหน้าเนื้อหาองค์กร
			</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="premium-table min-w-full">
            <thead>
							<tr>
								<th width="50%">ชื่อหัวข้อ</th>
								<th width="5%">แก้ไขล่าสุด</th>
								<?php if ($userPermissions['edit']): ?>
								<th width="5%">จัดการ</th>
								<?php endif; ?>
							</tr>
            </thead>
            <tbody>
                <?php if (empty($pages)): ?>
                <tr>
                    <td colspan="6" class="text-center py-8 text-gray-500">
                        <i class="ph ph-folder-open text-4xl mb-2 text-gray-300"></i>
                        <p>ยังไม่มีข้อมูลหน้าเนื้อหาองค์กร</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($pages as $i => $row): ?>
                    <tr>
											<td class="font-medium text-gray-800"><?php echo htmlspecialchars($row['Title_TH']); ?></td>
											<td class="text-sm text-gray-400">
													<?php echo $row['UpdateDate'] ? date('d/m/Y H:i', strtotime($row['UpdateDate'])) : '-'; ?>
											</td>
											<?php if ($userPermissions['edit']): ?>
											<td>
												<div class="flex items-center justify-center gap-2">
													<a href="form.php?id=<?php echo $row['PageID']; ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="แก้ไข">
														<i class="ph ph-pencil-simple"></i>
													</a>
													<button type="button" onclick="openHistoryModal(<?php echo $row['PageID']; ?>, 'org_pages')" class="w-8 h-8 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center hover:bg-gray-200 transition-colors" title="ประวัติการใช้งาน">
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
</div>
<?php include_once('../includes/history_modal.php'); ?>
<?php include_once('../includes/footer.php'); ?>