<?php

namespace App\Filament\Resources\MembershipStatusResource\Pages;

use App\Filament\Resources\MembershipApplicationResource;
use App\Filament\Resources\MembershipStatusResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMembershipStatus extends CreateRecord
{
    protected static string $resource = MembershipStatusResource::class;

    public function mount(): void
    {
        Notification::make()
            ->title('Membership statuses page moved')
            ->body('Use Membership > Applications to review and update application statuses.')
            ->warning()
            ->send();

        $this->redirect(MembershipApplicationResource::getUrl('index'));
    }
}
