<?php

namespace App\Filament\Resources\FeedSourceResource\Pages;

use App\Filament\Resources\FeedSourceResource;
use App\Support\Filament\OptionalFilamentActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFeedSources extends ListRecords
{
    protected static string $resource = FeedSourceResource::class;

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
