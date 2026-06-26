<?php

namespace App\Filament\Resources\SiteSettingResource\Pages;

use App\Filament\Resources\SiteSettingResource;
use App\Support\Filament\OptionalFilamentActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiteSettings extends ListRecords
{
    protected static string $resource = SiteSettingResource::class;

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
