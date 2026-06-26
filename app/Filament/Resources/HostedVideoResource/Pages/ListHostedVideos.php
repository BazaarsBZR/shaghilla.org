<?php

namespace App\Filament\Resources\HostedVideoResource\Pages;

use App\Filament\Resources\HostedVideoResource;
use App\Support\Filament\OptionalFilamentActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHostedVideos extends ListRecords
{
    protected static string $resource = HostedVideoResource::class;

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
