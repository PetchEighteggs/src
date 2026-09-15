<?php
$pageTitle = "จัดการผู้ใช้งานระบบ";
include_once('../includes/header.php');

$id = mosGetParam($_FORM, 'id', '');
$row = [
    'StaffID' => '',
    'Username' => '',
    'Password' => '',
    'Name' => '',
    'Email' => '',
    'Tel' => '',
    'Tel' => '',
    'GroupID' => 'S',
    'SetGroupID' => 0,
    'Status' => 'Y'
];

if ($id != '') {
    $stmt = $DB->prepare("SELECT * FROM staff WHERE StaffID = ? AND DelData = 'N'");
    $stmt->execute([$id]);
    if ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row = $data;
        $row['Password'] = ''; // Don't show hashed password
    }
}

// -------- Load Microsite Menus from DB (microsite_menu_items) --------
$micrositeMenus = [];
try {
    $stmtMenus = $DB->prepare("SELECT MenuID AS `id`, MenuKey AS `key`, MenuNameTH AS `label`, MenuIcon AS `icon` FROM microsite_menu_items WHERE IsActive = 'Y' ORDER BY MenuOrder ASC");
    $stmtMenus->execute();
    $micrositeMenus = $stmtMenus->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fallback ถ้าตาราง microsite_menu_items ยังไม่ได้ migrate
if (empty($micrositeMenus)) {
    $micrositeMenus = [
        ['id' => 1,  'key' => 'settings',   'label' => 'ตั้งค่าหน่วยงาน',              'icon' => 'ph ph-gear'],
        ['id' => 2,  'key' => 'menus',      'label' => 'จัดการเมนู',                    'icon' => 'ph ph-list'],
        ['id' => 3,  'key' => 'intro',      'label' => 'Intro',                         'icon' => 'ph ph-presentation-chart'],
        ['id' => 4,  'key' => 'popup',      'label' => 'Popup',                         'icon' => 'ph ph-browser'],
        ['id' => 5,  'key' => 'topgraphic', 'label' => 'Top Graphic',                   'icon' => 'ph ph-image'],
        ['id' => 6,  'key' => 'executives', 'label' => 'ผู้บริหาร',                     'icon' => 'ph ph-users-three'],
        ['id' => 7,  'key' => 'about',      'label' => 'เกี่ยวกับหน่วยงาน',              'icon' => 'ph ph-info'],
        ['id' => 8,  'key' => 'news',       'label' => 'ข่าวประชาสัมพันธ์',              'icon' => 'ph ph-newspaper'],
        ['id' => 9,  'key' => 'banner',     'label' => 'ช่องทางประชาสัมพันธ์ (Banner)',  'icon' => 'ph ph-megaphone'],
        ['id' => 10, 'key' => 'download',   'label' => 'ดาวน์โหลด',                     'icon' => 'ph ph-download-simple'],
        ['id' => 11, 'key' => 'multimedia', 'label' => 'สื่อมัลติมีเดีย',                'icon' => 'ph ph-video'],
        ['id' => 12, 'key' => 'gallery',    'label' => 'อัลบั้มภาพ',                     'icon' => 'ph ph-images'],
        ['id' => 13, 'key' => 'contact',    'label' => 'ติดต่อหน่วยงาน',                 'icon' => 'ph ph-phone'],
        ['id' => 14, 'key' => 'sitemap',    'label' => 'แผนผังเว็บไซต์',                 'icon' => 'ph ph-tree-structure'],
    ];
}


// -------- Load Existing Selected Dept & Menu Permissions --------
$menuPermissions    = [];
$existingAccessDeptID = 0;
if ($id != '') {
    try {
        $stmtAcc2 = $DB->prepare("SELECT DeptID FROM staff_microsite_access WHERE StaffID = ? LIMIT 1");
        $stmtAcc2->execute([$id]);
        $existingAccessDeptID = (int)$stmtAcc2->fetchColumn();

        $stmtMP = $DB->prepare("SELECT MenuID, CanView, CanAdd, CanEdit, CanDelete, CanApprove FROM staff_microsite_menu_permission WHERE StaffID = ?");
        $stmtMP->execute([$id]);
        $mpRows = $stmtMP->fetchAll(PDO::FETCH_ASSOC);
        foreach ($mpRows as $mp) {
            $menuPermissions[(int)$mp['MenuID']] = $mp;
        }
    } catch (PDOException $e) {}
}

// -------- Load Departments --------
$depts = [];
try {
    $stmtDept = $DB->prepare("SELECT DeptID, NameTH FROM microsite_departments WHERE IsActive = 'Y' ORDER BY NameTH ASC");
    $stmtDept->execute();
    $depts = $stmtDept->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<div class="glass-panel p-6">
    <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
        <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <h2 class="text-xl font-bold text-gray-800 flex-1">
            <?php echo $id == '' ? 'เพิ่มผู้ใช้งาน' : 'แก้ไขผู้ใช้งาน'; ?>
        </h2>
        
        <!-- Status Toggle -->
        <div class="flex items-center gap-3">
            <span class="text-sm font-medium text-gray-600">สถานะการใช้งาน</span>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="Status" value="N">
                <input type="checkbox" name="Status" value="Y" class="sr-only peer" <?php echo (!isset($row['Status']) || $row['Status'] == 'Y') ? 'checked' : ''; ?>>
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
            </label>
        </div>
    </div>

    <form action="action.php" method="POST" class="space-y-6" onsubmit="showLoading()">
        <input type="hidden" name="action" value="<?php echo $id == '' ? 'add' : 'edit'; ?>">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">ชื่อผู้ใช้งาน<span class="text-red-500">*</span></label>
                    <input type="text" name="Username" value="<?php echo htmlspecialchars($row['Username']); ?>" required <?php echo $id != '' ? 'readonly class="w-full px-4 py-3 border border-gray-200 rounded-lg bg-gray-100 text-gray-500"' : 'class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:border-[#0088ff]"'; ?>>
                    <?php if($id != ''): ?><p class="text-xs text-gray-500 mt-1">ไม่สามารถเปลี่ยนชื่อผู้ใช้ได้</p><?php endif; ?>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">รหัสผ่าน <?php echo $id == '' ? '<span class="text-red-500">*</span>' : ''; ?></label>
                    <input type="password" name="Password" placeholder="<?php echo $id != '' ? 'ปล่อยว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน' : ''; ?>" <?php echo $id == '' ? 'required' : ''; ?> class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:border-[#0088ff]">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">ชื่อ - นามสกุล <span class="text-red-500">*</span></label>
                    <input type="text" name="Name" value="<?php echo htmlspecialchars($row['Name']); ?>" required class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:border-[#0088ff]">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">เบอร์โทรศัพท์</label>
                        <input type="text" name="Tel" value="<?php echo htmlspecialchars($row['Tel']); ?>" class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:border-[#0088ff]">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">อีเมล</label>
                        <input type="email" name="Email" value="<?php echo htmlspecialchars($row['Email']); ?>" class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:border-[#0088ff]">
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 space-y-6">
                    <div class="flex items-center gap-2 mb-2">
                      <i class="ph ph-shield-check text-blue-600 text-xl"></i>
                      <h3 class="text-lg font-bold text-blue-900">กำหนดสิทธิ์การใช้งาน</h3>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">ระดับการเข้าถึง</label>
                        <select name="GroupID" id="GroupIDSelect" onchange="toggleSetGroup()" class="w-full px-4 py-3 border border-gray-200 rounded-lg bg-white text-gray-700 focus:outline-none focus:border-[#0088ff] appearance-none" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 1rem top 50%; background-size: 0.65rem auto;">
                            <option value="S" <?php echo $row['GroupID'] == 'S' ? 'selected' : ''; ?>>เจ้าหน้าที่ทั่วไป</option>
                            <option value="A" <?php echo $row['GroupID'] == 'A' ? 'selected' : ''; ?>>ผู้ดูแลระบบสูงสุด</option>
                        </select>
                    </div>

                    <div id="setGroupContainer" style="<?php echo $row['GroupID'] == 'S' ? '' : 'display: none;'; ?>">
                        <label class="block text-sm font-bold text-gray-700 mb-2">กลุ่มผู้ใช้งานระบบ</label>
                        <select name="SetGroupID" id="SetGroupID" onchange="toggleSetGroup()" class="w-full px-4 py-3 border border-gray-200 rounded-lg bg-white text-gray-700 focus:outline-none focus:border-[#0088ff] appearance-none" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 1rem top 50%; background-size: 0.65rem auto;">
                            <option value="0">-- เลือกกลุ่มสิทธิ์ --</option>
                            <?php
                            try {
                                $stmtGroup = $DB->prepare("SELECT * FROM staff_set_group WHERE DelData = 'N' AND Approved = 'Y' ORDER BY SetOrder ASC, SetGroupID ASC");
                                $stmtGroup->execute();
                                while ($g = $stmtGroup->fetch(PDO::FETCH_ASSOC)) {
                                  $selected = $row['SetGroupID'] == $g['SetGroupID'] ? 'selected' : '';
                                  echo '<option value="'.$g['SetGroupID'].'" '.$selected.'>'.htmlspecialchars($g['SetGroupName']).'</option>';
                                }
                            } catch(PDOException $e) {}
                            ?>
                        </select>
                    </div>
                </div>

                <div id="micrositeAccessContainer" style="display: none;">
                    <!-- Summary Card (shown on main form) -->
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <i class="ph ph-buildings text-blue-600 text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-blue-500 font-medium uppercase tracking-wide">หน่วยงาน Microsite</p>
                                    <p id="msSummaryDept" class="text-sm font-bold text-blue-900 mt-0.5">
                                        <?php
                                        if ($existingAccessDeptID > 0) {
                                            $found = array_filter($depts, fn($d) => $d['DeptID'] == $existingAccessDeptID);
                                            echo $found ? htmlspecialchars(reset($found)['NameTH']) : 'ยังไม่ได้ตั้งค่า';
                                        } else { echo 'ยังไม่ได้ตั้งค่า'; }
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <button type="button" onclick="openMicrositeModal()"
                                class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                                <i class="ph ph-lock-key"></i> ตั้งค่าสิทธิ์
                            </button>
                        </div>
                        <!-- Mini permission summary badges -->
                        <div id="msSummaryBadges" class="mt-3 flex flex-wrap gap-1.5">
                            <?php if ($existingAccessDeptID > 0 && !empty($menuPermissions)): ?>
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full"><?= count($menuPermissions) ?> เมนู</span>
                            <?php else: ?>
                            <span class="text-xs text-blue-400 italic">กด "ตั้งค่าสิทธิ์" เพื่อเลือกหน่วยงานและกำหนดสิทธิ์</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden inputs that hold the actual dept + permission values for form submission -->
        <div id="msHiddenInputs"></div>

        <div class="border-t border-gray-100 pt-6 flex justify-end gap-4 mt-8">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center">
                <i class="ph ph-x mr-2"></i> ยกเลิก
            </a>
            <button type="submit" class="btn-primary px-8 py-3 text-lg">
                <i class="ph ph-floppy-disk mr-2"></i> บันทึกข้อมูล
            </button>
        </div>


<!-- ===================== MICROSITE PERMISSION MODAL ===================== -->
<div id="micrositeModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeMicrositeModal()"></div>

    <!-- Modal Panel -->
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">

        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white shrink-0">
            <div class="flex items-center gap-3">
                <i class="ph ph-buildings text-2xl"></i>
                <div>
                    <h3 class="text-lg font-bold">ตั้งค่าสิทธิ์หน่วยงาน</h3>
                    <p class="text-blue-200 text-xs mt-0.5">เลือกหน่วยงาน และกำหนดสิทธิ์การใช้งานแต่ละเมนู</p>
                </div>
            </div>
            <button type="button" onclick="closeMicrositeModal()" class="w-9 h-9 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="flex flex-1 overflow-hidden">

            <!-- LEFT: Dept List -->
            <div class="w-64 shrink-0 border-r border-gray-200 overflow-y-auto bg-gray-50">
                <div class="px-4 py-3 border-b border-gray-200">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">เลือกหน่วยงาน</p>
                </div>
                <?php if (empty($depts)): ?>
                    <div class="p-4 text-sm text-gray-400">ไม่พบข้อมูลหน่วยงาน</div>
                <?php else: ?>
                <div class="py-2">
                    <?php foreach ($depts as $d):
                        $isSelected = ($d['DeptID'] == $existingAccessDeptID && $existingAccessDeptID > 0);
                    ?>
                    <label class="ms-dept-label flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-blue-50 transition-colors <?= $isSelected ? 'bg-blue-50' : '' ?>" data-dept-id="<?= $d['DeptID'] ?>" data-dept-name="<?= htmlspecialchars($d['NameTH'], ENT_QUOTES) ?>">
                        <input type="radio" name="modal_dept" value="<?= $d['DeptID'] ?>"
                            <?= $isSelected ? 'checked' : '' ?>
                            class="w-4 h-4 text-blue-600 cursor-pointer"
                            onchange="onModalDeptChange(this)">
                        <span class="text-sm text-gray-800 leading-tight"><?= htmlspecialchars($d['NameTH']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: Permission Table -->
            <div class="flex-1 overflow-y-auto">
                <!-- No dept selected state -->
                <div id="msNoDeptMsg" class="flex flex-col items-center justify-center h-full text-center p-8 <?= $existingAccessDeptID > 0 ? 'hidden' : '' ?>">
                    <div class="w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center mb-4">
                        <i class="ph ph-arrow-left text-blue-400 text-2xl"></i>
                    </div>
                    <p class="text-gray-500 font-medium">เลือกหน่วยงานทางซ้ายก่อน</p>
                    <p class="text-gray-400 text-sm mt-1">จากนั้นกำหนดสิทธิ์เมนูจะปรากฏที่นี่</p>
                </div>

                <!-- Permission table -->
                <div id="msPermTable" class="<?= $existingAccessDeptID > 0 ? '' : 'hidden' ?>">
                    <!-- Table toolbar -->
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-white sticky top-0 z-10">
                        <div class="flex items-center gap-2">
                            <i class="ph ph-lock-key text-blue-600"></i>
                            <span class="text-sm font-bold text-gray-700">สิทธิ์การใช้งานเมนู</span>
                            <span id="msSelectedDeptBadge" class="ml-1 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium"></span>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" onclick="mSelectAllMenus(true)" class="text-xs text-blue-600 hover:underline font-medium">เลือกทั้งหมด</button>
                            <span class="text-gray-300">|</span>
                            <button type="button" onclick="mSelectAllMenus(false)" class="text-xs text-red-500 hover:underline font-medium">ล้างทั้งหมด</button>
                        </div>
                    </div>
                    <div class="px-5 py-3">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500 border-b border-gray-200">
                                    <th class="pb-2 text-left font-semibold">เมนู</th>
                                    <th class="pb-2 text-center font-semibold w-12">ดู</th>
                                    <th class="pb-2 text-center font-semibold w-12">เพิ่ม</th>
                                    <th class="pb-2 text-center font-semibold w-12">แก้ไข</th>
                                    <th class="pb-2 text-center font-semibold w-12">ลบ</th>
                                    <th class="pb-2 text-center font-semibold w-16">อนุมัติ</th>
                                    <th class="pb-2 text-center font-semibold w-16">ทั้งหมด</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php foreach ($micrositeMenus as $menu):
                                    $mid       = (int)($menu['id'] ?? 0);
                                    $p         = $menuPermissions[$mid] ?? ['CanView' => 'N', 'CanAdd' => 'N', 'CanEdit' => 'N', 'CanDelete' => 'N', 'CanApprove' => 'N'];
                                    $isView    = $p['CanView']    == 'Y';
                                    $isAdd     = $p['CanAdd']     == 'Y';
                                    $isEdit    = $p['CanEdit']    == 'Y';
                                    $isDel     = $p['CanDelete']  == 'Y';
                                    $isApprove = $p['CanApprove'] == 'Y';
                                    $isAll     = $isView && $isAdd && $isEdit && $isDel && $isApprove;
                                ?>
                                <tr class="hover:bg-blue-50/40 transition-colors group">
                                    <td class="py-2.5">
                                        <div class="flex items-center gap-2">
                                            <i class="<?= htmlspecialchars($menu['icon'] ?? 'ph ph-layout') ?> text-gray-400 group-hover:text-blue-500 transition-colors"></i>
                                            <span class="font-medium text-gray-700"><?= htmlspecialchars($menu['label']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center"><input type="checkbox" name="microsite_perm[<?= $mid ?>][view]"    value="Y" class="w-4 h-4 rounded border-gray-300 text-blue-600 cursor-pointer mperm-row-<?= $mid ?>" <?= $isView    ? 'checked' : '' ?> onclick="mCheckAllRow(<?= $mid ?>)"></td>
                                    <td class="text-center"><input type="checkbox" name="microsite_perm[<?= $mid ?>][add]"     value="Y" class="w-4 h-4 rounded border-gray-300 text-blue-600 cursor-pointer mperm-row-<?= $mid ?>" <?= $isAdd     ? 'checked' : '' ?> onclick="mCheckAllRow(<?= $mid ?>)"></td>
                                    <td class="text-center"><input type="checkbox" name="microsite_perm[<?= $mid ?>][edit]"    value="Y" class="w-4 h-4 rounded border-gray-300 text-blue-600 cursor-pointer mperm-row-<?= $mid ?>" <?= $isEdit    ? 'checked' : '' ?> onclick="mCheckAllRow(<?= $mid ?>)"></td>
                                    <td class="text-center"><input type="checkbox" name="microsite_perm[<?= $mid ?>][del]"     value="Y" class="w-4 h-4 rounded border-gray-300 text-blue-600 cursor-pointer mperm-row-<?= $mid ?>" <?= $isDel     ? 'checked' : '' ?> onclick="mCheckAllRow(<?= $mid ?>)"></td>
                                    <td class="text-center"><input type="checkbox" name="microsite_perm[<?= $mid ?>][approve]" value="Y" class="w-4 h-4 rounded border-gray-300 text-blue-600 cursor-pointer mperm-row-<?= $mid ?>" <?= $isApprove ? 'checked' : '' ?> onclick="mCheckAllRow(<?= $mid ?>)"></td>
                                    <td class="text-center"><input type="checkbox" class="w-4 h-4 rounded border-gray-300 text-indigo-600 cursor-pointer mperm-all-<?= $mid ?>" <?= $isAll ? 'checked' : '' ?> onclick="mToggleAllRow(this, <?= $mid ?>)"></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50 shrink-0">
            <p class="text-xs text-gray-400"><i class="ph ph-info"></i> การตั้งค่าจะมีผลเมื่อบันทึกสำเร็จเท่านั้น</p>
            <div class="flex gap-3">
                <button type="button" onclick="closeMicrositeModal()" class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg text-sm transition-colors">
                    ปิดหน้าต่าง
                </button>
                <button type="button" onclick="confirmMicrositeModal()" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm transition-colors flex items-center gap-2">
                    <i class="ph ph-check"></i> ยืนยันการตั้งค่า
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden input for accessible_dept (from modal) -->
<input type="hidden" name="accessible_dept" id="accessible_dept_hidden" value="<?= $existingAccessDeptID > 0 ? $existingAccessDeptID : '' ?>">

    </form>
</div>

<script>
function toggleSetGroup() {
    var groupID        = document.getElementById('GroupIDSelect').value;
    var container      = document.getElementById('setGroupContainer');
    var select         = document.getElementById('SetGroupID');
    var microContainer = document.getElementById('micrositeAccessContainer');

    if (groupID === 'S') {
        container.style.display = 'block';
        select.required = true;
        var selectedText = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text.trim() : '';
        if (selectedText === 'เว็บไซต์หน่วยงาน') {
            microContainer.style.display = 'block';
        } else {
            microContainer.style.display = 'none';
        }
    } else {
        container.style.display = 'none';
        select.required = false;
        select.value = '0';
        microContainer.style.display = 'none';
    }
}

// ---- Modal functions ----
function openMicrositeModal() {
    var modal = document.getElementById('micrositeModal');
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeMicrositeModal() {
    document.getElementById('micrositeModal').style.display = 'none';
    document.body.style.overflow = '';
}

function confirmMicrositeModal() {
    var selected = document.querySelector('input[name="modal_dept"]:checked');
    if (!selected) {
        alert('กรุณาเลือกหน่วยงานก่อน');
        return;
    }
    var deptId   = selected.value;
    var label    = selected.closest('label');
    var deptName = label ? label.dataset.deptName : deptId;

    // Sync hidden input for form submission
    document.getElementById('accessible_dept_hidden').value = deptId;

    // Sync permission checkboxes to hidden inputs in the form
    var hiddenContainer = document.getElementById('msHiddenInputs');
    hiddenContainer.innerHTML = '';
    document.querySelectorAll('#msPermTable input[type="checkbox"]:checked').forEach(function(cb) {
        if (cb.name) {
            var hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = cb.name;
            hiddenInput.value = cb.value;
            hiddenContainer.appendChild(hiddenInput);
        }
    });

    // Update summary on main form
    document.getElementById('msSummaryDept').textContent = deptName;

    // Count checked menus for badge
    var checkedMenus = document.querySelectorAll('#msPermTable input[type="checkbox"]:checked').length;
    var badge = document.getElementById('msSummaryBadges');
    badge.innerHTML = checkedMenus > 0
        ? '<span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">' + checkedMenus + ' รายการที่เลือก</span>'
        : '<span class="text-xs text-blue-400 italic">ไม่ได้เลือกสิทธิ์ใด</span>';

    closeMicrositeModal();
}

function onModalDeptChange(radio) {
    var label    = radio.closest('label');
    var deptName = label ? label.dataset.deptName : '';

    // Highlight selected row
    document.querySelectorAll('.ms-dept-label').forEach(function(l) {
        l.classList.remove('bg-blue-50');
    });
    if (label) label.classList.add('bg-blue-50');

    // Show permission table
    document.getElementById('msNoDeptMsg').classList.add('hidden');
    document.getElementById('msPermTable').classList.remove('hidden');

    // Update badge in table header
    var badge = document.getElementById('msSelectedDeptBadge');
    if (badge) badge.textContent = deptName;

    // Reset all checkboxes
    document.querySelectorAll('#msPermTable input[type="checkbox"]').forEach(function(cb) { cb.checked = false; });
    // Update "all" checkboxes to reflect unchecked state
    document.querySelectorAll('#msPermTable [class*="mperm-all-"]').forEach(function(cb) { cb.checked = false; });
}

function mToggleAllRow(source, mk) {
    document.querySelectorAll('.mperm-row-' + mk).forEach(function(cb) { cb.checked = source.checked; });
}

function mCheckAllRow(mk) {
    var checkboxes = document.querySelectorAll('.mperm-row-' + mk);
    var allChecked = true;
    checkboxes.forEach(function(cb) { if (!cb.checked) allChecked = false; });
    var allBox = document.querySelector('.mperm-all-' + mk);
    if (allBox) allBox.checked = allChecked;
}

function mSelectAllMenus(state) {
    document.querySelectorAll('#msPermTable input[type="checkbox"]').forEach(function(cb) { cb.checked = state; });
}

// Close modal on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMicrositeModal();
});

document.addEventListener('DOMContentLoaded', function () {
    toggleSetGroup();

    // Initial sync of permissions to hidden inputs
    // (Protects against the case where modal gets moved out of form but not confirmed)
    var hiddenContainer = document.getElementById('msHiddenInputs');
    if (hiddenContainer) {
        hiddenContainer.innerHTML = '';
        document.querySelectorAll('#msPermTable input[type="checkbox"]:checked').forEach(function(cb) {
            if (cb.name) {
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = cb.name;
                hiddenInput.value = cb.value;
                hiddenContainer.appendChild(hiddenInput);
            }
        });
    }

    // Initialize badge if dept already selected
    var deptHidden = document.getElementById('accessible_dept_hidden');
    if (deptHidden && deptHidden.value) {
        var checked = document.querySelectorAll('#msPermTable input[type="checkbox"]:checked').length;
        var badge = document.getElementById('msSummaryBadges');
        if (badge && checked > 0) {
            badge.innerHTML = '<span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">' + checked + ' รายการที่เลือก</span>';
        }
        // Set badge in modal table header
        var deptBadge = document.getElementById('msSelectedDeptBadge');
        var summaryDept = document.getElementById('msSummaryDept');
        if (deptBadge && summaryDept) deptBadge.textContent = summaryDept.textContent;
    }
});
</script>

<?php include_once('../includes/footer.php'); ?>