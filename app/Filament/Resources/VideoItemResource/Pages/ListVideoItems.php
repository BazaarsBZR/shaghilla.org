<?php

namespace App\Filament\Resources\VideoItemResource\Pages;

use App\Filament\Resources\VideoItemResource;
use App\Support\Filament\OptionalFilamentActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVideoItems extends ListRecords
{
    protected static string $resource = VideoItemResource::class;

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
