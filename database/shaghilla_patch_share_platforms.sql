-- Shaghilla.org — Share platforms (social share) patch (MySQL/MariaDB)
--
-- Creates `share_platforms` and seeds default platforms.
-- Default: WhatsApp, X, Instagram (native share), Facebook.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `share_platforms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `share_url_template` text DEFAULT NULL,
  `use_native_share` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_platforms_slug_unique` (`slug`),
  KEY `share_platforms_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `share_platforms`
(`name`,`slug`,`icon`,`share_url_template`,`use_native_share`,`is_active`,`sort_order`,`created_at`,`updated_at`)
VALUES
('WhatsApp','whatsapp','fa-whatsapp','https://wa.me/?text={title}%20{url}',0,1,10,NOW(),NOW()),
('X','x','fa-x-twitter','https://twitter.com/intent/tweet?url={url}&text={title}',0,1,20,NOW(),NOW()),
('Instagram','instagram','fa-instagram',NULL,1,1,30,NOW(),NOW()),
('Facebook','facebook','fa-facebook-f','https://www.facebook.com/sharer/sharer.php?u={url}',0,1,40,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `icon` = VALUES(`icon`),
  `share_url_template` = VALUES(`share_url_template`),
  `use_native_share` = VALUES(`use_native_share`),
  `is_active` = VALUES(`is_active`),
  `sort_order` = VALUES(`sort_order`),
  `updated_at` = VALUES(`updated_at`);

SET FOREIGN_KEY_CHECKS = 1;

