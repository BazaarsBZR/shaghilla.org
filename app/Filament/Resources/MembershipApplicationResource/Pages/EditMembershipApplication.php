<?php

namespace App\Filament\Resources\MembershipApplicationResource\Pages;

use App\Filament\Resources\MembershipApplicationResource;
use App\Models\MembershipApplication;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMembershipApplication extends EditRecord
{
    protected static string $resource = MembershipApplicationResource::class;

    protected function getHeaderActions(): array
    {
        /** @var MembershipApplication $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('viewIdDocument')
                ->label('View ID')
                ->icon('heroicon-o-identification')
                ->url(fn (): string => route('admin.membership-applications.id-document', $record))
                ->openUrlInNewTab()
                ->visible((bool) $record->id_document_path),

            Actions\ViewAction::make(),
        ];
    }
}

