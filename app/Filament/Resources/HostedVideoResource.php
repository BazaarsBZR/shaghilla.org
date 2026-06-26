<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HostedVideoResource\Pages;
use App\Models\HostedVideo;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HostedVideoResource extends Resource
{
    protected static ?string $model = HostedVideo::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static ?string $navigationGroup = 'Media';

    protected static ?string $navigationLabel = 'Hosted Videos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->helperText('Auto-generated if left blank.')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        FileUpload::make('video_path')
                            ->label('Video file')
                            ->disk('public_uploads')
                            ->directory('videos/hosted')
                            ->preserveFilenames(false)
                            ->required()
                            ->acceptedFileTypes([
                                'video/mp4',
                                'video/webm',
                                'video/ogg',
                                'video/quicktime',
                            ])
                            ->maxSize(512000) // 500 MB (depends on server limits)
                            ->columnSpanFull(),

                        FileUpload::make('poster_path')
                            ->label('Poster image (optional)')
                            ->disk('public_uploads')
                            ->directory('videos/posters')
                            ->preserveFilenames(false)
                            ->image()
                            ->maxSize(10240)
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

                TextColumn::make('updated_at')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHostedVideos::route('/'),
            'create' => Pages\CreateHostedVideo::route('/create'),
            'edit' => Pages\EditHostedVideo::route('/{record}/edit'),
        ];
    }
}

