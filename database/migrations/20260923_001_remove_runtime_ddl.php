<?php
/**
 * Move legacy schema changes out of Backoffice HTTP requests.
 *
 * Run before deploying the PHP files that remove Runtime DDL:
 * php database/migrations/20260923_001_remove_runtime_ddl.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('_VALID_ACCESS', 1);
require dirname(__DIR__, 2) . '/configuration.php';
require dirname(__DIR__, 2) . '/config/ms_com.php';

$db = mosConnectPDO();
$changes = 0;

function migrationTableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function migrationColumnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function migrationAddColumn(PDO $db, string $table, string $column, string $definition): bool
{
    if (!migrationTableExists($db, $table)) {
        throw new RuntimeException("Required table does not exist: {$table}");
    }
    if (migrationColumnExists($db, $table, $column)) {
        return false;
    }

    $safeTable = str_replace('`', '``', $table);
    $safeColumn = str_replace('`', '``', $column);
    $db->exec("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
    echo "Added {$table}.{$column}" . PHP_EOL;
    return true;
}

$db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `org_pages_attachments` (
    `AttachmentID` int NOT NULL AUTO_INCREMENT,
    `PageID` int NOT NULL,
    `FileName_TH` varchar(255) DEFAULT NULL,
    `FileName_EN` varchar(255) DEFAULT NULL,
    `FilePath` varchar(255) NOT NULL,
    `FileType` varchar(10) DEFAULT NULL,
    `FileSize` int DEFAULT 0,
    `OrderNo` int DEFAULT 1,
    `DelData` enum('Y','N') DEFAULT 'N',
    `CreateDate` datetime DEFAULT NULL,
    `CreateBy` int DEFAULT NULL,
    `CreateIP` varchar(50) DEFAULT NULL,
    `DelDataDate` datetime DEFAULT NULL,
    `DelDataBy` int DEFAULT NULL,
    `DelDataIP` varchar(50) DEFAULT NULL,
    PRIMARY KEY (`AttachmentID`),
    KEY `idx_org_pages_attachments_page` (`PageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `org_pages_timelines` (
    `TimelineID` int NOT NULL AUTO_INCREMENT,
    `PageID` int NOT NULL,
    `Year_TH` varchar(255) DEFAULT NULL,
    `Year_EN` varchar(255) DEFAULT NULL,
    `Title_TH` varchar(255) DEFAULT NULL,
    `Title_EN` varchar(255) DEFAULT NULL,
    `Description_TH` text DEFAULT NULL,
    `Description_EN` text DEFAULT NULL,
    `OrderNo` int DEFAULT 1,
    `IsHeader` enum('Y','N') DEFAULT 'N',
    `DelData` enum('Y','N') DEFAULT 'N',
    `CreateDate` datetime DEFAULT NULL,
    `CreateBy` int DEFAULT NULL,
    `CreateIP` varchar(50) DEFAULT NULL,
    `UpdateDate` datetime DEFAULT NULL,
    `UpdateBy` int DEFAULT NULL,
    `UpdateIP` varchar(50) DEFAULT NULL,
    `DelDataDate` datetime DEFAULT NULL,
    `DelDataBy` int DEFAULT NULL,
    `DelDataIP` varchar(50) DEFAULT NULL,
    PRIMARY KEY (`TimelineID`),
    KEY `idx_org_pages_timelines_page` (`PageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$columns = [
    'weblinks' => [
        'URLTH' => 'varchar(512) DEFAULT NULL',
        'URLEN' => 'varchar(512) DEFAULT NULL',
        'CoverImage' => 'varchar(255) DEFAULT NULL',
        'DelData' => "enum('Y','N') DEFAULT 'N'",
        'DelDataDate' => 'datetime DEFAULT NULL',
        'DelDataBy' => 'int DEFAULT NULL',
        'DelDataIP' => 'varchar(50) DEFAULT NULL',
    ],
    'weblinks_groups' => [
        'CreateBy' => 'int DEFAULT NULL',
        'CreateIP' => 'varchar(50) DEFAULT NULL',
        'UpdateDate' => 'datetime DEFAULT NULL',
        'UpdateBy' => 'int DEFAULT NULL',
        'UpdateIP' => 'varchar(50) DEFAULT NULL',
        'DelData' => "enum('Y','N') DEFAULT 'N'",
        'DelDataDate' => 'datetime DEFAULT NULL',
        'DelDataBy' => 'int DEFAULT NULL',
        'DelDataIP' => 'varchar(50) DEFAULT NULL',
    ],
    'videos' => [
        'URLTH' => 'varchar(512) DEFAULT NULL',
        'URLEN' => 'varchar(512) DEFAULT NULL',
        'CoverImage' => 'varchar(255) DEFAULT NULL',
        'DelData' => "enum('Y','N') DEFAULT 'N'",
        'DelDataDate' => 'datetime DEFAULT NULL',
        'DelDataBy' => 'int DEFAULT NULL',
        'DelDataIP' => 'varchar(50) DEFAULT NULL',
    ],
    'videos_groups' => [
        'CreateBy' => 'int DEFAULT NULL',
        'CreateIP' => 'varchar(50) DEFAULT NULL',
        'UpdateDate' => 'datetime DEFAULT NULL',
        'UpdateBy' => 'int DEFAULT NULL',
        'UpdateIP' => 'varchar(50) DEFAULT NULL',
        'DelData' => "enum('Y','N') DEFAULT 'N'",
        'DelDataDate' => 'datetime DEFAULT NULL',
        'DelDataBy' => 'int DEFAULT NULL',
        'DelDataIP' => 'varchar(50) DEFAULT NULL',
    ],
    'survey_form' => [
        'OrderNo' => 'int DEFAULT 0',
    ],
    'qa' => [
        'URLTH' => 'varchar(512) DEFAULT NULL',
        'URLEN' => 'varchar(512) DEFAULT NULL',
        'CoverImage' => 'varchar(255) DEFAULT NULL',
    ],
    'hero_banners' => [
        'PublishStart' => 'date DEFAULT NULL',
        'PublishEnd' => 'date DEFAULT NULL',
    ],
    'org_pages' => [
        'CanFile' => "enum('Y','N') DEFAULT 'Y'",
        'HasFile' => "enum('Y','N') DEFAULT 'N'",
        'CanTimeline' => "enum('Y','N') DEFAULT 'Y'",
        'HasTimeline' => "enum('Y','N') DEFAULT 'N'",
    ],
    'org_pages_timelines' => [
        'IsHeader' => "enum('Y','N') DEFAULT 'N' AFTER `OrderNo`",
    ],
];

foreach ($columns as $table => $tableColumns) {
    foreach ($tableColumns as $column => $definition) {
        if (migrationAddColumn($db, $table, $column, $definition)) {
            $changes++;
        }
    }
}

echo "Runtime DDL migration complete ({$changes} column changes)." . PHP_EOL;
