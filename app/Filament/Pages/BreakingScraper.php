<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\AlManarUrgentImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class BreakingScraper extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static string $view = 'filament.pages.breaking-scraper';

    protected static ?string $navigationGroup = 'Automation';

    protected static ?string $navigationLabel = 'Breaking Scraper';

    public ?array $data = [];

    public ?string $cronUrl = null;

    public ?string $lastImportAt = null;

    public ?array $lastImportResult = null;

    public ?string $lastImportSource = null;

    public ?string $lastCronHitAt = null;

    public ?string $lastCronHitIp = null;

    public ?string $lastCronHitMode = null;

    public ?int $cronIntervalMinutes = null;

    public ?bool $cronEnabled = null;

    public ?string $lastFetchAt = null;

    public ?string $lastFetchCount = null;

    public ?string $lastFetchSample = null;

    public function mount(): void
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $secret = $this->resolveSecret();

        $this->cronUrl = $secret !== '' ? "{$baseUrl}/tasks/import-almanar-urgent/{$secret}" : "{$baseUrl}/tasks/import-almanar-urgent/<secret>";
        $this->refreshStatus();

        $this->form->fill([
            'almanar_urgent_enabled' => SiteSetting::getBool('almanar_urgent_enabled', false),
            'almanar_urgent_limit' => SiteSetting::getInt('almanar_urgent_limit', 10),
            'almanar_urgent_secret' => $secret,
            'almanar_urgent_cron_enabled' => SiteSetting::getBool('almanar_urgent_cron_enabled', true),
            'almanar_urgent_cron_interval_minutes' => SiteSetting::getInt('almanar_urgent_cron_interval_minutes', 1),
        ]);
    }

    public function refreshStatus(): void
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $secret = $this->resolveSecret();
        $this->cronUrl = $secret !== '' ? "{$baseUrl}/tasks/import-almanar-urgent/{$secret}" : "{$baseUrl}/tasks/import-almanar-urgent/<secret>";

        $this->lastImportAt = SiteSetting::getValue('last_almanar_urgent_import_at');
        $this->lastImportSource = SiteSetting::getValue('last_almanar_urgent_import_source');

        $lastResultJson = SiteSetting::getValue('last_almanar_urgent_import_result');
        $this->lastImportResult = null;
        if (is_string($lastResultJson) && trim($lastResultJson) !== '') {
            $decoded = json_decode($lastResultJson, true);
            if (is_array($decoded)) {
                $this->lastImportResult = $decoded;
            }
        }

        $this->lastCronHitAt = SiteSetting::getValue('last_almanar_urgent_cron_hit_at');
        $this->lastCronHitIp = SiteSetting::getValue('last_almanar_urgent_cron_hit_ip');
        $this->lastCronHitMode = SiteSetting::getValue('last_almanar_urgent_cron_hit_mode');

        $this->cronEnabled = SiteSetting::getBool('almanar_urgent_cron_enabled', true);
        $this->cronIntervalMinutes = SiteSetting::getInt('almanar_urgent_cron_interval_minutes', 1);

        $this->lastFetchAt = SiteSetting::getValue('last_almanar_urgent_fetch_at');
        $this->lastFetchCount = SiteSetting::getValue('last_almanar_urgent_fetch_count');
        $this->lastFetchSample = SiteSetting::getValue('last_almanar_urgent_fetch_sample');
    }

    private function resolveSecret(): string
    {
        $fromDb = trim((string) SiteSetting::getValue('almanar_urgent_secret', ''));
        if ($fromDb !== '') {
            return $fromDb;
        }

        $fromEnv = trim((string) env('ALMANAR_URGENT_SECRET', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $generated = bin2hex(random_bytes(16));
        SiteSetting::setValues([
            'almanar_urgent_secret' => $generated,
        ]);

        return $generated;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Cron status')
                    ->description('Recommended: use ONE cron approach. Best: schedule runner. Alternative: cron URL (no-SSH).')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('cron_url')
                            ->label('Cron URL (use in cPanel Cron Jobs)')
                            ->content(fn (): string => $this->cronUrl ?: '—')
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('cron_command')
                            ->label('cPanel cron command (recommended)')
                            ->content(fn (): string => $this->cronUrl ? ('* * * * * curl -fsS -X POST "'.$this->cronUrl.'" >/dev/null 2>&1') : '—')
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('artisan_schedule_command')
                            ->label('cPanel cron command (recommended: schedule runner)')
                            ->content(fn (): string => '* * * * * php "'.base_path('artisan').'" schedule:run >/dev/null 2>&1')
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('artisan_direct_command')
                            ->label('Alternative: direct command (every minute)')
                            ->content(fn (): string => '* * * * * php "'.base_path('artisan').'" news:import-almanar-urgent >/dev/null 2>&1')
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('last_cron_hit')
                            ->label('Last cron hit')
                            ->content(function (): string {
                                $at = $this->lastCronHitAt ?: '—';
                                $mode = $this->lastCronHitMode ? " ({$this->lastCronHitMode})" : '';
                                $ip = $this->lastCronHitIp ? " | {$this->lastCronHitIp}" : '';

                                return "{$at}{$mode}{$ip}";
                            })
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('cron_health')
                            ->label('Cron health')
                            ->content(function (): string {
                                if (! $this->cronEnabled) {
                                    return 'DISABLED (imports won’t run from cron URL)';
                                }

                                if (! $this->lastCronHitAt) {
                                    return 'No cron hits yet (check cPanel cron)';
                                }

                                try {
                                    $last = Carbon::parse($this->lastCronHitAt);
                                } catch (\Throwable) {
                                    return 'Unknown (invalid timestamp stored)';
                                }

                                $interval = (int) ($this->cronIntervalMinutes ?: 1);
                                $interval = max(1, $interval);

                                $staleMinutes = max(2, $interval * 2);
                                $diffMinutes = $last->diffInMinutes(now());

                                return $diffMinutes <= $staleMinutes
                                    ? 'OK'
                                    : "STALE (last hit {$last->diffForHumans()})";
                            })
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('next_expected')
                            ->label('Next expected run')
                            ->content(function (): string {
                                if (! $this->lastCronHitAt) {
                                    return '—';
                                }

                                try {
                                    $last = Carbon::parse($this->lastCronHitAt);
                                } catch (\Throwable) {
                                    return '—';
                                }

                                $interval = (int) ($this->cronIntervalMinutes ?: 1);
                                $interval = max(1, $interval);
                                $next = $last->copy()->addMinutes($interval);
                                $remainingSeconds = now()->diffInSeconds($next, false);

                                if ($remainingSeconds <= 0) {
                                    return $next->toIso8601String().' (due now)';
                                }

                                return $next->toIso8601String().' ('.$next->diffForHumans().')';
                            })
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('last_import')
                            ->label('Last import')
                            ->content(function (): string {
                                $at = $this->lastImportAt ?: '—';
                                $source = $this->lastImportSource ? " | source={$this->lastImportSource}" : '';

                                if (! is_array($this->lastImportResult)) {
                                    return "{$at}{$source}";
                                }

                                $r = $this->lastImportResult;
                                $summary = sprintf(
                                    'imported=%s updated=%s cleared=%s skipped=%s failed=%s duration_ms=%s',
                                    $r['imported'] ?? '—',
                                    $r['updated'] ?? '—',
                                    $r['cleared'] ?? '—',
                                    $r['skipped'] ?? '—',
                                    $r['failed'] ?? '—',
                                    $r['duration_ms'] ?? '—',
                                );

                                return "{$at}{$source} | {$summary}";
                            })
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('last_fetch')
                            ->label('Last fetch (scrape diagnostics)')
                            ->content(function (): string {
                                $at = $this->lastFetchAt ?: '—';
                                $count = $this->lastFetchCount ?: '—';
                                $sample = $this->lastFetchSample ?: '';

                                $out = "{$at} | fetched={$count}";
                                if ($sample !== '') {
                                    $out .= "\n".$sample;
                                }

                                return $out;
                            })
                            ->columnSpanFull(),

                        Toggle::make('almanar_urgent_cron_enabled')
                            ->label('Enable cron URL imports')
                            ->helperText('If disabled, your cPanel cron can still hit the URL (for monitoring) but the import will not run.')
                            ->inline(false)
                            ->default(true),

                        TextInput::make('almanar_urgent_cron_interval_minutes')
                            ->label('Expected cron interval (minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->default(1)
                            ->helperText('Used for the “Next expected run” countdown above (monitoring only).'),
                    ])
                    ->columns(2),

                Section::make('Al-Manar urgent (red) importer')
                    ->description('Scrapes https://almanar.com.lb/24-hour-news/ and imports only the red “الأخبار العاجلة” items into the Breaking ticker.')
                    ->schema([
                        Toggle::make('almanar_urgent_enabled')
                            ->label('Enable importer')
                            ->helperText('When enabled, the scheduler / cron URL will import urgent items and add them to the breaking bar.')
                            ->inline(false)
                            ->default(false),

                        TextInput::make('almanar_urgent_limit')
                            ->label('Max items per run')
                            ->numeric()
                            ->default(10)
                            ->helperText('Recommended: 5–10.'),

                        TextInput::make('almanar_urgent_secret')
                            ->label('Cron secret')
                            ->helperText('Used by the no-SSH cron URL. Leave empty to use ALMANAR_URGENT_SECRET from .env.')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveSettings')
                ->label('Save')
                ->color('primary')
                ->action('saveSettings'),

            Action::make('testCronUrl')
                ->label('Test cron URL')
                ->url(fn (): string => $this->cronUrl ? ($this->cronUrl.'?test=1') : '#')
                ->openUrlInNewTab()
                ->color('gray'),

            Action::make('runNow')
                ->label('Run now')
                ->color('warning')
                ->action('runNow'),
        ];
    }

    public function saveSettings(): void
    {
        $data = (array) $this->form->getState();

        $limit = (int) ($data['almanar_urgent_limit'] ?? 10);
        $limit = max(1, min(25, $limit));

        $cronEnabled = (bool) ($data['almanar_urgent_cron_enabled'] ?? true);
        $cronInterval = (int) ($data['almanar_urgent_cron_interval_minutes'] ?? 1);
        $cronInterval = max(1, min(1440, $cronInterval));

        SiteSetting::setValues([
            'almanar_urgent_enabled' => (bool) ($data['almanar_urgent_enabled'] ?? false),
            'almanar_urgent_limit' => (string) $limit,
            'almanar_urgent_secret' => trim((string) ($data['almanar_urgent_secret'] ?? '')),
            'almanar_urgent_cron_enabled' => $cronEnabled,
            'almanar_urgent_cron_interval_minutes' => (string) $cronInterval,
        ]);

        $this->data = array_merge($this->data ?? [], [
            'almanar_urgent_enabled' => (bool) ($data['almanar_urgent_enabled'] ?? false),
            'almanar_urgent_limit' => $limit,
            'almanar_urgent_secret' => trim((string) ($data['almanar_urgent_secret'] ?? '')),
            'almanar_urgent_cron_enabled' => $cronEnabled,
            'almanar_urgent_cron_interval_minutes' => $cronInterval,
        ]);

        $this->refreshStatus();

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }

    public function runNow(): void
    {
        SiteSetting::setValues([
            'last_almanar_urgent_import_source' => 'admin',
        ]);

        $result = app(AlManarUrgentImporter::class)->importNow();

        $this->refreshStatus();

        Notification::make()
            ->title('Breaking import completed')
            ->body("Imported: {$result['imported']} | Updated: {$result['updated']} | Cleared old: {$result['cleared']} | Failed: {$result['failed']}")
            ->success()
            ->send();
    }
}
