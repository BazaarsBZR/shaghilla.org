-- Patch: feed destination + article placement (home vs breaking ticker)
-- Use (phpMyAdmin): select your DB -> Import this file.
--
-- Safe to import multiple times:
-- - Adds missing columns using INFORMATION_SCHEMA checks

SET NAMES utf8mb4;

SET @db := DATABASE();

-- feed_sources.destination (home | both | breaking)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='feed_sources' AND COLUMN_NAME='destination');
SET @sql := IF(@col = 0, 'ALTER TABLE `feed_sources` ADD COLUMN `destination` varchar(20) NOT NULL DEFAULT ''both'' AFTER `url`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='feed_sources' AND INDEX_NAME='feed_sources_destination_index');
SET @sql := IF(@idx = 0, 'CREATE INDEX `feed_sources_destination_index` ON `feed_sources` (`destination`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- articles.show_on_home
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='articles' AND COLUMN_NAME='show_on_home');
SET @sql := IF(@col = 0, 'ALTER TABLE `articles` ADD COLUMN `show_on_home` tinyint(1) NOT NULL DEFAULT 1 AFTER `is_breaking`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='articles' AND INDEX_NAME='articles_show_on_home_index');
SET @sql := IF(@idx = 0, 'CREATE INDEX `articles_show_on_home_index` ON `articles` (`show_on_home`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- articles.is_breaking_locked (prevents RSS importer overriding admin choice)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='articles' AND COLUMN_NAME='is_breaking_locked');
SET @sql := IF(@col = 0, 'ALTER TABLE `articles` ADD COLUMN `is_breaking_locked` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_on_home`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='articles' AND INDEX_NAME='articles_is_breaking_locked_index');
SET @sql := IF(@idx = 0, 'CREATE INDEX `articles_is_breaking_locked_index` ON `articles` (`is_breaking_locked`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- articles.show_on_home_locked (prevents RSS importer overriding admin choice)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='articles' AND COLUMN_NAME='show_on_home_locked');
SET @sql := IF(@col = 0, 'ALTER TABLE `articles` ADD COLUMN `show_on_home_locked` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_breaking_locked`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='articles' AND INDEX_NAME='articles_show_on_home_locked_index');
SET @sql := IF(@idx = 0, 'CREATE INDEX `articles_show_on_home_locked_index` ON `articles` (`show_on_home_locked`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

