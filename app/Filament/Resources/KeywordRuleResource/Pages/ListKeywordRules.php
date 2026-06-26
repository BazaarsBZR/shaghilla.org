<?php

namespace App\Filament\Resources\KeywordRuleResource\Pages;

use App\Filament\Resources\KeywordRuleResource;
use App\Support\Filament\OptionalFilamentActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKeywordRules extends ListRecords
{
    protected static string $resource = KeywordRuleResource::class;

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
