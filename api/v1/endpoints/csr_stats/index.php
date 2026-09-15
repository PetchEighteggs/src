<?php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    // 1. Fetch Configuration
    $configStmt = $DB->prepare("SELECT * FROM csr_config LIMIT 1");
    $configStmt->execute();
    $configData = $configStmt->fetch(PDO::FETCH_ASSOC);
    
    $header = [
        'eyebrow' => (string)($configData['Eyebrow_TH'] ?? ''),
        'heading' => (string)($configData['Heading_TH'] ?? ''),
        'summary' => (string)($configData['Summary_TH'] ?? ''),
        'eyebrowEn' => (string)($configData['Eyebrow_EN'] ?? ''),
        'headingEn' => (string)($configData['Heading_EN'] ?? ''),
        'summaryEn' => (string)($configData['Summary_EN'] ?? '')
    ];

    // 2. Fetch Stats List
    $statsStmt = $DB->prepare("
        SELECT Title_TH, Title_EN, ValueText, SubValueText, Unit_TH, Unit_EN 
        FROM csr_stats 
        WHERE DelData='N' AND IsActive='Y' 
        ORDER BY OrderNo ASC, StatID DESC
    ");
    $statsStmt->execute();
    
    $statsList = [];
    foreach ($statsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $statsList[] = [
            'title' => (string)$row['Title_TH'],
            'titleEn' => (string)($row['Title_EN'] ?? ''),
            'value' => (string)$row['ValueText'],
            'subValue' => (string)($row['SubValueText'] ?? ''),
            'unit' => (string)$row['Unit_TH'],
            'unitEn' => (string)($row['Unit_EN'] ?? '')
        ];
    }
    
    sendResponse([
        'header' => $header,
        'stats' => $statsList
    ]);
} catch (PDOException $e) {
    error_log('csr_stats endpoint: ' . $e->getMessage());
    sendError('Unable to load CSR stats', 500);
}
