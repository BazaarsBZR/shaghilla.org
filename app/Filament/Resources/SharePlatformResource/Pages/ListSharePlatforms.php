<?php

namespace App\Filament\Resources\SharePlatformResource\Pages;

use App\Filament\Resources\SharePlatformResource;
use App\Filament\Pages\MaintenanceTools;
use App\Support\Filament\OptionalFilamentActions;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Schema;

class ListSharePlatforms extends ListRecords
{
    protected static string $resource = SharePlatformResource::class;

    public function mount(): void
    {
        parent::mount();

        if (! Schema::hasTable('share_platforms')) {
            Notification::make()
                ->title('Missing DB table: share_platforms')
                ->body('Run migrations first (Admin → Automation → Maintenance → Run migrations).')
                ->warning()
                ->send();

            $this->redirect(MaintenanceTools::getUrl());
        }
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        if ($exportAction = OptionalFilamentActions::exportAction()) {
            $actions[] = $exportAction;
        }

        return $actions;
    }
}
