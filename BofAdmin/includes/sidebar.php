<div id="mainSidebar" class="sidebar flex flex-col">
    <div class="p-6 flex items-center gap-4 border-b border-gray-100">
        <?php
        $logoPath = '';
        if (file_exists('assets/images/logo.png')) $logoPath = 'assets/images/logo.png';
        else if (file_exists('../assets/images/logo.png')) $logoPath = '../assets/images/logo.png';
        else if (file_exists('../../assets/images/logo.png')) $logoPath = '../../assets/images/logo.png';
        else if (file_exists('../../../assets/images/logo.png')) $logoPath = '../../../assets/images/logo.png';
        else if (file_exists('../../../../assets/images/logo.png')) $logoPath = '../../../../assets/images/logo.png';
        else $logoPath = '/BofAdmin/assets/images/logo.png';
        ?>
        <img src="<?php echo $logoPath; ?>" alt="Logo" class="w-10 h-10 object-contain">
        <div>
            <h2 class="font-bold text-lg text-primary leading-tight">DGR</h2>
            <div class="text-xs text-gray-500">Backoffice System</div>
        </div>
    </div>
    
    <!-- Role Badge -->
    <div class="px-6 py-3 border-b border-gray-100 bg-gray-50 text-sm">
        <div class="flex items-center gap-2">
            <i class="ph ph-user-circle text-gray-400 text-lg"></i>
            <span class="font-medium text-gray-700 truncate"><?php echo htmlspecialchars($_SESSION['Name'] ?? 'ผู้ใช้งาน'); ?></span>
        </div>
        <div class="mt-1">
            <?php if(($_SESSION['GroupID'] ?? '') === 'A'): ?>
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Super Admin</span>
            <?php elseif(($_SESSION['GroupID'] ?? '') === 'S' && !empty($_SESSION['ActiveDeptID'])): ?>
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 truncate max-w-full" title="<?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? ''); ?>">
                    <?php echo htmlspecialchars($_SESSION['ActiveDeptName'] ?? 'Staff'); ?>
                </span>
            <?php else: ?>
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">Staff ทั่วไป</span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="flex-1 overflow-y-auto py-4">
        <?php
        $linkPrefix = '';
        if (file_exists('login.php')) $linkPrefix = '';
        else if (file_exists('../login.php')) $linkPrefix = '../';
        else if (file_exists('../../login.php')) $linkPrefix = '../../';
        else if (file_exists('../../../login.php')) $linkPrefix = '../../../';
        else if (file_exists('../../../../login.php')) $linkPrefix = '../../../../';

        $currentUrl = '';
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $bofAdminPos = strpos($scriptPath, '/BofAdmin/');
        if ($bofAdminPos !== false) {
            $currentUrl = substr($scriptPath, $bofAdminPos + 10);
        } else {
            $uriParts = explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
            if(count($uriParts) > 1 && $uriParts[count($uriParts)-2] !== 'BofAdmin') {
                $currentUrl = $uriParts[count($uriParts)-2] . '/' . basename($_SERVER['PHP_SELF']);
            } else {
                $currentUrl = basename($_SERVER['PHP_SELF']);
            }
        }

        try {
            if(isset($DB)) {
                $userGroupID = $_SESSION['GroupID'] ?? '';
                $userSetGroupID = $_SESSION['SetGroupID'] ?? 0;

                // Load permissions if Staff
                $permissions = [];
                if ($userGroupID === 'S') {
                    $permStmt = $DB->prepare("SELECT SectionID FROM staff_permission WHERE SetGroupID = ? AND (CanAdd = 'Y' OR CanEdit = 'Y' OR CanDelete = 'Y')");
                    $permStmt->execute([$userSetGroupID]);
                    $perms = $permStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach($perms as $p) {
                        $permissions[] = $p['SectionID'];
                    }
                }

                $grpStmt = $DB->prepare("SELECT * FROM bof_section_group WHERE SectionGroupShow = 'Y' ORDER BY SectionGroupOrder ASC");
                $grpStmt->execute();
                $groups = $grpStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach($groups as $group) {
                    $secStmt = $DB->prepare("SELECT * FROM bof_section WHERE SectionGroupID = ? AND SectionShow = 'Y' ORDER BY SectionOrder ASC");
                    $secStmt->execute([$group['SectionGroupID']]);
                    $sections = $secStmt->fetchAll(PDO::FETCH_ASSOC);

                    // Filter sections based on permissions
                    $visibleSections = [];
                    foreach($sections as $sec) {
                        // All sections are visible to Admin ('A'). 
                        // For 'S', check if SectionID is in $permissions
                        if ($userGroupID === 'A' || in_array($sec['SectionID'], $permissions)) {
                            $visibleSections[] = $sec;
                        }
                    }

                    if(count($visibleSections) > 0) {
                        // 1. Check which sections are active and if the group should be open
                        $groupIsActive = false;
                        $processedSections = [];
                        
                        foreach($visibleSections as $sec) {
                            $isActive = false;
                            
                            $secUrlParts = explode('?', $sec['SectionURL']);
                            $secPath = $secUrlParts[0];
                            $secQueryStr = $secUrlParts[1] ?? '';
                            
                            $currentFolder = explode('/', $currentUrl)[0];
                            $secFolder = explode('/', $secPath)[0];
                            
                            if ($currentUrl === $secPath || ($currentFolder === $secFolder && $currentFolder !== 'index.php')) {
                                $isActive = true;
                                
                                $currentQueryString = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '';
                                parse_str($currentQueryString, $currentQuery);
                                parse_str($secQueryStr, $secQuery);
                                
                                foreach (['group', 'cat', 'type'] as $routeParam) {
                                    $cVal = $currentQuery[$routeParam] ?? '';
                                    $sVal = $secQuery[$routeParam] ?? '';
                                    if ($cVal !== $sVal) {
                                        $isActive = false;
                                        break;
                                    }
                                }
                            }
                            
                            if ($currentUrl === 'index.php' && $sec['SectionURL'] !== 'index.php') {
                                $isActive = false; // Prevent root index matching everything
                            }
                            
                            if ($isActive) $groupIsActive = true;
                            
                            $processedSections[] = [
                                'sec' => $sec,
                                'isActive' => $isActive
                            ];
                        }
                        
                        // 2. Render Group Header
                        $groupId = "sidebar-group-" . $group['SectionGroupID'];
                        $groupDisplayClass = $groupIsActive ? 'flex' : 'hidden';
                        $iconRotateClass = $groupIsActive ? 'rotate-180' : '';
                        
                        echo '<button type="button" onclick="toggleSidebarGroup(\''.$groupId.'\')" class="w-full flex items-center justify-between px-6 py-3 mt-2 hover:bg-gray-50 transition-colors focus:outline-none group">';
                        echo '<span class="text-[15px] font-bold text-gray-600 group-hover:text-primary transition-colors">'.htmlspecialchars($group['SectionGroupName']).'</span>';
                        echo '<i class="ph ph-caret-down text-gray-400 transition-transform duration-300 '.$iconRotateClass.'" id="icon-'.$groupId.'"></i>';
                        echo '</button>';

                        // 3. Render Sub-items inside Collapsible Div
                        echo '<div id="'.$groupId.'" class="'.$groupDisplayClass.' flex-col pb-2 space-y-1">';
                        foreach($processedSections as $psec) {
                            $sec = $psec['sec'];
                            $activeClass = $psec['isActive'] ? 'active' : '';
                            
                            echo '<a href="'.$linkPrefix.$sec['SectionURL'].'" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$activeClass.'">';
                            echo '<i class="ph '.$sec['SectionIcon'].'"></i> '.htmlspecialchars($sec['SectionName']);
                            echo '</a>';
                        }
                        echo '</div>';
                    } // end if count > 0
                } // end foreach groups
            } // end if isset DB
            

            // --- Render Microsite Menu (Hardcoded) ---
            $allowedDeptsCount = isset($_SESSION['AllowedDepts']) ? count($_SESSION['AllowedDepts']) : 0;
            $hasOverview = ($userGroupID === 'A' || $allowedDeptsCount > 1);
            $hasActiveDept = !empty($_SESSION['ActiveDeptID']);

            if ($hasOverview || $hasActiveDept) {
                $isMicrositeActive = (strpos($_SERVER['REQUEST_URI'], '/microsite/') !== false);
                $mainGroupId = "microsite-main-group";
                $mainGroupDisplayClass = $isMicrositeActive ? 'flex' : 'hidden';
                $mainIconRotateClass = $isMicrositeActive ? 'rotate-180' : '';

                echo '<button type="button" onclick="toggleSidebarGroup(\''.$mainGroupId.'\')" class="w-full flex items-center justify-between px-6 py-3 mt-4 hover:bg-gray-50 transition-colors focus:outline-none group">';
                echo '<span class="text-[15px] font-bold text-gray-600 group-hover:text-primary transition-colors">จัดการเว็บไซต์หน่วยงาน</span>';
                echo '<i class="ph ph-caret-down text-gray-400 transition-transform duration-300 '.$mainIconRotateClass.'" id="icon-'.$mainGroupId.'"></i>';
                echo '</button>';

                echo '<div id="'.$mainGroupId.'" class="'.$mainGroupDisplayClass.' flex-col pb-2 space-y-1">';

                if ($hasOverview) {
                    $isActive = (strpos($_SERVER['REQUEST_URI'], '/microsite/index.php') !== false) ? 'active' : '';
                    echo '<a href="'.$linkPrefix.'microsite/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActive.'">';
                    echo '<i class="ph ph-buildings"></i> ' . ($userGroupID === 'A' ? 'ภาพรวมทุกหน่วยงาน' : 'สลับหน่วยงาน');
                    echo '</a>';
                }
                
                if ($hasActiveDept) {
                    $micrositeIsActive = (strpos($_SERVER['REQUEST_URI'], '/microsite/') !== false && strpos($_SERVER['REQUEST_URI'], '/microsite/index.php') === false);
                    $groupId = "microsite-group";
                    $groupDisplayClass = $micrositeIsActive ? 'flex' : 'hidden';
                    $iconRotateClass = $micrositeIsActive ? 'rotate-180' : '';

                    // Show Dept-specific modules if Dept is selected (Both Admin and Staff)
                    echo '<button type="button" onclick="toggleSidebarGroup(\''.$groupId.'\')" class="w-[calc(100%-1rem)] px-4 py-3 mt-2 mx-2 text-[15px] font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors focus:outline-none rounded flex items-center justify-between gap-1">';
                    echo '<span class="truncate flex items-center gap-1" title="'.htmlspecialchars($_SESSION['ActiveDeptName'] ?? '').'"><i class="ph ph-map-pin"></i> ' . htmlspecialchars($_SESSION['ActiveDeptName'] ?? 'Microsite') . '</span>';
                    
                    echo '<div class="flex items-center gap-2">';
                    echo '<i class="ph ph-caret-down text-blue-400 transition-transform duration-300 '.$iconRotateClass.'" id="icon-'.$groupId.'"></i>';
                    // Only show exit department button if they can actually switch
                    if ($userGroupID === 'A' || $allowedDeptsCount > 1) {
                        echo '<a href="'.$linkPrefix.'microsite/action.php?action=exit_dept" onclick="event.stopPropagation()" class="text-red-500 hover:text-red-700 font-bold shrink-0 text-base ml-1" title="ออกจากหน่วยงาน"><i class="ph ph-x-circle"></i></a>';
                    }
                    echo '</div>';
                    echo '</button>';

                    echo '<div id="'.$groupId.'" class="'.$groupDisplayClass.' flex-col pb-2 space-y-1">';
                    
                    // Helper to check view permission for sidebar
                    $staffID = $_SESSION['StaffID'] ?? 0;
                    $activeDeptID = $_SESSION['ActiveDeptID'];
                    $dbConn = $DB ?? mosConnectPDO();
                    if ($userGroupID === 'S') {
                        require_once(__DIR__ . '/microsite_permission.php');
                    }
                    
                    $canViewMenu = function($module) use ($userGroupID, $staffID, $activeDeptID, $dbConn) {
                        if ($userGroupID === 'A') return true;
                        return checkMicrositeMenuPermission($dbConn, $staffID, $activeDeptID, $module, 'CanView');
                    };

                    if ($canViewMenu('settings')) {
                        $isActiveInfo = (strpos($_SERVER['REQUEST_URI'], '/microsite/view.php') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/view.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveInfo.'">';
                        echo '<i class="ph ph-gear"></i> ตั้งค่าหน่วยงาน';
                        echo '</a>';
                    }

                    if ($canViewMenu('menus')) {
                        $isActiveMenus = (strpos($_SERVER['REQUEST_URI'], '/microsite/menus/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/menus/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveMenus.'">';
                        echo '<i class="ph ph-list"></i> จัดการเมนู';
                        echo '</a>';
                    }

                    if ($canViewMenu('intro')) {
                        $isActiveIntro = (strpos($_SERVER['REQUEST_URI'], '/microsite/intro/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/intro/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveIntro.'">';
                        echo '<i class="ph ph-presentation-chart"></i> Intro';
                        echo '</a>';
                    }

                    if ($canViewMenu('popup')) {
                        $isActivePopup = (strpos($_SERVER['REQUEST_URI'], '/microsite/popup/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/popup/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActivePopup.'">';
                        echo '<i class="ph ph-browser"></i> Popup';
                        echo '</a>';
                    }

                    if ($canViewMenu('topgraphic')) {
                        $isActiveTopG = (strpos($_SERVER['REQUEST_URI'], '/microsite/topgraphic/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/topgraphic/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveTopG.'">';
                        echo '<i class="ph ph-image"></i> Top Graphic';
                        echo '</a>';
                    }

                    if ($canViewMenu('executives')) {
                        $isActiveExec = (strpos($_SERVER['REQUEST_URI'], '/microsite/executives/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/executives/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveExec.'">';
                        echo '<i class="ph ph-users-three"></i> ผู้บริหาร';
                        echo '</a>';
                    }

                    if ($canViewMenu('about')) {
                        $isActiveAbout = (strpos($_SERVER['REQUEST_URI'], '/microsite/about/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/about/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveAbout.'">';
                        echo '<i class="ph ph-buildings"></i> เกี่ยวกับหน่วยงาน';
                        echo '</a>';
                    }
                    
                    if ($canViewMenu('news')) {
                        $isActiveNews = (strpos($_SERVER['REQUEST_URI'], '/microsite/news/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/news/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveNews.'">';
                        echo '<i class="ph ph-article"></i> ข่าวประชาสัมพันธ์';
                        echo '</a>';
                    }

                    if ($canViewMenu('banner')) {
                        $isActiveBanner = (strpos($_SERVER['REQUEST_URI'], '/microsite/banner/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/banner/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveBanner.'">';
                        echo '<i class="ph ph-megaphone"></i> ช่องทางประชาสัมพันธ์ (Banner)';
                        echo '</a>';
                    }

                    if ($canViewMenu('download')) {
                        $isActiveDl = (strpos($_SERVER['REQUEST_URI'], '/microsite/download/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/download/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveDl.'">';
                        echo '<i class="ph ph-download-simple"></i> ดาวน์โหลด';
                        echo '</a>';
                    }

                    if ($canViewMenu('multimedia')) {
                        $isActiveMedia = (strpos($_SERVER['REQUEST_URI'], '/microsite/multimedia/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/multimedia/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveMedia.'">';
                        echo '<i class="ph ph-video-camera"></i> สื่อมัลติมีเดีย';
                        echo '</a>';
                    }

                    if ($canViewMenu('gallery')) {
                        $isActiveGal = (strpos($_SERVER['REQUEST_URI'], '/microsite/gallery/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/gallery/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveGal.'">';
                        echo '<i class="ph ph-images"></i> อัลบั้มภาพ';
                        echo '</a>';
                    }

                    if ($canViewMenu('contact')) {
                        $isActiveContact = (strpos($_SERVER['REQUEST_URI'], '/microsite/contact/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/contact/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveContact.'">';
                        echo '<i class="ph ph-phone"></i> ติดต่อหน่วยงาน';
                        echo '</a>';
                    }

                    if ($canViewMenu('sitemap')) {
                        $isActiveSite = (strpos($_SERVER['REQUEST_URI'], '/microsite/sitemap/') !== false) ? 'active' : '';
                        echo '<a href="'.$linkPrefix.'microsite/sitemap/index.php" class="menu-item ml-4 pl-4 border-l border-gray-200 hover:border-gray-400 '.$isActiveSite.'">';
                        echo '<i class="ph ph-tree-structure"></i> แผนผังเว็บไซต์';
                        echo '</a>';
                    }
                    
                    // Add more modules here as needed
                    echo '</div>'; // End microsite group div
                }
                
                echo '</div>'; // End main microsite group div
            }
            // ----------------------------------------
            
        } catch(PDOException $e) {}
        ?>
    </div>

</div>
<script>
function toggleSidebarGroup(groupId) {
    const groupDiv = document.getElementById(groupId);
    const icon = document.getElementById('icon-' + groupId);
    if (groupDiv.classList.contains('hidden')) {
        groupDiv.classList.remove('hidden');
        groupDiv.classList.add('flex');
        if (icon) icon.classList.add('rotate-180');
    } else {
        groupDiv.classList.add('hidden');
        groupDiv.classList.remove('flex');
        if (icon) icon.classList.remove('rotate-180');
    }
}
</script>