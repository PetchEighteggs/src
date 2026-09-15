-- Home cards: admin module, permissions, and API data source.
-- homeCards.json is a field-shape example only and is not read at runtime.

CREATE TABLE IF NOT EXISTS `home_cards` (
  `CardID` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสการ์ดหน้าหลัก',
  `Slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ชื่ออ้างอิงรายการสำหรับใช้งานใน URL',
  `Title_TH` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'หัวข้อการ์ดภาษาไทย',
  `Title_EN` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'หัวข้อการ์ดภาษาอังกฤษ',
  `Description_TH` text COLLATE utf8mb4_unicode_ci COMMENT 'รายละเอียดการ์ดภาษาไทย',
  `Description_EN` text COLLATE utf8mb4_unicode_ci COMMENT 'รายละเอียดการ์ดภาษาอังกฤษ',
  `Link_TH` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ลิงก์ปลายทางสำหรับเว็บไซต์ภาษาไทย',
  `Link_EN` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ลิงก์ปลายทางสำหรับเว็บไซต์ภาษาอังกฤษ',
  `BgClass` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bg-grad-0' COMMENT 'ชื่อ CSS class สีพื้นหลัง',
  `Image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ชื่อไฟล์รูปภาพ',
  `Direction` enum('left','right') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left' COMMENT 'ตำแหน่งการจัดวางเนื้อหา',
  `OrderNo` int NOT NULL DEFAULT '1' COMMENT 'ลำดับการแสดงผล',
  `IsActive` enum('Y','N') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Y' COMMENT 'สถานะการแสดงผล Y=เปิดใช้งาน และ N=ปิดใช้งาน',
  `CreateDate` datetime DEFAULT NULL COMMENT 'วันที่และเวลาที่เพิ่มข้อมูล',
  `CreateBy` int DEFAULT NULL COMMENT 'รหัสผู้ดูแลระบบที่เพิ่มข้อมูล',
  `CreateIP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address ของผู้เพิ่มข้อมูล',
  `UpdateDate` datetime DEFAULT NULL COMMENT 'วันที่และเวลาที่แก้ไขข้อมูลล่าสุด',
  `UpdateBy` int DEFAULT NULL COMMENT 'รหัสผู้ดูแลระบบที่แก้ไขข้อมูลล่าสุด',
  `UpdateIP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address ของผู้แก้ไขข้อมูลล่าสุด',
  `DelData` enum('Y','N') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N' COMMENT 'สถานะการลบ Y=ลบแล้ว และ N=ยังไม่ลบ',
  `DelDataDate` datetime DEFAULT NULL COMMENT 'วันที่และเวลาที่ลบข้อมูล',
  `DelDataBy` int DEFAULT NULL COMMENT 'รหัสผู้ดูแลระบบที่ลบข้อมูล',
  `DelDataIP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address ของผู้ลบข้อมูล',
  PRIMARY KEY (`CardID`),
  UNIQUE KEY `uk_home_cards_slug` (`Slug`),
  KEY `idx_home_cards_listing` (`DelData`,`IsActive`,`OrderNo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bof_section`
  (`SectionGroupID`, `SectionName`, `SectionURL`, `SectionIcon`, `SectionShow`, `SectionOrder`)
SELECT 4, 'จัดการการ์ดหน้าหลัก', 'home_cards/index.php', 'ph-cards', 'N',
       (SELECT COALESCE(MAX(`SectionOrder`), 0) + 1 FROM `bof_section` s2 WHERE s2.`SectionGroupID` = 4)
WHERE NOT EXISTS (
  SELECT 1 FROM `bof_section` WHERE `SectionURL` = 'home_cards/index.php'
);

SET @home_cards_section_id := (
  SELECT `SectionID`
  FROM `bof_section`
  WHERE `SectionURL` = 'home_cards/index.php'
  ORDER BY `SectionID` DESC
  LIMIT 1
);

INSERT INTO `staff_permission`
  (`SectionID`, `SetGroupID`, `CanAdd`, `CanEdit`, `CanDelete`, `CanApprove`,
   `CreateDate`, `CreateBy`, `CreateIP`, `DelData`)
SELECT @home_cards_section_id, sg.`SetGroupID`, 'Y', 'Y', 'Y', 'Y', NOW(), NULL, NULL, 'N'
FROM `staff_set_group` sg
WHERE @home_cards_section_id IS NOT NULL
  AND sg.`DelData` = 'N'
  AND NOT EXISTS (
    SELECT 1
    FROM `staff_permission` sp
    WHERE sp.`SectionID` = @home_cards_section_id
      AND sp.`SetGroupID` = sg.`SetGroupID`
  );

-- Initial home-card content from homeCards.json.
-- Image is intentionally NULL; image files will be uploaded from BofAdmin later.
INSERT IGNORE INTO `home_cards`
  (`Slug`, `Title_TH`, `Title_EN`, `Description_TH`, `Description_EN`,
   `Link_TH`, `Link_EN`, `BgClass`, `Image`, `Direction`, `OrderNo`, `IsActive`,
   `CreateDate`, `CreateBy`, `CreateIP`, `DelData`)
VALUES
  (
    'daylygroundwatersituation', 'สรุปสถานการณ์ประจำวัน', CONCAT('Groundwater', CHAR(10), 'Situation'),
    'ประจำวันที่ 21 พฤษภาคม 2569', 'Daily situation report on May 21, 2026',
    '/th/services/groundwater-situation', '/en/services/groundwater-situation',
    'bg-grad-0', NULL, 'left', 1, 'Y', NOW(), NULL, NULL, 'N'
  ),
  (
    'applypermit', 'ขอใบอนุญาต', 'Apply Permit',
    CONCAT('การเจาะบ่อน้ำบาดาล', CHAR(10), 'และขออนุญาตใช้'),
    'Drilling a groundwater well and applying for a permit.',
    '/th/operation/drill-use-permission', '/en/operation/drill-use-permission',
    'bg-grad-2', NULL, 'left', 2, 'Y', NOW(), NULL, NULL, 'N'
  ),
  (
    'groundwatermap', 'แผนที่น้ำบาดาล', 'Groundwater Map',
    'แผนที่แสดงศักยภาพน้ำบาดาลในแต่ละจังหวัด',
    'Map showing groundwater potential in each province',
    '/th/services/map', '/en/services/map',
    'bg-grad-3', NULL, 'left', 3, 'Y', NOW(), NULL, NULL, 'N'
  ),
  (
    'accountinformation', 'บัญชีข้อมูล', 'Account Information',
    'DGR DATA CATALOG', 'DGR DATA CATALOG',
    '/th/services/data-catalog', '/en/services/data-catalog',
    'bg-grad-4', NULL, 'left', 4, 'Y', NOW(), NULL, NULL, 'N'
  ),
  (
    'groundwaterlaw', 'กฎหมายน้ำบาดาล', 'Groundwater Law',
    'พ.ร.บ. กฎกระทรวง และ ประกาศต่างๆ',
    'Water Resources Act, regulations, and announcements',
    '/th/services/law', '/en/services/law',
    'bg-grad-5', NULL, 'left', 5, 'Y', NOW(), NULL, NULL, 'N'
  ),
  (
    'pasutara', 'ข้อมูลบ่อน้ำบาดาล', 'PASUTATR',
    'สืบค้นข้อมูลบ่อน้ำบาดาลทะเบียนบ่อ',
    'Search for groundwater well data registered in the well book',
    '/th/services/well-data', '/en/services/well-data',
    'bg-grad-6', NULL, 'left', 6, 'Y', NOW(), NULL, NULL, 'N'
  ),
  (
    'hydrogeologicaldatabase', CONCAT('ฐานข้อมูล', CHAR(10), 'อุทกธรณีวิทยา 2 มิติ /3 มิติ'),
    'Hydrogeological Database 2D/3D',
    'แบบจำลองชั้นน้ำบาดาลเพื่อการศึกษา',
    'Groundwater layer modeling for education',
    '/th/services/hydro-db', '/en/services/hydro-db',
    'bg-grad-8', NULL, 'left', 7, 'Y', NOW(), NULL, NULL, 'N'
  ),
  (
    'receivecomplaints', 'ความรู้ในการเติมน้ำใต้ดิน', 'Groundwater Recharge.',
    'กรมทรัพยากรน้ำบาดาล', 'Water Resources Department',
    '/th/news/knowlaged', '/en/news/knowlaged',
    'bg-grad-9', NULL, 'left', 8, 'Y', NOW(), NULL, NULL, 'N'
  ),
  (
    'news', 'ข่าวสาร ประกาศ', 'News & Announcements',
    'ข่าวสารและประกาศล่าสุด', 'Latest news and announcements',
    '/th/news', '/en/news',
    'bg-grad-10', NULL, 'left', 9, 'Y', NOW(), NULL, NULL, 'N'
  );
