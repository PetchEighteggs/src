<?php
define('_VALID_ACCESS', 1);
$pageTitle = "ตั้งค่ารูปแบบแบนเนอร์หน้าแรก";
include_once('../includes/header.php');

$validTemplates = [
    'one-grid-slider',
    'two-grid-slider',
    'three-grid',
    'four-grid',
    'five-grid',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
    $Template = (string) ($_POST['Template'] ?? 'three-grid');
    if (!in_array($Template, $validTemplates, true)) {
        $Template = 'three-grid';
    }
    try {
        if (isset($DB)) {
            $check = $DB->query("SELECT COUNT(*) FROM hero_banner_config WHERE ConfigID = 1")->fetchColumn();
            if ($check == 0) {
                $stmt = $DB->prepare("INSERT INTO hero_banner_config (ConfigID, Template, UpdateDate, UpdateBy, UpdateIP) VALUES (1, ?, NOW(), ?, ?)");
                $stmt->execute([$Template, $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']]);
            } else {
                $stmt = $DB->prepare("UPDATE hero_banner_config SET Template = ?, UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE ConfigID = 1");
                $stmt->execute([$Template, $_SESSION['admin_id'] ?? 1, $_SERVER['REMOTE_ADDR']]);
            }
            echo "<script>window.location.href = 'index.php';</script>";
        }
    } catch (PDOException $e) {
        echo "<script>Swal.fire('ข้อผิดพลาด', 'ไม่สามารถบันทึกข้อมูลได้', 'error');</script>";
    }
}

$currentTemplate = 'three-grid';
try {
    if (isset($DB)) {
        $stmt = $DB->query("SELECT Template FROM hero_banner_config WHERE ConfigID = 1");
        $configRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($configRow) {
            $currentTemplate = (string) $configRow['Template'];
            if (!in_array($currentTemplate, $validTemplates, true)) {
                $currentTemplate = 'three-grid';
            }
        }
    }
} catch (PDOException $e) {}
?>
<style>
/* Custom styled radio cards */
.select-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    border-width: 2px !important;
}
.select-card:hover {
    border-color: #3b82f6 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08);
}
.select-card.active-card {
    border-color: #3b82f6 !important;
    background-color: rgba(59, 130, 246, 0.03) !important;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.12);
}
.check-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background-color: #3b82f6;
    color: white;
    width: 20px;
    height: 20px;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    opacity: 0;
    transform: scale(0.8);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 10;
}
.select-card.active-card .check-badge {
    opacity: 1;
    transform: scale(1);
}
</style>

<div class="glass-panel p-6">
    <form action="config.php" method="POST" class="w-full" onsubmit="showLoading()">
        <input type="hidden" name="action" value="save_config">
        
        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
            <a href="index.php" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors" title="ย้อนกลับ">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <h2 class="text-xl font-semibold text-gray-800 flex-1">
                <i class="ph ph-gear text-primary mr-2"></i> ตั้งค่ารูปแบบแบนเนอร์หน้าแรก
            </h2>
        </div>

        <div class="space-y-6 max-w-4xl">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-4">เลือกรูปแบบเลย์เอาต์ที่ต้องการแสดงผล <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <!-- Option 1: Single full-width slider -->
                    <label class="relative flex flex-col items-center justify-center p-4 border rounded-xl cursor-pointer hover:bg-gray-50/50 transition-all select-card border-gray-200 <?php echo $currentTemplate === 'one-grid-slider' ? 'active-card' : 'bg-white'; ?>" data-val="one-grid-slider">
                        <input type="radio" name="Template" value="one-grid-slider" <?php echo $currentTemplate === 'one-grid-slider' ? 'checked' : ''; ?> class="sr-only">
                        <div class="check-badge"><i class="ph ph-check text-xs font-bold"></i></div>
                        <div class="w-full h-16 bg-gray-50 rounded-lg mb-3 overflow-hidden border border-gray-200 p-1">
                            <div class="relative w-full h-full bg-blue-500/10 rounded border border-blue-500/20 flex items-center justify-center">
                                <i class="ph ph-caret-left absolute left-1 text-blue-400 text-[9px]"></i>
                                <i class="ph ph-image text-blue-500 text-lg"></i>
                                <i class="ph ph-caret-right absolute right-1 text-blue-400 text-[9px]"></i>
                                <div class="absolute bottom-1 flex gap-0.5"><span class="w-1 h-1 rounded-full bg-blue-500"></span><span class="w-1 h-1 rounded-full bg-blue-200"></span><span class="w-1 h-1 rounded-full bg-blue-200"></span></div>
                            </div>
                        </div>
                        <span class="text-sm font-bold text-gray-700 text-center">1 Grid Slider</span>
                    </label>

                    <!-- Option 2: Two sliders -->
                    <label class="relative flex flex-col items-center justify-center p-4 border rounded-xl cursor-pointer hover:bg-gray-50/50 transition-all select-card border-gray-200 <?php echo $currentTemplate === 'two-grid-slider' ? 'active-card' : 'bg-white'; ?>" data-val="two-grid-slider">
                        <input type="radio" name="Template" value="two-grid-slider" <?php echo $currentTemplate === 'two-grid-slider' ? 'checked' : ''; ?> class="sr-only">
                        <div class="check-badge"><i class="ph ph-check text-xs font-bold"></i></div>
                        <div class="w-full h-16 bg-gray-50 rounded-lg grid grid-cols-2 mb-3 overflow-hidden border border-gray-200 p-1 gap-1">
                            <div class="relative bg-blue-500/10 rounded border border-blue-500/20 flex items-center justify-center"><i class="ph ph-image text-blue-500 text-xs"></i><span class="absolute bottom-1 w-1 h-1 rounded-full bg-blue-500"></span></div>
                            <div class="relative bg-blue-500/10 rounded border border-blue-500/20 flex items-center justify-center"><i class="ph ph-image text-blue-500 text-xs"></i><span class="absolute bottom-1 w-1 h-1 rounded-full bg-blue-500"></span></div>
                        </div>
                        <span class="text-sm font-bold text-gray-700 text-center">2 Grid Slider</span>
                    </label>

                    <!-- Option 3: One large and two small panels -->
                    <label class="relative flex flex-col items-center justify-center p-4 border rounded-xl cursor-pointer hover:bg-gray-50/50 transition-all select-card border-gray-200 <?php echo $currentTemplate === 'three-grid' ? 'active-card' : 'bg-white'; ?>" data-val="three-grid">
                        <input type="radio" name="Template" value="three-grid" <?php echo $currentTemplate === 'three-grid' ? 'checked' : ''; ?> class="sr-only">
                        <div class="check-badge"><i class="ph ph-check text-xs font-bold"></i></div>
                        <div class="w-full h-16 bg-gray-50 rounded-lg grid grid-cols-2 grid-rows-2 mb-3 overflow-hidden border border-gray-200 p-1 gap-1">
                            <div class="row-span-2 bg-blue-500/10 rounded border border-blue-500/20 flex items-center justify-center"><i class="ph ph-image text-blue-500 text-xs"></i></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                        </div>
                        <span class="text-sm font-bold text-gray-700 text-center">3 Grid</span>
                    </label>

                    <!-- Option 4: 2 x 2 grid -->
                    <label class="relative flex flex-col items-center justify-center p-4 border rounded-xl cursor-pointer hover:bg-gray-50/50 transition-all select-card border-gray-200 <?php echo $currentTemplate === 'four-grid' ? 'active-card' : 'bg-white'; ?>" data-val="four-grid">
                        <input type="radio" name="Template" value="four-grid" <?php echo $currentTemplate === 'four-grid' ? 'checked' : ''; ?> class="sr-only">
                        <div class="check-badge"><i class="ph ph-check text-xs font-bold"></i></div>
                        <div class="w-full h-16 bg-gray-50 rounded-lg grid grid-cols-2 grid-rows-2 mb-3 overflow-hidden border border-gray-200 p-1 gap-1">
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                        </div>
                        <span class="text-sm font-bold text-gray-700 text-center">4 Grid</span>
                    </label>

                    <!-- Option 5: 3 x 3 grid preview -->
                    <label class="relative flex flex-col items-center justify-center p-4 border rounded-xl cursor-pointer hover:bg-gray-50/50 transition-all select-card border-gray-200 <?php echo $currentTemplate === 'five-grid' ? 'active-card' : 'bg-white'; ?>" data-val="five-grid">
                        <input type="radio" name="Template" value="five-grid" <?php echo $currentTemplate === 'five-grid' ? 'checked' : ''; ?> class="sr-only">
                        <div class="check-badge"><i class="ph ph-check text-xs font-bold"></i></div>
                        <div class="w-full h-16 bg-gray-50 rounded-lg grid grid-cols-3 grid-rows-3 mb-3 overflow-hidden border border-gray-200 p-1 gap-1">
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                            <div class="bg-blue-500/10 rounded border border-blue-500/20"></div>
                        </div>
                        <span class="text-sm font-bold text-gray-700 text-center">5 Grid</span>
                    </label>
                </div>
            </div>
            
            
        </div>

        <div class="border-t border-gray-100 mt-8 pt-6 flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors flex items-center justify-center"><i class="ph ph-x mr-2"></i> ยกเลิก</a>
            <button type="submit" class="btn-primary px-8 py-3"><i class="ph ph-floppy-disk mr-2"></i> บันทึกการตั้งค่า</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.select-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.select-card').forEach(c => {
                c.classList.remove('active-card');
            });
            this.classList.add('active-card');
        });
    });
});
</script>
<?php include_once('../includes/footer.php'); ?>
