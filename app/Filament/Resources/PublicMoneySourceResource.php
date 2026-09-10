<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PublicMoneySourceResource\Pages;
use App\Models\PublicMoneySource;
use App\Services\PublicMoney\PublicMoneyImporter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PublicMoneySourceResource extends Resource
{
    protected static ?string $model = PublicMoneySource::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = 'Public Money';
    protected static ?string $navigationLabel = 'Official sources';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name_en')->required(),
            Forms\Components\TextInput::make('name_ar')->required(),
            Forms\Components\TextInput::make('key')->disabled(),
            Forms\Components\TextInput::make('adapter')->disabled(),
            Forms\Components\TextInput::make('discovery_url')->url()->required()->columnSpanFull(),
            Forms\Components\Toggle::make('is_enabled'),
            Forms\Components\TextInput::make('check_interval_minutes')->numeric()->minValue(60),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name_en')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('adapter')->badge(),
            Tables\Columns\IconColumn::make('is_enabled')->boolean(),
            Tables\Columns\TextColumn::make('last_success_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('last_error')->color('danger')->limit(55)->toggleable(),
        ])->actions([
            Tables\Actions\Action::make('import')->icon('heroicon-o-arrow-path')->requiresConfirmation()->action(function (PublicMoneySource $record): void {
                $result = app(PublicMoneyImporter::class)->import($record->key, 50, false);
                Notification::make()->title('Official source checked')->body(json_encode($result[$record->key] ?? []))->success()->send();
            }),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPublicMoneySources::route('/'), 'edit' => Pages\EditPublicMoneySource::route('/{record}/edit')];
    }
}
