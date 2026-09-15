CREATE TABLE IF NOT EXISTS `csr_config` (
  `ConfigID` int NOT NULL AUTO_INCREMENT,
  `Eyebrow_TH` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Heading_TH` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Summary_TH` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Eyebrow_EN` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Heading_EN` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Summary_EN` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CreateDate` datetime DEFAULT NULL,
  `CreateBy` int DEFAULT NULL,
  `CreateIP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `UpdateDate` datetime DEFAULT NULL,
  `UpdateBy` int DEFAULT NULL,
  `UpdateIP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ConfigID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `csr_stats` (
  `StatID` int NOT NULL AUTO_INCREMENT,
  `Title_TH` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'หัวข้อ',
  `Title_EN` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ValueText` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ค่าหลัก (เช่น 947K)',
  `SubValueText` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ค่ารอง (เช่น 946,675)',
  `Unit_TH` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'หน่วย',
  `Unit_EN` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `OrderNo` int NOT NULL DEFAULT '1',
  `IsActive` enum('Y','N') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Y',
  `CreateDate` datetime DEFAULT NULL,
  `CreateBy` int DEFAULT NULL,
  `CreateIP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `UpdateDate` datetime DEFAULT NULL,
  `UpdateBy` int DEFAULT NULL,
  `UpdateIP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DelData` enum('Y','N') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `DelDataDate` datetime DEFAULT NULL,
  `DelDataBy` int DEFAULT NULL,
  `DelDataIP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`StatID`),
  KEY `idx_csr_stats_listing` (`DelData`,`IsActive`,`OrderNo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bof_section`
  (`SectionGroupID`, `SectionName`, `SectionURL`, `SectionIcon`, `SectionShow`, `SectionOrder`)
SELECT 4, 'จัดการข้อมูลสถิติ CSR', 'csr_stats/index.php', 'ph-chart-bar', 'Y',
       (SELECT COALESCE(MAX(`SectionOrder`), 0) + 1 FROM `bof_section` s2 WHERE s2.`SectionGroupID` = 4)
WHERE NOT EXISTS (
  SELECT 1 FROM `bof_section` WHERE `SectionURL` = 'csr_stats/index.php'
);

SET @csr_stats_section_id := (
  SELECT `SectionID`
  FROM `bof_section`
  WHERE `SectionURL` = 'csr_stats/index.php'
  ORDER BY `SectionID` DESC
  LIMIT 1
);

INSERT INTO `staff_permission`
  (`SectionID`, `SetGroupID`, `CanAdd`, `CanEdit`, `CanDelete`, `CanApprove`,
   `CreateDate`, `CreateBy`, `CreateIP`, `DelData`)
SELECT @csr_stats_section_id, sg.`SetGroupID`, 'Y', 'Y', 'Y', 'Y', NOW(), NULL, NULL, 'N'
FROM `staff_set_group` sg
WHERE @csr_stats_section_id IS NOT NULL
  AND sg.`DelData` = 'N'
  AND NOT EXISTS (
    SELECT 1
    FROM `staff_permission` sp
    WHERE sp.`SectionID` = @csr_stats_section_id
      AND sp.`SetGroupID` = sg.`SetGroupID`
  );

-- Insert initial data
INSERT INTO `csr_config` (`Eyebrow_TH`, `Heading_TH`, `Summary_TH`, `CreateDate`)
SELECT 'CSR', 'รายงานการให้ความช่วยเหลือ\nประชาชน CSR\nปีงบประมาณ 2569', 'สรุปรายงานตั้งแต่วันที่ 1 ตุลาคม 2568 ถึง วันที่ 9 กุมภาพันธ์ 2569', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `csr_config`);

INSERT INTO `csr_stats` (`Title_TH`, `ValueText`, `SubValueText`, `Unit_TH`, `OrderNo`, `IsActive`, `CreateDate`)
SELECT 'แจกจ่ายน้ำรวม', '947K', '946,675', 'ลิตร', 1, 'Y', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `csr_stats` WHERE `Title_TH` = 'แจกจ่ายน้ำรวม');

INSERT INTO `csr_stats` (`Title_TH`, `ValueText`, `SubValueText`, `Unit_TH`, `OrderNo`, `IsActive`, `CreateDate`)
SELECT 'ประชาชน\nได้รับประโยชน์', '202K', '221,970', 'คน', 2, 'Y', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `csr_stats` WHERE `Title_TH` = 'ประชาชน\nได้รับประโยชน์');

INSERT INTO `csr_stats` (`Title_TH`, `ValueText`, `SubValueText`, `Unit_TH`, `OrderNo`, `IsActive`, `CreateDate`)
SELECT 'ครัวเรือน\nได้รับประโยชน์', '53K', '53,241', 'ครัวเรือน', 3, 'Y', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `csr_stats` WHERE `Title_TH` = 'ครัวเรือน\nได้รับประโยชน์');
