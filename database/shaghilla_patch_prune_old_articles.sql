-- Patch: prune old RSS articles (optional maintenance)
-- Use (phpMyAdmin): select your DB -> Import this file.
--
-- IMPORTANT:
-- - Backup your DB first (Export).
-- - This deletes data and cannot be undone.
--
-- What it does:
-- - Deletes RSS-imported articles older than @keep_days.
-- - Keeps manual articles (feed_source_id IS NULL), including Al‑Manar breaking imports.
-- - Optionally keeps breaking items.
--
-- Adjust these variables as needed:
SET NAMES utf8mb4;

SET @keep_days := 30;          -- keep the last N days
SET @keep_breaking := 1;       -- 1 = keep breaking articles, 0 = delete them too

-- Delete RSS articles older than @keep_days
DELETE FROM `articles`
WHERE `feed_source_id` IS NOT NULL
  AND (
        (`published_at` IS NOT NULL AND `published_at` < DATE_SUB(NOW(), INTERVAL @keep_days DAY))
        OR (`published_at` IS NULL AND `imported_at` < DATE_SUB(NOW(), INTERVAL @keep_days DAY))
      )
  AND (@keep_breaking = 0 OR `is_breaking` = 0);

-- Optional: reclaim space (can be slow on big tables)
-- OPTIMIZE TABLE `articles`;
