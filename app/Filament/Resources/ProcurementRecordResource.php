<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcurementRecordResource\Pages;
use App\Models\ProcurementRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProcurementRecordResource extends Resource
{
    protected static ?string $model = ProcurementRecord::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Public Money';
    protected static ?string $navigationLabel = 'Procurement records';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Official record')->schema([
                Forms\Components\TextInput::make('title')->required()->columnSpanFull(),
                Forms\Components\TextInput::make('authority')->required(),
                Forms\Components\TextInput::make('supplier'),
                Forms\Components\Select::make('stage')->options(['award' => 'Award', 'contract' => 'Contract', 'implementation' => 'Implementation'])->required(),
                Forms\Components\TextInput::make('status_normalized'),
                Forms\Components\TextInput::make('amount')->numeric(),
                Forms\Components\TextInput::make('currency')->maxLength(12),
                Forms\Components\DatePicker::make('event_on'),
                Forms\Components\TextInput::make('source_url')->url()->required()->columnSpanFull(),
                Forms\Components\Textarea::make('description')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('event_on')->date()->sortable(),
            Tables\Columns\TextColumn::make('stage')->badge()->sortable(),
            Tables\Columns\TextColumn::make('title')->searchable()->wrap()->limit(70),
            Tables\Columns\TextColumn::make('authority')->searchable()->wrap()->toggleable(),
            Tables\Columns\TextColumn::make('amount')->numeric(decimalPlaces: 2)->suffix(fn (ProcurementRecord $record): string => ' '.($record->currency ?: '')),
            Tables\Columns\TextColumn::make('review_status')->badge()->color(fn (string $state): string => match ($state) {'approved' => 'success', 'rejected' => 'danger', default => 'warning'}),
            Tables\Columns\TextColumn::make('publication_status')->badge(),
        ])->filters([
            Tables\Filters\SelectFilter::make('stage')->options(['award' => 'Award', 'contract' => 'Contract', 'implementation' => 'Implementation']),
            Tables\Filters\SelectFilter::make('review_status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
        ])->defaultSort('event_on', 'desc')->actions([
            Tables\Actions\EditAction::make(),
            ...self::reviewActions(),
        ]);
    }

    private static function reviewActions(): array
    {
        $reason = [Forms\Components\Textarea::make('reason')->required()->maxLength(1000)];
        return [
            Tables\Actions\Action::make('approve')->color('success')->icon('heroicon-o-check')->form($reason)->action(fn (ProcurementRecord $record, array $data) => $record->transition('approve', auth()->user(), $data['reason'])),
            Tables\Actions\Action::make('publish')->color('primary')->icon('heroicon-o-globe-alt')->requiresConfirmation()->action(fn (ProcurementRecord $record) => $record->transition('publish', auth()->user(), 'Published after editorial review.')),
            Tables\Actions\Action::make('reject')->color('danger')->icon('heroicon-o-x-mark')->form($reason)->action(fn (ProcurementRecord $record, array $data) => $record->transition('reject', auth()->user(), $data['reason'])),
            Tables\Actions\Action::make('correct')->color('warning')->icon('heroicon-o-pencil-square')->form($reason)->action(fn (ProcurementRecord $record, array $data) => $record->transition('correct', auth()->user(), $data['reason'])),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProcurementRecords::route('/'), 'edit' => Pages\EditProcurementRecord::route('/{record}/edit')];
    }
}
