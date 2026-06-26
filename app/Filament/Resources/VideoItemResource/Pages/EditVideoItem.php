<?php

namespace App\Filament\Resources\VideoItemResource\Pages;

use App\Filament\Resources\VideoItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVideoItem extends EditRecord
{
    protected static string $resource = VideoItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
