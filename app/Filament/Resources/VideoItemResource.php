<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoItemResource\Pages;
use App\Models\VideoItem;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VideoItemResource extends Resource
{
    protected static ?string $model = VideoItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationGroup = 'Media';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('type')
                            ->options([
                                VideoItem::TYPE_EPISODE => 'episode',
                                VideoItem::TYPE_REPORT => 'report',
                                VideoItem::TYPE_HOME => 'home',
                            ])
                            ->required(),

                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('youtube_url')
                            ->label('YouTube URL')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('thumbnail_url')
                            ->label('Thumbnail URL')
                            ->rows(2)
                            ->columnSpanFull(),

                        DateTimePicker::make('published_at')
                            ->label('Published at'),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),

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

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        VideoItem::TYPE_EPISODE => 'episode',
                        VideoItem::TYPE_REPORT => 'report',
                        VideoItem::TYPE_HOME => 'home',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('sort_order')
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
            'index' => Pages\ListVideoItems::route('/'),
            'create' => Pages\CreateVideoItem::route('/create'),
            'edit' => Pages\EditVideoItem::route('/{record}/edit'),
        ];
    }
}
