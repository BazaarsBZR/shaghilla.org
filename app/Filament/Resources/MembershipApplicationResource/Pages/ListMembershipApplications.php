<?php

namespace App\Filament\Resources\MembershipApplicationResource\Pages;

use App\Filament\Resources\MembershipApplicationResource;
use App\Support\Filament\OptionalFilamentActions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Schema;

class ListMembershipApplications extends ListRecords
{
    protected static string $resource = MembershipApplicationResource::class;

    public function mount(): void
    {
        try {
            $tableExists = Schema::hasTable('membership_applications');
        } catch (\Throwable) {
            $tableExists = false;
        }

        if (! $tableExists) {
            Notification::make()
                ->title('Membership applications table is missing')
                ->body('Import database/shaghilla_patch_membership_applications.sql in phpMyAdmin, then refresh this page.')
                ->danger()
                ->send();

            $this->redirect(route('filament.admin.pages.membership-settings'));

            return;
        }

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($exportAction = OptionalFilamentActions::exportAction()) {
            $actions[] = $exportAction;
        }

        return $actions;
    }
}
