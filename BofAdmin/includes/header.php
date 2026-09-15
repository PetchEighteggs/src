<?php
define('_VALID_ACCESS', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$loginPath = '';
if (file_exists('login.php')) $loginPath = 'login.php';
else if (file_exists('../login.php')) $loginPath = '../login.php';
else if (file_exists('../../login.php')) $loginPath = '../../login.php';
else if (file_exists('../../../login.php')) $loginPath = '../../../login.php';
else if (file_exists('../../../../login.php')) $loginPath = '../../../../login.php';
else $loginPath = '/BofAdmin/login.php';

// Check if logged in
if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true || !isset($_SESSION['StaffToken'])) {
    session_unset();
    session_destroy();
    header("Location: " . $loginPath . "?error=session_expired");
    exit;
}

// Ensure DB is connected for sidebar and logging
if (!isset($DB)) {
    // Find the correct base path dynamically
    $basePath = '';
    if (file_exists('configuration.php')) $basePath = '';
    else if (file_exists('../configuration.php')) $basePath = '../';
    else if (file_exists('../../configuration.php')) $basePath = '../../';
    else if (file_exists('../../../configuration.php')) $basePath = '../../../';
    else if (file_exists('../../../../configuration.php')) $basePath = '../../../../';
    
    if ($basePath !== '' || file_exists('configuration.php')) {
        include_once($basePath . 'configuration.php');
        include_once($basePath . 'config/ms_com.php');
        $DB = mosConnectPDO();
    }
}

// Advanced Session Check (Token & Expiry)
if (isset($DB) && isset($_SESSION['StaffID']) && isset($_SESSION['StaffToken'])) {
    try {
        $stmt = $DB->prepare("SELECT StaffToken, StaffTokenEXP FROM staff WHERE StaffID = ? AND StaffToken = ?");
        $stmt->execute([$_SESSION['StaffID'], $_SESSION['StaffToken']]);
        $staffRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($staffRow && strtotime($staffRow['StaffTokenEXP']) > time()) {
            // Valid, extend expiry
            $newExp = date('Y-m-d H:i:s', strtotime('+2 hours'));
            $updStmt = $DB->prepare("UPDATE staff SET StaffTokenEXP = ?, LastAccess = NOW() WHERE StaffID = ?");
            $updStmt->execute([$newExp, $_SESSION['StaffID']]);
        } else {
            // Invalid or expired
            session_unset();
            session_destroy();
            header("Location: " . $loginPath . "?error=session_expired");
            exit;
        }
    } catch(PDOException $e) {}
}

$userPermissions = ['view' => true, 'add' => true, 'edit' => true, 'delete' => true];

// Page Level Security (RBAC)
if (isset($_SESSION['GroupID']) && $_SESSION['GroupID'] === 'S') {
    $userPermissions = ['view' => false, 'add' => false, 'edit' => false, 'delete' => false];
    $currentFilename = basename($_SERVER['PHP_SELF']);
    if ($currentFilename !== 'logout.php') {
        // Find which bof_section matches this URL
        $uriPartsAuth = explode('/', $_SERVER['REQUEST_URI']);
        $inSubDirAuth = (count($uriPartsAuth) > 1 && $uriPartsAuth[count($uriPartsAuth)-2] !== 'BofAdmin' && $uriPartsAuth[count($uriPartsAuth)-2] !== 'src');
        
        $checkUrl = $inSubDirAuth ? $uriPartsAuth[count($uriPartsAuth)-2] . '/index.php' : $currentFilename;
        $likeUrl = $inSubDirAuth ? $uriPartsAuth[count($uriPartsAuth)-2] . '/%' : $currentFilename;

        try {
            $secStmt = $DB->prepare("SELECT SectionID FROM bof_section WHERE SectionURL = ? OR SectionURL LIKE ? LIMIT 1");
            $secStmt->execute([$checkUrl, $likeUrl]);
            $secRow = $secStmt->fetch(PDO::FETCH_ASSOC);

            if ($secRow) {
                $userSetGroupID = $_SESSION['SetGroupID'] ?? 0;
                $permStmt = $DB->prepare("SELECT * FROM staff_permission WHERE SetGroupID = ? AND SectionID = ?");
                $permStmt->execute([$userSetGroupID, $secRow['SectionID']]);
                if ($permRow = $permStmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($permRow['CanApprove'] == 'Y' || $permRow['CanAdd'] == 'Y' || $permRow['CanEdit'] == 'Y' || $permRow['CanDelete'] == 'Y') {
                        $userPermissions['view'] = true;
                        $userPermissions['add'] = ($permRow['CanAdd'] == 'Y');
                        $userPermissions['edit'] = ($permRow['CanEdit'] == 'Y');
                        $userPermissions['delete'] = ($permRow['CanDelete'] == 'Y');
                    }
                }
                
                if (!$userPermissions['view']) {
                    $isMicrositePage = (strpos(str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? ''), '/microsite/') !== false) || (strpos(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/microsite/') !== false);
                    if (!$isMicrositePage) {
                        if ($currentFilename === 'index.php' || $currentFilename === '') {
                            if (($_SESSION['GroupID'] ?? '') === 'S') {
                                $adminRootPrefix = str_replace('login.php', '', $loginPath);
                                header("Location: " . $adminRootPrefix . "microsite/index.php");
                                exit;
                            } else {
                                $userSetGroupID = $_SESSION['SetGroupID'] ?? 0;
                                $firstMenuStmt = $DB->prepare("
                                    SELECT s.SectionURL 
                                    FROM bof_section s
                                    JOIN staff_permission p ON s.SectionID = p.SectionID
                                    WHERE p.SetGroupID = ? AND p.CanView = 'Y' AND s.IsActive = 'Y'
                                    AND s.SectionURL != 'index.php'
                                    ORDER BY s.SectionGroupID ASC, s.OrderNo ASC
                                    LIMIT 1
                                ");
                                $firstMenuStmt->execute([$userSetGroupID]);
                                $firstUrl = $firstMenuStmt->fetchColumn();
                                
                                if ($firstUrl) {
                                    header("Location: " . $firstUrl);
                                    exit;
                                }
                            }
                        }
                        
                        $homePath = 'index.php';
                        die('<!DOCTYPE html>
												<html lang="th">
												<head>
														<meta charset="utf-8">
														<meta name="viewport" content="width=device-width, initial-scale=1.0">
														<title>ปฏิเสธการเข้าถึง (Access Denied)</title>
														<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
														<script src="https://cdn.tailwindcss.com"></script>
														<script>
																tailwind.config = {
																		theme: {
																				extend: {
																						fontFamily: { sans: ["Kanit", "sans-serif"] },
																						colors: { primary: "#005A9C" }
																				}
																		}
																}
														</script>
														<script src="https://unpkg.com/@phosphor-icons/web"></script>
												</head>
												<body class="bg-gray-50 h-screen flex items-center justify-center p-4">
														<div class="bg-white p-8 md:p-12 rounded-2xl shadow-xl max-w-lg w-full text-center border border-gray-100">
																<div class="w-24 h-24 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
																		<i class="ph ph-shield-warning text-5xl text-red-500"></i>
																</div>
																<h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">ปฏิเสธการเข้าถึง</h2>
																<p class="text-gray-500 mb-8">ขออภัย คุณไม่มีสิทธิ์เข้าถึงในส่วนนี้ หรือไม่มีสิทธิ์ในการจัดการข้อมูล</p>
																<a href="' . $homePath . '" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-primary hover:bg-blue-700 text-white rounded-xl font-medium transition-colors w-full sm:w-auto">
																		<i class="ph ph-arrow-left text-lg"></i>
																		กลับหน้าหลักของเมนูนี้
																</a>
														</div>
												</body>
												</html>');
                    }
                }
            }
        } catch(PDOException $e) {}
    }
}

$uriParts = explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$inSubDir = (count($uriParts) > 1 && $uriParts[count($uriParts)-2] !== 'BofAdmin' && $uriParts[count($uriParts)-2] !== 'src');
$basePrefix = $inSubDir ? '../' : '';

// Find assets path dynamically
$assetsPath = '';
if (file_exists('assets/css/style.css')) $assetsPath = 'assets';
else if (file_exists('../assets/css/style.css')) $assetsPath = '../assets';
else if (file_exists('../../assets/css/style.css')) $assetsPath = '../../assets';
else if (file_exists('../../../assets/css/style.css')) $assetsPath = '../../../assets';
else if (file_exists('../../../../assets/css/style.css')) $assetsPath = '../../../../assets';
else $assetsPath = '/BofAdmin/assets';

$styleFile = dirname(__DIR__) . '/assets/css/style.css';
$mainJsFile = dirname(__DIR__) . '/assets/js/main.js';
$styleVersion = is_file($styleFile) ? (string) filemtime($styleFile) : '1';
$mainJsVersion = is_file($mainJsFile) ? (string) filemtime($mainJsFile) : '1';

// Log page visit
try {
    if(isset($DB)) {
        $pageNameTh = "หน้า: " . basename($_SERVER['PHP_SELF']);
        
        $currentFilename = basename($_SERVER['PHP_SELF']);
        $checkUrl = $inSubDir ? $uriParts[count($uriParts)-2] . '/index.php' : $currentFilename;
        $likeUrl = $inSubDir ? $uriParts[count($uriParts)-2] . '/%' : $currentFilename;
        
        $nameStmt = $DB->prepare("SELECT SectionName FROM bof_section WHERE SectionURL = ? OR SectionURL LIKE ? LIMIT 1");
        $nameStmt->execute([$checkUrl, $likeUrl]);
        $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);
        if ($nameRow) {
            $pageNameTh = $nameRow['SectionName'];
        }

        if (function_exists('addBofLog')) {
            addBofLog($DB, "เข้าชมหน้า: " . $pageNameTh);
        }
    }
} catch(PDOException $e) { }

$pageTitle = $pageTitle ?? 'DGR Backoffice';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - DGR</title>
    <link rel="icon" href="/BofAdmin/favicon.ico" type="image/x-icon">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (for layout) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Kanit', 'sans-serif'] },
                    colors: {
                        primary: '#005A9C',
                        secondary: '#009FE3',
                        dark: '#1a202c',
                        light: '#f8fafc'
                    }
                }
            }
        }
    </script>
    
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* SweetAlert2 Kanit Font Customization */
        .swal2-container {
            font-family: 'Kanit', sans-serif !important;
        }
    </style>
    
    <!-- Custom CSS & JS -->
    <link rel="stylesheet" href="<?php echo $assetsPath; ?>/css/style.css?v=<?php echo $styleVersion; ?>">
    <script src="<?php echo $assetsPath; ?>/js/main.js?v=<?php echo $mainJsVersion; ?>" defer></script>
</head>
<body>
    
    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    
    <!-- Sidebar -->
    <?php include_once('sidebar.php'); ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="flex items-center gap-4">
                <button id="sidebarToggle" class="lg:hidden p-2 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 focus:outline-none transition-colors">
                    <i class="ph ph-list text-2xl"></i>
                </button>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p class="text-sm text-gray-500">ระบบจัดการเนื้อหาเว็บไซต์ กรมทรัพยากรน้ำบาดาล</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="#" class="flex items-center gap-2 text-blue-600 bg-blue-50 px-4 py-2 rounded-lg hover:bg-blue-100 transition-colors tooltip" data-tip="คู่มือการใช้งานระบบ">
                    <i class="ph ph-book-open text-xl"></i>
                    <span class="text-sm font-medium hidden sm:inline">คู่มือการใช้งาน</span>
                </a>
                <div class="h-8 w-px bg-gray-200"></div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold">
                        <?php echo strtoupper(substr($_SESSION['BofAdmin_User'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div>
                        <div class="font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['BofAdmin_User'] ?? 'Admin'); ?></div>
                        <div class="text-xs text-gray-500">Administrator</div>
                    </div>
                </div>
                <div class="h-8 w-px bg-gray-200"></div>
                <?php
                $logoutPath = '';
                if (file_exists('logout.php')) $logoutPath = 'logout.php';
                else if (file_exists('../logout.php')) $logoutPath = '../logout.php';
                else if (file_exists('../../logout.php')) $logoutPath = '../../logout.php';
                else if (file_exists('../../../logout.php')) $logoutPath = '../../../logout.php';
                else if (file_exists('../../../../logout.php')) $logoutPath = '../../../../logout.php';
                else $logoutPath = '/BofAdmin/logout.php';
                ?>
                <a href="<?php echo $logoutPath; ?>" class="text-red-500 hover:text-red-700 flex items-center gap-2 transition-colors">
                    <i class="ph ph-sign-out text-xl"></i>
                    <span class="text-sm font-medium">ออกจากระบบ</span>
                </a>
            </div>
        </div>
