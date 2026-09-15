-- Run once before deploying the updated microsite/news/action.php.
-- Adds the standard create/update audit fields used by other microsite tables.
ALTER TABLE `microsite_news_files`
    ADD COLUMN `CreateDate` datetime DEFAULT NULL AFTER `DownloadCount`,
    ADD COLUMN `CreateBy` int DEFAULT NULL AFTER `CreateDate`,
    ADD COLUMN `CreateIP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `CreateBy`,
    ADD COLUMN `UpdateDate` datetime DEFAULT NULL AFTER `CreateIP`,
    ADD COLUMN `UpdateBy` int DEFAULT NULL AFTER `UpdateDate`,
    ADD COLUMN `UpdateIP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `UpdateBy`;

ALTER TABLE `microsite_download` ADD `StartDate` DATETIME NULL DEFAULT NULL AFTER `IsActive`, ADD `EndDate` DATETIME NULL DEFAULT NULL AFTER `StartDate`;
ALTER TABLE `microsite_download` ADD `Views` INT NOT NULL DEFAULT '0' AFTER `File`;
