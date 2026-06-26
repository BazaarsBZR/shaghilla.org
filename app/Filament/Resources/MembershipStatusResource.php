<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MembershipStatusResource\Pages;
use App\Models\MembershipApplication;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class MembershipStatusResource extends Resource
{
    protected static ?string $model = MembershipApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static ?string $navigationGroup = 'Membership';

    protected static ?string $navigationLabel = 'Membership Statuses (Legacy)';

    protected static ?int $navigationSort = 99;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return MembershipApplicationResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return MembershipApplicationResource::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembershipStatuses::route('/'),
            'create' => Pages\CreateMembershipStatus::route('/create'),
            'edit' => Pages\EditMembershipStatus::route('/{record}/edit'),
        ];
    }
}
