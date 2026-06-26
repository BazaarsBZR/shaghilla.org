-- Shaghilla.org (Laravel 11 / Filament) — MySQL/MariaDB install
-- Database: shag
--
-- How to use (phpMyAdmin):
-- 1) Select your database (shag)
-- 2) Import this file (SQL)
-- 3) Set the admin password (this file ships WITHOUT one — see the admin-user
--    seed near the end of this file), then login at https://shaghilla.org/admin
--    Email: admin@shaghilla.org
--
-- IMPORTANT — this file contains NO passwords or secrets. After import:
--   - Set the admin password (see "Seed: admin user" below for the one-liner).
--   - Set a real rss_import_secret (seeded as "change-me") in Filament → Site Settings.
--   - Set a real almanar_urgent_secret if you use the Al-Manar urgent webhook.
--
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL,
  `name_ar` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feed_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `url` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_fetched_at` timestamp NULL DEFAULT NULL,
  `etag` varchar(255) DEFAULT NULL,
  `last_modified` varchar(255) DEFAULT NULL,
  `default_category_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `feed_sources_is_active_index` (`is_active`),
  KEY `feed_sources_default_category_id_foreign` (`default_category_id`),
  CONSTRAINT `feed_sources_default_category_id_foreign`
    FOREIGN KEY (`default_category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `keyword_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL,
  `keyword` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `keyword_rules_type_keyword_unique` (`type`,`keyword`),
  KEY `keyword_rules_type_is_active_index` (`type`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL,
  `route_name` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `external_url` text DEFAULT NULL,
  `title_ar` varchar(255) NOT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `content_html_ar` longtext DEFAULT NULL,
  `content_html_en` longtext DEFAULT NULL,
  `open_in_new_tab` tinyint(1) NOT NULL DEFAULT 0,
  `show_in_header` tinyint(1) NOT NULL DEFAULT 0,
  `show_in_footer` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_pages_route_name_unique` (`route_name`),
  UNIQUE KEY `site_pages_slug_unique` (`slug`),
  KEY `site_pages_type_index` (`type`),
  KEY `site_pages_show_in_header_index` (`show_in_header`),
  KEY `site_pages_show_in_footer_index` (`show_in_footer`),
  KEY `site_pages_sort_order_index` (`sort_order`),
  KEY `site_pages_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `membership_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `mother_name` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `phone` varchar(255) NOT NULL,
  `emergency_phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `profession` varchar(255) DEFAULT NULL,
  `marital_status` varchar(255) DEFAULT NULL,
  `children_count` int unsigned DEFAULT NULL,
  `blood_type` varchar(255) DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS `video_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `youtube_url` text NOT NULL,
  `thumbnail_url` text DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `video_items_published_at_index` (`published_at`),
  KEY `video_items_type_is_active_sort_order_index` (`type`,`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hosted_videos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `video_path` varchar(255) NOT NULL,
  `poster_path` varchar(255) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hosted_videos_slug_unique` (`slug`),
  KEY `hosted_videos_published_at_index` (`published_at`),
  KEY `hosted_videos_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `contact_messages_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `articles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `feed_source_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned NOT NULL,
  `title` text NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `canonical_url` text DEFAULT NULL,
  `canonical_url_hash` char(64) DEFAULT NULL,
  `guid` text DEFAULT NULL,
  `guid_hash` char(64) NOT NULL,
  `image_url` text DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `imported_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_breaking` tinyint(1) NOT NULL DEFAULT 0,
  `language` varchar(5) NOT NULL DEFAULT 'ar',
  `status` varchar(255) NOT NULL DEFAULT 'published',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `articles_slug_unique` (`slug`),
  UNIQUE KEY `articles_feed_source_id_guid_hash_unique` (`feed_source_id`,`guid_hash`),
  UNIQUE KEY `articles_canonical_url_hash_unique` (`canonical_url_hash`),
  KEY `articles_published_at_index` (`published_at`),
  KEY `articles_imported_at_index` (`imported_at`),
  KEY `articles_is_breaking_index` (`is_breaking`),
  KEY `articles_status_index` (`status`),
  KEY `articles_category_id_published_at_index` (`category_id`,`published_at`),
  KEY `articles_feed_source_id_foreign` (`feed_source_id`),
  CONSTRAINT `articles_feed_source_id_foreign`
    FOREIGN KEY (`feed_source_id`) REFERENCES `feed_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `articles_category_id_foreign`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Seed: categories
INSERT INTO `categories` (`slug`, `name_ar`, `name_en`, `sort_order`, `is_system`, `created_at`, `updated_at`)
VALUES
  ('lebanon', 'لبنان', 'Lebanon', 1, 1, NOW(), NOW()),
  ('workers', 'عمال', 'Workers', 2, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name_ar` = VALUES(`name_ar`),
  `name_en` = VALUES(`name_en`),
  `sort_order` = VALUES(`sort_order`),
  `is_system` = VALUES(`is_system`),
  `updated_at` = VALUES(`updated_at`);

-- Seed: keyword rules
INSERT INTO `keyword_rules` (`type`, `keyword`, `is_active`, `created_at`, `updated_at`) VALUES
  ('worker','عامل',1,NOW(),NOW()),
  ('worker','عمال',1,NOW(),NOW()),
  ('worker','العمل',1,NOW(),NOW()),
  ('worker','وظائف',1,NOW(),NOW()),
  ('worker','وظيفة',1,NOW(),NOW()),
  ('worker','توظيف',1,NOW(),NOW()),
  ('worker','أجور',1,NOW(),NOW()),
  ('worker','رواتب',1,NOW(),NOW()),
  ('worker','الحد الأدنى للأجور',1,NOW(),NOW()),
  ('worker','ضمان',1,NOW(),NOW()),
  ('worker','الضمان الاجتماعي',1,NOW(),NOW()),
  ('worker','تعويض',1,NOW(),NOW()),
  ('worker','تقاعد',1,NOW(),NOW()),
  ('worker','بطالة',1,NOW(),NOW()),
  ('worker','دوام',1,NOW(),NOW()),
  ('worker','حقوق العمال',1,NOW(),NOW()),
  ('worker','نقابة',1,NOW(),NOW()),
  ('worker','نقابات',1,NOW(),NOW()),
  ('worker','اتحاد العمال',1,NOW(),NOW()),
  ('worker','اتحاد',1,NOW(),NOW()),
  ('worker','إضراب',1,NOW(),NOW()),
  ('worker','اعتصام',1,NOW(),NOW()),
  ('worker','احتجاج',1,NOW(),NOW()),
  ('worker','تظاهرة',1,NOW(),NOW()),
  ('worker','تحرك عمالي',1,NOW(),NOW()),
  ('worker','مطالب عمالية',1,NOW(),NOW()),
  ('worker','وزارة العمل',1,NOW(),NOW()),
  ('worker','تفتيش العمل',1,NOW(),NOW()),
  ('worker','قانون العمل',1,NOW(),NOW()),
  ('worker','الصندوق الوطني للضمان',1,NOW(),NOW()),
  ('breaking','عاجل',1,NOW(),NOW()),
  ('breaking','الآن',1,NOW(),NOW()),
  ('breaking','بيروت',1,NOW(),NOW()),
  ('breaking','لبنان',1,NOW(),NOW()),
  ('breaking','إضراب',1,NOW(),NOW()),
  ('breaking','اعتصام',1,NOW(),NOW()),
  ('breaking','احتجاج',1,NOW(),NOW()),
  ('breaking','قطع طرق',1,NOW(),NOW()),
  ('breaking','تصعيد',1,NOW(),NOW()),
  ('breaking','مواجهات',1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  `is_active` = VALUES(`is_active`),
  `updated_at` = VALUES(`updated_at`);

-- Seed: feed sources (insert only if missing)
INSERT INTO `feed_sources` (`name`, `url`, `is_active`, `default_category_id`, `created_at`, `updated_at`)
SELECT 'Lebanon24 - Lebanon', 'https://www.lebanon24.com/Rss/News/1/لبنان', 1, (SELECT `id` FROM `categories` WHERE `slug`='lebanon' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `feed_sources` WHERE `url`='https://www.lebanon24.com/Rss/News/1/لبنان' LIMIT 1);

INSERT INTO `feed_sources` (`name`, `url`, `is_active`, `default_category_id`, `created_at`, `updated_at`)
SELECT 'Lebanon24 - Breaking', 'https://www.lebanon24.com/Rss/News/23/أخبار-عاجلة', 1, (SELECT `id` FROM `categories` WHERE `slug`='lebanon' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `feed_sources` WHERE `url`='https://www.lebanon24.com/Rss/News/23/أخبار-عاجلة' LIMIT 1);

INSERT INTO `feed_sources` (`name`, `url`, `is_active`, `default_category_id`, `created_at`, `updated_at`)
SELECT 'NNA (English)', 'https://www.nna-leb.gov.lb/en/rss', 1, (SELECT `id` FROM `categories` WHERE `slug`='lebanon' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `feed_sources` WHERE `url`='https://www.nna-leb.gov.lb/en/rss' LIMIT 1);

-- Seed: site settings
INSERT INTO `site_settings` (`key`, `value`, `created_at`, `updated_at`) VALUES
  ('live_youtube_url','',NOW(),NOW()),
  ('live_youtube_playlist_limit','12',NOW(),NOW()),
  ('ticker_limit','10',NOW(),NOW()),
  ('breaking_list_limit','20',NOW(),NOW()),
  ('rss_item_limit','10',NOW(),NOW()),
  ('rss_cron_enabled','1',NOW(),NOW()),
  ('rss_cron_interval_minutes','60',NOW(),NOW()),
  ('almanar_urgent_enabled','0',NOW(),NOW()),
  ('almanar_urgent_limit','10',NOW(),NOW()),
  ('almanar_urgent_secret','',NOW(),NOW()),
  ('almanar_urgent_cron_enabled','1',NOW(),NOW()),
  ('almanar_urgent_cron_interval_minutes','1',NOW(),NOW()),
  ('breaking_ticker_poll_enabled','0',NOW(),NOW()),
  ('breaking_ticker_poll_interval_seconds','45',NOW(),NOW()),
  ('home_hosted_videos_enabled','1',NOW(),NOW()),
  ('home_hosted_videos_title_ar','الفيديو',NOW(),NOW()),
  ('home_hosted_videos_limit','12',NOW(),NOW()),
  ('home_hosted_videos_layout','grid',NOW(),NOW()),
  ('home_hosted_videos_include_youtube','1',NOW(),NOW()),
  ('home_hosted_videos_placeholders_enabled','1',NOW(),NOW()),
  ('home_hosted_videos_placeholder_count','8',NOW(),NOW()),
  ('home_hosted_videos_placeholder_title_ar','رابطة الشغيلة',NOW(),NOW()),
  ('home_hosted_videos_placeholder_youtube_url','https://www.youtube.com/watch?v=QyR01ZMIIqE&t=10690s',NOW(),NOW()),
  ('contact_page_intro_ar','اكتب لنا تفاصيل طلب الخدمة وسنقوم بالمتابعة بأقرب وقت.',NOW(),NOW()),
  ('contact_page_success_ar','تم إرسال رسالتك بنجاح.',NOW(),NOW()),
  ('contact_page_title_ar','طلب الخدمة',NOW(),NOW()),
  ('contact_form_name_label_ar','الاسم',NOW(),NOW()),
  ('contact_form_email_label_ar','رقم الهاتف',NOW(),NOW()),
  ('contact_form_subject_label_ar','الموضوع',NOW(),NOW()),
  ('contact_form_message_label_ar','الرسالة',NOW(),NOW()),
  ('contact_form_submit_label_ar','إرسال',NOW(),NOW()),
  ('membership_page_intro_ar','يرجى تعبئة نموذج الانتساب والتطوّع كاملًا ثم الضغط على «تحقّق».',NOW(),NOW()),
  ('membership_page_title_ar','طلب انتساب إلى رابطة الشغيلــة',NOW(),NOW()),
  ('membership_form_success_ar','تم إرسال طلب الانتساب بنجاح.',NOW(),NOW()),
  ('membership_form_submit_label_ar','إرسال الطلب',NOW(),NOW()),
  ('membership_form_id_label_ar','صورة الهوية / جواز السفر',NOW(),NOW()),
  ('header_weather_enabled','1',NOW(),NOW()),
  ('header_weather_label_ar','لبنان',NOW(),NOW()),
  ('header_weather_lat','33.8938',NOW(),NOW()),
  ('header_weather_lon','35.5018',NOW(),NOW()),
  ('rss_import_secret','change-me',NOW(),NOW()),
  ('last_rss_import_at',NULL,NOW(),NOW()),
  ('last_rss_import_source',NULL,NOW(),NOW()),
  ('last_rss_import_result',NULL,NOW(),NOW()),
  ('last_rss_cron_hit_at',NULL,NOW(),NOW()),
  ('last_rss_cron_hit_ip',NULL,NOW(),NOW()),
  ('last_rss_cron_hit_method',NULL,NOW(),NOW()),
  ('last_rss_cron_hit_ua',NULL,NOW(),NOW()),
  ('last_rss_cron_hit_mode',NULL,NOW(),NOW()),
  ('last_almanar_urgent_import_at',NULL,NOW(),NOW()),
  ('last_almanar_urgent_import_source',NULL,NOW(),NOW()),
  ('last_almanar_urgent_import_result',NULL,NOW(),NOW()),
  ('last_almanar_urgent_cron_hit_at',NULL,NOW(),NOW()),
  ('last_almanar_urgent_cron_hit_ip',NULL,NOW(),NOW()),
  ('last_almanar_urgent_cron_hit_method',NULL,NOW(),NOW()),
  ('last_almanar_urgent_cron_hit_ua',NULL,NOW(),NOW()),
  ('last_almanar_urgent_cron_hit_mode',NULL,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  `value` = VALUES(`value`),
  `updated_at` = VALUES(`updated_at`);

-- Seed: menus (header/footer) + custom pages
INSERT INTO `site_pages` (`type`, `route_name`, `slug`, `external_url`, `title_ar`, `title_en`, `content_html_ar`, `content_html_en`, `open_in_new_tab`, `show_in_header`, `show_in_footer`, `sort_order`, `is_system`, `is_active`, `created_at`, `updated_at`)
VALUES
  ('route','home',NULL,NULL,'الرئيسية','Home',NULL,NULL,0,1,1,1,1,1,NOW(),NOW()),
  ('route','live',NULL,NULL,'مباشر','Live',NULL,NULL,0,1,1,2,1,1,NOW(),NOW()),
  ('route','membership',NULL,NULL,'انتساب','Membership',NULL,NULL,0,1,1,3,1,1,NOW(),NOW()),
  ('route','contact',NULL,NULL,'طلب الخدمة','Contact us',NULL,NULL,0,1,1,4,1,1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  `type` = VALUES(`type`),
  `title_ar` = VALUES(`title_ar`),
  `title_en` = VALUES(`title_en`),
  `show_in_header` = VALUES(`show_in_header`),
  `show_in_footer` = VALUES(`show_in_footer`),
  `sort_order` = VALUES(`sort_order`),
  `is_system` = VALUES(`is_system`),
  `is_active` = VALUES(`is_active`),
  `updated_at` = VALUES(`updated_at`);

-- Seed: admin user (insert only if missing).
-- No password ships in this file: the row is created with an EMPTY password hash,
-- so the account CANNOT be logged into until you set one. To set the password,
-- generate a bcrypt hash and run the UPDATE below (replace YOUR_NEW_PASSWORD):
--   php -r "echo password_hash('YOUR_NEW_PASSWORD', PASSWORD_BCRYPT, ['cost' => 12]);"
--   UPDATE `users` SET `password` = '<paste-the-generated-hash-here>'
--     WHERE `email` = 'admin@shaghilla.org';
-- (Alternatively, run the AdminUserSeeder, which reads ADMIN_EMAIL / ADMIN_PASSWORD from .env.)
INSERT INTO `users` (`name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`)
SELECT 'Admin', 'admin@shaghilla.org', NOW(), '', NULL, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'admin@shaghilla.org' LIMIT 1);
