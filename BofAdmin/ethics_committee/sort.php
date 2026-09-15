<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'จัดเรียงลำดับคณะกรรมการจริยธรรมประจำกรม';
include_once('../includes/header.php');

$items = [];
try {
    if (isset($DB)) {
        $sql = "SELECT EthicsID, NameTH, PositionTH, Image, IsActive 
                FROM org_ethics_committee 
                WHERE DelData = 'N' 
                ORDER BY OrderNo ASC";
        $stmt = $DB->prepare($sql);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

?>
<div class="glass-panel p-6">
    <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
        <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">จัดเรียงลำดับคณะกรรมการจริยธรรมประจำกรม</h2>
    </div>

    <div class="bg-blue-50 text-blue-700 p-4 rounded-lg mb-6 flex items-center gap-3 border border-blue-100">
      <i class="ph ph-info text-xl flex-shrink-0"></i>
      <p class="text-sm leading-none m-0 pt-0.5">คลิกค้างที่รายการแล้วลากขึ้น-ลง เพื่อจัดเรียงลำดับใหม่ (ระบบจะบันทึกให้อัตโนมัติ)</p>
    </div>
    <div id="noDataMessage" class="<?php echo empty($items) ? '' : 'hidden'; ?> text-center py-10 bg-gray-50 rounded-xl border border-gray-100">
      <i class="ph ph-users-three text-4xl text-gray-300 mb-2"></i>
      <p class="text-gray-500">ไม่พบข้อมูลคณะกรรมการจริยธรรมประจำกรม</p>
    </div>
    <div id="listContainer" class="<?php echo empty($items) ? 'hidden' : ''; ?>">
        <div id="sortableList" class="space-y-2">
            <?php foreach($items as $index => $item): 
                $imgPath = !empty($item['Image']) ? $ImgPath . 'ethics_committee/' . $item['Image'] : Config_web_full_url . '/assets/images/no-avatar.png';
                $isActive = ($item['IsActive'] ?? 'N') === 'Y';
                
                // Conditional styles
                $boxBg = $isActive ? 'bg-white border-gray-200 hover:bg-gray-50' : 'bg-gray-50 border-gray-200 opacity-60 hover:opacity-80';
                $titleColor = $isActive ? 'text-gray-800' : 'text-gray-500';
            ?>
            <div class="p-4 rounded-lg border shadow-sm flex items-center gap-4 cursor-grab transition-opacity transition-colors group sortable-item <?php echo $boxBg; ?>" data-id="<?php echo $item['EthicsID']; ?>">
                <div class="text-gray-300 group-hover:text-gray-500 flex items-center justify-center px-2 py-2 transition-colors">
                    <i class="ph ph-dots-six-vertical text-2xl"></i>
                </div>
                <div class="w-12 h-12 rounded-full overflow-hidden flex-shrink-0 border border-gray-200 <?php echo $isActive ? 'bg-gray-100' : 'bg-gray-200 grayscale'; ?>">
                    <img src="<?php echo htmlspecialchars($imgPath); ?>" class="w-full h-full object-cover">
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-semibold text-sm truncate flex items-center gap-2 <?php echo $titleColor; ?>">
                        <?php echo htmlspecialchars($item['NameTH']); ?>
                        <?php if (!$isActive): ?>
                            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-sm bg-gray-200 text-gray-600">ปิดใช้งาน</span>
                        <?php endif; ?>
                    </h4>
                    <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($item['PositionTH']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sortableList = document.getElementById('sortableList');
        if (sortableList) {
            new Sortable(sortableList, {
                animation: 150,
                ghostClass: 'bg-blue-50',
                handle: '.cursor-grab',
                onEnd: function (evt) {
                    saveOrder();
                }
            });
        }
    });

    function saveOrder() {
        const items = document.querySelectorAll('.sortable-item');
        if (items.length === 0) return;
        
        // ajax_save_order.php สำหรับเมนูนี้ถูกตั้งค่าให้รับ { orderData: ... } 
        // หรือถ้าอยากให้เหมือนเดิมเป๊ะๆ ก็ควรส่งเป็น { order: ... } ให้ตรงกับรูปแบบเดิม 
        // แต่ใน ajax_save_order.php (ของ org_ethics_committee) ผมเขียนให้รับ orderData
        const orderData = Array.from(items).map((item, index) => {
            return {
                id: item.getAttribute('data-id'),
                order: index + 1
            };
        });
        
        fetch('ajax_save_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ orderData: orderData })
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
                Swal.fire('ข้อผิดพลาด', data.msg || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
            }
        })
        .catch(err => {
            Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
        });
    }
</script>
<?php include_once('../includes/footer.php'); ?>
