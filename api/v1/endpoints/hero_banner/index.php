<?php
// src/api/v1/endpoints/hero_banner/index.php
require_once '../../config.php';
require_once '../../helper.php';

// Set standard headers (or let helper.php handle it if we use sendResponse)
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB, $ImgPath;

// Handle parameter from URL Rewrite (could be numeric ID)
$idParam = isset($_GET['id']) ? $_GET['id'] : 0;
$id = is_numeric($idParam) ? intval($idParam) : 0;

try {
    if (isset($DB)) {
        // Fetch active hero banners
        $sql = "SELECT * FROM hero_banners WHERE DelData = 'N' AND IsActive = 'Y'";
        if ($id > 0) {
            $sql .= " AND BannerID = :id";
        }
        $sql .= " ORDER BY OrderNo ASC, BannerID DESC";
        
        $stmt = $DB->prepare($sql);
        if ($id > 0) {
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $bannersRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($bannersRaw)) {
          echo json_encode(null);
          exit;
        }

        // Fetch Global Config
        $configStmt = $DB->prepare("SELECT Template FROM hero_banner_config WHERE ConfigID = 1");
        $configStmt->execute();
        $globalConfig = $configStmt->fetch(PDO::FETCH_ASSOC);
        $globalTemplate = $globalConfig ? (string) $globalConfig['Template'] : 'three-grid';

        // Template options mapping
        $templateOptions = [
            "one-grid-slider",
            "two-grid-slider",
            "three-grid",
            "four-grid",
            "five-grid"
        ];

        $imagesMap = [];
        $bannersList = [];

        foreach ($bannersRaw as $index => $banner) {
            $bannerID = $banner['BannerID'];
            
            // Build banner image key
            $bannerImageKey = "banner_" . $bannerID;
            $imagesMap[$bannerImageKey] = $banner['Image'] ? $ImgPath . 'hero_banner/' . $banner['Image'] : "";

            $thColumns = [];
            $enColumns = [];

            $templateIndex = array_search($globalTemplate, $templateOptions);
            if ($templateIndex === false) {
                $templateIndex = 2; // Default fallback: three-grid
            }

            $bannersList[] = [
                "id" => $bannerID,
                "templateIndex" => $templateIndex,
                "templateName" => $globalTemplate,
                "imageKey" => $bannerImageKey,
                "th" => [
                    "title" => $banner['Title_TH'],
                    "description" => $banner['Description_TH'] ?? "",
                    "primaryCTA" => [
                        "text" => $banner['CTAText_TH'] ?: "รายละเอียด",
                        "link" => $banner['CTALink_TH'] ?: "#"
                    ],
                    "columns" => $thColumns
                ],
                "en" => [
                    "title" => $banner['Title_EN'] ?: $banner['Title_TH'],
                    "description" => $banner['Description_EN'] ?? "",
                    "primaryCTA" => [
                        "text" => $banner['CTAText_EN'] ?: "Details",
                        "link" => $banner['CTALink_EN'] ?: "#"
                    ],
                    "columns" => $enColumns
                ]
            ];
        }

        // Set defaultTemplateIndex based on Global Config
        $defaultTemplateIndex = array_search($globalTemplate, $templateOptions);
        if ($defaultTemplateIndex === false) {
            $defaultTemplateIndex = 2; // fallback to 'three-grid'
        }

        // Reconstruct the response safely
        $response = [
            "templateOptions" => $templateOptions,
            "defaultTemplateIndex" => $defaultTemplateIndex,
            "images" => $imagesMap,
            "content" => [
                "default" => [
                    "th" => [
                        "title" => "กรมทรัพยากรน้ำบาดาล",
                        "description" => "Department of Groundwater Resources",
                        "primaryCTA" => [
                            "text" => "เรียนรู้เพิ่มเติม",
                            "link" => "/th/about"
                        ]
                    ],
                    "en" => [
                        "title" => "Department of Groundwater Resources",
                        "description" => "กรมทรัพยากรน้ำบาดาล",
                        "primaryCTA" => [
                            "text" => "Learn More",
                            "link" => "/en/about"
                        ]
                    ]
                ],
                "home" => !empty($bannersList) ? [
                    "th" => $bannersList[0]['th'],
                    "en" => $bannersList[0]['en']
                ] : null,
                "banners" => $bannersList
            ]
        ];

        if ($id > 0) {
            echo json_encode(!empty($bannersList) ? $bannersList[0] : null, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    } else {
        echo json_encode(null);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
