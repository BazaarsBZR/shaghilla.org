<?php

namespace App\Filament\Pages;

use App\Filament\Resources\FeedSourceResource;
use App\Filament\Resources\KeywordRuleResource;
use App\Models\FeedSource;
use App\Models\SiteSetting;
use App\Services\RssImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class RssAutomation extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static string $view = 'filament.pages.rss-automation';

    protected static ?string $navigationGroup = 'Automation';

    protected static ?string $navigationLabel = 'RSS Import';

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

    /**
     * @var array<int, array{name:string,last_fetched_at:?string,is_active:bool,url:string}>
     */
    public array $feedStatus = [];

    public function mount(): void
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $secret = SiteSetting::getValue('rss_import_secret', '');

        $this->cronUrl = $secret !== '' ? "{$baseUrl}/tasks/import-rss/{$secret}" : "{$baseUrl}/tasks/import-rss/<secret>";
        $this->refreshStatus();

        $this->form->fill([
            'rss_item_limit' => SiteSetting::getInt('rss_item_limit', 10),
            'rss_existing_item_behavior' => SiteSetting::getValue('rss_existing_item_behavior', RssImporter::EXISTING_ITEM_BEHAVIOR_FILL_MISSING),
            'rss_force_no_skip' => SiteSetting::getBool('rss_force_no_skip', false),
            'rss_require_image_for_home' => SiteSetting::getBool('rss_require_image_for_home', false),
            'rss_cron_enabled' => SiteSetting::getBool('rss_cron_enabled', true),
            'rss_cron_interval_minutes' => SiteSetting::getInt('rss_cron_interval_minutes', 60),
        ]);
    }

    public function refreshStatus(): void
    {
        $this->lastImportAt = SiteSetting::getValue('last_rss_import_at');
        $this->lastImportSource = SiteSetting::getValue('last_rss_import_source');

        $lastResultJson = SiteSetting::getValue('last_rss_import_result');
        $this->lastImportResult = null;
        if (is_string($lastResultJson) && trim($lastResultJson) !== '') {
            $decoded = json_decode($lastResultJson, true);
            if (is_array($decoded)) {
                $this->lastImportResult = $decoded;
            }
        }

        $this->lastCronHitAt = SiteSetting::getValue('last_rss_cron_hit_at');
        $this->lastCronHitIp = SiteSetting::getValue('last_rss_cron_hit_ip');
        $this->lastCronHitMode = SiteSetting::getValue('last_rss_cron_hit_mode');

        $this->cronEnabled = SiteSetting::getBool('rss_cron_enabled', true);
        $this->cronIntervalMinutes = SiteSetting::getInt('rss_cron_interval_minutes', 60);

        $feeds = FeedSource::query()
            ->orderBy('id')
            ->get(['name', 'url', 'is_active', 'last_fetched_at']);

        $this->feedStatus = $feeds->map(function (FeedSource $feed): array {
            return [
                'name' => (string) $feed->name,
                'url' => (string) $feed->url,
                'is_active' => (bool) $feed->is_active,
                'last_fetched_at' => $feed->last_fetched_at?->toIso8601String(),
            ];
        })->all();
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
                            ->content(fn (): string => $this->cronUrl ? ('0 * * * * curl -fsS -X POST "'.$this->cronUrl.'" >/dev/null 2>&1') : '—')
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('artisan_schedule_command')
                            ->label('cPanel cron command (recommended: schedule runner)')
                            ->content(fn (): string => '* * * * * php "'.base_path('artisan').'" schedule:run >/dev/null 2>&1')
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('artisan_direct_command')
                            ->label('Alternative: direct command (hourly)')
                            ->content(fn (): string => '0 * * * * php "'.base_path('artisan').'" news:import-rss >/dev/null 2>&1')
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
                                if ($this->cronEnabled === false) {
                                    return 'DISABLED (imports will not run from cron URL)';
                                }

                                $interval = $this->cronIntervalMinutes ?? 60;
                                $interval = max(1, min(1440, $interval));

                                try {
                                    $last = $this->lastCronHitAt ? Carbon::parse($this->lastCronHitAt) : null;
                                } catch (\Throwable) {
                                    $last = null;
                                }
                                if (! $last) {
                                    return 'No cron hits recorded yet';
                                }

                                $minutesAgo = $last->diffInMinutes(now());
                                $threshold = max(2, $interval * 2);

                                return $minutesAgo <= $threshold
                                    ? "OK (last hit {$last->diffForHumans()})"
                                    : "STALE (last hit {$last->diffForHumans()})";
                            })
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('next_expected')
                            ->label('Next expected run')
                            ->content(function (): string {
                                $interval = $this->cronIntervalMinutes ?? 60;
                                $interval = max(1, min(1440, $interval));

                                try {
                                    $last = $this->lastCronHitAt ? Carbon::parse($this->lastCronHitAt) : null;
                                } catch (\Throwable) {
                                    $last = null;
                                }
                                if (! $last) {
                                    return '—';
                                }

                                $next = $last->copy()->addMinutes($interval);
                                $remainingSeconds = now()->diffInSeconds($next, false);

                                if ($remainingSeconds <= 0) {
                                    return $next->toIso8601String().' (due now)';
                                }

                                return $next->toIso8601String().' ('.$next->diffForHumans().')';
                            })
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('next_scheduled')
                            ->label('Next scheduled run (hourly)')
                            ->content(function (): string {
                                $next = now()->copy()->addHour()->startOfHour();

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
                                    'imported=%s updated=%s skipped=%s filtered=%s failed=%s duration_ms=%s',
                                    $r['imported'] ?? '—',
                                    $r['updated'] ?? '—',
                                    $r['skipped'] ?? '—',
                                    $r['filtered_no_image'] ?? '—',
                                    $r['failed_feeds'] ?? '—',
                                    $r['duration_ms'] ?? '—',
                                );

                                return "{$at}{$source} | {$summary}";
                            })
                            ->columnSpanFull(),

                        Toggle::make('rss_cron_enabled')
                            ->label('Enable cron URL imports')
                            ->helperText('If disabled, your cPanel cron can still hit the URL (for monitoring) but the import will not run.')
                            ->inline(false)
                            ->default(true),

                        TextInput::make('rss_cron_interval_minutes')
                            ->label('Expected cron interval (minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->default(60)
                            ->helperText('Used for the “Next expected run” countdown above (monitoring only).'),
                    ])
                    ->columns(2),

                Section::make('RSS Settings')
                    ->schema([
                        TextInput::make('rss_item_limit')
                            ->label('Max items per feed')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(10)
                            ->helperText('How many items to read per feed each run (1–50).'),

                        Toggle::make('rss_force_no_skip')
                            ->label('Force: do not skip (overwrite + refetch)')
                            ->helperText('When enabled, importer always re-fetches feeds (ignores ETag/Last-Modified), relaxes strict URL checks, and overwrites existing items (so duplicates won’t count as “Skipped”).')
                            ->columnSpanFull(),

                        Toggle::make('rss_require_image_for_home')
                            ->label('Require hero image for Home')
                            ->helperText('If enabled, any RSS item that would appear on the Home page but has no real image (after scraping) will be filtered out to avoid blank cards.')
                            ->columnSpanFull(),

                        Select::make('rss_existing_item_behavior')
                            ->label('When an item already exists')
                            ->options([
                                RssImporter::EXISTING_ITEM_BEHAVIOR_FILL_MISSING => 'Fill missing fields (safe default)',
                                RssImporter::EXISTING_ITEM_BEHAVIOR_OVERWRITE_IF_TITLE_MATCH => 'Overwrite when title is identical',
                                RssImporter::EXISTING_ITEM_BEHAVIOR_OVERWRITE => 'Overwrite always',
                                RssImporter::EXISTING_ITEM_BEHAVIOR_SKIP => 'Skip entirely',
                            ])
                            ->default(RssImporter::EXISTING_ITEM_BEHAVIOR_FILL_MISSING)
                            ->helperText('If “Force” is enabled above, this dropdown is ignored and import will always overwrite.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Feed health')
                    ->description('Shows each feed’s last fetch time. If these stay old, your cron/scheduler isn’t running.')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('feed_health')
                            ->label('Active feeds')
                            ->content(function (): string {
                                if ($this->feedStatus === []) {
                                    return '—';
                                }

                                $lines = [];
                                foreach ($this->feedStatus as $feed) {
                                    $status = $feed['is_active'] ? 'ON' : 'OFF';
                                    $last = $feed['last_fetched_at'] ?: '—';
                                    $lines[] = "{$status} | {$feed['name']} | last_fetched_at={$last}";
                                }

                                return implode("\n", $lines);
                            })
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveSettings')
                ->label('Save settings')
                ->action('saveSettings')
                ->color('primary'),

            Action::make('testCronUrl')
                ->label('Test cron URL')
                ->url(fn (): string => $this->cronUrl ? ($this->cronUrl.'?test=1') : '#')
                ->openUrlInNewTab()
                ->color('gray'),

            Action::make('runImport')
                ->label('Run import now')
                ->action('runImport')
                ->color('warning'),

            Action::make('manageFeeds')
                ->label('Feed sources')
                ->url(fn (): string => FeedSourceResource::getUrl('index'))
                ->openUrlInNewTab(),

            Action::make('manageKeywords')
                ->label('Keyword rules')
                ->url(fn (): string => KeywordRuleResource::getUrl('index'))
                ->openUrlInNewTab(),
        ];
    }

    public function saveSettings(): void
    {
        $data = (array) $this->form->getState();

        $itemLimit = (int) ($data['rss_item_limit'] ?? 10);
        $itemLimit = max(1, min(50, $itemLimit));

        $behavior = (string) ($data['rss_existing_item_behavior'] ?? RssImporter::EXISTING_ITEM_BEHAVIOR_FILL_MISSING);
        $forceNoSkip = (bool) ($data['rss_force_no_skip'] ?? false);
        $requireImage = (bool) ($data['rss_require_image_for_home'] ?? false);
        $cronEnabled = (bool) ($data['rss_cron_enabled'] ?? true);
        $cronInterval = (int) ($data['rss_cron_interval_minutes'] ?? 60);
        $cronInterval = max(1, min(1440, $cronInterval));

        SiteSetting::setValues([
            'rss_item_limit' => (string) $itemLimit,
            'rss_existing_item_behavior' => $behavior,
            'rss_force_no_skip' => $forceNoSkip,
            'rss_require_image_for_home' => $requireImage,
            'rss_cron_enabled' => $cronEnabled,
            'rss_cron_interval_minutes' => (string) $cronInterval,
        ]);

        $this->refreshStatus();

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }

    public function runImport(): void
    {
        $data = (array) $this->form->getState();

        $itemLimit = (int) ($data['rss_item_limit'] ?? 10);
        $itemLimit = max(1, min(50, $itemLimit));

        $behavior = (string) ($data['rss_existing_item_behavior'] ?? RssImporter::EXISTING_ITEM_BEHAVIOR_FILL_MISSING);
        $forceNoSkip = (bool) ($data['rss_force_no_skip'] ?? false);
        $requireImage = (bool) ($data['rss_require_image_for_home'] ?? false);

        SiteSetting::setValues([
            'last_rss_import_source' => 'admin',
        ]);

        $result = app(RssImporter::class)->importAll(
            $itemLimit,
            $behavior,
            $forceNoSkip,
            $requireImage,
        );

        $this->refreshStatus();

        Notification::make()
            ->title('RSS import completed')
            ->body("Imported: {$result['imported']} | Updated: {$result['updated']} | Skipped: {$result['skipped']} | No-image filtered: {$result['filtered_no_image']} | Failed feeds: {$result['failed_feeds']} | Force: ".($forceNoSkip ? 'ON' : 'OFF')." | Require image: ".($requireImage ? 'ON' : 'OFF')." | Behavior: {$behavior}")
            ->success()
            ->send();
    }
}
