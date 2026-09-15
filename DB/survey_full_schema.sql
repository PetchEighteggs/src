-- --------------------------------------------------------
-- Table structure for table `survey_form`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `survey_form` (
  `FormID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `FormName` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Description` text COLLATE utf8mb4_unicode_ci,
  `IsActive` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Y',
  `CreateDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `CreateBy` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `UpdateDate` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `UpdateBy` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`FormID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='เก็บข้อมูลหลักของแบบฟอร์ม';

-- --------------------------------------------------------
-- Table structure for table `survey_section`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `survey_section` (
  `SectionID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `FormID` int(10) UNSIGNED NOT NULL,
  `Title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Description` text COLLATE utf8mb4_unicode_ci,
  `SectionOrder` int(11) NOT NULL DEFAULT '1',
  `CreateDate` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`SectionID`),
  KEY `FormID` (`FormID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ส่วนที่ (Sections) ของฟอร์ม';

-- --------------------------------------------------------
-- Table structure for table `survey_question`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `survey_question` (
  `QuestionID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `FormID` int(10) UNSIGNED NOT NULL,
  `SectionID` int(10) UNSIGNED DEFAULT NULL,
  `ParentQuestionID` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `Title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Description` text COLLATE utf8mb4_unicode_ci,
  `TitleL2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Required` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Y',
  `Publish` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `QuestionOrder` int(11) NOT NULL DEFAULT '1',
  `CreateDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `CreateBy` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `UpdateDate` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `UpdateBy` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DelData` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  PRIMARY KEY (`QuestionID`),
  KEY `FormID` (`FormID`),
  KEY `SectionID` (`SectionID`),
  KEY `ParentQuestionID` (`ParentQuestionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ชื่อฟิลด์ (คำถาม) รวมถึง Sub-question ของ Matrix';

-- --------------------------------------------------------
-- Table structure for table `survey_question_type`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `survey_question_type` (
  `QuestionTypeID` int(11) NOT NULL AUTO_INCREMENT,
  `QuestionID` int(10) UNSIGNED NOT NULL,
  `QuestionType` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'text, textarea, radio, checkbox, select, matrix',
  PRIMARY KEY (`QuestionTypeID`),
  KEY `QuestionID` (`QuestionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ประเภทคำตอบ';

-- --------------------------------------------------------
-- Table structure for table `survey_question_option`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `survey_question_option` (
  `QuestionOptionID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `QuestionID` int(10) UNSIGNED NOT NULL,
  `GroupName` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TitleL2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `StatusOther` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `Publish` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `QuestionOptionOrder` int(11) DEFAULT '1',
  `CreateDate` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`QuestionOptionID`),
  KEY `QuestionID` (`QuestionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ช่องคำตอบ (ตัวเลือก)';

-- --------------------------------------------------------
-- Table structure for table `survey_submission`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `survey_submission` (
  `SubmissionID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `FormID` int(10) UNSIGNED NOT NULL,
  `SubmitIP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SubmitAgent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CreateDate` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`SubmissionID`),
  KEY `FormID` (`FormID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='เก็บ Session การส่งแบบสอบถาม 1 ครั้ง';

-- --------------------------------------------------------
-- Table structure for table `survey_answer`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `survey_answer` (
  `AnswerID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `SubmissionID` int(10) UNSIGNED NOT NULL,
  `QuestionID` int(10) UNSIGNED NOT NULL,
  `Answer` text COLLATE utf8mb4_unicode_ci,
  `CreateDate` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AnswerID`),
  KEY `SubmissionID` (`SubmissionID`),
  KEY `QuestionID` (`QuestionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='คำตอบข้อความหรือผูกกับ submission';

-- --------------------------------------------------------
-- Table structure for table `survey_answer_option`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `survey_answer_option` (
  `AnswerOptionID` int(11) NOT NULL AUTO_INCREMENT,
  `AnswerID` int(10) UNSIGNED NOT NULL,
  `QuestionOptionID` int(10) UNSIGNED NOT NULL,
  `QuestionOptionText` text COLLATE utf8mb4_unicode_ci,
  `CreateDate` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AnswerOptionID`),
  KEY `AnswerID` (`AnswerID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='คำตอบตัวเลือก';

-- --------------------------------------------------------
-- Mockup Data (Seeding)
-- --------------------------------------------------------

-- 1. ฟอร์มหลัก
INSERT INTO `survey_form` (`FormID`, `FormName`, `Description`, `IsActive`) VALUES
(1, 'แบบสอบถามแสดงความคิดเห็นให้คำแนะนำเกี่ยวกับเว็บไซต์', 'เว็บไซต์อยู่ระหว่างพัฒนาโปรดร่วมแสดงความคิดเห็น ระบุสิ่งที่ต้องการเพิ่มเติม หรือปรับปรุงแก้ไข เพื่อนำไปพัฒนาต่อไป', 'Y');

-- 2. ส่วนที่ (Sections)
INSERT INTO `survey_section` (`SectionID`, `FormID`, `Title`, `Description`, `SectionOrder`) VALUES
(1, 1, 'ส่วนที่ 1: หน่วยงาน/ สังกัด', NULL, 1),
(2, 1, 'ส่วนที่ 2: ความพึงพอใจต่อการใช้งานเว็บไซต์', '(ระดับคะแนน: 5 = ดีมาก, 4 = ดี, 3 = พอใช้, 2 = น้อย, 1 = ควรปรับปรุง)', 2),
(3, 1, 'ส่วนที่ 3: ข้อเสนอแนะและคำแนะนำเพิ่มเติม', NULL, 3);

-- 3. คำถาม (Questions)
INSERT INTO `survey_question` (`QuestionID`, `FormID`, `SectionID`, `ParentQuestionID`, `Title`, `Description`, `Required`, `QuestionOrder`, `Publish`) VALUES
-- Section 1
(1, 1, 1, 0, 'หน่วยงาน/ สังกัด', NULL, 'Y', 1, 'Y'),

-- Section 2 (Matrix Root Question)
(2, 1, 2, 0, 'หัวข้อการประเมิน', NULL, 'Y', 2, 'Y'),
-- Section 2 (Matrix Sub-Questions)
(3, 1, 2, 2, '1. ความสวยงาม', 'ภาพรวมการออกแบบดูเป็นมืออาชีพ ทันสมัย เหมาะกับองค์กร/แบรนด์', 'Y', 1, 'Y'),
(4, 1, 2, 2, '2. ความสบายตาในการอ่าน', 'ขนาด สี และรูปแบบตัวอักษร อ่านง่าย ไม่ปวดตา', 'Y', 2, 'Y'),
(5, 1, 2, 2, '3. ความง่ายในการจัดหมวดหมู่', 'การแบ่งหมวดหมู่เมนูหลัก เช่น ข้อมูลองค์กร บริการประชาชน หรือข่าวสาร ชัดเจน ไม่สับสน', 'Y', 3, 'Y'),
(6, 1, 2, 2, '4. การรองรับมือถือ และอุปกรณ์ต่างๆ', 'หน้าเว็บจัดวางสวยงาม อ่านง่าย และใช้งานสะดวกบนสมาร์ทโฟน', 'Y', 4, 'Y'),

-- Section 3
(7, 1, 3, 0, 'เมนูหรือฟังก์ชันใดที่คุณใช้งานบ่อยที่สุด:', NULL, 'N', 1, 'Y'),
(8, 1, 3, 0, 'ปัญหาหรืออุปสรรคที่พบเจอระหว่างใช้งานเว็บไซต์ (ถ้ามี):', NULL, 'N', 2, 'Y'),
(9, 1, 3, 0, 'ข้อเสนอแนะเพิ่มเติมเพื่อการปรับปรุงและพัฒนาเว็บไซต์:', NULL, 'N', 3, 'Y');

-- 4. ประเภทคำถาม (Question Types)
INSERT INTO `survey_question_type` (`QuestionTypeID`, `QuestionID`, `QuestionType`) VALUES
(1, 1, 'select'),
(2, 2, 'matrix'),
(3, 3, 'radio'),
(4, 4, 'radio'),
(5, 5, 'radio'),
(6, 6, 'radio'),
(7, 7, 'text'),
(8, 8, 'textarea'),
(9, 9, 'textarea');

-- 5. ตัวเลือก (Options)
INSERT INTO `survey_question_option` (`QuestionOptionID`, `QuestionID`, `GroupName`, `Title`, `StatusOther`, `QuestionOptionOrder`, `Publish`) VALUES
-- Options for Select (Q1) - ส่วนกลาง
(1, 1, 'ส่วนกลาง', 'นักงานผู้เชี่ยวชาญ', 'N', 1, 'Y'),
(2, 1, 'ส่วนกลาง', 'สำนักบริหารกลาง', 'N', 2, 'Y'),
(3, 1, 'ส่วนกลาง', 'ส่วนบริหารทรัพยากรบุคคล', 'N', 3, 'Y'),
(4, 1, 'ส่วนกลาง', 'ส่วนฝึกอบรม', 'N', 4, 'Y'),
(5, 1, 'ส่วนกลาง', 'ส่วนวินัยและคุณธรรม', 'N', 5, 'Y'),
(6, 1, 'ส่วนกลาง', 'กองแผนงาน', 'N', 6, 'Y'),
(7, 1, 'ส่วนกลาง', 'กลุ่มพัฒนาระบบบริหาร', 'N', 7, 'Y'),
(8, 1, 'ส่วนกลาง', 'กลุ่มตรวจสอบภายใน', 'N', 8, 'Y'),
(9, 1, 'ส่วนกลาง', 'กลุ่มนิติการ', 'N', 9, 'Y'),
(10, 1, 'ส่วนกลาง', 'สำนักพัฒนาน้ำบาดาล', 'N', 10, 'Y'),
(11, 1, 'ส่วนกลาง', 'สำนักควบคุมกิจการน้ำบาดาล', 'N', 11, 'Y'),
(12, 1, 'ส่วนกลาง', 'สำนักสำรวจและประเมินศักยภาพน้ำบาดาล', 'N', 12, 'Y'),
(13, 1, 'ส่วนกลาง', 'สำนักอนุรักษ์และฟื้นฟูทรัพยากรน้ำบาดาล', 'N', 13, 'Y'),
(14, 1, 'ส่วนกลาง', 'กองมาตรฐานคุณภาพน้ำบาดาล', 'N', 14, 'Y'),
(15, 1, 'ส่วนกลาง', 'ศูนย์เทคโนโลยีสารสนเทศทรัพยากรน้ำบาดาล', 'N', 15, 'Y'),
(16, 1, 'ส่วนกลาง', 'กองบริหารกองทุนพัฒนาน้ำบาดาล', 'N', 16, 'Y'),
(17, 1, 'ส่วนกลาง', 'ศูนย์ปฏิบัติการเพื่อการสนับสนุนโครงการอัน เนื่องมาจากพระราชดำริและโครงการพิเศษ', 'N', 17, 'Y'),
(18, 1, 'ส่วนกลาง', 'ศูนย์ข้อมูลข่าวสารอิเล็กทรอนิกส์ของราชการ', 'N', 18, 'Y'),

-- Options for Select (Q1) - ส่วนภูมิภาค
(19, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 1 (ลำปาง)', 'N', 19, 'Y'),
(20, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 2 (สุพรรณบุรี)', 'N', 20, 'Y'),
(21, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 3 (สระบุรี)', 'N', 21, 'Y'),
(22, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 4 (ขอนแก่น)', 'N', 22, 'Y'),
(23, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 5 (นครราชสีมา)', 'N', 23, 'Y'),
(24, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 6 (ตรัง)', 'N', 24, 'Y'),
(25, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 7 (กำแพงเพชร)', 'N', 25, 'Y'),
(26, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 8 (ราชบุรี)', 'N', 26, 'Y'),
(27, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 9 (ระยอง)', 'N', 27, 'Y'),
(28, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 10 (อุดรธานี)', 'N', 28, 'Y'),
(29, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 11 (อุบลราชธานี)', 'N', 29, 'Y'),
(30, 1, 'ส่วนภูมิภาค', 'สำนักทรัพยากรน้ำบาดาล เขต 12 (สงขลา)', 'N', 30, 'Y'),

-- Options for Select (Q1) - Other
(31, 1, NULL, 'อื่นๆ ระบุ', 'Y', 31, 'Y'),

-- Options for Matrix (Q2 holds the columns)
(32, 2, NULL, '5', 'N', 1, 'Y'),
(33, 2, NULL, '4', 'N', 2, 'Y'),
(34, 2, NULL, '3', 'N', 3, 'Y'),
(35, 2, NULL, '2', 'N', 4, 'Y'),
(36, 2, NULL, '1', 'N', 5, 'Y');
