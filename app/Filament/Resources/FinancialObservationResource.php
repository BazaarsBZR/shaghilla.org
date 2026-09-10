<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinancialObservationResource\Pages;
use App\Models\FinancialObservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FinancialObservationResource extends Resource
{
    protected static ?string $model = FinancialObservation::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationGroup = 'Public Money';
    protected static ?string $navigationLabel = 'Budget and spending';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('category')->required()->columnSpanFull(),
            Forms\Components\Select::make('measure_type')->options(['allocation' => 'Budget allocation', 'reported_expenditure' => 'Reported expenditure', 'revenue' => 'Revenue'])->required(),
            Forms\Components\TextInput::make('fiscal_period')->required(),
            Forms\Components\TextInput::make('amount')->numeric()->required(),
            Forms\Components\TextInput::make('currency')->required(),
            Forms\Components\TextInput::make('original_unit')->required(),
            Forms\Components\TextInput::make('source_url')->url()->required()->columnSpanFull(),
            Forms\Components\TextInput::make('page_reference'),
            Forms\Components\Toggle::make('is_total'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('fiscal_period')->sortable(),
            Tables\Columns\TextColumn::make('measure_type')->badge()->sortable(),
            Tables\Columns\TextColumn::make('category')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('amount')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('original_unit'),
            Tables\Columns\TextColumn::make('review_status')->badge(),
            Tables\Columns\TextColumn::make('publication_status')->badge(),
        ])->defaultSort('fiscal_period', 'desc')->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('approve')->color('success')->form([Forms\Components\Textarea::make('reason')->required()])->action(fn (FinancialObservation $record, array $data) => $record->transition('approve', auth()->user(), $data['reason'])),
            Tables\Actions\Action::make('publish')->color('primary')->requiresConfirmation()->action(fn (FinancialObservation $record) => $record->transition('publish', auth()->user(), 'Published after editorial review.')),
            Tables\Actions\Action::make('reject')->color('danger')->form([Forms\Components\Textarea::make('reason')->required()])->action(fn (FinancialObservation $record, array $data) => $record->transition('reject', auth()->user(), $data['reason'])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFinancialObservations::route('/'), 'edit' => Pages\EditFinancialObservation::route('/{record}/edit')];
    }
}
