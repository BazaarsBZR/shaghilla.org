-- Shaghilla.org — Remove legacy Telegram tables/data (MySQL/MariaDB)
--
-- Use this when you no longer use Telegram features and want to clean the DB.
-- Safe to run multiple times.
--
-- How to use (phpMyAdmin):
-- 1) Select your database (e.g. `shag`)
-- 2) Import this file

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop legacy Telegram-related tables (if they exist).
DROP TABLE IF EXISTS `membership_statuses`;
DROP TABLE IF EXISTS `membership_status`;
DROP TABLE IF EXISTS `telegram_webhook_updates`;
DROP TABLE IF EXISTS `telegram_updates`;
DROP TABLE IF EXISTS `telegram_users`;

-- Remove Telegram from social share platforms (if the table exists).
SET @db := DATABASE();
SET @has_share_platforms := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = @db AND table_name = 'share_platforms'
);
SET @sql := IF(
  @has_share_platforms > 0,
  "DELETE FROM `share_platforms` WHERE `slug`='telegram' OR `icon`='fa-telegram' OR `name`='Telegram';",
  "SELECT 1;"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remove Telegram-related settings (if any).
SET @has_site_settings := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = @db AND table_name = 'site_settings'
);
SET @sql2 := IF(
  @has_site_settings > 0,
  "DELETE FROM `site_settings` WHERE `key` LIKE 'telegram_%' OR `key` LIKE 'TELEGRAM_%';",
  "SELECT 1;"
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET FOREIGN_KEY_CHECKS = 1;

