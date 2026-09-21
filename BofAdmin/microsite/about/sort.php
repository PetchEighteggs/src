<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดเรียงลำดับหมวดหมู่และเนื้อหา';
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
    $canView = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'about', 'CanView');
    $canEdit = checkMicrositeMenuPermission($DB, $staffID, $deptID, 'about', 'CanEdit');
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
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">จัดเรียงลำดับเกี่ยวกับหน่วยงาน</h2>
                <p class="text-sm text-gray-500 mt-1">ลากและวางรายการเพื่อสลับลำดับ ระบบจะบันทึกข้อมูลให้อัตโนมัติ</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Left Column: About Groups -->
    <div class="lg:col-span-5 glass-panel p-5 flex flex-col h-[calc(100vh-250px)] min-h-[500px]">
        <h3 class="font-bold text-lg text-gray-800 mb-2 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">1</span> 
            จัดเรียงหมวดหมู่
        </h3>
        <p class="text-xs text-gray-500 mb-4 border-b pb-4">คลิก <i class="ph ph-list"></i> ค้างเพื่อเรียงลำดับ หรือคลิกที่ชื่อเพื่อดูเนื้อหา</p>
        
        <div id="groupLoading" class="text-center py-10 hidden">
            <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
            <p class="text-gray-500 text-sm">กำลังโหลดข้อมูล...</p>
        </div>
        
        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
            <div id="groupSortableList" class="space-y-2"></div>
        </div>
    </div>

    <!-- Right Column: About Contents -->
    <div class="lg:col-span-7 glass-panel p-5 flex flex-col h-[calc(100vh-250px)] min-h-[500px]">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">2</span> 
                จัดเรียงเนื้อหา
            </h3>
            <span id="selectedGroupBadge" class="hidden bg-blue-50 text-blue-700 text-xs px-3 py-1 rounded-full font-medium border border-blue-100"></span>
        </div>
        
        <p class="text-xs text-gray-500 mb-4 border-b pb-4" id="aboutHelpText">กรุณาเลือกหมวดหมู่จากด้านซ้ายก่อน</p>

        <div id="aboutLoading" class="text-center py-10 hidden">
            <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
            <p class="text-gray-500 text-sm">กำลังโหลดข้อมูล...</p>
        </div>
        
        <div id="aboutEmpty" class="text-center py-16 flex-1 flex flex-col items-center justify-center border-2 border-dashed border-gray-100 rounded-xl bg-gray-50/50">
            <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4">
                <i class="ph ph-file-text text-3xl text-gray-300"></i>
            </div>
            <p class="text-gray-500 text-sm">คลิกเลือกหมวดหมู่เพื่อดูและจัดเรียงเนื้อหา</p>
        </div>

        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
            <div id="aboutSortableList" class="space-y-2 hidden"></div>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    .group-item.active { border-color: #3b82f6; background-color: #eff6ff; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const csrfToken = <?php echo json_encode(bofSecurityCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const groupList = document.getElementById('groupSortableList');
    const aboutList = document.getElementById('aboutSortableList');
    const groupLoading = document.getElementById('groupLoading');
    const aboutLoading = document.getElementById('aboutLoading');
    const aboutEmpty = document.getElementById('aboutEmpty');
    const selectedGroupBadge = document.getElementById('selectedGroupBadge');
    const aboutHelpText = document.getElementById('aboutHelpText');
    
    const canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
    let currentGroupId = null;

    // Load Groups on start
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
                Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลหมวดหมู่ได้', 'error');
            });
    }

    function renderGroups(items) {
        groupList.innerHTML = '';
        items.forEach((item) => {
            const div = document.createElement('div');
            const isActiveClass = item.IsActive === 'Y' ? '' : 'opacity-60';
            const statusBadge = item.IsActive === 'Y' ? '' : '<span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded ml-2">ปิดใช้งาน</span>';
            const isUncategorized = item.GroupID == 0;
            
            div.className = `group-item ${isActiveClass} p-3 rounded-lg border shadow-sm flex items-center gap-3 transition-colors bg-white hover:bg-gray-50`;
            div.dataset.id = item.GroupID;
            
            let dragHandleHtml = '';
            if (isUncategorized) {
                dragHandleHtml = `<div class="text-gray-200 p-1 flex-shrink-0 w-7"><i class="ph ph-folder text-xl"></i></div>`;
            } else {
                dragHandleHtml = `<div class="cursor-grab text-gray-300 hover:text-gray-500 p-1 flex-shrink-0 drag-handle"><i class="ph ph-list text-xl"></i></div>`;
            }
            
            div.innerHTML = `
                ${dragHandleHtml}
                <div class="flex-1 min-w-0 cursor-pointer pt-1 pb-1 pr-2 flex items-center" onclick="selectGroup(${item.GroupID}, '${item.GroupNameTH.replace(/'/g, "\\'")}')">
                    <h4 class="font-medium text-sm text-gray-800 truncate ${isUncategorized ? 'italic' : ''}">${item.GroupNameTH}</h4>
                    ${statusBadge}
                </div>
                <div class="flex-shrink-0 cursor-pointer" onclick="selectGroup(${item.GroupID}, '${item.GroupNameTH.replace(/'/g, "\\'")}')">
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
                filter: '.filtered', // Don't sort uncategorized
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
        
        // Update UI styling
        document.querySelectorAll('.group-item').forEach(el => {
            if(el.dataset.id == groupId) {
                el.classList.add('active', 'ring-1', 'ring-blue-500');
            } else {
                el.classList.remove('active', 'ring-1', 'ring-blue-500');
            }
        });
        
        selectedGroupBadge.textContent = groupName;
        selectedGroupBadge.classList.remove('hidden');
        aboutHelpText.innerHTML = `ลาก <i class="ph ph-list"></i> เพื่อจัดเรียงเนื้อหาใน <strong>${groupName}</strong>`;
        
        loadAbouts(groupId);
    }

    function loadAbouts(groupId) {
        aboutEmpty.classList.add('hidden');
        aboutList.classList.add('hidden');
        aboutLoading.classList.remove('hidden');
        
        fetch(`ajax_get_abouts.php?group_id=${groupId}`)
            .then(res => res.json())
            .then(data => {
                aboutLoading.classList.add('hidden');
                if (data.status === 'success' && data.data.length > 0) {
                    renderAbouts(data.data);
                    aboutList.classList.remove('hidden');
                } else {
                    aboutEmpty.classList.remove('hidden');
                    aboutEmpty.innerHTML = `
                        <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4">
                            <i class="ph ph-file-text text-3xl text-gray-300"></i>
                        </div>
                        <p class="text-gray-500 text-sm">ไม่พบข้อมูลเนื้อหาในหมวดหมู่นี้</p>
                    `;
                }
            })
            .catch(err => {
                aboutLoading.classList.add('hidden');
                Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลเนื้อหาได้', 'error');
            });
    }

    function renderAbouts(items) {
        aboutList.innerHTML = '';
        items.forEach((item) => {
            const div = document.createElement('div');
            const isActiveClass = item.IsActive === 'Y' ? '' : 'opacity-60';
            const statusBadge = item.IsActive === 'Y' ? '' : '<span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded ml-2">ปิดใช้งาน</span>';
            
            div.className = `p-3 rounded-lg border shadow-sm flex items-center gap-4 bg-white hover:bg-gray-50 transition-colors ${isActiveClass}`;
            div.dataset.id = item.AboutID;
            
            div.innerHTML = `
                <div class="cursor-grab drag-handle text-gray-300 hover:text-gray-500 p-2">
                    <i class="ph ph-list text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h4 class="font-medium text-sm text-gray-800 truncate">${item.Title_TH}</h4>
                        ${statusBadge}
                    </div>
                    ${item.Title_EN ? `<p class="text-xs text-gray-500 truncate mt-0.5">${item.Title_EN}</p>` : ''}
                </div>
            `;
            aboutList.appendChild(div);
        });
        
        if (canEdit) {
            new Sortable(aboutList, {
                animation: 150,
                ghostClass: 'bg-blue-50',
                handle: '.drag-handle',
                onEnd: function () {
                    saveAboutOrder();
                }
            });
        } else {
            document.querySelectorAll('#aboutSortableList .drag-handle').forEach(el => {
                el.classList.remove('cursor-grab');
                el.classList.add('cursor-not-allowed', 'opacity-50');
            });
        }
    }

    function saveGroupOrder() {
        if (!canEdit) return;
        
        const items = Array.from(groupList.children);
        // Exclude the uncategorized one (id = 0)
        const orderData = items.filter(item => item.dataset.id > 0).map((item, index) => ({
            id: item.dataset.id,
            order: index + 1
        }));
        
        if(orderData.length === 0) return;

        fetch('ajax_save_group_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ order: orderData })
        }).then(showToast).catch(showError);
    }

    function saveAboutOrder() {
        if (!canEdit) return;
        
        const items = Array.from(aboutList.children);
        const orderData = items.map((item, index) => ({
            id: item.dataset.id,
            order: index + 1
        }));
        
        fetch('ajax_save_about_order.php', {
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
