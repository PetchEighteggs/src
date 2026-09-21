<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดเรียงลำดับดาวน์โหลดเอกสาร';
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
    $canView = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'download', 'CanView');
    $canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'download', 'CanEdit');
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
    <div class="lg:col-span-4 glass-panel p-5 flex flex-col h-[calc(100vh-250px)] min-h-[500px]">
        <h3 class="font-bold text-base text-gray-800 mb-2 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">1</span> 
            หมวดหมู่หลัก
        </h3>
        <p class="text-[11px] text-gray-500 mb-4 border-b pb-4">ลากสลับลำดับ หรือคลิกเพื่อดูหมวดหมู่ย่อย</p>
        
        <div id="groupLoading" class="text-center py-10 hidden">
            <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
        </div>
        
        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
            <div id="groupSortableList" class="space-y-2"></div>
        </div>
    </div>

    <!-- Middle Column: Sub Groups -->
    <div class="lg:col-span-4 glass-panel p-5 flex flex-col h-[calc(100vh-250px)] min-h-[500px]">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-bold text-base text-gray-800 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">2</span> 
                หมวดหมู่ย่อย
            </h3>
        </div>
        
        <div class="mb-4 border-b pb-4 h-[25px]">
            <span id="selectedGroupBadge" class="hidden bg-blue-50 text-blue-700 text-[11px] px-2 py-1 rounded font-medium border border-blue-100 truncate w-full inline-block"></span>
        </div>

        <div id="subgroupLoading" class="text-center py-10 hidden">
            <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
        </div>
        
        <div id="subgroupEmpty" class="text-center py-16 flex-1 flex flex-col items-center justify-center border-2 border-dashed border-gray-100 rounded-xl bg-gray-50/50">
            <i class="ph ph-list-dashes text-2xl text-gray-300 mb-2"></i>
            <p class="text-gray-500 text-xs px-4">เลือกหมวดหมู่หลัก</p>
        </div>

        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
            <div id="subgroupSortableList" class="space-y-2 hidden"></div>
        </div>
    </div>
    
    <!-- Right Column: Files -->
    <div class="lg:col-span-4 glass-panel p-5 flex flex-col h-[calc(100vh-250px)] min-h-[500px]">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-bold text-base text-gray-800 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">3</span> 
                รายการเนื้อหาไฟล์
            </h3>
        </div>
        
        <div class="mb-4 border-b pb-4 h-[25px]">
            <span id="selectedSubgroupBadge" class="hidden bg-purple-50 text-purple-700 text-[11px] px-2 py-1 rounded font-medium border border-purple-100 truncate w-full inline-block"></span>
        </div>

        <div id="itemLoading" class="text-center py-10 hidden">
            <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
        </div>
        
        <div id="itemEmpty" class="text-center py-16 flex-1 flex flex-col items-center justify-center border-2 border-dashed border-gray-100 rounded-xl bg-gray-50/50">
            <i class="ph ph-file-text text-2xl text-gray-300 mb-2"></i>
            <p class="text-gray-500 text-xs px-4">เลือกหมวดหมู่ย่อย</p>
        </div>

        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
            <div id="itemSortableList" class="space-y-2 hidden"></div>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    .menu-item.active { border-color: #3b82f6; background-color: #eff6ff; }
    .subgroup-item.active { border-color: #a855f7; background-color: #faf5ff; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const csrfToken = <?php echo json_encode(bofSecurityCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const groupList = document.getElementById('groupSortableList');
    const subgroupList = document.getElementById('subgroupSortableList');
    const itemList = document.getElementById('itemSortableList');
    
    const groupLoading = document.getElementById('groupLoading');
    const subgroupLoading = document.getElementById('subgroupLoading');
    const itemLoading = document.getElementById('itemLoading');
    
    const subgroupEmpty = document.getElementById('subgroupEmpty');
    const itemEmpty = document.getElementById('itemEmpty');
    
    const selectedGroupBadge = document.getElementById('selectedGroupBadge');
    const selectedSubgroupBadge = document.getElementById('selectedSubgroupBadge');
    
    const canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
    
    let currentGroupId = null;
    let currentSubgroupId = null;

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
            
            div.className = `menu-item ${isActiveClass} p-2 rounded-lg border shadow-sm flex items-center gap-2 transition-colors bg-white hover:bg-gray-50`;
            div.dataset.id = item.GroupID;
            
            let title = item.GroupNameTH || 'ไม่มีชื่อ';
            
            div.innerHTML = `
                <div class="cursor-grab text-gray-300 hover:text-gray-500 flex-shrink-0 drag-handle">
                    <i class="ph ph-dots-six-vertical text-xl"></i>
                </div>
                <div class="flex-1 min-w-0 cursor-pointer py-1 flex items-center" onclick="selectGroup(${item.GroupID}, '${title.replace(/'/g, "\\'")}')">
                    <h4 class="font-medium text-xs text-gray-800 truncate">${title}</h4>
                </div>
                <div class="flex-shrink-0 cursor-pointer" onclick="selectGroup(${item.GroupID}, '${title.replace(/'/g, "\\'")}')">
                    <i class="ph ph-caret-right text-gray-400 text-sm"></i>
                </div>
            `;
            groupList.appendChild(div);
        });
        
        if (canEdit) {
            new Sortable(groupList, {
                animation: 150,
                ghostClass: 'bg-blue-50',
                handle: '.drag-handle',
                onEnd: function () { saveGroupOrder(); }
            });
        }
    }

    function selectGroup(groupId, groupName) {
        currentGroupId = groupId;
        currentSubgroupId = null;
        
        // Update UI
        document.querySelectorAll('.menu-item').forEach(el => {
            if(el.dataset.id == groupId) {
                el.classList.add('active', 'ring-1', 'ring-blue-500');
            } else {
                el.classList.remove('active', 'ring-1', 'ring-blue-500');
            }
        });
        
        selectedGroupBadge.textContent = groupName;
        selectedGroupBadge.classList.remove('hidden');
        
        // Reset Item Column
        selectedSubgroupBadge.classList.add('hidden');
        itemList.classList.add('hidden');
        itemEmpty.classList.remove('hidden');
        itemEmpty.innerHTML = `<i class="ph ph-file-text text-2xl text-gray-300 mb-2"></i><p class="text-gray-500 text-xs px-4">เลือกหมวดหมู่ย่อย</p>`;
        
        loadSubgroups(groupId);
    }

    function loadSubgroups(groupId) {
        subgroupEmpty.classList.add('hidden');
        subgroupList.classList.add('hidden');
        subgroupLoading.classList.remove('hidden');
        
        fetch(`ajax_get_subgroups.php?group_id=${groupId}`)
            .then(res => res.json())
            .then(data => {
                subgroupLoading.classList.add('hidden');
                if (data.status === 'success' && data.data.length > 0) {
                    renderSubgroups(data.data);
                    subgroupList.classList.remove('hidden');
                } else {
                    subgroupEmpty.classList.remove('hidden');
                    subgroupEmpty.innerHTML = `<i class="ph ph-list-dashes text-2xl text-gray-300 mb-2"></i><p class="text-gray-500 text-xs px-4">ไม่พบหมวดหมู่ย่อย</p>`;
                }
            })
            .catch(err => {
                subgroupLoading.classList.add('hidden');
            });
    }
    
    function renderSubgroups(items) {
        subgroupList.innerHTML = '';
        items.forEach((item) => {
            const div = document.createElement('div');
            const isActiveClass = item.IsActive === 'Y' ? '' : 'opacity-60';
            
            div.className = `subgroup-item ${isActiveClass} p-2 rounded-lg border shadow-sm flex items-center gap-2 transition-colors bg-white hover:bg-gray-50`;
            div.dataset.id = item.SubGroupID;
            
            let title = item.SubGroupNameTH || 'ไม่มีชื่อ';
            
            div.innerHTML = `
                <div class="cursor-grab text-gray-300 hover:text-gray-500 flex-shrink-0 drag-handle">
                    <i class="ph ph-dots-six-vertical text-xl"></i>
                </div>
                <div class="flex-1 min-w-0 cursor-pointer py-1 flex items-center" onclick="selectSubgroup(${item.SubGroupID}, '${title.replace(/'/g, "\\'")}')">
                    <h4 class="font-medium text-xs text-gray-800 truncate">${title}</h4>
                </div>
                <div class="flex-shrink-0 cursor-pointer" onclick="selectSubgroup(${item.SubGroupID}, '${title.replace(/'/g, "\\'")}')">
                    <i class="ph ph-caret-right text-gray-400 text-sm"></i>
                </div>
            `;
            subgroupList.appendChild(div);
        });
        
        if (canEdit) {
            new Sortable(subgroupList, {
                animation: 150,
                ghostClass: 'bg-purple-50',
                handle: '.drag-handle',
                onEnd: function () { saveSubgroupOrder(); }
            });
        }
    }
    
    function selectSubgroup(subgroupId, subgroupName) {
        currentSubgroupId = subgroupId;
        
        document.querySelectorAll('.subgroup-item').forEach(el => {
            if(el.dataset.id == subgroupId) {
                el.classList.add('active', 'ring-1', 'ring-purple-500');
            } else {
                el.classList.remove('active', 'ring-1', 'ring-purple-500');
            }
        });
        
        selectedSubgroupBadge.textContent = subgroupName;
        selectedSubgroupBadge.classList.remove('hidden');
        
        loadItems(subgroupId);
    }

    function loadItems(subgroupId) {
        itemEmpty.classList.add('hidden');
        itemList.classList.add('hidden');
        itemLoading.classList.remove('hidden');
        
        fetch(`ajax_get_items.php?subgroup_id=${subgroupId}`)
            .then(res => res.json())
            .then(data => {
                itemLoading.classList.add('hidden');
                if (data.status === 'success' && data.data.length > 0) {
                    renderItems(data.data);
                    itemList.classList.remove('hidden');
                } else {
                    itemEmpty.classList.remove('hidden');
                    itemEmpty.innerHTML = `<i class="ph ph-file-text text-2xl text-gray-300 mb-2"></i><p class="text-gray-500 text-xs px-4">ไม่พบข้อมูลไฟล์</p>`;
                }
            })
            .catch(err => {
                itemLoading.classList.add('hidden');
            });
    }

    function renderItems(items) {
        itemList.innerHTML = '';
        items.forEach((item) => {
            const div = document.createElement('div');
            const isActiveClass = item.IsActive === 'Y' ? '' : 'opacity-60';
            
            div.className = `p-2 rounded-lg border shadow-sm flex items-center gap-2 bg-white hover:bg-gray-50 transition-colors ${isActiveClass}`;
            div.dataset.id = item.DownloadID;
            
            let title = item.Title_TH || 'ไม่มีชื่อ';
            
            div.innerHTML = `
                <div class="cursor-grab drag-handle text-gray-300 hover:text-gray-500">
                    <i class="ph ph-dots-six-vertical text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-medium text-xs text-gray-800 truncate">${title}</h4>
                </div>
            `;
            itemList.appendChild(div);
        });
        
        if (canEdit) {
            new Sortable(itemList, {
                animation: 150,
                ghostClass: 'bg-green-50',
                handle: '.drag-handle',
                onEnd: function () { saveItemOrder(); }
            });
        }
    }

    function saveGroupOrder() {
        if (!canEdit) return;
        const orderData = Array.from(groupList.children).map((item, i) => ({ id: item.dataset.id, order: i + 1 }));
        fetch('ajax_save_group_order.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ order: orderData }) })
        .then(showToast).catch(showError);
    }
    
    function saveSubgroupOrder() {
        if (!canEdit) return;
        const orderData = Array.from(subgroupList.children).map((item, i) => ({ id: item.dataset.id, order: i + 1 }));
        fetch('ajax_save_subgroup_order.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ order: orderData }) })
        .then(showToast).catch(showError);
    }

    function saveItemOrder() {
        if (!canEdit) return;
        const orderData = Array.from(itemList.children).map((item, i) => ({ id: item.dataset.id, order: i + 1 }));
        fetch('ajax_save_item_order.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ order: orderData }) })
        .then(showToast).catch(showError);
    }

    function showToast(res) {
        res.json().then(data => {
            if (data.status === 'success') {
                Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 }).fire({ icon: 'success', title: 'บันทึกลำดับเรียบร้อย' });
            }
        });
    }
    
    function showError(err) {
        Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
    }
</script>
<?php include_once('../../includes/footer.php'); ?>
