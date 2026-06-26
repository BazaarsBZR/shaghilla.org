-- Patch: add site_pages (menus + custom pages)
-- Use (phpMyAdmin): select your DB -> Import this file.

SET NAMES utf8mb4;

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

