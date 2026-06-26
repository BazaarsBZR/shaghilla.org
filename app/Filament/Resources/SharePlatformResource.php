<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SharePlatformResource\Pages;
use App\Models\SharePlatform;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SharePlatformResource extends Resource
{
    protected static ?string $model = SharePlatform::class;

    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                if ((string) $get('slug') !== '') {
                                    return;
                                }

                                $set('slug', Str::slug((string) $state));
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),

                        Select::make('icon')
                            ->label('Icon (Font Awesome)')
                            ->options(SharePlatform::iconOptions())
                            ->searchable()
                            ->required(),

                        Placeholder::make('icon_preview')
                            ->label('Preview')
                            ->content(function (Get $get): HtmlString {
                                $icon = (string) ($get('icon') ?? '');

                                return new HtmlString(
                                    view('filament.forms.share-platform-icon-preview', [
                                        'icon' => $icon,
                                    ])->render(),
                                );
                            }),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Toggle::make('use_native_share')
                            ->label('Use native share sheet (Web Share API)')
                            ->helperText('Use for platforms that do not support a URL-based share endpoint (e.g., Instagram).')
                            ->default(false),

                        Textarea::make('share_url_template')
                            ->label('Share URL template')
                            ->rows(3)
                            ->helperText('Use placeholders {url} and {title}. Leave empty to rely on native share/copy.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('icon')
                    ->label('Icon')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('use_native_share')
                    ->label('Native')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('sort_order')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->reorderable('sort_order')
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
            'index' => Pages\ListSharePlatforms::route('/'),
            'create' => Pages\CreateSharePlatform::route('/create'),
            'edit' => Pages\EditSharePlatform::route('/{record}/edit'),
        ];
    }
}
