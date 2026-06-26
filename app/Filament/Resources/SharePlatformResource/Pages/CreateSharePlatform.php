<?php

namespace App\Filament\Resources\SharePlatformResource\Pages;

use App\Filament\Resources\SharePlatformResource;
use App\Filament\Pages\MaintenanceTools;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Schema;

class CreateSharePlatform extends CreateRecord
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
}
