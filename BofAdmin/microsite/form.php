<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'ตั้งค่าหน่วยงาน';
include_once('../includes/header.php');
require_once('../includes/action_security.php');

$action = $_GET['action'] ?? 'edit';
$deptID = ($action === 'add') ? 0 : ($_SESSION['ActiveDeptID'] ?? 0);

if ($action !== 'add' && empty($deptID)) {
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Check Permissions for 'settings' module
$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = false;
if ($userGroupID === 'A') {
    $hasAccess = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../includes/microsite_permission.php');
    if ($action === 'add') {
        $hasAccess = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'settings', 'CanAdd');
    } else {
        $hasAccess = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'settings', 'CanEdit');
    }
}

if (!$hasAccess) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location.href = 'view.php';</script>";
    exit;
}

// Fetch Dept Info if editing
$dept = [
	'DeptID' => 0,
	'Slug' => '',
	'NameTH' => '',
	'NameEN' => '',
	'DeptGroup' => 'central',
	'DefaultLayout' => 'standard',
	'IsActive' => 'Y',
	'Image' => '',
	'MapImage' => '',
	'Address' => '',
	'AddressEN' => '',
	'Tel' => '',
	'Fax' => '',
	'Email' => '',
	'Facebook' => '',
	'YouTube' => '',
	'Latitude' => '',
	'Longitude' => '',
	'SEOTitle' => '',
	'SEODescription' => '',
	'SEOKeywords' => '',
	'CreateDate' => date('Y-m-d H:i:s')
];

if ($deptID > 0) {
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
}
?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<form id="deptForm" action="save_dept.php" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="showLoading()">
    <?php echo bofSecurityCsrfField(); ?>
    <input type="hidden" name="DeptID" value="<?php echo $deptID; ?>">

    <div class="glass-panel p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-all border border-gray-100" title="กลับไปหน้ารวม">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">
                        ข้อมูลหน่วยงาน
                    </h2>
                    <div class="text-sm text-gray-500 mt-1">หน่วยงาน: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($dept['NameTH']); ?></span></div>
                </div>
            </div>
            
            <!-- Status Toggle -->
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-650">สถานะการแสดงผล</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="hidden" name="IsActive" value="N">
                  <input type="checkbox" name="IsActive" value="Y" class="sr-only peer" <?php echo ($dept['IsActive'] == 'Y') ? 'checked' : ''; ?>>
                  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <div class="lg:col-span-4 space-y-6">
                <div class="glass-panel p-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-3"><i class="ph ph-image"></i> รูปภาพโลโก้</label>
                    <div class="mb-3" id="previewContainerLogo" style="<?php echo !empty($dept['Image']) ? '' : 'display: none;'; ?>">
                        <div class="w-full h-48 rounded-lg overflow-hidden border border-gray-200 bg-gray-50 cursor-pointer relative group">
                            <img id="previewImg" src="<?php echo !empty($dept['Image']) ? $ImgPath.'microsite/'.htmlspecialchars($dept['Slug']).'/'.htmlspecialchars($dept['Image']) : ''; ?>" class="w-full h-full object-contain" onclick="openLightbox(this.src)">
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                            </div>
                            <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110" onclick="removeLogoImage(event)">
                              <i class="ph ph-x font-bold"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="delete_logo_image" id="deleteLogoImage" value="0">
                    <div class="mt-2">
                        <input type="file" name="Image" id="imageFile" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">ขนาดที่แนะนำ: <?php echo CONF_IMG_REC_W; ?>x<?php echo CONF_IMG_REC_H; ?> px (รองรับ JPG, PNG)</p>
                    </div>
                </div>

                <div class="glass-panel p-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-3"><i class="ph ph-map-pin"></i> รูปภาพแผนที่</label>
                    <div class="mb-3" id="previewContainerMap" style="<?php echo !empty($dept['MapImage']) ? '' : 'display: none;'; ?>">
                        <div class="w-full h-48 rounded-lg overflow-hidden border border-gray-200 bg-gray-50 cursor-pointer relative group">
                            <img id="previewMapImg" src="<?php echo !empty($dept['MapImage']) ? $ImgPath.'microsite/'.htmlspecialchars($dept['Slug']).'/'.htmlspecialchars($dept['MapImage']) : ''; ?>" class="w-full h-full object-contain" onclick="openLightbox(this.src)">
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                <i class="ph ph-magnifying-glass-plus text-white text-3xl"></i>
                            </div>
                            <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110" onclick="removeMapImage(event)">
                              <i class="ph ph-x font-bold"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="delete_map_image" id="deleteMapImage" value="0">
                    
                    <div class="mt-2">
                        <input type="file" name="MapImage" id="mapImageFile" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">ขนาดที่แนะนำ: <?php echo CONF_IMG_REC_W; ?>x<?php echo CONF_IMG_REC_H; ?> px (รองรับ JPG, PNG)</p>
                    </div>
                </div>

								<!-- Google Map Coordinates -->
                <div class="glass-panel p-4">
                    <h3 class="font-bold text-gray-800 border-b border-gray-100 pb-3 flex items-center gap-2">
                        <i class="ph ph-map-trifold text-primary"></i> แผนที่ Google Map
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">ละติจูด</label>
                            <input type="text" name="Latitude" value="<?php echo htmlspecialchars($dept['Latitude'] ?? ''); ?>" placeholder="เช่น 14.01524" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">ลองจิจูด</label>
                            <input type="text" name="Longitude" value="<?php echo htmlspecialchars($dept['Longitude'] ?? ''); ?>" placeholder="เช่น 100.52844" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Details, SEO & Layouts (lg:col-span-8) -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Info Section -->
                <div class="glass-panel p-6 space-y-4">
                    <h3 class="font-bold text-gray-800 border-b border-gray-100 pb-3 flex items-center gap-2">
                      <i class="ph ph-file-text text-primary"></i> ข้อมูลหน่วยงานหลัก
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อหน่วยงาน (<?php echo $Lang1;?>) <span class="text-red-500">*</span></label>
                            <input type="text" name="NameTH" value="<?php echo htmlspecialchars($dept['NameTH']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อหน่วยงาน (<?php echo $Lang2;?>)</label>
                            <input type="text" name="NameEN" value="<?php echo htmlspecialchars($dept['NameEN'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">กลุ่มหน่วยงาน</label>
                            <select name="DeptGroup" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none bg-white">
                                <option value="central" <?php echo $dept['DeptGroup'] == 'central' ? 'selected' : ''; ?>>ส่วนกลาง</option>
                                <option value="regional" <?php echo $dept['DeptGroup'] == 'regional' ? 'selected' : ''; ?>>ส่วนภูมิภาค</option>
                                <option value="other" <?php echo $dept['DeptGroup'] == 'other' ? 'selected' : ''; ?>>กรมทรัพยากรน้ำบาดาล</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Slug <span class="text-red-500">*</span></label>
                            <?php if($deptID == 0): ?>
                                <input type="text" name="Slug" value="" required placeholder="e.g. bgr13" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none font-mono">
                                <p class="text-xs text-gray-400 mt-1">ภาษาอังกฤษพิมพ์เล็กเท่านั้น ห้ามมีช่องว่าง</p>
                            <?php else: ?>
                                <input type="text" name="Slug" value="<?php echo htmlspecialchars($dept['Slug']); ?>" readonly class="w-full px-4 py-2 border border-gray-205 bg-gray-50 text-gray-500 rounded-lg outline-none cursor-not-allowed font-mono">
                                <p class="text-xs text-gray-400 mt-1">เช่น <a href="<?php echo $_Config_live_site; ?>/<?php echo htmlspecialchars($dept['Slug']); ?>" target="_blank"><?php echo $_Config_live_site; ?>/<?php echo htmlspecialchars($dept['Slug']); ?></a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

								<!-- Contact & Other details -->
                <div class="glass-panel p-6 space-y-4">
                    <h3 class="font-bold text-gray-800 border-b border-gray-100 pb-3 flex items-center gap-2">
                        <i class="ph ph-address-book text-primary"></i> ข้อมูลการติดต่อ
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">ที่อยู่ (<?php echo $Lang1;?>)</label>
                            <textarea name="Address" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none"><?php echo htmlspecialchars($dept['Address'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">ที่อยู่ (<?php echo $Lang2;?>)</label>
                            <textarea name="AddressEN" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none"><?php echo htmlspecialchars($dept['AddressEN'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                                <input type="text" name="Tel" value="<?php echo htmlspecialchars($dept['Tel'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">โทรสาร</label>
                                <input type="text" name="Fax" value="<?php echo htmlspecialchars($dept['Fax'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">อีเมล </label>
                            <input type="email" name="Email" value="<?php echo htmlspecialchars($dept['Email'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Facebook URL</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                        <i class="ph-fill ph-facebook-logo"></i>
                                    </div>
                                    <input type="text" name="Facebook" value="<?php echo htmlspecialchars($dept['Facebook'] ?? ''); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">YouTube URL</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                        <i class="ph-fill ph-youtube-logo"></i>
                                    </div>
                                    <input type="text" name="YouTube" value="<?php echo htmlspecialchars($dept['YouTube'] ?? ''); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                                </div>
                            </div>

														<div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1"> X (Twitter) URL</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                        <i class="ph-fill ph-x-logo"></i>
                                    </div>
                                    <input type="text" name="Twitter" value="<?php echo htmlspecialchars($dept['Twitter'] ?? ''); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Template Selector -->
                <div class="glass-panel p-6">
                    <h3 class="font-bold text-gray-800 border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
                        <i class="ph ph-layout text-primary"></i> กำหนดรูปแบบในการแสดงผล
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <!-- Option 1: Standard -->
                        <label class="cursor-pointer group block border-2 rounded-2xl p-3 transition-all relative select-layout-card <?php echo $dept['DefaultLayout'] == '1' || $dept['DefaultLayout'] === 'standard' || empty($dept['DefaultLayout']) ? 'border-primary ring-2 ring-primary/20 bg-primary/5' : 'border-gray-200 hover:border-gray-300'; ?>" id="layoutCard_1">
                            <input type="radio" name="DefaultLayout" value="1" <?php echo $dept['DefaultLayout'] == '1' || $dept['DefaultLayout'] === 'standard' || empty($dept['DefaultLayout']) ? 'checked' : ''; ?> class="hidden" onchange="selectLayout('1')">
                            <!-- Wireframe for Layout 1 (แบบที่ 1) -->
                            <div class="w-full aspect-[3/4] bg-slate-50 border border-slate-200 rounded-xl p-2 flex flex-col gap-1.5 shadow-inner select-none">
                                <!-- Header Banner -->
                                <div class="h-6 bg-blue-500/90 rounded flex items-center justify-center text-[8px] text-white font-bold shadow-sm">
                                    <i class="ph ph-image mr-0.5"></i> สไลด์แบนเนอร์
                                </div>
                                <!-- 2 Column Section -->
                                <div class="flex gap-1.5 flex-grow overflow-hidden">
                                    <!-- Left Side Column -->
                                    <div class="w-1/3 flex flex-col gap-1">
                                        <!-- Exec Box -->
                                        <div class="h-14 bg-emerald-500/90 rounded flex flex-col items-center justify-center p-0.5 text-[8px] text-white font-bold text-center shadow-sm">
                                            <div class="w-4 h-4 rounded-full bg-white/30 mb-0.5 flex items-center justify-center text-[6px]"><i class="ph ph-user"></i></div>
                                            ผู้บริหาร
                                        </div>
                                        <!-- Banners -->
                                        <div class="h-6 bg-amber-500/90 rounded flex items-center justify-center text-[7px] text-white font-bold shadow-sm text-center">
                                            แบนเนอร์
                                        </div>
                                        <!-- Gallery -->
                                        <div class="flex-grow bg-teal-500/90 rounded flex items-center justify-center text-[7px] text-white font-bold shadow-sm text-center">
                                            คลังภาพ
                                        </div>
                                    </div>
                                    <!-- Right Side Column -->
                                    <div class="w-2/3 flex flex-col gap-1">
                                        <!-- News Grid -->
                                        <div class="h-10 bg-orange-500/90 rounded flex flex-col items-center justify-center p-0.5 text-[8px] text-white font-bold shadow-sm text-center">
                                            ข่าวประชาสัมพันธ์
                                        </div>
                                        <!-- Procurements -->
                                        <div class="h-8 bg-orange-400/90 rounded flex items-center justify-center text-[7px] text-white font-bold shadow-sm text-center">
                                            จัดซื้อจัดจ้าง
                                        </div>
                                        <!-- Downloads -->
                                        <div class="flex-grow bg-indigo-500/90 rounded flex items-center justify-center text-[7px] text-white font-bold shadow-sm text-center">
                                            ดาวน์โหลด
                                        </div>
                                    </div>
                                </div>
                                <!-- Footer -->
                                <div class="h-4 bg-slate-200 rounded flex items-center justify-center text-[7px] text-slate-500 font-medium">
                                    Footer / ติดต่อเรา
                                </div>
                            </div>
                            <div class="text-center mt-3 text-sm font-bold text-gray-700">แบบที่ 1 (Standard)</div>
                        </label>
                        
                        <!-- Option 2: Hero -->
                        <label class="cursor-pointer group block border-2 rounded-2xl p-3 transition-all relative select-layout-card <?php echo $dept['DefaultLayout'] == '2' || $dept['DefaultLayout'] === 'hero' ? 'border-primary ring-2 ring-primary/20 bg-primary/5' : 'border-gray-200 hover:border-gray-300'; ?>" id="layoutCard_2">
                            <input type="radio" name="DefaultLayout" value="2" <?php echo $dept['DefaultLayout'] == '2' || $dept['DefaultLayout'] === 'hero' ? 'checked' : ''; ?> class="hidden" onchange="selectLayout('2')">
                            <!-- Wireframe for Layout 2 (แบบที่ 2) -->
                            <div class="w-full aspect-[3/4] bg-slate-50 border border-slate-200 rounded-xl p-2 flex flex-col gap-1.5 shadow-inner select-none">
                                <!-- Tall Header Banner -->
                                <div class="h-9 bg-emerald-600/90 rounded flex items-center justify-center text-[8px] text-white font-bold shadow-sm">
                                    <i class="ph ph-image mr-0.5"></i> แบนเนอร์ใหญ่ (Hero)
                                </div>
                                <!-- Middle Row -->
                                <div class="flex gap-1.5 h-14 overflow-hidden">
                                    <!-- Exec Box -->
                                    <div class="w-1/3 bg-emerald-500/90 rounded flex flex-col items-center justify-center p-0.5 text-[8px] text-white font-bold text-center shadow-sm">
                                        <div class="w-4 h-4 rounded-full bg-white/30 mb-0.5 flex items-center justify-center text-[6px]"><i class="ph ph-user"></i></div>
                                        ผู้บริหาร
                                    </div>
                                    <!-- News List -->
                                    <div class="w-2/3 bg-orange-500/90 rounded flex items-center justify-center text-[8px] text-white font-bold shadow-sm text-center">
                                        ข่าวประชาสัมพันธ์
                                    </div>
                                </div>
                                <!-- Downloads -->
                                <div class="h-6 bg-indigo-500/90 rounded flex items-center justify-center text-[7px] text-white font-bold shadow-sm text-center">
                                    เอกสารดาวน์โหลด
                                </div>
                                <!-- Banners -->
                                <div class="flex-grow bg-amber-500/90 rounded flex items-center justify-center text-[7px] text-white font-bold shadow-sm text-center">
                                    ปุ่มแบนเนอร์ลิงก์
                                </div>
                                <!-- Footer -->
                                <div class="h-4 bg-slate-200 rounded flex items-center justify-center text-[7px] text-slate-500 font-medium">
                                    Footer / ติดต่อเรา
                                </div>
                            </div>
                            <div class="text-center mt-3 text-sm font-bold text-gray-700">แบบที่ 2 (Hero)</div>
                        </label>

                        <!-- Option 3: Grid -->
                        <label class="cursor-pointer group block border-2 rounded-2xl p-3 transition-all relative select-layout-card <?php echo $dept['DefaultLayout'] == '3' || $dept['DefaultLayout'] === 'grid' ? 'border-primary ring-2 ring-primary/20 bg-primary/5' : 'border-gray-200 hover:border-gray-300'; ?>" id="layoutCard_3">
                            <input type="radio" name="DefaultLayout" value="3" <?php echo $dept['DefaultLayout'] == '3' || $dept['DefaultLayout'] === 'grid' ? 'checked' : ''; ?> class="hidden" onchange="selectLayout('3')">
                            <!-- Wireframe for Layout 3 (แบบที่ 3) -->
                            <div class="w-full aspect-[3/4] bg-slate-50 border border-slate-200 rounded-xl p-2 flex flex-col gap-1.5 shadow-inner select-none">
                                <!-- Header Banner -->
                                <div class="h-6 bg-blue-500/90 rounded flex items-center justify-center text-[8px] text-white font-bold shadow-sm">
                                    <i class="ph ph-image mr-0.5"></i> สไลด์แบนเนอร์
                                </div>
                                <!-- 2 Column Section -->
                                <div class="flex gap-1.5 flex-grow overflow-hidden">
                                    <!-- Left Side Column -->
                                    <div class="w-2/3 flex flex-col gap-1">
                                        <!-- News Grid -->
                                        <div class="flex-grow bg-orange-500/90 rounded flex flex-col items-center justify-center p-0.5 text-[8px] text-white font-bold shadow-sm text-center">
                                            ข่าวประชาสัมพันธ์
                                        </div>
                                        <!-- Banners -->
                                        <div class="h-6 bg-amber-500/90 rounded flex items-center justify-center text-[7px] text-white font-bold shadow-sm text-center">
                                            แบนเนอร์
                                        </div>
                                    </div>
                                    <!-- Right Side Column -->
                                    <div class="w-1/3 flex flex-col gap-1">
                                        <!-- Exec Box -->
                                        <div class="h-14 bg-emerald-500/90 rounded flex flex-col items-center justify-center p-0.5 text-[8px] text-white font-bold text-center shadow-sm">
                                            <div class="w-4 h-4 rounded-full bg-white/30 mb-0.5 flex items-center justify-center text-[6px]"><i class="ph ph-user"></i></div>
                                            ผู้บริหาร
                                        </div>
                                        <!-- Procurements -->
                                        <div class="h-6 bg-orange-400/90 rounded flex items-center justify-center text-[7px] text-white font-bold shadow-sm text-center">
                                            จัดซื้อจัดจ้าง
                                        </div>
                                        <!-- Downloads -->
                                        <div class="flex-grow bg-indigo-500/90 rounded flex items-center justify-center text-[7px] text-white font-bold shadow-sm text-center">
                                            ดาวน์โหลด
                                        </div>
                                    </div>
                                </div>
                                <!-- Footer -->
                                <div class="h-4 bg-slate-200 rounded flex items-center justify-center text-[7px] text-slate-500 font-medium">
                                    Footer / ติดต่อเรา
                                </div>
                            </div>
                            <div class="text-center mt-3 text-sm font-bold text-gray-700">แบบที่ 3 (Grid)</div>
                        </label>
                    </div>
                </div>

                <!-- SEO tags -->
                <div class="glass-panel p-6 space-y-4">
									<h3 class="font-bold text-gray-800 border-b border-gray-100 pb-3 flex items-center gap-2">
										<i class="ph ph-globe text-primary"></i> ข้อมูลสำหรับการค้นหา Search Engine (SEO)
									</h3>
									<div class="space-y-4">
										<div>
											<label class="block text-sm font-semibold text-gray-700 mb-1">Tag Title</label>
											<input type="text" name="SEOTitle" value="<?php echo htmlspecialchars($dept['SEOTitle'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
											<p class="text-xs text-gray-400 mt-1">ข้อความที่จะแสดงบนแท็บของเบราว์เซอร์และเป็นพาดหัวหลักในผลลัพธ์ของ Google</p>
										</div>
										<div>
												<label class="block text-sm font-semibold text-gray-700 mb-1">Tag Description</label>
												<textarea name="SEODescription" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none"><?php echo htmlspecialchars($dept['SEODescription'] ?? ''); ?></textarea>
												<p class="text-xs text-gray-400 mt-1">รายละเอียดหรือสรุปย่อของเว็บไซต์ที่จะปรากฏใต้ลิงก์ในหน้าแสดงผลการค้นหา</p>
										</div>
										<div>
											<label class="block text-sm font-semibold text-gray-700 mb-1">Tag Keywords</label>
											<input type="text" name="SEOKeywords" value="<?php echo htmlspecialchars($dept['SEOKeywords'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none" placeholder="คีย์เวิร์ด 1, คีย์เวิร์ด 2">
											<p class="text-xs text-gray-400 mt-1">คำค้นหาของเว็บไซต์ ควรแบ่งแต่ละคำด้วยเครื่องหมายคอมม่า (,)</p>
										</div>
									</div>
                </div>
                <!-- Display Date -->
                <div class="glass-panel p-6 space-y-4">
									<h3 class="font-bold text-gray-800 border-b border-gray-100 pb-3 flex items-center gap-2">
										<i class="ph ph-calendar-blank text-primary"></i> กำหนดวันที่สร้างในการแสดงผล
									</h3>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-1">วันที่สร้าง</label>
										<input type="text" name="CreateDate" id="createDateInput" value="<?php echo !empty($dept['CreateDate']) ? date('d/m/Y', strtotime($dept['CreateDate'])) : date('d/m/Y'); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none bg-white">
									</div>
                </div>
            </div>
        </div>
        
        <!-- Bottom Actions -->
        <div class="border-t border-gray-100 pt-6 mt-8 flex justify-end gap-3">
            <a href="view.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 h-12 text-base font-semibold shadow-lg shadow-primary/30 flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> บันทึกข้อมูล
            </button>
        </div>
    </form>
</div>

<!-- Lightbox Modal -->
<div id="lightboxModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-[9999] hidden" onclick="closeLightbox()">
    <div class="relative max-w-4xl max-h-[85vh] p-4" onclick="event.stopPropagation()">
        <button type="button" onclick="closeLightbox()" class="absolute -top-10 -right-2 text-white hover:text-gray-300 text-3xl font-bold flex items-center justify-center w-8 h-8 rounded-full bg-black/40">&times;</button>
        <img id="lightboxImg" class="max-w-full max-h-[80vh] rounded-lg shadow-2xl object-contain" src="">
    </div>
</div>

<!-- Flatpickr JS & Locale -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date picker
    flatpickr("#createDateInput", {
        dateFormat: "d/m/Y",
        locale: "th",
        defaultDate: "<?php echo !empty($dept['CreateDate']) ? date('d/m/Y', strtotime($dept['CreateDate'])) : date('d/m/Y'); ?>"
    });
});

document.getElementById('imageFile').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('previewContainerLogo').style.display = 'block';
            document.getElementById('deleteLogoImage').value = '0';
        }
        reader.readAsDataURL(file);
    }
});

function removeLogoImage(e) {
    e.stopPropagation();
    document.getElementById('imageFile').value = '';
    document.getElementById('previewContainerLogo').style.display = 'none';
    document.getElementById('previewImg').src = '';
    document.getElementById('deleteLogoImage').value = '1';
}

document.getElementById('mapImageFile').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewMapImg').src = e.target.result;
            document.getElementById('previewContainerMap').style.display = 'block';
            document.getElementById('deleteMapImage').value = '0';
        }
        reader.readAsDataURL(file);
    }
});

function removeMapImage(e) {
    e.stopPropagation();
    document.getElementById('mapImageFile').value = '';
    document.getElementById('previewContainerMap').style.display = 'none';
    document.getElementById('previewMapImg').src = '';
    document.getElementById('deleteMapImage').value = '1';
}

function selectLayout(layout) {
    document.querySelectorAll('.select-layout-card').forEach(card => {
        card.classList.remove('border-primary', 'ring-2', 'ring-primary/20', 'bg-primary/5');
        card.classList.add('border-gray-200');
    });
    const activeCard = document.getElementById('layoutCard_' + layout);
    if (activeCard) {
        activeCard.classList.remove('border-gray-200');
        activeCard.classList.add('border-primary', 'ring-2', 'ring-primary/20', 'bg-primary/5');
        
        // Find input inside
        const radio = activeCard.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
    }
}

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

