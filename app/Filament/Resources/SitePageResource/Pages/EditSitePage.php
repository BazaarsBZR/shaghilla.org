<?php

namespace App\Filament\Resources\SitePageResource\Pages;

use App\Filament\Resources\SitePageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSitePage extends EditRecord
{
    protected static string $resource = SitePageResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Actions\Action::make('viewPublic')
                ->label('View')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => $record->url())
                ->openUrlInNewTab(),

            Actions\Action::make('editPageContent')
                ->label('Edit content')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): ?string => SitePageResource::getContentEditorUrl($record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) SitePageResource::getContentEditorUrl($record)),

            Actions\DeleteAction::make()
                ->visible(! $record->is_system),
        ];
    }
}
