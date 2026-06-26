<?php

namespace App\Filament\Resources\KeywordRuleResource\Pages;

use App\Filament\Resources\KeywordRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKeywordRule extends EditRecord
{
    protected static string $resource = KeywordRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
