<?php

namespace App\Filament\Resources\SitePageResource\Pages;

use App\Filament\Resources\SitePageResource;
use App\Support\Filament\OptionalFilamentActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSitePages extends ListRecords
{
    protected static string $resource = SitePageResource::class;

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
