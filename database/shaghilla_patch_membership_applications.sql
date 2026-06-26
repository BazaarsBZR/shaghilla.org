-- Patch: membership_applications (public membership form submissions)
-- Use (phpMyAdmin): select your DB -> Import this file.
--
-- This patch is safe to import multiple times:
-- - Creates the table if missing
-- - Adds new columns for older installs

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `membership_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `mother_name` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `registry_number` varchar(255) DEFAULT NULL,
  `registration_place` varchar(255) DEFAULT NULL,
  `phone` varchar(255) NOT NULL,
  `emergency_phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `profession` varchar(255) DEFAULT NULL,
  `marital_status` varchar(255) DEFAULT NULL,
  `children_count` int unsigned DEFAULT NULL,
  `blood_type` varchar(255) DEFAULT NULL,
  `volunteer_areas` longtext DEFAULT NULL,
  `volunteer_other` varchar(255) DEFAULT NULL,
  `has_volunteer_experience` tinyint(1) DEFAULT NULL,
  `volunteer_experience_details` longtext DEFAULT NULL,
  `id_document_path` varchar(255) DEFAULT NULL,
  `signature_name` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `membership_applications_birth_date_index` (`birth_date`),
  KEY `membership_applications_marital_status_index` (`marital_status`),
  KEY `membership_applications_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing columns (for installs that imported the older patch)
SET @db := DATABASE();

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='membership_applications' AND COLUMN_NAME='registry_number');
SET @sql := IF(@col = 0, 'ALTER TABLE `membership_applications` ADD COLUMN `registry_number` varchar(255) DEFAULT NULL AFTER `birth_date`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='membership_applications' AND COLUMN_NAME='registration_place');
SET @sql := IF(@col = 0, 'ALTER TABLE `membership_applications` ADD COLUMN `registration_place` varchar(255) DEFAULT NULL AFTER `registry_number`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='membership_applications' AND COLUMN_NAME='volunteer_areas');
SET @sql := IF(@col = 0, 'ALTER TABLE `membership_applications` ADD COLUMN `volunteer_areas` longtext DEFAULT NULL AFTER `blood_type`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='membership_applications' AND COLUMN_NAME='volunteer_other');
SET @sql := IF(@col = 0, 'ALTER TABLE `membership_applications` ADD COLUMN `volunteer_other` varchar(255) DEFAULT NULL AFTER `volunteer_areas`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='membership_applications' AND COLUMN_NAME='has_volunteer_experience');
SET @sql := IF(@col = 0, 'ALTER TABLE `membership_applications` ADD COLUMN `has_volunteer_experience` tinyint(1) DEFAULT NULL AFTER `volunteer_other`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='membership_applications' AND COLUMN_NAME='volunteer_experience_details');
SET @sql := IF(@col = 0, 'ALTER TABLE `membership_applications` ADD COLUMN `volunteer_experience_details` longtext DEFAULT NULL AFTER `has_volunteer_experience`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Optional: seed default settings for the membership form
INSERT INTO `site_settings` (`key`, `value`, `created_at`, `updated_at`) VALUES
  ('membership_page_title_ar','طلب انتساب إلى رابطة الشغيلــة',NOW(),NOW()),
  ('membership_form_success_ar','تم إرسال طلب الانتساب بنجاح.',NOW(),NOW()),
  ('membership_form_submit_label_ar','إرسال الطلب',NOW(),NOW()),
  ('membership_form_id_label_ar','صورة الهوية / جواز السفر',NOW(),NOW())
ON DUPLICATE KEY UPDATE
  `value` = VALUES(`value`),
  `updated_at` = VALUES(`updated_at`);
