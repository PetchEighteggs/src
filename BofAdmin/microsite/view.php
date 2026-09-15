<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'ตั้งค่าหน่วยงาน';
include_once('../includes/header.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;

if (empty($deptID)) {
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Check Permissions for 'settings' module
$userGroupID = $_SESSION['GroupID'] ?? '';
$canView = false;
$canEdit = false;

if ($userGroupID === 'A') {
    $canView = true;
    $canEdit = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../includes/microsite_permission.php');
    $canView = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'settings', 'CanView');
    $canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'settings', 'CanEdit');
}

if (!$canView) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (View Permission Required)'); window.location.href = 'index.php';</script>";
    exit;
}

// Fetch Dept Info
$dept = [];
try {
    if (isset($DB)) {
        $stmt = $DB->prepare("SELECT * FROM microsite_departments WHERE DeptID = ?");
        $stmt->execute([$deptID]);
        $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fetched) {
            $dept = $fetched;
        } else {
            echo "<script>alert('ไม่พบข้อมูลหน่วยงาน'); window.location.href = 'index.php';</script>";
            exit;
        }
    }
} catch (PDOException $e) {}

$imgUrl = !empty($dept['Image']) ? $ImgPath."microsite/".$dept['Slug']."/".$dept['Image'] : "../../assets/images/logo.png";
$mapUrl = !empty($dept['MapImage']) ? $ImgPath."microsite/".$dept['Slug']."/".$dept['MapImage'] : "";
$grp = $dept['DeptGroup'] ?? '';
$groupLabel = $grp === 'central' ? 'ส่วนกลาง' : ($grp === 'regional' ? 'ส่วนภูมิภาค' : 'กรมทรัพยากรน้ำบาดาล');

// Define layout label
$layoutLabels = [
	'1' => 'แบบที่ 1 (Standard)',
	'2' => 'แบบที่ 2 (Hero)',
	'3' => 'แบบที่ 3 (Grid)',
	'standard' => 'แบบที่ 1 (Standard)',
	'hero' => 'แบบที่ 2 (Hero)',
	'grid' => 'แบบที่ 3 (Grid)'
];
$layoutName = $layoutLabels[$dept['DefaultLayout'] ?? '1'] ?? 'แบบที่ 1 (Standard)';
?>

<div class="space-y-6">
    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <?php if ($_SESSION['GroupID'] === 'A'): ?>
                <a href="index.php" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <?php endif; ?>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">
                        ข้อมูลหน่วยงาน
                    </h2>
                    <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($dept['NameTH'] ?? ''); ?></span></div>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <?php if (($dept['IsActive'] ?? 'Y') === 'Y'): ?>
                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold flex items-center gap-1"><i class="ph ph-check-circle"></i> เปิดใช้งาน</span>
                <?php else: ?>
                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-bold flex items-center gap-1"><i class="ph ph-x-circle"></i> ปิดใช้งาน</span>
                <?php endif; ?>
                
                <?php if ($canEdit): ?>
                <a href="form.php?action=edit&id=<?php echo $deptID; ?>" class="btn-primary px-5 py-2 text-sm font-semibold flex items-center gap-2">
                    <i class="ph ph-pencil-simple"></i> แก้ไขข้อมูล
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Left Column: Logos & Maps -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Logo -->
                <div class="border border-gray-200 rounded-xl p-5 bg-white relative">
                    <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2 border-b border-gray-100 pb-2">
                        <i class="ph ph-image text-primary"></i> รูปภาพโลโก้
                    </h3>
                    <div class="flex justify-center p-4 bg-gray-50 rounded-lg border border-gray-100">
                        <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Logo" class="max-h-32 object-contain cursor-pointer hover:opacity-90 transition-opacity" onclick="openLightbox(this.src)">
                    </div>
                </div>

                <!-- Map Image -->
                <?php if ($mapUrl): ?>
                <div class="border border-gray-200 rounded-xl p-5 bg-white relative">
                    <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2 border-b border-gray-100 pb-2">
                        <i class="ph ph-map-pin text-primary"></i> รูปภาพแผนที่
                    </h3>
                    <div class="flex justify-center p-2 bg-gray-50 rounded-lg border border-gray-100">
                        <img src="<?php echo htmlspecialchars($mapUrl); ?>" alt="Map" class="max-h-40 object-cover rounded cursor-pointer hover:opacity-90 transition-opacity" onclick="openLightbox(this.src)">
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Google Map -->
                <?php if (!empty($dept['Latitude']) && !empty($dept['Longitude'])): ?>
                <div class="border border-gray-200 rounded-xl p-5 bg-white">
                    <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2 border-b border-gray-100 pb-2">
                        <i class="ph ph-map text-primary"></i> แผนที่ Google Map
                    </h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-gray-500 mb-1">ละติจูด</div>
                            <div class="font-medium text-gray-800"><?php echo htmlspecialchars($dept['Latitude']); ?></div>
                        </div>
                        <div>
                            <div class="text-gray-500 mb-1">ลองจิจูด</div>
                            <div class="font-medium text-gray-800"><?php echo htmlspecialchars($dept['Longitude']); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Details -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Basic Info -->
                <div class="glass-panel p-6">
                    <h3 class="font-bold text-gray-800 border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
                        <i class="ph ph-file-text text-primary"></i> ข้อมูลหน่วยงานหลัก
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                        <div>
                            <div class="text-sm text-gray-500 mb-1">ชื่อหน่วยงาน (<?php echo $Lang1;?>)</div>
                            <div class="font-medium text-gray-900 bg-gray-50 p-2.5 rounded-lg border border-gray-100"><?php echo htmlspecialchars($dept['NameTH'] ?? '-'); ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">ชื่อหน่วยงาน (<?php echo $Lang2;?>)</div>
                            <div class="font-medium text-gray-900 bg-gray-50 p-2.5 rounded-lg border border-gray-100"><?php echo htmlspecialchars($dept['NameEN'] ?: '-'); ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">กลุ่มหน่วยงาน</div>
                            <div class="font-medium text-gray-900 bg-gray-50 p-2.5 rounded-lg border border-gray-100"><?php echo $groupLabel; ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">Slug</div>
                            <div class="font-medium text-gray-900 bg-gray-50 p-2.5 rounded-lg border border-gray-100">/<?php echo htmlspecialchars($dept['Slug'] ?? ''); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="glass-panel p-6">
                    <h3 class="font-bold text-gray-800 border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
                        <i class="ph ph-address-book text-primary"></i> ข้อมูลการติดต่อ
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                        <div class="md:col-span-2">
                            <div class="text-sm text-gray-500 mb-1">ที่อยู่ (<?php echo $Lang1;?>)</div>
                            <div class="font-medium text-gray-900 bg-gray-50 p-3 rounded-lg border border-gray-100 whitespace-pre-line"><?php echo htmlspecialchars($dept['Address'] ?: '-'); ?></div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-sm text-gray-500 mb-1">ที่อยู่ (<?php echo $Lang2;?>)</div>
                            <div class="font-medium text-gray-900 bg-gray-50 p-3 rounded-lg border border-gray-100 whitespace-pre-line"><?php echo htmlspecialchars($dept['AddressEN'] ?: '-'); ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">เบอร์โทรศัพท์</div>
                            <div class="font-medium text-gray-900 bg-gray-50 p-2.5 rounded-lg border border-gray-100 flex items-center gap-2"><i class="ph ph-phone text-gray-400"></i> <?php echo htmlspecialchars($dept['Tel'] ?: '-'); ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">โทรสาร</div>
                            <div class="font-medium text-gray-900 bg-gray-50 p-2.5 rounded-lg border border-gray-100 flex items-center gap-2"><i class="ph ph-printer text-gray-400"></i> <?php echo htmlspecialchars($dept['Fax'] ?: '-'); ?></div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-sm text-gray-500 mb-1">อีเมล</div>
                            <div class="font-medium text-gray-900 bg-gray-50 p-2.5 rounded-lg border border-gray-100 flex items-center gap-2"><i class="ph ph-envelope text-gray-400"></i> <?php echo htmlspecialchars($dept['Email'] ?: '-'); ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">Facebook URL</div>
                            <div class="font-medium text-gray-900 bg-gray-50 p-2.5 rounded-lg border border-gray-100 overflow-hidden text-ellipsis whitespace-nowrap"><a href="<?php echo htmlspecialchars($dept['Facebook']); ?>" target="_blank" class="text-blue-600 hover:underline"><?php echo htmlspecialchars($dept['Facebook'] ?: '-'); ?></a></div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">YouTube URL</div>
                            <div class="font-medium text-gray-900 bg-gray-50 p-2.5 rounded-lg border border-gray-100 overflow-hidden text-ellipsis whitespace-nowrap"><a href="<?php echo htmlspecialchars($dept['YouTube']); ?>" target="_blank" class="text-blue-600 hover:underline"><?php echo htmlspecialchars($dept['YouTube'] ?: '-'); ?></a></div>
                        </div>
                    </div>
                </div>

                <!-- Settings -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="glass-panel p-6">
                        <h3 class="font-bold text-gray-800 border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
                            <i class="ph ph-layout text-primary"></i> รูปแบบการแสดงผล
                        </h3>
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 text-center flex flex-col items-center justify-center h-28">
                            <i class="ph ph-desktop text-3xl text-primary mb-2"></i>
                            <div class="font-bold text-gray-800"><?php echo $layoutName; ?></div>
                        </div>
                    </div>
                    
                    <div class="glass-panel p-6">
                        <h3 class="font-bold text-gray-800 border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
                            <i class="ph ph-globe text-primary"></i> ข้อมูล SEO
                        </h3>
                        <div class="space-y-3 text-sm">
													<div>
														<span class="text-gray-500 block mb-0.5">Tag Title:</span>
														<div class="font-medium text-gray-800 bg-gray-50 p-2 rounded border border-gray-100"><?php echo htmlspecialchars($dept['SEOTitle'] ?: '-'); ?></div>
													</div>
													<div>
														<span class="text-gray-500 block mb-0.5">Tag Description:</span>
														<div class="font-medium text-gray-800 bg-gray-50 p-2 rounded border border-gray-100"><?php echo htmlspecialchars($dept['SEODescription'] ?: '-'); ?></div>
													</div>
													<div>
														<span class="text-gray-500 block mb-0.5">Tag Keywords:</span>
														<div class="font-medium text-gray-800 bg-gray-50 p-2 rounded border border-gray-100"><?php echo htmlspecialchars($dept['SEOKeywords'] ?: '-'); ?></div>
													</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox Modal -->
<div id="lightboxModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-[9999] hidden" onclick="closeLightbox()">
    <div class="relative max-w-4xl max-h-[85vh] p-4" onclick="event.stopPropagation()">
        <button type="button" onclick="closeLightbox()" class="absolute -top-10 -right-2 text-white hover:text-gray-300 text-3xl font-bold flex items-center justify-center w-8 h-8 rounded-full bg-black/40">&times;</button>
        <img id="lightboxImg" class="max-w-full max-h-[80vh] rounded-lg shadow-2xl object-contain" src="">
    </div>
</div>

<script>
function openLightbox(src) {
    const modal = document.getElementById('lightboxModal');
    const img = document.getElementById('lightboxImg');
    if (modal && img) {
        img.src = src;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeLightbox() {
    const modal = document.getElementById('lightboxModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}
</script>

<?php include_once('../includes/footer.php'); ?>