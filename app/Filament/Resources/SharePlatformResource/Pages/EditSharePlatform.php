<?php

namespace App\Filament\Resources\SharePlatformResource\Pages;

use App\Filament\Resources\SharePlatformResource;
use App\Filament\Pages\MaintenanceTools;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Schema;

class EditSharePlatform extends EditRecord
{
    protected static string $resource = SharePlatformResource::class;

    public function mount($record): void
    {
        parent::mount($record);

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
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
