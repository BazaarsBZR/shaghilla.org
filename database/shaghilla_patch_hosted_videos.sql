-- Patch: add hosted_videos (self-hosted uploads)
-- Use (phpMyAdmin): select your DB -> Import this file.

SET NAMES utf8mb4;

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

-- Optional: seed default settings for the hosted videos section
INSERT INTO `site_settings` (`key`, `value`, `created_at`, `updated_at`) VALUES
  ('home_hosted_videos_enabled','1',NOW(),NOW()),
  ('home_hosted_videos_title_ar','الفيديو',NOW(),NOW()),
  ('home_hosted_videos_limit','12',NOW(),NOW()),
  ('home_hosted_videos_layout','grid',NOW(),NOW()),
  ('home_hosted_videos_include_youtube','1',NOW(),NOW()),
  ('home_hosted_videos_placeholders_enabled','1',NOW(),NOW()),
  ('home_hosted_videos_placeholder_count','8',NOW(),NOW()),
  ('home_hosted_videos_placeholder_title_ar','رابطة الشغيلة',NOW(),NOW()),
  ('home_hosted_videos_placeholder_youtube_url','https://www.youtube.com/watch?v=QyR01ZMIIqE&t=10690s',NOW(),NOW()),
  ('live_youtube_playlist_limit','12',NOW(),NOW())
ON DUPLICATE KEY UPDATE
  `value` = VALUES(`value`),
  `updated_at` = VALUES(`updated_at`);
