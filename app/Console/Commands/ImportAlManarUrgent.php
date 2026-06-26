<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Services\AlManarUrgentImporter;
use Illuminate\Console\Command;

class ImportAlManarUrgent extends Command
{
    protected $signature = 'news:import-almanar-urgent';

    protected $description = 'Scrape Al-Manar urgent (red) items and import them as breaking articles.';

    public function handle(): int
    {
        $cronEnabled = SiteSetting::getBool('almanar_urgent_cron_enabled', true);

        SiteSetting::setValues([
            'last_almanar_urgent_cron_hit_at' => now()->toIso8601String(),
            'last_almanar_urgent_cron_hit_ip' => 'cli',
            'last_almanar_urgent_cron_hit_method' => 'CLI',
            'last_almanar_urgent_cron_hit_ua' => 'artisan news:import-almanar-urgent',
        ]);

        if (! $cronEnabled) {
            SiteSetting::setValues([
                'last_almanar_urgent_cron_hit_mode' => 'disabled',
                'last_almanar_urgent_import_source' => 'scheduler',
            ]);

            $this->warn('Breaking scraper cron imports are disabled in the admin panel.');

            return self::SUCCESS;
        }

        SiteSetting::setValues([
            'last_almanar_urgent_cron_hit_mode' => 'import',
            'last_almanar_urgent_import_source' => 'scheduler',
        ]);

        $result = app(AlManarUrgentImporter::class)->importNow();

        $this->info(sprintf(
            'Imported: %d | Updated: %d | Cleared: %d | Skipped: %d | Failed: %d | %dms',
            $result['imported'],
            $result['updated'],
            $result['cleared'],
            $result['skipped'],
            $result['failed'],
            $result['duration_ms'],
        ));

        return self::SUCCESS;
    }
}
