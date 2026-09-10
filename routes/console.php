<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RSS import runs via `php artisan schedule:run` (cPanel-friendly).
// We schedule a 1-minute heartbeat and enforce the interval via SiteSetting so you
// don't need separate curl cron jobs unless you want "no-SSH" triggers.
Schedule::call(function (): void {
    try {
        if (! class_exists(\App\Models\SiteSetting::class)) {
            return;
        }

        $enabled = \App\Models\SiteSetting::getBool('rss_cron_enabled', true);
        if (! $enabled) {
            return;
        }

        $intervalMinutes = \App\Models\SiteSetting::getInt('rss_cron_interval_minutes', 60);
        $intervalMinutes = max(1, min(1440, (int) $intervalMinutes));

        $last = \App\Models\SiteSetting::getValue('last_rss_import_at');
        if (is_string($last) && trim($last) !== '') {
            try {
                $lastAt = \Illuminate\Support\Carbon::parse($last);
                if ($lastAt->diffInMinutes(now()) < $intervalMinutes) {
                    return;
                }
            } catch (\Throwable) {
                // continue
            }
        }

        Artisan::call('news:import-rss');
    } catch (\Throwable) {
        // swallow to avoid breaking the scheduler
        return;
    }
})
    ->name('news:import-rss')
    ->everyMinute()
    ->withoutOverlapping(55);

Schedule::command('news:import-almanar-urgent')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('public-money:import')
    ->dailyAt('03:15')
    ->withoutOverlapping(15);
