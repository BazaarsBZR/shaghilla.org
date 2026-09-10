<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\DashboardUpdater;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Updates extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string $view = 'filament.pages.updates';

    protected static ?string $navigationGroup = 'Automation';

    protected static ?string $navigationLabel = 'Updates';

    public ?string $currentBuildId = null;

    public ?string $appRootOverride = null;

    public ?string $lastUpdateAt = null;

    public bool $updaterEnabled = false;

    public function mount(): void
    {
        $this->updaterEnabled = (string) env('DASHBOARD_UPDATE_TOKEN', '') !== '';
        $this->currentBuildId = DashboardUpdater::readBuildId(base_path());
        $this->appRootOverride = DashboardUpdater::readAppRootOverride(public_path());
        $this->lastUpdateAt = SiteSetting::getValue('last_dashboard_update_at');
    }

    protected function getHeaderActions(): array
    {
        if (! $this->updaterEnabled) {
            return [];
        }

        return [
            Action::make('deploy')
                ->label('Deploy update zip')
                ->color('danger')
                ->modalHeading('Deploy update')
                ->modalDescription('Upload a zip created by scripts/build-cpanel-upload.sh (must contain app/ and public_html/).')
                ->form([
                    FileUpload::make('package')
                        ->label('Update package (.zip)')
                        ->disk('local')
                        ->directory('updates/packages')
                        ->preserveFilenames(true)
                        ->required()
                        ->acceptedFileTypes([
                            'application/zip',
                            'application/x-zip-compressed',
                            'application/octet-stream',
                        ])
                        ->maxSize(256000)
                        ->helperText('If upload fails, increase upload_max_filesize/post_max_size in PHP settings.'),

                    TextInput::make('token')
                        ->label('Update token')
                        ->password()
                        ->required()
                        ->helperText('Set DASHBOARD_UPDATE_TOKEN in .env to enable deployments from the dashboard.'),
                ])
                ->action(function (array $data): void {
                    $token = (string) ($data['token'] ?? '');
                    $packagePath = (string) ($data['package'] ?? '');

                    try {
                        $result = app(DashboardUpdater::class)->deploy($packagePath, $token);

                        SiteSetting::setValues([
                            'last_dashboard_update_at' => now()->toIso8601String(),
                            'last_dashboard_update_build_id' => $result['build_id'] ?? '',
                        ]);

                        $this->currentBuildId = DashboardUpdater::readBuildId(base_path());
                        $this->appRootOverride = DashboardUpdater::readAppRootOverride(public_path());
                        $this->lastUpdateAt = SiteSetting::getValue('last_dashboard_update_at');

                        Notification::make()
                            ->title('Update deployed')
                            ->body('Build: '.($result['build_id'] ?? 'unknown'))
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Update failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
