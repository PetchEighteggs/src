<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดเรียงลำดับข่าวประชาสัมพันธ์';
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
    $canView = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'news', 'CanView');
    $canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'news', 'CanEdit');
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
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2"><?php echo $pageTitle; ?></h2>
                <p class="text-sm text-gray-500 mt-1">ลากและวางรายการเพื่อสลับลำดับ ระบบจะบันทึกข้อมูลให้อัตโนมัติ</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Left Column: Groups -->
    <div class="lg:col-span-5 glass-panel p-5 flex flex-col h-[calc(100vh-250px)] min-h-[500px]">
        <h3 class="font-bold text-lg text-gray-800 mb-2 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">1</span> 
            จัดเรียงหมวดหมู่ข่าว
        </h3>
        <p class="text-xs text-gray-500 mb-4 border-b pb-4">คลิก <i class="ph ph-list"></i> ค้างเพื่อเรียงลำดับ หรือคลิกที่ชื่อเพื่อดูรายการย่อย</p>
        
        <div id="groupLoading" class="text-center py-10 hidden">
            <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
            <p class="text-gray-500 text-sm">กำลังโหลดข้อมูล...</p>
        </div>
        
        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
            <div id="groupSortableList" class="space-y-2"></div>
        </div>
    </div>

    <!-- Right Column: Items -->
    <div class="lg:col-span-7 glass-panel p-5 flex flex-col h-[calc(100vh-250px)] min-h-[500px]">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">2</span> 
                จัดเรียงรายการข่าว
            </h3>
            <span id="selectedGroupBadge" class="hidden bg-blue-50 text-blue-700 text-xs px-3 py-1 rounded-full font-medium border border-blue-100"></span>
        </div>
        
        <p class="text-xs text-gray-500 mb-4 border-b pb-4" id="itemHelpText">กรุณาเลือกหมวดหมู่ข่าวจากด้านซ้ายก่อน</p>

        <div id="itemLoading" class="text-center py-10 hidden">
            <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
            <p class="text-gray-500 text-sm">กำลังโหลดข้อมูล...</p>
        </div>
        
        <div id="itemEmpty" class="text-center py-16 flex-1 flex flex-col items-center justify-center border-2 border-dashed border-gray-100 rounded-xl bg-gray-50/50">
            <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4">
                <i class="ph ph-list-dashes text-3xl text-gray-300"></i>
            </div>
            <p class="text-gray-500 text-sm">คลิกเลือกหมวดหมู่ข่าวเพื่อดูและจัดเรียงรายการข่าว</p>
        </div>

        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
            <div id="itemSortableList" class="space-y-2 hidden"></div>
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
    const csrfToken = <?php echo json_encode(bofSecurityCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const groupList = document.getElementById('groupSortableList');
    const itemList = document.getElementById('itemSortableList');
    const groupLoading = document.getElementById('groupLoading');
    const itemLoading = document.getElementById('itemLoading');
    const itemEmpty = document.getElementById('itemEmpty');
    const selectedGroupBadge = document.getElementById('selectedGroupBadge');
    const itemHelpText = document.getElementById('itemHelpText');
    
    const canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
    let currentGroupId = null;

    loadGroups();

    function loadGroups() {
        groupLoading.classList.remove('hidden');
        groupList.innerHTML = '';
        
        fetch(`ajax_get_groups.php`)
            .then(res => res.json())
            .then(data => {
                groupLoading.classList.add('hidden');
                if (data.status === 'success' && data.data.length > 0) {
                    renderGroups(data.data);
                }
            })
            .catch(err => {
                groupLoading.classList.add('hidden');
                Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลได้', 'error');
            });
    }

    function renderGroups(items) {
        groupList.innerHTML = '';
        items.forEach((item) => {
            const div = document.createElement('div');
            const isActiveClass = item.IsActive === 'Y' ? '' : 'opacity-60';
            const statusBadge = item.IsActive === 'Y' ? '' : '<span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded ml-2">ปิดใช้งาน</span>';
            
            div.className = `menu-item ${isActiveClass} p-3 rounded-lg border shadow-sm flex items-center gap-3 transition-colors bg-white hover:bg-gray-50`;
            div.dataset.id = item.GroupID;
            
            let title = item.GroupNameTH || 'ไม่มีชื่อ';
            
            div.innerHTML = `
                <div class="cursor-grab text-gray-300 hover:text-gray-500 p-1 flex-shrink-0 drag-handle">
                    <i class="ph ph-list text-xl"></i>
                </div>
                <div class="flex-1 min-w-0 cursor-pointer pt-1 pb-1 pr-2 flex items-center" onclick="selectGroup(${item.GroupID}, '${title.replace(/'/g, "\\'")}')">
                    <h4 class="font-medium text-sm text-gray-800 truncate">${title}</h4>
                    ${statusBadge}
                </div>
                <div class="flex-shrink-0 cursor-pointer" onclick="selectGroup(${item.GroupID}, '${title.replace(/'/g, "\\'")}')">
                    <i class="ph ph-caret-right text-gray-400"></i>
                </div>
            `;
            groupList.appendChild(div);
        });
        
        if (canEdit) {
            new Sortable(groupList, {
                animation: 150,
                ghostClass: 'bg-blue-50',
                handle: '.drag-handle',
                onEnd: function () {
                    saveGroupOrder();
                }
            });
        } else {
            document.querySelectorAll('.drag-handle').forEach(el => {
                el.classList.remove('cursor-grab');
                el.classList.add('cursor-not-allowed', 'opacity-50');
            });
        }
    }

    function selectGroup(groupId, groupName) {
        currentGroupId = groupId;
        
        document.querySelectorAll('.menu-item').forEach(el => {
            if(el.dataset.id == groupId) {
                el.classList.add('active', 'ring-1', 'ring-blue-500');
            } else {
                el.classList.remove('active', 'ring-1', 'ring-blue-500');
            }
        });
        
        selectedGroupBadge.textContent = groupName;
        selectedGroupBadge.classList.remove('hidden');
        itemHelpText.innerHTML = `ลาก <i class="ph ph-list"></i> เพื่อจัดเรียงใน <strong>${groupName}</strong>`;
        
        loadItems(groupId);
    }

    function loadItems(groupId) {
        itemEmpty.classList.add('hidden');
        itemList.classList.add('hidden');
        itemLoading.classList.remove('hidden');
        
        fetch(`ajax_get_items.php?group_id=${groupId}`)
            .then(res => res.json())
            .then(data => {
                itemLoading.classList.add('hidden');
                if (data.status === 'success' && data.data.length > 0) {
                    renderItems(data.data);
                    itemList.classList.remove('hidden');
                } else {
                    itemEmpty.classList.remove('hidden');
                    itemEmpty.innerHTML = `
                        <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4">
                            <i class="ph ph-list-dashes text-3xl text-gray-300"></i>
                        </div>
                        <p class="text-gray-500 text-sm">ไม่พบข้อมูลในหมวดหมู่นี้</p>
                    `;
                }
            })
            .catch(err => {
                itemLoading.classList.add('hidden');
                Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลรายการได้', 'error');
            });
    }

    function renderItems(items) {
        itemList.innerHTML = '';
        items.forEach((item) => {
            const div = document.createElement('div');
            const isActiveClass = (item.IsActive === 'Y' || typeof item.IsActive === 'undefined') ? '' : 'opacity-60';
            const statusBadge = (item.IsActive === 'Y' || typeof item.IsActive === 'undefined') ? '' : '<span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded">ปิดใช้งาน</span>';
            
            div.className = `p-3 rounded-lg border shadow-sm flex items-center gap-4 bg-white hover:bg-gray-50 transition-colors ${isActiveClass}`;
            div.dataset.id = item.NewsID;
            
            let titleTH = item.Title_TH || 'ไม่มีชื่อ';
            let titleEN = item.Title_EN || '';
            
            div.innerHTML = `
                <div class="cursor-grab drag-handle text-gray-300 hover:text-gray-500 p-2">
                    <i class="ph ph-list text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h4 class="font-medium text-sm text-gray-800 truncate">${titleTH}</h4>
                        ${statusBadge}
                    </div>
                    ${titleEN ? `<p class="text-xs text-gray-500 truncate mt-0.5">${titleEN}</p>` : ''}
                </div>
            `;
            itemList.appendChild(div);
        });
        
        if (canEdit) {
            new Sortable(itemList, {
                animation: 150,
                ghostClass: 'bg-blue-50',
                handle: '.drag-handle',
                onEnd: function () {
                    saveItemOrder();
                }
            });
        } else {
            document.querySelectorAll('#itemSortableList .drag-handle').forEach(el => {
                el.classList.remove('cursor-grab');
                el.classList.add('cursor-not-allowed', 'opacity-50');
            });
        }
    }

    function saveGroupOrder() {
        if (!canEdit) return;
        
        const items = Array.from(groupList.children);
        const orderData = items.map((item, index) => ({
            id: item.dataset.id,
            order: index + 1
        }));
        
        fetch('ajax_save_group_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ order: orderData })
        }).then(showToast).catch(showError);
    }

    function saveItemOrder() {
        if (!canEdit) return;
        
        const items = Array.from(itemList.children);
        const orderData = items.map((item, index) => ({
            id: item.dataset.id,
            order: index + 1
        }));
        
        fetch('ajax_save_item_order.php', {
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
