<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeedSourceResource\Pages;
use App\Models\FeedSource;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class FeedSourceResource extends Resource
{
    protected static ?string $model = FeedSource::class;

    protected static ?string $navigationIcon = 'heroicon-o-rss';

    protected static ?string $navigationGroup = 'News';

    public static function form(Form $form): Form
    {
        $hasDestination = Schema::hasColumn('feed_sources', 'destination');

        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('url')
                            ->label('URL')
                            ->required()
                            ->url()
                            ->maxLength(2048)
                            ->columnSpanFull(),

                        Select::make('destination')
                            ->label('Destination')
                            ->options([
                                FeedSource::DESTINATION_HOME => 'Home page only',
                                FeedSource::DESTINATION_BOTH => 'Use on both (home + breaking)',
                                FeedSource::DESTINATION_BREAKING => 'Breaking ticker only',
                            ])
                            ->default(FeedSource::DESTINATION_BOTH)
                            ->helperText('Controls where imported items are shown.')
                            ->visible($hasDestination),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Select::make('default_category_id')
                            ->label('Default category')
                            ->relationship('defaultCategory', 'name_ar')
                            ->searchable()
                            ->preload(),

                        DateTimePicker::make('last_fetched_at')
                            ->label('Last fetched at')
                            ->disabled(),

                        TextInput::make('etag')
                            ->disabled(),

                        TextInput::make('last_modified')
                            ->label('Last modified')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $hasDestination = Schema::hasColumn('feed_sources', 'destination');

        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('url')
                    ->label('URL')
                    ->wrap()
                    ->toggleable()
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('destination')
                    ->label('Destination')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        FeedSource::DESTINATION_HOME => 'Home only',
                        FeedSource::DESTINATION_BREAKING => 'Breaking only',
                        default => 'Both',
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible($hasDestination),

                TextColumn::make('defaultCategory.name_ar')
                    ->label('Default category')
                    ->sortable(),

                TextColumn::make('last_fetched_at')
                    ->label('Last fetched at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedSources::route('/'),
            'create' => Pages\CreateFeedSource::route('/create'),
            'edit' => Pages\EditFeedSource::route('/{record}/edit'),
        ];
    }
}
