<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Services\RssImporter;
use Illuminate\Console\Command;

class ImportRssNews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:import-rss
        {--limit= : Max items per feed (1-50)}
        {--behavior= : Existing item behavior (fill_missing, overwrite_if_title_match, overwrite, skip)}
        {--force : Force overwrite existing items (no skip) and ignore ETag/Last-Modified}
        {--require-image : Filter out home items without a real image}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import RSS feeds into articles, dedupe, and apply keyword rules.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cronEnabled = SiteSetting::getBool('rss_cron_enabled', true);

        SiteSetting::setValues([
            'last_rss_cron_hit_at' => now()->toIso8601String(),
            'last_rss_cron_hit_ip' => 'cli',
            'last_rss_cron_hit_method' => 'CLI',
            'last_rss_cron_hit_ua' => 'artisan news:import-rss',
        ]);

        if (! $cronEnabled) {
            SiteSetting::setValues([
                'last_rss_cron_hit_mode' => 'disabled',
                'last_rss_import_source' => 'scheduler',
            ]);

            $this->warn('RSS cron imports are disabled in the admin panel.');

            return self::SUCCESS;
        }

        $limitOption = $this->option('limit');
        $limit = $limitOption !== null && $limitOption !== '' ? (int) $limitOption : null;

        $behaviorOption = trim((string) ($this->option('behavior') ?? ''));
        $behavior = $behaviorOption !== '' ? $behaviorOption : null;

        $force = (bool) $this->option('force');
        $requireImage = (bool) $this->option('require-image');

        SiteSetting::setValues([
            'last_rss_cron_hit_mode' => 'import',
            'last_rss_import_source' => 'scheduler',
        ]);

        $result = app(RssImporter::class)->importAll(
            $limit,
            $behavior,
            $force ? true : null,
            $requireImage ? true : null,
        );

        $this->info(sprintf(
            'Imported: %d | Updated: %d | Skipped: %d | No-image filtered: %d | Failed feeds: %d | %dms',
            $result['imported'],
            $result['updated'],
            $result['skipped'],
            $result['filtered_no_image'] ?? 0,
            $result['failed_feeds'],
            $result['duration_ms'],
        ));

        return self::SUCCESS;
    }
}
