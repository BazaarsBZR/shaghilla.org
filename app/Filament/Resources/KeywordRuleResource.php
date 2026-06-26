<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KeywordRuleResource\Pages;
use App\Models\KeywordRule;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class KeywordRuleResource extends Resource
{
    protected static ?string $model = KeywordRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationGroup = 'News';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('type')
                            ->options([
                                KeywordRule::TYPE_WORKER => 'worker',
                                KeywordRule::TYPE_BREAKING => 'breaking',
                            ])
                            ->required(),

                        TextInput::make('keyword')
                            ->required()
                            ->maxLength(255),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('keyword')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        KeywordRule::TYPE_WORKER => 'worker',
                        KeywordRule::TYPE_BREAKING => 'breaking',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('type')
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
            'index' => Pages\ListKeywordRules::route('/'),
            'create' => Pages\CreateKeywordRule::route('/create'),
            'edit' => Pages\EditKeywordRule::route('/{record}/edit'),
        ];
    }
}
