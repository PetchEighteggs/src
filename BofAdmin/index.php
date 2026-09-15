<?php
define('_VALID_ACCESS', 1);
$pageTitle = 'ภาพรวมระบบ';

session_start();
$basePath = '';
if (file_exists('configuration.php')) $basePath = '';
else if (file_exists('../configuration.php')) $basePath = '../';
if ($basePath !== '' || file_exists('configuration.php')) {
    include_once($basePath . 'configuration.php');
    include_once($basePath . 'config/ms_com.php');
    if (!isset($DB)) $DB = mosConnectPDO();
}

try {
    if (isset($DB)) {
			$stmtCheck = $DB->prepare("SELECT SectionID FROM bof_section WHERE SectionURL = 'index.php' LIMIT 1");
			$stmtCheck->execute();
			$secID = $stmtCheck->fetchColumn();
			
			if (!$secID) {
				$DB->exec("INSERT INTO bof_section (SectionGroupID, SectionName, SectionURL, SectionDesc, OrderNo, IsActive) VALUES (1, 'ภาพรวมระบบ (Dashboard)', 'index.php', 'หน้าแรกของระบบแอดมิน', 1, 'Y')");
				$secID = $DB->lastInsertId();
				$DB->exec("INSERT INTO staff_permission (SetGroupID, SectionID, CanAdd, CanEdit, CanDelete, CanApprove) 
				SELECT SetGroupID, $secID, 'Y', 'Y', 'Y', 'Y' FROM staff_set_group");
			}
    }
} catch (PDOException $e) {}

// --- DASHBOARD PERMISSION CHECK ---
$userGroupID = $_SESSION['GroupID'] ?? '';
$activeDeptID = $_SESSION['ActiveDeptID'] ?? 0;
$staffID = $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1;

if ($userGroupID !== 'A') {
    $hasDashboard = false;
    $firstUrl = '';
    
    try {
        $pStmt = $DB->prepare("
            SELECT bs.SectionID, bs.SectionURL 
            FROM staff_permission sp 
            JOIN bof_section bs ON sp.SectionID = bs.SectionID 
            WHERE sp.SetGroupID = ? AND (sp.CanAdd = 'Y' OR sp.CanEdit = 'Y' OR sp.CanDelete = 'Y' OR sp.CanApprove = 'Y')
            ORDER BY bs.SectionGroupID ASC, bs.SectionOrder ASC
        ");
        $pStmt->execute([$_SESSION['SetGroupID'] ?? 0]);
        $perms = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($perms as $p) {
            if (isset($secID) && $p['SectionID'] == $secID) {
                $hasDashboard = true;
            }
            if (empty($firstUrl) && $p['SectionURL'] !== 'index.php') {
                $firstUrl = $p['SectionURL'];
            }
        }
    } catch(PDOException $e) {}

    if (!$hasDashboard) {
        if (!empty($firstUrl)) {
            header("Location: " . $firstUrl);
            exit;
        } else if ($activeDeptID > 0) {
            require_once(__DIR__ . '/includes/microsite_permission.php');
            if (checkMicrositeMenuPermission($DB, $staffID, $activeDeptID, 'news', 'CanView')) {
                header("Location: microsite/news/index.php"); exit;
            } else if (checkMicrositeMenuPermission($DB, $staffID, $activeDeptID, 'banner', 'CanView')) {
                header("Location: microsite/banner/index.php"); exit;
            } else if (checkMicrositeMenuPermission($DB, $staffID, $activeDeptID, 'gallery', 'CanView')) {
                header("Location: microsite/gallery/index.php"); exit;
            } else if (checkMicrositeMenuPermission($DB, $staffID, $activeDeptID, 'download', 'CanView')) {
                header("Location: microsite/download/index.php"); exit;
            } else if (checkMicrositeMenuPermission($DB, $staffID, $activeDeptID, 'settings', 'CanView')) {
                header("Location: microsite/view.php"); exit;
            } else if (checkMicrositeMenuPermission($DB, $staffID, $activeDeptID, 'menus', 'CanView')) {
                header("Location: microsite/menus/index.php"); exit;
            }
            header("Location: login.php");
            exit;
        } else {
            header("Location: login.php");
            exit;
        }
    }
}

include_once('includes/header.php');

$userGroupID = $_SESSION['GroupID'] ?? '';
$activeDeptID = $_SESSION['ActiveDeptID'] ?? 0;
$staffID = $_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 1;

// --- DYNAMIC DATA FETCHING ---
$stats = [
    'card1_title' => '', 'card1_val' => 0, 'card1_icon' => 'ph-newspaper', 'card1_color' => 'blue', 'card1_url' => '#',
    'card2_title' => '', 'card2_val' => 0, 'card2_icon' => 'ph-address-book', 'card2_color' => 'emerald', 'card2_url' => '#',
    'card3_title' => '', 'card3_val' => 0, 'card3_icon' => 'ph-image', 'card3_color' => 'purple', 'card3_url' => '#',
    'card4_title' => '', 'card4_val' => 0, 'card4_icon' => 'ph-user-gear', 'card4_color' => 'orange', 'card4_url' => '#'
];
$recentLogs = [];
$quickLinks = [];

// --- CHART DATA PREPARATION ---
$chart1Labels = [];
$chart1Series = [];
$chart2Labels = ['ไม่มีข้อมูล'];
$chart2Series = [0];

try {
    if ($userGroupID === 'A') {
			// SUPER ADMIN STATS
			// Card 1: News
			$stmt = $DB->query("SELECT COUNT(*) FROM news WHERE (DelData IS NULL OR DelData='N')");
			$stats['card1_title'] = 'ข่าวประชาสัมพันธ์';
			$stats['card1_val'] = $stmt->fetchColumn();
			$stats['card1_url'] = 'news/index.php';
			
			// Card 2: Microsites (Departments)
			$stmt = $DB->query("SELECT COUNT(*) FROM microsite_departments");
			$stats['card2_title'] = 'หน่วยงาน';
			$stats['card2_val'] = $stmt->fetchColumn();
			$stats['card2_icon'] = 'ph-buildings';
			$stats['card2_url'] = 'microsite/index.php';
			
			// Card 3: Users
			$stmt = $DB->query("SELECT COUNT(*) FROM staff WHERE Status='Y' AND (DelData IS NULL OR DelData='N')");
			$stats['card3_title'] = 'ผู้ดูแลระบบ (Active)';
			$stats['card3_val'] = $stmt->fetchColumn();
			$stats['card3_icon'] = 'ph-users';
			$stats['card3_url'] = 'users/index.php';
			
			// Card 4: System Logs Today
			$stmt = $DB->query("SELECT COUNT(*) FROM bof_logs WHERE DATE(CreateDate) = CURDATE()");
			$stats['card4_title'] = 'การใช้งานระบบวันนี้';
			$stats['card4_val'] = $stmt->fetchColumn();
			$stats['card4_icon'] = 'ph-list-dashes';
			$stats['card4_url'] = 'logs/index.php';
			
			// Recent Logs
			$logStmt = $DB->query("SELECT l.*, s.Name, s.Username FROM bof_logs l LEFT JOIN staff s ON l.StaffID = s.StaffID ORDER BY l.LogID DESC LIMIT 6");
			$recentLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
			
			// Quick Links
			$quickLinks = [
					['title' => 'เพิ่มข่าวใหม่', 'desc' => 'ข่าวประชาสัมพันธ์, กิจกรรม', 'icon' => 'ph-plus-circle', 'color' => 'blue', 'url' => 'news/form.php'],
					['title' => 'เพิ่มผู้ดูแลระบบ', 'desc' => 'จัดการสิทธิ์ผู้ใช้งาน', 'icon' => 'ph-user-plus', 'color' => 'orange', 'url' => 'users/form.php'],
					['title' => 'ตั้งค่าแบนเนอร์หลัก', 'desc' => 'ภาพสไลด์หน้าแรกเว็บไซต์', 'icon' => 'ph-images', 'color' => 'purple', 'url' => 'hero_banner/index.php']
			];
        
    } else {
        // STAFF STATS DYNAMICALLY BY PERMISSION
        $staffPerms = [];
        try {
            $pStmt = $DB->prepare("SELECT SectionID FROM staff_permission WHERE SetGroupID = ? AND (CanAdd = 'Y' OR CanEdit = 'Y' OR CanDelete = 'Y' OR CanApprove = 'Y')");
            $pStmt->execute([$_SESSION['SetGroupID'] ?? 0]);
            $perms = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($perms as $p) {
                $staffPerms[] = $p['SectionID'];
            }
        } catch (PDOException $e) {}

        $idx = 1;
        $quickLinks = [];

        // Main Site News (SectionID = 7)
        if (in_array(7, $staffPerms)) {
            $stmt = $DB->query("SELECT COUNT(*) FROM news WHERE (DelData IS NULL OR DelData='N')");
            $stats['card'.$idx.'_title'] = 'ข่าวประชาสัมพันธ์';
            $stats['card'.$idx.'_val'] = $stmt->fetchColumn();
            $stats['card'.$idx.'_icon'] = 'ph-newspaper';
            $stats['card'.$idx.'_color'] = 'blue';
            $stats['card'.$idx.'_url'] = 'news/index.php';
            $quickLinks[] = ['title' => 'เพิ่มข่าวใหม่', 'desc' => 'ข่าวประชาสัมพันธ์', 'icon' => 'ph-plus-circle', 'color' => 'blue', 'url' => 'news/form.php'];
            $idx++;
        }

        // Main Site Banners (SectionID = 2)
        if (in_array(2, $staffPerms)) {
            $stmt = $DB->query("SELECT COUNT(*) FROM hero_banners");
            $stats['card'.$idx.'_title'] = 'แบนเนอร์หน้าแรก';
            $stats['card'.$idx.'_val'] = $stmt->fetchColumn();
            $stats['card'.$idx.'_icon'] = 'ph-image';
            $stats['card'.$idx.'_color'] = 'purple';
            $stats['card'.$idx.'_url'] = 'hero_banner/index.php';
            $quickLinks[] = ['title' => 'ตั้งค่าแบนเนอร์', 'desc' => 'ภาพสไลด์หน้าแรก', 'icon' => 'ph-images', 'color' => 'purple', 'url' => 'hero_banner/index.php'];
            $idx++;
        }

        // Users (SectionID = 8)
        if (in_array(8, $staffPerms)) {
            if ($idx <= 4) {
                $stmt = $DB->query("SELECT COUNT(*) FROM staff WHERE Status='Y' AND (DelData IS NULL OR DelData='N')");
                $stats['card'.$idx.'_title'] = 'ผู้ดูแลระบบ';
                $stats['card'.$idx.'_val'] = $stmt->fetchColumn();
                $stats['card'.$idx.'_icon'] = 'ph-users';
                $stats['card'.$idx.'_color'] = 'orange';
                $stats['card'.$idx.'_url'] = 'users/index.php';
                $idx++;
            }
            $quickLinks[] = ['title' => 'เพิ่มผู้ดูแลระบบ', 'desc' => 'จัดการสิทธิ์ผู้ใช้งาน', 'icon' => 'ph-user-plus', 'color' => 'orange', 'url' => 'users/form.php'];
        }

        // Events (SectionID = 13)
        if (in_array(13, $staffPerms)) {
            if ($idx <= 4) {
                $stmt = $DB->query("SELECT COUNT(*) FROM events WHERE (DelData IS NULL OR DelData='N')");
                $stats['card'.$idx.'_title'] = 'ปฏิทินกิจกรรม';
                $stats['card'.$idx.'_val'] = $stmt->fetchColumn();
                $stats['card'.$idx.'_icon'] = 'ph-calendar-check';
                $stats['card'.$idx.'_color'] = 'emerald';
                $stats['card'.$idx.'_url'] = 'events/index.php';
                $idx++;
            }
            $quickLinks[] = ['title' => 'เพิ่มกิจกรรม', 'desc' => 'ปฏิทินกิจกรรม', 'icon' => 'ph-calendar-plus', 'color' => 'emerald', 'url' => 'events/form.php'];
        }

        // Microsite Menu Permissions
        if ($activeDeptID > 0) {
            require_once(__DIR__ . '/includes/microsite_permission.php');
            $canViewMenu = function($module) use ($staffID, $activeDeptID, $DB) {
                return checkMicrositeMenuPermission($DB, $staffID, $activeDeptID, $module, 'CanView');
            };

            // Microsite News
            if ($canViewMenu('news')) {
                if ($idx <= 4) {
                    $stmt = $DB->prepare("SELECT COUNT(*) FROM microsite_news WHERE DeptID = ? AND (DelData IS NULL OR DelData='N')");
                    $stmt->execute([$activeDeptID]);
                    $stats['card'.$idx.'_title'] = 'ข่าวสารหน่วยงาน';
                    $stats['card'.$idx.'_val'] = $stmt->fetchColumn();
                    $stats['card'.$idx.'_icon'] = 'ph-newspaper';
                    $stats['card'.$idx.'_color'] = 'blue';
                    $stats['card'.$idx.'_url'] = 'microsite/news/index.php';
                    $idx++;
                }
                $quickLinks[] = ['title' => 'เพิ่มข่าวหน่วยงาน', 'desc' => 'ข่าวสารหน่วยงาน', 'icon' => 'ph-plus-circle', 'color' => 'blue', 'url' => 'microsite/news/form.php'];
            }

            // Microsite Gallery
            if ($canViewMenu('gallery')) {
                if ($idx <= 4) {
                    $stmt = $DB->prepare("SELECT COUNT(*) FROM microsite_gallery WHERE DeptID = ?");
                    $stmt->execute([$activeDeptID]);
                    $stats['card'.$idx.'_title'] = 'ภาพกิจกรรมหน่วยงาน';
                    $stats['card'.$idx.'_val'] = $stmt->fetchColumn();
                    $stats['card'.$idx.'_icon'] = 'ph-images';
                    $stats['card'.$idx.'_color'] = 'emerald';
                    $stats['card'.$idx.'_url'] = 'microsite/gallery/index.php';
                    $idx++;
                }
            }

            // Microsite Download
            if ($canViewMenu('download')) {
                if ($idx <= 4) {
                    $stmt = $DB->prepare("SELECT COUNT(*) FROM microsite_download WHERE DeptID = ?");
                    $stmt->execute([$activeDeptID]);
                    $stats['card'.$idx.'_title'] = 'เอกสารดาวน์โหลด';
                    $stats['card'.$idx.'_val'] = $stmt->fetchColumn();
                    $stats['card'.$idx.'_icon'] = 'ph-file-arrow-down';
                    $stats['card'.$idx.'_color'] = 'orange';
                    $stats['card'.$idx.'_url'] = 'microsite/download/index.php';
                    $idx++;
                }
            }

            // Microsite Menus
            if ($canViewMenu('menus')) {
                $quickLinks[] = ['title' => 'จัดการเมนู', 'desc' => 'เมนูเว็บไซต์ย่อย', 'icon' => 'ph-list-dashes', 'color' => 'emerald', 'url' => 'microsite/menus/index.php'];
            }
            // Microsite Contact
            if ($canViewMenu('contact')) {
                $quickLinks[] = ['title' => 'ข้อมูลติดต่อ', 'desc' => 'ที่อยู่หน่วยงาน', 'icon' => 'ph-address-book', 'color' => 'orange', 'url' => 'microsite/contact/index.php'];
            }
        }
        
        // Recent Logs
        $logStmt = $DB->prepare("SELECT l.*, s.Name, s.Username FROM bof_logs l LEFT JOIN staff s ON l.StaffID = s.StaffID WHERE l.StaffID = ? ORDER BY l.LogID DESC LIMIT 6");
        $logStmt->execute([$staffID]);
        $recentLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Chart 1: Activity Trends (Last 7 Days)
    $qChart1 = $userGroupID === 'A'  
        ? "SELECT DATE(CreateDate) as log_date, COUNT(*) as log_count FROM bof_logs WHERE CreateDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(CreateDate) ORDER BY log_date ASC"
        : "SELECT DATE(CreateDate) as log_date, COUNT(*) as log_count FROM bof_logs WHERE StaffID = ? AND CreateDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(CreateDate) ORDER BY log_date ASC";
    
    $stmtC1 = $DB->prepare($qChart1);
    $userGroupID === 'A' ? $stmtC1->execute() : $stmtC1->execute([$staffID]);
    $chart1DataRaw = $stmtC1->fetchAll(PDO::FETCH_ASSOC);

    for ($i = 6; $i >= 0; $i--) {
			$dateStr = date('Y-m-d', strtotime("-$i days"));
			$found = false;
			foreach ($chart1DataRaw as $row) {
				if ($row['log_date'] === $dateStr) {
					$chart1Series[] = (int)$row['log_count'];
					$found = true;
					break;
				}
			}
			if (!$found) $chart1Series[] = 0;
			$chart1Labels[] = date('d M', strtotime($dateStr));
    }

    if ($userGroupID === 'A') {
			$chart2Labels = ['ข่าวประชาสัมพันธ์', 'หน่วยงาน', 'ข่าวหน่วยงาน'];
			$newsCount = $DB->query("SELECT COUNT(*) FROM news WHERE (DelData IS NULL OR DelData='N')")->fetchColumn();
			$deptCount = $DB->query("SELECT COUNT(*) FROM microsite_departments")->fetchColumn();
			$msNewsCount = $DB->query("SELECT COUNT(*) FROM microsite_news WHERE (DelData IS NULL OR DelData='N')")->fetchColumn();
			$chart2Series = [(int)$newsCount, (int)$deptCount, (int)$msNewsCount];
    } else {
			$chart2Labels = [
				$stats['card1_title'] ?: 'ไม่มีข้อมูล',
				$stats['card2_title'] ?: 'ไม่มีข้อมูล',
				$stats['card3_title'] ?: 'ไม่มีข้อมูล',
				$stats['card4_title'] ?: 'ไม่มีข้อมูล'
			];
			$chart2Series = [
				(int)$stats['card1_val'], 
				(int)$stats['card2_val'], 
				(int)$stats['card3_val'],
				(int)$stats['card4_val']
			];
    }

} catch (PDOException $e) {
    echo "<!-- PDOError: " . htmlspecialchars($e->getMessage()) . " -->";
}

if (empty($chart1Labels)) {
    for ($i = 6; $i >= 0; $i--) {
        $chart1Labels[] = date('d M', strtotime("-$i days"));
        $chart1Series[] = 0;
    }
}

$userNameDisplay = $_SESSION['BofAdmin_User'] ?? 'User';
$roleDisplay = $userGroupID === 'A' ? 'ผู้ดูแลระบบระดับสูง (ดูภาพรวมทั้งระบบ)' : 'กำลังจัดการข้อมูลของหน่วยงาน';
if ($userGroupID !== 'A' && $activeDeptID > 0) {
    try {
        $deptStmt = $DB->prepare("SELECT DeptNameTH FROM microsite WHERE DeptID = ?");
        $deptStmt->execute([$activeDeptID]);
        if ($dName = $deptStmt->fetchColumn()) {
            $roleDisplay = "กำลังจัดการข้อมูลหน่วยงาน: <span class='font-bold text-blue-700'>" . htmlspecialchars($dName) . "</span>";
        }
    } catch(PDOException $e) {}
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->d == 0) {
        if ($diff->h > 0) return $diff->h . ' ชั่วโมงที่แล้ว';
        if ($diff->i > 0) return $diff->i . ' นาทีที่แล้ว';
        return 'เพิ่งแก้ไขเมื่อสักครู่';
    } else if ($diff->d == 1) {
        return 'เมื่อวานนี้ ' . $ago->format('H:i');
    }
    return $ago->format('d/m/Y H:i');
}
?>

<!-- Welcome Banner -->
<div class="bg-gradient-to-r from-primary to-blue-500 rounded-2xl p-6 text-white shadow-lg mb-8 flex items-center justify-between">
    <div>
        <h2 class="text-2xl font-bold mb-1">ยินดีต้อนรับ, <?php echo htmlspecialchars($userNameDisplay); ?></h2>
        <p class="text-blue-100"><?php echo $roleDisplay; ?></p>
    </div>
    <div class="hidden md:flex w-16 h-16 rounded-full bg-white/20 items-center justify-center backdrop-blur-sm">
        <i class="ph ph-hand-waving text-4xl text-white"></i>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <?php for($i=1; $i<=4; $i++): 
      $c = $stats['card'.$i.'_color'];
    ?>
    <a href="<?php echo $stats['card'.$i.'_url']; ?>" class="glass-panel flex items-center gap-4 hover:-translate-y-1 hover:shadow-md transition-all cursor-pointer group">
			<div class="w-14 h-14 rounded-full bg-<?php echo $c; ?>-100 text-<?php echo $c; ?>-600 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
				<i class="ph <?php echo $stats['card'.$i.'_icon']; ?>"></i>
			</div>
			<div>
				<div class="text-sm text-gray-500 font-medium"><?php echo $stats['card'.$i.'_title']; ?></div>
				<div class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($stats['card'.$i.'_val']); ?></div>
			</div>
    </a>
    <?php endfor; ?>
</div>
<!-- ApexCharts Script -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
	<!-- Chart 1: Activity Trends -->
	<div class="lg:col-span-2 glass-panel p-6">
		<h3 class="font-bold text-lg text-gray-800 mb-2">สถิติความเคลื่อนไหว (7 วันล่าสุด)</h3>
		<p class="text-sm text-gray-500 mb-4">แสดงปริมาณการบันทึกข้อมูลและใช้งานระบบ</p>
		<div id="chart-activity" class="w-full h-72"></div>
	</div>
	<!-- Chart 2: Content Distribution -->
	<div class="glass-panel p-6">
		<h3 class="font-bold text-lg text-gray-800 mb-2">สัดส่วนข้อมูลในระบบ</h3>
		<p class="text-sm text-gray-500 mb-4">แสดงปริมาณเนื้อหาแยกตามประเภท</p>
		<div id="chart-distribution" class="w-full h-72 flex items-center justify-center"></div>
	</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Activity Chart (Line/Area)
    var options1 = {
        series: [{
					name: 'การทำรายการ',
					data: <?php echo json_encode($chart1Series); ?>
        }],
        chart: {
					type: 'area',
					height: 300,
					toolbar: { show: false },
					fontFamily: 'Kanit, sans-serif'
        },
        colors: ['#005A9C'],
        fill: {
					type: 'gradient',
					gradient: {
						shadeIntensity: 1,
						opacityFrom: 0.4,
						opacityTo: 0.05,
						stops: [0, 100]
					}
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        xaxis: {
            categories: <?php echo json_encode($chart1Labels); ?>,
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            min: 0,
            labels: {
                formatter: function (val) { return Math.floor(val); }
            }
        },
        grid: {
            borderColor: '#f1f5f9',
            strokeDashArray: 4,
        }
    };
    var chart1 = new ApexCharts(document.querySelector("#chart-activity"), options1);
    chart1.render();

    // Content Distribution Chart (Donut)
    var options2 = {
        series: <?php echo json_encode($chart2Series); ?>,
        labels: <?php echo json_encode($chart2Labels); ?>,
        chart: {
          type: 'donut',
          height: 320,
          fontFamily: 'Kanit, sans-serif'
        },
        colors: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'],
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: { show: true },
                        value: { show: true, fontSize: '24px', fontWeight: 'bold' },
                        total: {
                            show: true,
                            showAlways: true,
                            label: 'ทั้งหมด',
                            fontSize: '16px',
                            color: '#64748b'
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        stroke: { width: 0 },
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            markers: { radius: 12 }
        }
    };
    var chart2 = new ApexCharts(document.querySelector("#chart-distribution"), options2);
    chart2.render();
});
</script>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 glass-panel p-6">
        <div class="flex justify-between items-center border-b border-gray-100 pb-4 mb-4">
            <h3 class="font-bold text-lg text-gray-800">การใช้งานล่าสุด (Recent Activities)</h3>
            <?php if($userGroupID === 'A'): ?>
            <a href="logs/index.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">ดูทั้งหมด <i class="ph ph-arrow-right"></i></a>
            <?php endif; ?>
        </div>
        <?php if(!empty($recentLogs)): ?>
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                    <th class="pb-3 font-medium">ผู้ใช้งาน</th>
                    <th class="pb-3 font-medium">การกระทำ</th>
                    <th class="pb-3 font-medium text-right">เวลา</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach($recentLogs as $log): 
                    $name = !empty($log['Name']) ? $log['Name'] : ($log['Username'] ?? 'System');
                    $initial = mb_substr($name, 0, 1, 'UTF-8');
                ?>
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-xs font-bold shrink-0">
                                <?php echo htmlspecialchars($initial); ?>
                            </div>
                            <span class="font-medium text-gray-700 text-sm truncate max-w-[120px]"><?php echo htmlspecialchars($name); ?></span>
                        </div>
                    </td>
                    <td class="py-3 text-sm text-gray-600">
                        <?php echo htmlspecialchars($log['LogAction'] ?? ''); ?>
                    </td>
                    <td class="py-3 text-sm text-gray-500 text-right whitespace-nowrap">
                        <?php echo time_elapsed_string($log['CreateDate']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="text-center py-8 text-gray-500">
            <i class="ph ph-clock text-4xl text-gray-300 mb-2"></i>
            <p>ยังไม่มีประวัติการใช้งาน</p>
        </div>
        <?php endif; ?>
    </div>
    <div class="glass-panel p-6 h-fit">
        <h3 class="font-bold text-lg text-gray-800 mb-4 pb-4 border-b border-gray-100">ทางลัดจัดการข้อมูล (Quick Links)</h3>
        <div class="flex flex-col gap-3">
            <?php foreach($quickLinks as $link): ?>
            <a href="<?php echo $link['url']; ?>" class="p-4 rounded-xl border border-gray-100 hover:border-<?php echo $link['color']; ?>-300 hover:bg-<?php echo $link['color']; ?>-50 hover:-translate-y-0.5 flex items-center gap-4 transition-all group shadow-sm">
                <div class="w-10 h-10 rounded-lg bg-white shadow-sm flex items-center justify-center text-<?php echo $link['color']; ?>-600 group-hover:scale-110 transition-transform">
                    <i class="ph <?php echo $link['icon']; ?> text-xl"></i>
                </div>
                <div>
                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($link['title']); ?></div>
                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($link['desc']); ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php include_once('includes/footer.php'); ?>