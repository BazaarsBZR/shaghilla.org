<?php

namespace App\Filament\Pages;

use App\Models\Article;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class MaintenanceTools extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string $view = 'filament.pages.maintenance-tools';

    protected static ?string $navigationGroup = 'Automation';

    protected static ?string $navigationLabel = 'Maintenance';

    public ?string $lastCacheClearAt = null;

    public ?string $lastScheduleClearAt = null;

    public function mount(): void
    {
        $this->lastCacheClearAt = SiteSetting::getValue('last_cache_clear_at');
        $this->lastScheduleClearAt = SiteSetting::getValue('last_schedule_clear_at');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runMigrations')
                ->label('Run migrations')
                ->requiresConfirmation()
                ->color('primary')
                ->modalDescription('Runs `php artisan migrate --force` on the server. Use this after uploading a new build that contains new migrations.')
                ->action('runMigrations'),

            Action::make('seedSharePlatforms')
                ->label('Seed share platforms')
                ->requiresConfirmation()
                ->color('primary')
                ->modalDescription('Seeds the default social share platforms (WhatsApp, X, Instagram, Facebook). Safe to run multiple times.')
                ->action('seedSharePlatforms'),

            Action::make('clearCaches')
                ->label('Clear caches')
                ->requiresConfirmation()
                ->color('warning')
                ->action('clearCaches'),

            Action::make('clearScheduleLocks')
                ->label('Clear scheduler locks')
                ->requiresConfirmation()
                ->color('warning')
                ->action('clearScheduleLocks')
                ->modalDescription('If scheduled tasks got stuck (e.g., RSS import not running since yesterday), this clears Laravel’s schedule mutex locks so cron can run jobs again.'),

            Action::make('clearPlaceholderImages')
                ->label('Clear placeholder images')
                ->color('warning')
                ->modalHeading('Clear placeholder images')
                ->modalDescription('Removes known “default template” images (e.g., Lebanon24 purple placeholders) from articles so the site shows the neutral fallback instead. This does not delete your uploaded images.')
                ->form([
                    TextInput::make('days')
                        ->label('Only affect articles from the last (days)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(3650)
                        ->default(30)
                        ->required(),

                    Toggle::make('only_rss')
                        ->label('Only RSS-imported articles')
                        ->default(true)
                        ->inline(false),

                    TextInput::make('confirm')
                        ->label('Type CLEAR to confirm')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $days = (int) ($data['days'] ?? 30);
                    $days = max(1, min(3650, $days));
                    $onlyRss = (bool) ($data['only_rss'] ?? true);
                    $confirm = trim((string) ($data['confirm'] ?? ''));

                    if ($confirm !== 'CLEAR') {
                        Notification::make()
                            ->title('Confirmation failed')
                            ->body('You must type CLEAR to run this.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $cutoff = now()->subDays($days);

                    $placeholderUrls = [
                        'Default-Document-Thumbnail',
                        'Default-Document-Picture',
                        'default-document-thumbnail',
                        'default-document-picture',
                    ];

                    $placeholderHashes = collect([
                        'https://www.lebanon24.com/uploadImages/DocumentImages/Default-Document-Thumbnail.jpg',
                        'https://www.lebanon24.com/uploadImages/DocumentImages/Default-Document-Picture.jpg',
                    ])->map(fn (string $url): string => substr(hash('sha256', $url), 0, 24))
                        ->unique()
                        ->values()
                        ->all();

                    $query = Article::query()
                        ->whereNotNull('image_url')
                        ->where(function ($q) use ($cutoff) {
                            $q->whereNotNull('published_at')->where('published_at', '>=', $cutoff)
                                ->orWhere(function ($q) use ($cutoff) {
                                    $q->whereNull('published_at')->where('imported_at', '>=', $cutoff);
                                });
                        });

                    if ($onlyRss) {
                        $query->whereNotNull('feed_source_id');
                    }

                    $query->where(function ($q) use ($placeholderUrls, $placeholderHashes) {
                        foreach ($placeholderUrls as $needle) {
                            $q->orWhere('image_url', 'like', '%'.$needle.'%');
                        }

                        foreach ($placeholderHashes as $hash) {
                            $q->orWhere('image_url', 'like', '%/'.$hash.'.%');
                            $q->orWhere('image_url', 'like', '%'.$hash.'%');
                        }
                    });

                    $matched = (int) (clone $query)->count();
                    $cleared = (int) $query->update(['image_url' => null]);

                    Cache::forget('news.breaking.ticker');
                    Cache::forget('news.breaking.live');
                    Cache::forget('news.home.hero');
                    Cache::forget('news.home.latest');

                    foreach (range(1, 10) as $page) {
                        Cache::forget("home.page.data.v1.{$page}");
                        Cache::forget("home.page.data.v2.{$page}");
                    }

                    SiteSetting::setValues([
                        'last_placeholder_clear_at' => now()->toIso8601String(),
                        'last_placeholder_clear_result' => json_encode([
                            'days' => $days,
                            'only_rss' => $onlyRss,
                            'cleared' => $cleared,
                            'matched' => $matched,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);

                    Notification::make()
                        ->title('Placeholder images cleared')
                        ->body("Cleared: {$cleared} (matched: {$matched})")
                        ->success()
                        ->send();
                }),

            Action::make('pruneRssArticles')
                ->label('Prune old RSS articles')
                ->color('danger')
                ->modalHeading('Prune old RSS articles')
                ->modalDescription('Deletes RSS-imported articles older than N days. Manual articles (including Al‑Manar breaking imports) are kept.')
                ->form([
                    TextInput::make('days')
                        ->label('Delete articles older than (days)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(3650)
                        ->default(30)
                        ->required(),

                    Toggle::make('keep_breaking')
                        ->label('Keep breaking items')
                        ->helperText('If enabled, breaking articles are not deleted.')
                        ->default(true)
                        ->inline(false),

                    TextInput::make('confirm')
                        ->label('Type DELETE to confirm')
                        ->required()
                        ->helperText('This action cannot be undone. Backup your DB first.'),
                ])
                ->action(function (array $data): void {
                    $days = (int) ($data['days'] ?? 30);
                    $days = max(1, min(3650, $days));
                    $keepBreaking = (bool) ($data['keep_breaking'] ?? true);
                    $confirm = trim((string) ($data['confirm'] ?? ''));

                    if ($confirm !== 'DELETE') {
                        Notification::make()
                            ->title('Confirmation failed')
                            ->body('You must type DELETE to run this.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $cutoff = now()->subDays($days);

                    $query = Article::query()
                        ->whereNotNull('feed_source_id')
                        ->where(function ($q) use ($cutoff) {
                            $q->whereNotNull('published_at')->where('published_at', '<', $cutoff)
                                ->orWhere(function ($q) use ($cutoff) {
                                    $q->whereNull('published_at')->where('imported_at', '<', $cutoff);
                                });
                        });

                    if ($keepBreaking && Schema::hasColumn('articles', 'is_breaking')) {
                        $query->where('is_breaking', false);
                    }

                    $toDelete = (int) (clone $query)->count();
                    $deleted = (int) $query->delete();

                    Cache::forget('news.breaking.ticker');
                    Cache::forget('news.breaking.live');
                    Cache::forget('news.home.hero');
                    Cache::forget('news.home.latest');

                    SiteSetting::setValues([
                        'last_articles_prune_at' => now()->toIso8601String(),
                        'last_articles_prune_result' => json_encode([
                            'days' => $days,
                            'keep_breaking' => $keepBreaking,
                            'deleted' => $deleted,
                            'matched' => $toDelete,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);

                    Notification::make()
                        ->title('Prune completed')
                        ->body("Deleted: {$deleted} (matched: {$toDelete})")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function clearCaches(): void
    {
        try {
            Artisan::call('optimize:clear');

            SiteSetting::setValues([
                'last_cache_clear_at' => now()->toIso8601String(),
            ]);

            $this->lastCacheClearAt = SiteSetting::getValue('last_cache_clear_at');

            Notification::make()
                ->title('Caches cleared')
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Failed to clear caches')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function runMigrations(): void
    {
        try {
            $baselined = $this->baselineLegacyMigrations();

            Artisan::call('migrate', [
                '--force' => true,
            ]);

            $output = trim((string) Artisan::output());
            $output = $output !== '' ? $output : 'Migrations completed.';

            if ($baselined !== []) {
                $output = 'Baselined existing migrations: '.count($baselined)."\n\n".$output;
            }

            SiteSetting::setValues([
                'last_migrate_at' => now()->toIso8601String(),
                'last_migrate_output' => mb_substr($output, 0, 2000),
            ]);

            Notification::make()
                ->title('Migrations completed')
                ->body(mb_substr($output, 0, 200))
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            $output = trim((string) Artisan::output());
            $body = $output !== '' ? ($output."\n\n".$exception->getMessage()) : $exception->getMessage();

            Notification::make()
                ->title('Failed to run migrations')
                ->body(mb_substr($body, 0, 400))
                ->danger()
                ->send();
        }
    }

    /**
     * On shared hosting it's common to import SQL manually, so the DB schema exists but
     * the `migrations` table doesn't reflect reality. Also, we no longer use Telegram;
     * legacy telegram migrations should be skipped.
     *
     * @return list<string> Baselined migration names.
     */
    private function baselineLegacyMigrations(): array
    {
        $this->ensureMigrationsTableExists();

        $ran = DB::table('migrations')->pluck('migration')->all();
        $ranLookup = array_fill_keys($ran, true);

        $batch = (int) (DB::table('migrations')->max('batch') ?? 0);
        $batch = max(0, $batch) + 1;

        $baselined = [];

        $paths = File::glob(database_path('migrations').'/*_*.php') ?: [];
        sort($paths);

        foreach ($paths as $path) {
            $migration = basename($path, '.php');

            if (isset($ranLookup[$migration])) {
                continue;
            }

            $contents = '';
            try {
                $contents = (string) File::get($path);
            } catch (\Throwable) {
                continue;
            }

            $combinedLower = strtolower($migration."\n".$contents);

            // Telegram is no longer used: always skip legacy telegram migrations.
            if (str_contains($combinedLower, 'telegram')) {
                DB::table('migrations')->insert([
                    'migration' => $migration,
                    'batch' => $batch,
                ]);

                $ranLookup[$migration] = true;
                $baselined[] = $migration;
                continue;
            }

            // If a migration creates tables that already exist, baseline it to prevent
            // "table already exists" errors on `migrate`.
            preg_match_all("/Schema::create\\(\\s*['\\\"]([^'\\\"]+)['\\\"]/i", $contents, $matches);
            $tables = array_values(array_unique($matches[1] ?? []));

            if ($tables === []) {
                continue;
            }

            $allExist = true;
            foreach ($tables as $tableName) {
                if (! Schema::hasTable($tableName)) {
                    $allExist = false;
                    break;
                }
            }

            if (! $allExist) {
                continue;
            }

            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $batch,
            ]);

            $ranLookup[$migration] = true;
            $baselined[] = $migration;
        }

        return $baselined;
    }

    private function ensureMigrationsTableExists(): void
    {
        if (Schema::hasTable('migrations')) {
            return;
        }

        Schema::create('migrations', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });
    }

    public function seedSharePlatforms(): void
    {
        try {
            if (! Schema::hasTable('share_platforms')) {
                Artisan::call('migrate', [
                    '--force' => true,
                    '--path' => [
                        'database/migrations/2026_02_05_000000_create_share_platforms_table.php',
                    ],
                ]);

                if (! Schema::hasTable('share_platforms')) {
                    Notification::make()
                        ->title('Missing table: share_platforms')
                        ->body('Migration did not create the table. Check DB permissions, then try again.')
                        ->warning()
                        ->send();
                    return;
                }
            }

            Artisan::call('db:seed', [
                '--class' => \Database\Seeders\SharePlatformSeeder::class,
                '--force' => true,
            ]);

            $output = trim((string) Artisan::output());
            $output = $output !== '' ? $output : 'Seeder completed.';

            SiteSetting::setValues([
                'last_share_platform_seed_at' => now()->toIso8601String(),
                'last_share_platform_seed_output' => mb_substr($output, 0, 2000),
            ]);

            Notification::make()
                ->title('Share platforms seeded')
                ->body(mb_substr($output, 0, 200))
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Failed to seed share platforms')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearScheduleLocks(): void
    {
        try {
            Artisan::call('schedule:clear-cache');

            SiteSetting::setValues([
                'last_schedule_clear_at' => now()->toIso8601String(),
            ]);

            $this->lastScheduleClearAt = SiteSetting::getValue('last_schedule_clear_at');

            Notification::make()
                ->title('Scheduler locks cleared')
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Failed to clear scheduler locks')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
