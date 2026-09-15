<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดเรียงลำดับหน่วยงานและตำแหน่ง';
include_once('../includes/header.php');
?>
<div class="glass-panel p-6 mb-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div>
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">จัดเรียงลำดับหน่วยงานและตำแหน่ง</h2>
                <p class="text-sm text-gray-500 mt-1">ลากและวางรายการเพื่อสลับลำดับ ระบบจะบันทึกข้อมูลให้อัตโนมัติ</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Left Column: Departments -->
    <div class="lg:col-span-5 glass-panel p-5 flex flex-col h-[calc(100vh-250px)] min-h-[500px]">
        <h3 class="font-bold text-lg text-gray-800 mb-2 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">1</span> 
            จัดเรียงหน่วยงาน
        </h3>
        <p class="text-xs text-gray-500 mb-4 border-b pb-4">คลิก <i class="ph ph-list"></i> ค้างเพื่อเรียงลำดับ หรือคลิกที่ชื่อเพื่อดูตำแหน่ง</p>
        
        <div id="deptLoading" class="text-center py-10 hidden">
            <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
            <p class="text-gray-500 text-sm">กำลังโหลดข้อมูล...</p>
        </div>
        
        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
            <div id="deptSortableList" class="space-y-2"></div>
        </div>
    </div>

    <!-- Right Column: Positions -->
    <div class="lg:col-span-7 glass-panel p-5 flex flex-col h-[calc(100vh-250px)] min-h-[500px]">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">2</span> 
                จัดเรียงตำแหน่งย่อย
            </h3>
            <span id="selectedDeptBadge" class="hidden bg-blue-50 text-blue-700 text-xs px-3 py-1 rounded-full font-medium border border-blue-100"></span>
        </div>
        
        <p class="text-xs text-gray-500 mb-4 border-b pb-4" id="posHelpText">กรุณาเลือกหน่วยงานจากด้านซ้ายก่อน</p>

        <div id="posLoading" class="text-center py-10 hidden">
            <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
            <p class="text-gray-500 text-sm">กำลังโหลดข้อมูล...</p>
        </div>
        
        <div id="posEmpty" class="text-center py-16 flex-1 flex flex-col items-center justify-center border-2 border-dashed border-gray-100 rounded-xl bg-gray-50/50">
            <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4">
                <i class="ph ph-users-three text-3xl text-gray-300"></i>
            </div>
            <p class="text-gray-500 text-sm">คลิกเลือกหน่วยงานเพื่อดูและจัดเรียงตำแหน่ง</p>
        </div>

        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
            <div id="posSortableList" class="space-y-2 hidden"></div>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    .dept-item.active { border-color: #3b82f6; background-color: #eff6ff; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const deptList = document.getElementById('deptSortableList');
    const posList = document.getElementById('posSortableList');
    const deptLoading = document.getElementById('deptLoading');
    const posLoading = document.getElementById('posLoading');
    const posEmpty = document.getElementById('posEmpty');
    const selectedDeptBadge = document.getElementById('selectedDeptBadge');
    const posHelpText = document.getElementById('posHelpText');
    
    let currentDeptId = null;

    // Load Departments on start
    loadDepartments();

    function loadDepartments() {
        deptLoading.classList.remove('hidden');
        deptList.innerHTML = '';
        
        fetch(`ajax_get_departments.php`)
            .then(res => res.json())
            .then(data => {
                deptLoading.classList.add('hidden');
                if (data.status === 'success' && data.data.length > 0) {
                    renderDepartments(data.data);
                }
            })
            .catch(err => {
                deptLoading.classList.add('hidden');
                Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลหน่วยงานได้', 'error');
            });
    }

    function renderDepartments(items) {
        deptList.innerHTML = '';
        items.forEach((item) => {
            const div = document.createElement('div');
            div.className = `dept-item p-3 rounded-lg border shadow-sm flex items-center gap-3 transition-colors bg-white hover:bg-gray-50`;
            div.dataset.id = item.DeptID;
            
            div.innerHTML = `
                <div class="cursor-grab text-gray-300 hover:text-gray-500 p-1 flex-shrink-0 drag-handle">
                    <i class="ph ph-list text-xl"></i>
                </div>
                <div class="flex-1 min-w-0 cursor-pointer pt-1 pb-1 pr-2" onclick="selectDepartment(${item.DeptID}, '${item.NameTH.replace(/'/g, "\\'")}')">
                    <h4 class="font-medium text-sm text-gray-800 truncate">${item.NameTH}</h4>
                </div>
                <div class="flex-shrink-0 cursor-pointer" onclick="selectDepartment(${item.DeptID}, '${item.NameTH.replace(/'/g, "\\'")}')">
                    <i class="ph ph-caret-right text-gray-400"></i>
                </div>
            `;
            deptList.appendChild(div);
        });
        
        new Sortable(deptList, {
            animation: 150,
            ghostClass: 'bg-blue-50',
            handle: '.drag-handle',
            onEnd: function () {
                saveDeptOrder();
            }
        });
    }

    function selectDepartment(deptId, deptName) {
        currentDeptId = deptId;
        
        // Update UI styling
        document.querySelectorAll('.dept-item').forEach(el => {
            if(el.dataset.id == deptId) {
                el.classList.add('active', 'ring-1', 'ring-blue-500');
            } else {
                el.classList.remove('active', 'ring-1', 'ring-blue-500');
            }
        });
        
        selectedDeptBadge.textContent = deptName;
        selectedDeptBadge.classList.remove('hidden');
        posHelpText.innerHTML = `ลาก <i class="ph ph-list"></i> เพื่อจัดเรียงตำแหน่งใน <strong>${deptName}</strong>`;
        
        loadPositions(deptId);
    }

    function loadPositions(deptId) {
        posEmpty.classList.add('hidden');
        posList.classList.add('hidden');
        posLoading.classList.remove('hidden');
        
        fetch(`ajax_get_positions.php?dept_id=${deptId}`)
            .then(res => res.json())
            .then(data => {
                posLoading.classList.add('hidden');
                if (data.status === 'success' && data.data.length > 0) {
                    renderPositions(data.data);
                    posList.classList.remove('hidden');
                } else {
                    posEmpty.classList.remove('hidden');
                    posEmpty.innerHTML = `
                        <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4">
                            <i class="ph ph-users-three text-3xl text-gray-300"></i>
                        </div>
                        <p class="text-gray-500 text-sm">ไม่พบข้อมูลตำแหน่งในหน่วยงานนี้</p>
                    `;
                }
            })
            .catch(err => {
                posLoading.classList.add('hidden');
                Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลตำแหน่งได้', 'error');
            });
    }

    function renderPositions(items) {
        posList.innerHTML = '';
        items.forEach((item) => {
            const div = document.createElement('div');
            div.className = `p-3 rounded-lg border shadow-sm flex items-center gap-4 bg-white hover:bg-gray-50 transition-colors`;
            div.dataset.id = item.PositionID;
            
            div.innerHTML = `
                <div class="cursor-grab drag-handle text-gray-300 hover:text-gray-500 p-2">
                    <i class="ph ph-list text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-medium text-sm text-gray-800 truncate">${item.Name_TH}</h4>
                    ${item.Name_EN ? `<p class="text-xs text-gray-500 truncate mt-0.5">${item.Name_EN}</p>` : ''}
                </div>
            `;
            posList.appendChild(div);
        });
        
        new Sortable(posList, {
            animation: 150,
            ghostClass: 'bg-blue-50',
            handle: '.drag-handle',
            onEnd: function () {
                savePosOrder();
            }
        });
    }

    function saveDeptOrder() {
        const items = Array.from(deptList.children);
        const orderData = items.map((item, index) => ({
            id: item.dataset.id,
            order: index + 1
        }));
        
        fetch('ajax_save_dept_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order: orderData })
        }).then(showToast).catch(showError);
    }

    function savePosOrder() {
        const items = Array.from(posList.children);
        const orderData = items.map((item, index) => ({
            id: item.dataset.id,
            order: index + 1
        }));
        
        fetch('ajax_save_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order: orderData })
        }).then(showToast).catch(showError);
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
<?php include_once('../includes/footer.php'); ?>
