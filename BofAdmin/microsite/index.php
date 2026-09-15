<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'ภาพรวมหน่วยงาน';
include_once('../includes/header.php');

// Fetch Departments Data
$departments = [];
$searchKeyword = $_GET['keyword'] ?? '';
$searchGroup = $_GET['group'] ?? '';

try {
    if (isset($DB)) {
        $userGroupID = $_SESSION['GroupID'] ?? '';
        
        // --- Redirect Staff directly to their assigned department ---
        if ($userGroupID === 'S') {
            $staffID = $_SESSION['StaffID'] ?? 0;
            $checkAccess = $DB->prepare("SELECT DeptID FROM staff_microsite_access WHERE StaffID = ? LIMIT 1");
            $checkAccess->execute([$staffID]);
            $staffDept = $checkAccess->fetchColumn();
            
            if ($staffDept) {
                // Instantly redirect to action.php to switch active department and go to view
                echo "<script>window.location.href = 'action.php?action=switch_dept&id={$staffDept}';</script>";
                exit;
            } else {
                echo "<div class='glass-panel p-8 text-center text-red-500'>คุณยังไม่ได้รับการกำหนดสิทธิ์ให้ดูแลหน่วยงานใดเลย กรุณาติดต่อผู้ดูแลระบบ</div>";
                include_once('../includes/footer.php');
                exit;
            }
        }
        // -----------------------------------------------------------

        $baseSql = "FROM microsite_departments WHERE 1=1";
        $params = [];
        
        if (!empty($searchKeyword)) {
            $baseSql .= " AND (NameTH LIKE :keyword1 OR NameEN LIKE :keyword2 OR Slug LIKE :keyword3)";
            $params[':keyword1'] = "%$searchKeyword%";
            $params[':keyword2'] = "%$searchKeyword%";
            $params[':keyword3'] = "%$searchKeyword%";
        }
        
        if (!empty($searchGroup)) {
            $baseSql .= " AND DeptGroup = :group";
            $params[':group'] = $searchGroup;
        }

        // Pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $countStmt = $DB->prepare("SELECT COUNT(*) " . $baseSql);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT DeptID, Slug, NameTH, NameEN, DeptGroup, DefaultLayout, Image, IsActive " . $baseSql . " ORDER BY NameTH ASC LIMIT $limit OFFSET $offset";
        $stmt = $DB->prepare($sql);
        $stmt->execute($params);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Summary Counts
        $sumSql = "SELECT 
            COUNT(*) as total, 
            SUM(CASE WHEN DeptGroup='central' THEN 1 ELSE 0 END) as central_count,
            SUM(CASE WHEN DeptGroup='regional' THEN 1 ELSE 0 END) as regional_count
            FROM microsite_departments
            WHERE 1=1";
        $sumParams = [];
        $sumStmt = $DB->prepare($sumSql);
        $sumStmt->execute($sumParams);
        $summary = $sumStmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}
?>

<?php
$isAllActive = empty($searchGroup) ? 'border-primary ring-2 ring-primary/20' : 'border-gray-100 hover:border-primary/50';
$isCentralActive = $searchGroup === 'central' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-100 hover:border-primary/50';
$isRegionalActive = $searchGroup === 'regional' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-100 hover:border-primary/50';
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
    
    <a href="index.php?group=central" class="bg-white p-6 rounded-2xl shadow-sm border <?php echo $isCentralActive; ?> flex justify-between items-center transition-all group">
        <div>
            <div class="text-sm text-gray-500 font-medium mb-1">ส่วนกลาง</div>
            <div class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['central_count'] ?? 0); ?></div>
        </div>
        <div class="w-14 h-14 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-500 group-hover:scale-110 transition-transform">
            <i class="ph ph-map-pin text-3xl"></i>
        </div>
    </a>
    
    <a href="index.php?group=regional" class="bg-white p-6 rounded-2xl shadow-sm border <?php echo $isRegionalActive; ?> flex justify-between items-center transition-all group">
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
            <i class="ph ph-buildings text-primary"></i>
            หน่วยงานทั้งหมด
        </h2>
        <?php if ($userGroupID === 'A'): ?>
        <a href="form.php?action=add" class="btn-primary flex items-center gap-2">
            <i class="ph ph-plus-circle"></i>
            เพิ่มหน่วยงานใหม่
        </a>
        <?php endif; ?>
    </div>

    <!-- Search Form -->
    <form method="GET" action="index.php" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหาหน่วยงาน</label>
            <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="ชื่อหน่วยงาน หรือ Slug..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div class="sm:w-64">
            <label class="block text-sm font-medium text-gray-700 mb-1">กลุ่มหน่วยงาน</label>
            <select name="group" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                <option value="">-- ทั้งหมด --</option>
                <option value="central" <?php echo $searchGroup == 'central' ? 'selected' : ''; ?>>ส่วนกลาง</option>
                <option value="regional" <?php echo $searchGroup == 'regional' ? 'selected' : ''; ?>>ส่วนภูมิภาค</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary flex items-center justify-center gap-2 h-[42px] px-6">
              ค้นหา
            </button>
            <?php if($searchKeyword != '' || $searchGroup != ''): ?>
                <a href="index.php" class="px-6 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center h-[42px]">
                    ล้างค่า
                </a>
            <?php endif; ?>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="premium-table">
            <thead>
                <tr>
                    <th width="80" class="text-center">รูปโลโก้</th>
                    <th>ชื่อหน่วยงาน</th>
                    <th>กลุ่มหน่วยงาน</th>
                    <th width="100" class="text-center">สถานะ</th>
                    <th width="120" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departments)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-12 text-gray-500">
                            <i class="ph ph-folder-open text-5xl mb-3 text-gray-300 block mx-auto"></i>
                            <p>ไม่พบข้อมูลหน่วยงาน</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($departments as $dept): 
                        $imgUrl = !empty($dept['Image']) ? $ImgPath."microsite/".$dept['Slug']."/".$dept['Image'] : "../../assets/images/logo.png";
                        $grp = $dept['DeptGroup'] ?? '';
                        $groupLabel = $grp == 'central' ? 'ส่วนกลาง' : ($grp == 'regional' ? 'ส่วนภูมิภาค' : 'กรมทรัพยากรน้ำบาดาล');
                        $groupClass = $grp == 'central' ? 'bg-indigo-100 text-indigo-700 border-indigo-200' : ($grp == 'regional' ? 'bg-teal-100 text-teal-700 border-teal-200' : 'bg-orange-100 text-orange-700 border-orange-200');
                    ?>
                    <tr>
                        <td class="text-center">
                            <div class="w-12 h-12 rounded-lg border border-gray-100 shadow-sm bg-gray-50 flex items-center justify-center overflow-hidden mx-auto">
                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="max-w-[32px] max-h-[32px] object-contain" alt="Logo">
                            </div>
                        </td>
                        <td>
                          <div class="font-bold text-gray-800 text-sm mb-1"><?php echo htmlspecialchars($dept['NameTH']); ?></div>
                          <div class="text-xs text-gray-500"><?php echo htmlspecialchars($dept['NameEN'] ?? '-'); ?></div>
                        </td>
                        <td>
                            <span class="inline-flex px-2.5 py-1 rounded-md text-[11px] font-bold border <?php echo $groupClass; ?>">
                                <?php echo $groupLabel; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($dept['IsActive'] == 'Y'): ?>
                                <span class="inline-flex items-center justify-center px-2 py-1 bg-green-100 text-green-700 rounded-full text-[11px] font-medium"> เปิดใช้งาน</span>
                            <?php else: ?>
                                <span class="inline-flex items-center justify-center px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-[11px] font-medium"> ปิดใช้งาน</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="action.php?action=switch_dept&id=<?php echo $dept['DeptID']; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors mx-auto" title="จัดการข้อมูล">
                                <i class="ph ph-pencil-simple text-lg"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalRecords > 0): ?>
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-gray-100 gap-4">
        <div class="text-sm text-gray-500">
            แสดงข้อมูลหน้า <?php echo $page; ?> จาก <?php echo $totalPages; ?> (ทั้งหมด <?php echo $totalRecords; ?> รายการ)
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="flex gap-2 flex-wrap items-center">
            <!-- First Page -->
            <?php if ($page > 1): ?>
                <a href="index.php?page=1&keyword=<?php echo urlencode($searchKeyword); ?>&group=<?php echo urlencode($searchGroup); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าแรก">
                    <i class="ph ph-caret-double-left text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $page - 1; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&group=<?php echo urlencode($searchGroup); ?>" 
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
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="index.php?page=<?php echo $i; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&group=<?php echo urlencode($searchGroup); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg border <?php echo $i == $page ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:text-primary transition-colors'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <!-- Next Page -->
            <?php if ($page < $totalPages): ?>
                <a href="index.php?page=<?php echo $page + 1; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&group=<?php echo urlencode($searchGroup); ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary transition-colors border border-gray-200" title="หน้าถัดไป">
                    <i class="ph ph-caret-right text-sm"></i>
                </a>
                <a href="index.php?page=<?php echo $totalPages; ?>&keyword=<?php echo urlencode($searchKeyword); ?>&group=<?php echo urlencode($searchGroup); ?>" 
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

<?php include_once('../includes/footer.php'); ?>
