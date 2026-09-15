<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดเรียงลำดับสถิติ CSR';
include_once('../includes/header.php');
?>
<div class="glass-panel p-6">
    <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
        <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200"><i class="ph ph-arrow-left text-xl"></i></a>
        <h2 class="text-xl font-bold text-gray-800">จัดเรียงลำดับสถิติ CSR</h2>
    </div>

    <div class="bg-blue-50 text-blue-700 p-4 rounded-lg mb-6 flex items-center gap-3 border border-blue-100">
        <i class="ph ph-info text-xl flex-shrink-0"></i>
        <p class="text-sm leading-none m-0 pt-0.5">คลิกค้างที่รายการแล้วลากขึ้น-ลง เพื่อจัดเรียงลำดับใหม่ (ระบบจะบันทึกให้อัตโนมัติ)</p>
    </div>

    <div id="loading" class="text-center py-12 text-gray-500"><i class="ph ph-spinner animate-spin text-3xl text-primary"></i><p class="mt-2">กำลังโหลดข้อมูล...</p></div>
    <div id="empty" class="hidden text-center py-12 text-gray-400"><i class="ph ph-chart-bar text-4xl"></i><p class="mt-2">ไม่พบข้อมูล</p></div>
    <div id="sortable" class="hidden space-y-3"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
const list = document.getElementById('sortable');
document.addEventListener('DOMContentLoaded', loadItems);

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
}
function loadItems() {
    fetch('ajax_get_items.php', {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(response => response.json())
        .then(result => {
            document.getElementById('loading').classList.add('hidden');
            if (result.status !== 'success' || !result.data.length) {
                document.getElementById('empty').classList.remove('hidden');
                return;
            }
            result.data.forEach(item => {
                const row = document.createElement('div');
                row.dataset.id = item.StatID;
                row.className = `group flex items-center gap-4 p-4 rounded-xl border border-gray-200 shadow-sm cursor-grab ${item.IsActive === 'Y' ? 'bg-white' : 'bg-gray-50 opacity-60'}`;
                
                row.innerHTML = `<div class="text-gray-300 group-hover:text-gray-500 flex items-center justify-center px-2"><i class="ph ph-dots-six-vertical text-2xl"></i></div>
                    <div class="flex-1 min-w-0 flex items-center justify-between">
                        <div>
                            <div class="text-base font-bold text-gray-900 leading-snug">${escapeHtml(item.Title_TH)}</div>
                            <div class="text-xs text-gray-500 mt-1">${escapeHtml(item.SubValueText)}</div>
                        </div>
                        <div class="text-right">
                            <span class="text-lg font-bold text-primary">${escapeHtml(item.ValueText)}</span>
                            <span class="text-sm text-gray-600 ml-1">${escapeHtml(item.Unit_TH)}</span>
                        </div>
                    </div>
                    <span class="order-label text-sm font-semibold text-gray-500"></span>`;
                list.appendChild(row);
            });
            list.classList.remove('hidden');
            refreshLabels();
            new Sortable(list, {
                animation: 150,
                ghostClass: 'bg-blue-50',
                handle: '.cursor-grab',
                onEnd: saveOrder
            });
        })
        .catch(() => {
            document.getElementById('loading').classList.add('hidden');
            Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลได้', 'error');
        });
}
function refreshLabels() { Array.from(list.children).forEach((row, index) => row.querySelector('.order-label').textContent = ``); }
function saveOrder() {
    refreshLabels();
    const order = Array.from(list.children).map((row, index) => ({id:Number(row.dataset.id), order:index + 1}));
    fetch('ajax_save_order.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({order})
    })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
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
                Swal.fire('Error', result.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
            }
        })
        .catch(() => {
            Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
        });
}
</script>
<?php include_once('../includes/footer.php'); ?>
