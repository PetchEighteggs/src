<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดเรียงลำดับเมนูและเมนูย่อย';
include_once('../../includes/header.php');
require_once('../../includes/action_security.php');

$deptID = $_SESSION['ActiveDeptID'] ?? 0;
if (empty($deptID)) {
    echo "<script>window.location.href = '../index.php';</script>";
    exit;
}

// Check Permissions
$userGroupID = $_SESSION['GroupID'] ?? '';
$canView = false;
$canEdit = false;

if ($userGroupID === 'A') {
    $canView = true;
    $canEdit = true;
} else if ($userGroupID === 'S') {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../../includes/microsite_permission.php');
    $canView = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'menus', 'CanView');
    $canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'menus', 'CanEdit');
}

if (!$canView) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href = '../index.php';</script>";
    exit;
}
?>

<div class="glass-panel p-6 mb-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div>
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">จัดเรียงลำดับเมนูและเมนูย่อย</h2>
                <p class="text-sm text-gray-500 mt-1">ลากและวางรายการเพื่อสลับลำดับ ระบบจะบันทึกข้อมูลให้อัตโนมัติ</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Left Column: Main Menus -->
    <div class="lg:col-span-5 glass-panel p-5 flex flex-col h-[calc(100vh-250px)] min-h-[500px]">
        <h3 class="font-bold text-lg text-gray-800 mb-2 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">1</span> 
            จัดเรียงเมนูหลัก
        </h3>
        <p class="text-xs text-gray-500 mb-4 border-b pb-4">คลิก <i class="ph ph-list"></i> ค้างเพื่อเรียงลำดับ หรือคลิกที่ชื่อเพื่อดูเมนูย่อย</p>
        
        <div id="menuLoading" class="text-center py-10 hidden">
            <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
            <p class="text-gray-500 text-sm">กำลังโหลดข้อมูล...</p>
        </div>
        
        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
            <div id="menuSortableList" class="space-y-2"></div>
        </div>
    </div>

    <!-- Right Column: Submenus -->
    <div class="lg:col-span-7 glass-panel p-5 flex flex-col h-[calc(100vh-250px)] min-h-[500px]">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">2</span> 
                จัดเรียงเมนูย่อย
            </h3>
            <span id="selectedMenuBadge" class="hidden bg-blue-50 text-blue-700 text-xs px-3 py-1 rounded-full font-medium border border-blue-100"></span>
        </div>
        
        <p class="text-xs text-gray-500 mb-4 border-b pb-4" id="submenuHelpText">กรุณาเลือกเมนูหลักจากด้านซ้ายก่อน</p>

        <div id="submenuLoading" class="text-center py-10 hidden">
            <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
            <p class="text-gray-500 text-sm">กำลังโหลดข้อมูล...</p>
        </div>
        
        <div id="submenuEmpty" class="text-center py-16 flex-1 flex flex-col items-center justify-center border-2 border-dashed border-gray-100 rounded-xl bg-gray-50/50">
            <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4">
                <i class="ph ph-list-dashes text-3xl text-gray-300"></i>
            </div>
            <p class="text-gray-500 text-sm">คลิกเลือกเมนูหลักเพื่อดูและจัดเรียงเมนูย่อย</p>
        </div>

        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
            <div id="submenuSortableList" class="space-y-2 hidden"></div>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    .menu-item.active { border-color: #3b82f6; background-color: #eff6ff; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const menuList = document.getElementById('menuSortableList');
    const submenuList = document.getElementById('submenuSortableList');
    const menuLoading = document.getElementById('menuLoading');
    const submenuLoading = document.getElementById('submenuLoading');
    const submenuEmpty = document.getElementById('submenuEmpty');
    const selectedMenuBadge = document.getElementById('selectedMenuBadge');
    const submenuHelpText = document.getElementById('submenuHelpText');
    
    const canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
    const csrfToken = <?php echo json_encode(bofSecurityCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let currentMenuId = null;

    // Load Menus on start
    loadMenus();

    function loadMenus() {
        menuLoading.classList.remove('hidden');
        menuList.innerHTML = '';
        
        fetch(`ajax_get_menus.php`)
            .then(res => res.json())
            .then(data => {
                menuLoading.classList.add('hidden');
                if (data.status === 'success' && data.data.length > 0) {
                    renderMenus(data.data);
                }
            })
            .catch(err => {
                menuLoading.classList.add('hidden');
                Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลเมนูหลักได้', 'error');
            });
    }

    function renderMenus(items) {
        menuList.innerHTML = '';
        items.forEach((item) => {
            const div = document.createElement('div');
            const isActiveClass = item.IsActive === 'Y' ? '' : 'opacity-60';
            const statusBadge = item.IsActive === 'Y' ? '' : '<span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded ml-2">ปิดใช้งาน</span>';
            
            div.className = `menu-item ${isActiveClass} p-3 rounded-lg border shadow-sm flex items-center gap-3 transition-colors bg-white hover:bg-gray-50`;
            div.dataset.id = item.MenuID;
            
            div.innerHTML = `
                <div class="cursor-grab text-gray-300 hover:text-gray-500 p-1 flex-shrink-0 drag-handle">
                    <i class="ph ph-list text-xl"></i>
                </div>
                <div class="flex-1 min-w-0 cursor-pointer pt-1 pb-1 pr-2 flex items-center" onclick="selectMenu(${item.MenuID}, '${item.MenuNameTH.replace(/'/g, "\\'")}')">
                    <h4 class="font-medium text-sm text-gray-800 truncate">${item.MenuNameTH}</h4>
                    ${statusBadge}
                </div>
                <div class="flex-shrink-0 cursor-pointer" onclick="selectMenu(${item.MenuID}, '${item.MenuNameTH.replace(/'/g, "\\'")}')">
                    <i class="ph ph-caret-right text-gray-400"></i>
                </div>
            `;
            menuList.appendChild(div);
        });
        
        if (canEdit) {
            new Sortable(menuList, {
                animation: 150,
                ghostClass: 'bg-blue-50',
                handle: '.drag-handle',
                onEnd: function () {
                    saveMenuOrder();
                }
            });
        } else {
            document.querySelectorAll('.drag-handle').forEach(el => {
                el.classList.remove('cursor-grab');
                el.classList.add('cursor-not-allowed', 'opacity-50');
            });
        }
    }

    function selectMenu(menuId, menuName) {
        currentMenuId = menuId;
        
        // Update UI styling
        document.querySelectorAll('.menu-item').forEach(el => {
            if(el.dataset.id == menuId) {
                el.classList.add('active', 'ring-1', 'ring-blue-500');
            } else {
                el.classList.remove('active', 'ring-1', 'ring-blue-500');
            }
        });
        
        selectedMenuBadge.textContent = menuName;
        selectedMenuBadge.classList.remove('hidden');
        submenuHelpText.innerHTML = `ลาก <i class="ph ph-list"></i> เพื่อจัดเรียงเมนูย่อยใน <strong>${menuName}</strong>`;
        
        loadSubmenus(menuId);
    }

    function loadSubmenus(menuId) {
        submenuEmpty.classList.add('hidden');
        submenuList.classList.add('hidden');
        submenuLoading.classList.remove('hidden');
        
        fetch(`ajax_get_submenus.php?menu_id=${menuId}`)
            .then(res => res.json())
            .then(data => {
                submenuLoading.classList.add('hidden');
                if (data.status === 'success' && data.data.length > 0) {
                    renderSubmenus(data.data);
                    submenuList.classList.remove('hidden');
                } else {
                    submenuEmpty.classList.remove('hidden');
                    submenuEmpty.innerHTML = `
                        <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4">
                            <i class="ph ph-list-dashes text-3xl text-gray-300"></i>
                        </div>
                        <p class="text-gray-500 text-sm">ไม่พบข้อมูลเมนูย่อยในเมนูหลักนี้</p>
                    `;
                }
            })
            .catch(err => {
                submenuLoading.classList.add('hidden');
                Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลเมนูย่อยได้', 'error');
            });
    }

    function renderSubmenus(items) {
        submenuList.innerHTML = '';
        items.forEach((item) => {
            const div = document.createElement('div');
            const isActiveClass = item.IsActive === 'Y' ? '' : 'opacity-60';
            const statusBadge = item.IsActive === 'Y' ? '' : '<span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded">ปิดใช้งาน</span>';
            
            div.className = `p-3 rounded-lg border shadow-sm flex items-center gap-4 bg-white hover:bg-gray-50 transition-colors ${isActiveClass}`;
            div.dataset.id = item.SubMenuID;
            
            div.innerHTML = `
                <div class="cursor-grab drag-handle text-gray-300 hover:text-gray-500 p-2">
                    <i class="ph ph-list text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h4 class="font-medium text-sm text-gray-800 truncate">${item.SubMenuNameTH}</h4>
                        ${statusBadge}
                    </div>
                    ${item.SubMenuNameEN ? `<p class="text-xs text-gray-500 truncate mt-0.5">${item.SubMenuNameEN}</p>` : ''}
                </div>
            `;
            submenuList.appendChild(div);
        });
        
        if (canEdit) {
            new Sortable(submenuList, {
                animation: 150,
                ghostClass: 'bg-blue-50',
                handle: '.drag-handle',
                onEnd: function () {
                    saveSubmenuOrder();
                }
            });
        } else {
            document.querySelectorAll('#submenuSortableList .drag-handle').forEach(el => {
                el.classList.remove('cursor-grab');
                el.classList.add('cursor-not-allowed', 'opacity-50');
            });
        }
    }

    function saveMenuOrder() {
        if (!canEdit) return;
        
        const items = Array.from(menuList.children);
        const orderData = items.map((item, index) => ({
            id: item.dataset.id,
            order: index + 1
        }));
        
        fetch('ajax_save_menu_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ order: orderData })
        }).then(showToast).catch(showError);
    }

    function saveSubmenuOrder() {
        if (!canEdit) return;
        
        const items = Array.from(submenuList.children);
        const orderData = items.map((item, index) => ({
            id: item.dataset.id,
            order: index + 1
        }));
        
        fetch('ajax_save_submenu_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ order: orderData })
        }).then(showToast).catch(showError);
    }

    function showToast(res) {
        res.json().then(data => {
            if (data.status === 'success') {
                Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 }).fire({ icon: 'success', title: 'บันทึกลำดับเรียบร้อย' });
            } else {
                Swal.fire('Error', data.message || 'เกิดข้อผิดพลาดในการบันทึก', 'error');
            }
        });
    }
    
    function showError(err) {
        Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
    }
</script>
<?php include_once('../../includes/footer.php'); ?>
