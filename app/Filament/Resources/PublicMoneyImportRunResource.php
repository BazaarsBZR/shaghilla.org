<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PublicMoneyImportRunResource\Pages;
use App\Models\PublicMoneyImportRun;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PublicMoneyImportRunResource extends Resource
{
    protected static ?string $model = PublicMoneyImportRun::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Public Money';
    protected static ?string $navigationLabel = 'Import runs';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('source.name_en')->label('Source')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {'succeeded' => 'success', 'failed' => 'danger', 'partial' => 'warning', default => 'gray'}),
            Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('discovered_count')->label('Found'),
            Tables\Columns\TextColumn::make('created_count')->label('New'),
            Tables\Columns\TextColumn::make('updated_count')->label('Seen/updated'),
            Tables\Columns\TextColumn::make('failed_count')->label('Failed'),
            Tables\Columns\TextColumn::make('error_summary')->limit(70)->wrap(),
        ])->defaultSort('started_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPublicMoneyImportRuns::route('/')];
    }
}
