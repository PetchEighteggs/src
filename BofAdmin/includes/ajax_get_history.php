<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
$DB = mosConnectPDO();

header('Content-Type: application/json');

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$module = isset($_GET['module']) ? $_GET['module'] : '';

if ($id <= 0 || empty($module)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Parameters']);
    exit;
}

try {
    $title = "";
    $creatorData = null;
    $sectionId = 0;

    if ($module === 'news') {
        $sectionId = 7;
        $stmt = $DB->prepare("SELECT Title_TH as Title, CreateDate, CreateIP, CreateBy FROM news WHERE NewsID = :id LIMIT 1");
    } else if ($module === 'events') {
        $sectionId = 13;
        $stmt = $DB->prepare("SELECT Title_TH as Title, CreateDate, CreateIP, CreateBy FROM events WHERE EventID = :id LIMIT 1");
    } else if ($module === 'news_category') {
        $sectionId = 12;
        $stmt = $DB->prepare("SELECT CategoryName_TH as Title, CreateDate, CreateIP, CreateBy FROM news_category WHERE CategoryID = :id LIMIT 1");
    } else if ($module === 'users') {
        $sectionId = 8;
        $stmt = $DB->prepare("SELECT Name as Title, CreateDate, CreateIP, CreateBy FROM staff WHERE StaffID = :id LIMIT 1");
    } else if ($module === 'hero_banner') {
        $sectionId = 2;
        $stmt = $DB->prepare("SELECT Title_TH as Title, CreateDate, CreateIP, CreateBy FROM hero_banners WHERE BannerID = :id LIMIT 1");
    } else if ($module === 'intro') {
        $sectionId = 1;
        $stmt = $DB->prepare("SELECT Title as Title, CreateDate, CreateIP, CreateBy FROM intro WHERE IntroID = :id LIMIT 1");
    } else if ($module === 'popup') {
        $sectionId = 1;
        $stmt = $DB->prepare("SELECT Title as Title, CreateDate, CreateIP, CreateBy FROM popup WHERE PopupID = :id LIMIT 1");
    } else if ($module === 'executives') {
        $sectionId = 7;
        $stmt = $DB->prepare("SELECT NameTH as Title, CreateDate, CreateIP, CreateBy FROM microsite_executives WHERE ExecID = :id LIMIT 1");
    } else if ($module === 'past_executives') {
        $sectionId = 7;
        $stmt = $DB->prepare("SELECT NameTH as Title, CreateDate, CreateIP, CreateBy FROM org_past_executives WHERE PastExecID = :id LIMIT 1");
    } else if ($module === 'org_directory') {
        $sectionId = 1000; // No StaffLog used for this module
        $stmt = $DB->prepare("SELECT NameTH as Title, CreateDate, CreateIP, CreateBy FROM org_directory WHERE DirID = :id LIMIT 1");
    } else if ($module === 'departments') {
        $sectionId = 1000; // No StaffLog used for this module
        $stmt = $DB->prepare("SELECT NameTH as Title, CreateDate, CreateIP, CreateBy FROM microsite_departments WHERE DeptID = :id LIMIT 1");
    } else if ($module === 'org_documents') {
        $sectionId = 106;
        $stmt = $DB->prepare("SELECT Title_TH as Title, CreateDate, CreateIP, CreateBy FROM org_documents WHERE DocID = :id LIMIT 1");
    } else if ($module === 'org_pages') {
        $sectionId = 101;
        $stmt = $DB->prepare("SELECT Title_TH as Title, CreateDate, CreateIP, CreateBy FROM org_pages WHERE PageID = :id LIMIT 1");
    } else if ($module === 'org_related') {
        $sectionId = 107;
        $stmt = $DB->prepare("SELECT Title_TH as Title, CreateDate, CreateIP, CreateBy FROM org_related WHERE RelatedID = :id LIMIT 1");
    } else if ($module === 'groundwater') {
        $sectionId = 10;
        $stmt = $DB->prepare("SELECT TitleTH as Title, CreateDate, CreateIP, CreateBy FROM groundwater_docs WHERE DocID = :id LIMIT 1");
    } else if ($module === 'hot_issue') {
        $sectionId = 124;
        $stmt = $DB->prepare("SELECT Title as Title, CreateDate, CreateIP, CreateBy FROM hot_issue WHERE IssueID = :id LIMIT 1");
    } else if ($module === 'home_cards') {
        $sectionStmt = $DB->prepare("SELECT SectionID FROM bof_section WHERE SectionURL = 'home_cards/index.php' LIMIT 1");
        $sectionStmt->execute();
        $sectionId = (int)$sectionStmt->fetchColumn();
        $stmt = $DB->prepare("SELECT Title_TH as Title, CreateDate, CreateIP, CreateBy FROM home_cards WHERE CardID = :id LIMIT 1");
    } else if ($module === 'qa') {
        $sectionId = 10;
        $stmt = $DB->prepare("SELECT TitleTH as Title, CreateDate, CreateIP, CreateBy FROM qa WHERE DocID = :id LIMIT 1");
    } else if ($module === 'videos') {
        $sectionId = 7;
        $stmt = $DB->prepare("SELECT TitleTH as Title, CreateDate, CreateIP, CreateBy FROM videos WHERE DocID = :id LIMIT 1");
    } else if ($module === 'faq') {
        $sectionId = 10;
        $stmt = $DB->prepare("SELECT TitleTH as Title, CreateDate, CreateIP, CreateBy FROM faq WHERE DocID = :id LIMIT 1");
    } else if ($module === 'cio_news') {
        $sectionId = 7;
        $stmt = $DB->prepare("SELECT Title_TH as Title, CreateDate, CreateIP, CreateBy FROM cio_news WHERE NewsID = :id LIMIT 1");
    } else if ($module === 'cio_events') {
        $sectionId = 13;
        $stmt = $DB->prepare("SELECT Title_TH as Title, CreateDate, CreateIP, CreateBy FROM cio_events WHERE EventID = :id LIMIT 1");
    } else if ($module === 'cio_documents') {
        $sectionId = 7;
        $stmt = $DB->prepare("SELECT Title_TH as Title, CreateDate, CreateIP, CreateBy FROM cio_documents WHERE DocumentID = :id LIMIT 1");
    } else if ($module === 'cio_executives') {
        $sectionId = 14;
        $stmt = $DB->prepare("SELECT FirstName_TH as Title, CreateDate, CreateIP, CreateBy FROM cio_executives WHERE CioID = :id LIMIT 1");
    } else if ($module === 'service_docs') {
        $sectionId = 10;
        $stmt = $DB->prepare("SELECT TitleTH as Title, CreateDate, CreateIP, CreateBy FROM service_docs WHERE DocID = :id LIMIT 1");
    } else if ($module === 'org_docs') {
        $sectionId = 106;
        $stmt = $DB->prepare("SELECT TitleTH as Title, CreateDate, CreateIP, CreateBy FROM org_docs WHERE DocID = :id LIMIT 1");
    } else if ($module === 'csr_stats') {
        $sectionStmt = $DB->prepare("SELECT SectionID FROM bof_section WHERE SectionURL = 'csr_stats/index.php' LIMIT 1");
        $sectionStmt->execute();
        $sectionId = (int)$sectionStmt->fetchColumn();
        $stmt = $DB->prepare("SELECT Title_TH as Title, CreateDate, CreateIP, CreateBy FROM csr_stats WHERE StatID = :id LIMIT 1");
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unknown Module']);
        exit;
    }
    
    // Legacy search terms
    $legacySearch = "";
    $legacySearchAlt = "";
    if ($module === 'news') {
        $legacySearch = "%News ID: {$id}%";
        $legacySearchAlt = "%News ID: {$id}%";
    } else if ($module === 'events') {
        $legacySearch = "%Event ID: {$id}%";
        $legacySearchAlt = "%Event ID: {$id}%";
    } else if ($module === 'news_category') {
        $legacySearch = "%News Category ID: {$id}%";
        $legacySearchAlt = "%Category ID: {$id}%";
    } else if ($module === 'users') {
        $legacySearch = "%User ID: {$id}%";
        $legacySearchAlt = "%User ID: {$id}%";
    } else if ($module === 'hero_banner') {
        $legacySearch = "%แบนเนอร์หน้าแรก ID: {$id}%";
        $legacySearchAlt = "%แบนเนอร์หน้าแรก ID: {$id}%";
    } else if ($module === 'intro') {
        $legacySearch = "%Intro ID: {$id}%";
        $legacySearchAlt = "%Intro ID: {$id}%";
    } else if ($module === 'popup') {
        $legacySearch = "%Popup ID: {$id}%";
        $legacySearchAlt = "%Popup ID: {$id}%";
    } else if ($module === 'hot_issue') {
        $legacySearch = "%Hot Issue ID: {$id}%";
        $legacySearchAlt = "%hot_issue ID: {$id}%";
    } else if (in_array($module, ['service_docs', 'org_docs'])) {
        $legacySearch = "%ID: {$id}%";
        $legacySearchAlt = "%ID: {$id}%";
    }

    $stmt->execute([':id' => $id]);
    $creatorData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $title = $creatorData ? $creatorData['Title'] : '';

    // Fetch Logs
    $logStmt = $DB->prepare("
        SELECT l.LogAction, l.CreateDate, l.CreateIP, s.Name as StaffName, s.Username as StaffUsername, l.StaffID
        FROM bof_logs l 
        LEFT JOIN staff s ON l.StaffID = s.StaffID
        WHERE (l.SectionID = :section_id AND l.ID = :id)
           OR (l.LogAction LIKE :legacy_search OR l.LogAction LIKE :legacy_search_alt)
        ORDER BY l.LogID DESC
    ");
    
    $logStmt->execute([
        ':section_id' => $sectionId,
        ':id' => $id,
        ':legacy_search' => $legacySearch,
        ':legacy_search_alt' => $legacySearchAlt
    ]);
    $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedLogs = [];
    foreach ($logs as $log) {
        $cleanAction = preg_replace('/\s*\(?ID:\s*\d+\)?/i', '', $log['LogAction']);
        $staff = !empty($log['StaffName']) ? $log['StaffName'] : (!empty($log['StaffUsername']) ? $log['StaffUsername'] : $log['StaffID']);
        
        $formattedLogs[] = [
            'action' => $cleanAction,
            'date' => date('d/m/Y H:i:s', strtotime($log['CreateDate'])),
            'staff' => $staff,
            'ip' => $log['CreateIP']
        ];
    }
    
    // Add creation data if available
    if ($creatorData && !empty($creatorData['CreateDate']) && $creatorData['CreateDate'] != '0000-00-00 00:00:00') {
        $creatorStaff = 'ระบบ (System)';
        if (!empty($creatorData['CreateBy'])) {
					// Get Staff Name
					$sStmt = $DB->prepare("SELECT Name, Username FROM staff WHERE StaffID = ? OR Username = ? LIMIT 1");
					$sStmt->execute([$creatorData['CreateBy'], $creatorData['CreateBy']]);
					$sRow = $sStmt->fetch(PDO::FETCH_ASSOC);
					if ($sRow && !empty($sRow['Name'])) $creatorStaff = $sRow['Name'];
                    elseif ($sRow && !empty($sRow['Username'])) $creatorStaff = $sRow['Username'];
					else $creatorStaff = $creatorData['CreateBy'];
        }
        
        $formattedLogs[] = [
					'action' => 'สร้างข้อมูล',
					'date' => date('d/m/Y H:i:s', strtotime($creatorData['CreateDate'])),
					'staff' => $creatorStaff,
					'ip' => $creatorData['CreateIP'] ?: '-'
        ];
    }
    
    echo json_encode([
        'status' => 'success', 
        'data' => $formattedLogs,
        'title' => $title
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
