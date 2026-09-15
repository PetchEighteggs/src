<script>
function openHistoryModal(id, module = 'news') {
    document.getElementById('historyModalNewsId').textContent = 'ID: ' + id;
    document.getElementById('historyModal').classList.remove('hidden');
    document.getElementById('historyModal').classList.add('flex');
    
    document.getElementById('historyLoading').classList.remove('hidden');
    document.getElementById('historyTableContainer').classList.add('hidden');
    document.getElementById('historyEmpty').classList.add('hidden');
    document.getElementById('historyError').classList.add('hidden');
    
    fetch('../includes/ajax_get_history.php?id=' + id + '&module=' + module)
        .then(response => response.json())
        .then(data => {
            document.getElementById('historyLoading').classList.add('hidden');
            if (data.status === 'success') {
                if (data.title) {
                    let displayTitle = data.title;
                    if (displayTitle.length > 30) {
                        displayTitle = displayTitle.substring(0, 30) + '...';
                    }
                    document.getElementById('historyModalNewsId').textContent = 'ID: ' + id + ' ' + displayTitle;
                }
                
                if (data.data.length > 0) {
                    let html = '';
                    data.data.forEach(log => {
                        html += `
                            <tr class="border-b border-gray-50 hover:bg-blue-50/30 transition-colors">
                                <td class="py-3 px-4 text-gray-500 whitespace-nowrap"><i class="ph ph-clock mr-1"></i> ${log.date}</td>
                                <td class="py-3 px-4"><span class="font-semibold text-gray-800 bg-gray-100 px-2 py-1 rounded-md text-xs">${log.staff}</span></td>
                                <td class="py-3 px-4 text-gray-700">${log.action}</td>
                                <td class="py-3 px-4 text-gray-500 text-xs font-mono">${log.ip}</td>
                            </tr>
                        `;
                    });
                    document.getElementById('historyTableBody').innerHTML = html;
                    document.getElementById('historyTableContainer').classList.remove('hidden');
                } else {
                    document.getElementById('historyEmpty').classList.remove('hidden');
                }
            } else {
                document.getElementById('historyError').textContent = data.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
                document.getElementById('historyError').classList.remove('hidden');
            }
        })
        .catch(error => {
            document.getElementById('historyLoading').classList.add('hidden');
            document.getElementById('historyError').textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
            document.getElementById('historyError').classList.remove('hidden');
        });
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.add('hidden');
    document.getElementById('historyModal').classList.remove('flex');
}
</script>

<!-- History Modal -->
<div id="historyModal" class="fixed inset-0 hidden items-center justify-center z-[9999] backdrop-blur-sm" style="background-color: rgba(0,0,0,0.5);" onclick="closeHistoryModal()">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50 rounded-t-xl">
            <h3 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                <i class="ph ph-clock-counter-clockwise text-primary"></i> ประวัติการใช้งาน (<span id="historyModalNewsId"></span>)
            </h3>
            <button type="button" onclick="closeHistoryModal()" class="w-8 h-8 rounded-full bg-white border border-gray-200 text-gray-500 hover:text-red-500 hover:bg-red-50 hover:border-red-200 flex items-center justify-center transition-colors">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 bg-white">
            <div id="historyLoading" class="hidden flex-col items-center justify-center py-10">
                <div class="w-10 h-10 border-4 border-blue-200 border-t-primary rounded-full animate-spin mb-3"></div>
                <div class="text-gray-500">กำลังโหลดประวัติ...</div>
            </div>
            <div id="historyError" class="hidden text-center py-8 text-red-500 bg-red-50 rounded-lg border border-red-100"></div>
            
            <div class="overflow-x-auto hidden" id="historyTableContainer">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr>
                            <th width="150" class="bg-gray-50 text-gray-600 font-medium py-3 px-4 text-left">วันเวลา</th>
                            <th class="bg-gray-50 text-gray-600 font-medium py-3 px-4 text-left">ผู้ใช้งาน</th>
                            <th class="bg-gray-50 text-gray-600 font-medium py-3 px-4 text-left">การกระทำ</th>
                            <th width="130" class="bg-gray-50 text-gray-600 font-medium py-3 px-4 text-left">IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                    </tbody>
                </table>
            </div>
            
            <div id="historyEmpty" class="hidden text-center py-10 text-gray-500 bg-gray-50 rounded-lg border border-gray-100">
                <i class="ph ph-clock-counter-clockwise text-4xl mb-3 text-gray-300"></i>
                <p>ไม่พบประวัติการใช้งาน</p>
            </div>
        </div>
        <div class="p-4 border-t border-gray-100 bg-gray-50 rounded-b-xl flex justify-end">
            <button type="button" onclick="closeHistoryModal()" class="px-6 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 font-medium hover:bg-gray-100 transition-colors">ปิด</button>
        </div>
    </div>
</div>
