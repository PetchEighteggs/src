<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดเรียงลำดับนามสงเคราะห์';
include_once('../includes/header.php');
?>
<div class="glass-panel p-6">
    <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
        <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">จัดเรียงลำดับนามสงเคราะห์</h2>
    </div>

    <div class="bg-blue-50 text-blue-700 p-4 rounded-lg mb-6 flex items-center gap-3 border border-blue-100">
      <i class="ph ph-info text-xl flex-shrink-0"></i>
      <p class="text-sm leading-none m-0 pt-0.5">คลิกค้างที่รายการแล้วลากขึ้น-ลง เพื่อจัดเรียงลำดับใหม่ (ระบบจะบันทึกให้อัตโนมัติ)</p>
    </div>

    <div id="loadingIndicator" class="text-center py-10">
      <i class="ph ph-spinner animate-spin text-3xl text-primary mb-2"></i>
      <p class="text-gray-500">กำลังโหลดข้อมูล...</p>
    </div>
    <div id="noDataMessage" class="hidden text-center py-10 bg-gray-50 rounded-xl border border-gray-100">
        <i class="ph ph-users-three text-4xl text-gray-300 mb-2"></i>
        <p class="text-gray-500">ไม่พบข้อมูล</p>
    </div>

    <div id="listContainer" class="hidden">
        <div id="sortableList" class="space-y-2"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const sortableList = document.getElementById('sortableList');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const noDataMessage = document.getElementById('noDataMessage');
    const listContainer = document.getElementById('listContainer');
    
    document.addEventListener('DOMContentLoaded', function() {
        loadItems();
    });
    
    function loadItems() {
        loadingIndicator.classList.remove('hidden');
        listContainer.classList.add('hidden');
        noDataMessage.classList.add('hidden');
        sortableList.innerHTML = '';
        
        fetch(`ajax_get_items.php`)
            .then(res => res.json())
            .then(data => {
                loadingIndicator.classList.add('hidden');
                
                if (data.status === 'success' && data.data.length > 0) {
                    renderList(data.data);
                    listContainer.classList.remove('hidden');
                } else {
                    noDataMessage.classList.remove('hidden');
                }
            })
            .catch(err => {
                loadingIndicator.classList.add('hidden');
                Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลได้', 'error');
            });
    }
    
    function renderList(items) {
        sortableList.innerHTML = '';
        items.forEach((item, index) => {
            const isActive = item.IsActive === 'Y';
            const boxBg = isActive ? 'bg-white border-gray-200 hover:bg-gray-50' : 'bg-gray-50 border-gray-200 opacity-60 hover:opacity-80';
            const titleColor = isActive ? 'text-gray-800' : 'text-gray-500';
            const badge = !isActive ? '<span class="ml-2 text-[10px] font-medium px-1.5 py-0.5 rounded-sm bg-gray-200 text-gray-600">ปิดใช้งาน</span>' : '';

            const div = document.createElement('div');
            div.className = `p-4 rounded-lg border shadow-sm flex items-center gap-4 cursor-grab transition-opacity transition-colors group ${boxBg}`;
            div.dataset.id = item.DirID;
            
            const imgHtml = item.Image 
                ? `<img src="<?php echo $ImgPath; ?>${item.Image}" class="w-full h-full object-cover">`
                : `<div class="w-full h-full flex items-center justify-center text-gray-400 bg-gray-100"><i class="ph ph-user text-xl"></i></div>`;
            
            div.innerHTML = `
                <div class="text-gray-300 group-hover:text-gray-500 flex items-center justify-center px-2 py-2 transition-colors">
                    <i class="ph ph-dots-six-vertical text-2xl"></i>
                </div>
                <div class="w-14 h-14 rounded-full overflow-hidden flex-shrink-0 border border-gray-200 bg-white">
                    ${imgHtml}
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-semibold text-sm truncate flex items-center ${titleColor}">${item.NameTH}${badge}</h4>
                </div>
            `;
            sortableList.appendChild(div);
        });
        
        // Initialize Sortable
        new Sortable(sortableList, {
            animation: 150,
            ghostClass: 'bg-blue-50',
            handle: '.cursor-grab',
            onEnd: function (evt) {
                saveOrder();
            }
        });
    }
    
    function saveOrder() {
        const items = Array.from(sortableList.children);
        const orderData = items.map((item, index) => ({
            id: item.dataset.id,
            order: index + 1
        }));
        
        fetch('ajax_save_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ order: orderData })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: 'บันทึกลำดับเรียบร้อยแล้ว'
                });
            } else {
                Swal.fire('Error', data.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
            }
        })
        .catch(err => {
            Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
        });
    }
</script>
<?php include_once('../includes/footer.php'); ?>
