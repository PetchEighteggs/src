<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'รายละเอียดการติดต่อ';
include_once('../../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>alert('กรุณาเลือกหน่วยงานก่อนจัดการข้อมูล'); window.location.href = '../index.php';</script>";
    exit;
}

// Check Permissions
$userGroupID = $_SESSION['GroupID'] ?? '';
$canView = false;
if ($userGroupID === 'A') {
    $canView = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $canView = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'contact', 'CanView');
}

if (!$canView) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = '../index.php';</script>";
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (empty($id)) {
    echo "<script>alert('ไม่พบข้อมูล'); window.location.href = 'index.php';</script>";
    exit;
}

$stmt = $DB->prepare("SELECT c.*, g.GroupNameTH FROM microsite_contact c LEFT JOIN microsite_contact_groups g ON c.GroupID = g.GroupID WHERE c.ContactID = ? AND c.DeptID = ? AND (c.DelData IS NULL OR c.DelData = 'N')");
$stmt->execute([$id, $deptID]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo "<script>alert('ไม่พบข้อมูล หรือข้อมูลถูกลบไปแล้ว'); window.location.href = 'index.php';</script>";
    exit;
}

$stmtSlug = $DB->prepare("SELECT Slug FROM microsite_departments WHERE DeptID = ?");
$stmtSlug->execute([$deptID]);
$deptSlug = $stmtSlug->fetchColumn();

?>

<div class="glass-panel p-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-100 pb-4 mb-6 gap-4">
        <div class="flex items-center gap-4">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                <i class="ph ph-arrow-left text-lg"></i>
            </a>
            <div>
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                  รายละเอียดการติดต่อ
                </h2>
                <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? ''); ?></span></div>
            </div>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg shadow-sm text-sm text-gray-600">
                <i class="ph ph-calendar-blank text-gray-400"></i>
                <span>วันที่ส่งข้อมูล: <span class="font-medium text-gray-700"><?php echo !empty($item['CreateDate']) ? date('d/m/Y H:i', strtotime($item['CreateDate'])) : '-'; ?></span></span>
            </div>
            <?php if (!empty($item['ClientIP'])): ?>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg shadow-sm text-sm text-gray-600">
                <i class="ph ph-globe text-gray-400"></i>
                <span>IP: <span class="font-medium text-gray-700"><?php echo htmlspecialchars($item['ClientIP']); ?></span></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
        <div>
            <div class="text-sm text-gray-500 mb-1">ชื่อ-สกุล</div>
            <div class="font-medium text-gray-800 text-lg">
                <?php echo htmlspecialchars(trim(($item['Title'] ?? '') . ' ' . ($item['Name'] ?? '-'))); ?>
            </div>
        </div>
        <div>
            <div class="text-sm text-gray-500 mb-1">กลุ่มติดต่อหน่วยงาน</div>
            <div class="font-medium text-gray-800 text-lg">
                <?php echo htmlspecialchars($item['GroupNameTH'] ?? '-'); ?>
            </div>
        </div>
        <div>
            <div class="text-sm text-gray-500 mb-1">อีเมล</div>
            <div class="font-medium text-blue-600 text-lg break-all">
                <?php echo htmlspecialchars($item['Email'] ?? '-'); ?>
            </div>
        </div>
        <div>
            <div class="text-sm text-gray-500 mb-1">เบอร์โทรศัพท์</div>
            <div class="font-medium text-gray-800 text-lg">
                <?php echo htmlspecialchars($item['Tel'] ?? '-'); ?>
            </div>
        </div>
        
        <div class="md:col-span-2 mt-2">
            <div class="text-sm text-gray-500 mb-2">รายละเอียด</div>
            <div class="font-medium text-gray-800 text-lg">
            <?php echo htmlspecialchars($item['Detail'] ?? '-'); ?>
            </div>
        </div>
        
        <div class="md:col-span-2 mt-2 border-t border-gray-100 pt-6">
            <div class="text-sm text-gray-500 mb-3 font-medium">ไฟล์แนบ</div>
            <?php 
            if (!empty($item['File'])) {
                $ext = strtolower(pathinfo($item['File'], PATHINFO_EXTENSION));
                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                $folder = $isImg ? 'images' : 'files';
                $fileUrl = "../../../uploads/{$folder}/microsite/{$deptSlug}/contact/" . $item['File'];
                
                if ($isImg) {
                    echo "<a href='{$fileUrl}' target='_blank' class='block w-64 max-w-full h-auto overflow-hidden rounded-lg border border-gray-200 hover:shadow-md transition-shadow'><img src='{$fileUrl}' class='w-full object-cover'></a>";
                } else {
                    echo "<div class='flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg text-sm group w-full max-w-md'>
                            <div class='flex items-center gap-3 flex-1 min-w-0'>
                                <i class='ph ph-file-text text-red-500 text-2xl flex-shrink-0'></i>
                                <a href='{$fileUrl}' target='_blank' class='block truncate hover:text-blue-600 hover:underline font-medium text-gray-700 text-base'>{$item['File']}</a>
                            </div>
                            <div class='flex items-center gap-3 flex-shrink-0 pl-4 border-l border-gray-100'>
                                <a href='{$fileUrl}' target='_blank' class='w-8 h-8 rounded-full bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center transition-colors' title='ดูไฟล์ / ดาวน์โหลด'>
                                    <i class='ph ph-eye text-lg'></i>
                                </a>
                            </div>
                          </div>";
                }
            } else {
                echo "ไม่มีไฟล์แนบ";
            }
            ?>
        </div>
    </div>
    
</div>
<?php include_once('../../includes/footer.php'); ?>