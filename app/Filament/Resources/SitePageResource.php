<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SitePageResource\Pages;
use App\Filament\Pages\AppearanceSettings;
use App\Filament\Pages\HomepageBuilder;
use App\Filament\Pages\MembershipSettings;
use App\Filament\Pages\ServiceRequestSettings;
use App\Models\SitePage;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SitePageResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = SitePage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Menu & Pages';

    public static function getContentEditorUrl(SitePage $record): ?string
    {
        if ($record->type === SitePage::TYPE_PAGE) {
            return static::getUrl('edit', ['record' => $record]);
        }

        if ($record->type !== SitePage::TYPE_ROUTE) {
            return null;
        }

        return match ($record->route_name) {
            'home' => HomepageBuilder::getUrl(),
            'live' => AppearanceSettings::getUrl(),
            'membership' => MembershipSettings::getUrl(),
            'contact' => ServiceRequestSettings::getUrl(),
            default => null,
        };
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Group::make()
                            ->schema([
                                Select::make('type')
                                    ->options([
                                        SitePage::TYPE_ROUTE => 'Route (built-in page)',
                                        SitePage::TYPE_PAGE => 'Custom page',
                                        SitePage::TYPE_EXTERNAL => 'External link',
                                    ])
                                    ->required()
                                    ->default(SitePage::TYPE_PAGE)
                                    ->disabled(fn (?SitePage $record) => (bool) ($record?->is_system))
                                    ->live(),

                                Select::make('route_name')
                                    ->label('Route name')
                                    ->helperText('Only for built-in pages.')
                                    ->options([
                                        'home' => 'home (/)',
                                        'live' => 'live (/live)',
                                        'membership' => 'membership (/membership)',
                                        'contact' => 'contact (/contact)',
                                        'search' => 'search (/search)',
                                    ])
                                    ->required(fn ($get) => $get('type') === SitePage::TYPE_ROUTE)
                                    ->disabled(fn (?SitePage $record) => (bool) ($record?->is_system))
                                    ->visible(fn ($get) => $get('type') === SitePage::TYPE_ROUTE)
                                    ->unique(ignoreRecord: true),

                                TextInput::make('slug')
                                    ->helperText('URL will be /pages/{slug}')
                                    ->required(fn ($get) => $get('type') === SitePage::TYPE_PAGE)
                                    ->visible(fn ($get) => $get('type') === SitePage::TYPE_PAGE)
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                TextInput::make('external_url')
                                    ->label('External URL')
                                    ->url()
                                    ->required(fn ($get) => $get('type') === SitePage::TYPE_EXTERNAL)
                                    ->visible(fn ($get) => $get('type') === SitePage::TYPE_EXTERNAL)
                                    ->maxLength(2048),
                            ])
                            ->columns(2),

                        Group::make()
                            ->schema([
                                TextInput::make('title_ar')
                                    ->label('Title (Arabic)')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('title_en')
                                    ->label('Title (English)')
                                    ->maxLength(255),
                            ])
                            ->columns(2),

                        Group::make()
                            ->schema([
                                Toggle::make('show_in_header')
                                    ->label('Show in header menu')
                                    ->default(false),

                                Toggle::make('show_in_footer')
                                    ->label('Show in footer')
                                    ->default(false),

                                Toggle::make('open_in_new_tab')
                                    ->label('Open in new tab')
                                    ->default(false),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ])
                            ->columns(4),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ]),

                Section::make('Built-in page content')
                    ->visible(fn ($get) => $get('type') === SitePage::TYPE_ROUTE)
                    ->schema([
                        Placeholder::make('builtInHelp')
                            ->label('Where do I edit the content?')
                            ->content('This is a built-in page. Use the buttons below to edit its content/settings.'),

                        Actions::make([
                            FormAction::make('editHome')
                                ->label('Edit Home page')
                                ->icon('heroicon-o-home')
                                ->url(fn (): string => HomepageBuilder::getUrl())
                                ->openUrlInNewTab()
                                ->visible(fn ($get) => (string) $get('route_name') === 'home'),

                            FormAction::make('editLive')
                                ->label('Edit Live page (YouTube)')
                                ->icon('heroicon-o-video-camera')
                                ->url(fn (): string => AppearanceSettings::getUrl())
                                ->openUrlInNewTab()
                                ->visible(fn ($get) => (string) $get('route_name') === 'live'),

                            FormAction::make('editMembership')
                                ->label('Edit Membership page')
                                ->icon('heroicon-o-identification')
                                ->url(fn (): string => MembershipSettings::getUrl())
                                ->openUrlInNewTab()
                                ->visible(fn ($get) => (string) $get('route_name') === 'membership'),

                            FormAction::make('editContact')
                                ->label('Edit Contact page')
                                ->icon('heroicon-o-inbox')
                                ->url(fn (): string => ServiceRequestSettings::getUrl())
                                ->openUrlInNewTab()
                                ->visible(fn ($get) => (string) $get('route_name') === 'contact'),
                        ])->fullWidth(),
                    ]),

                Section::make('Page content (Arabic)')
                    ->visible(fn ($get) => $get('type') === SitePage::TYPE_PAGE)
                    ->schema([
                        RichEditor::make('content_html_ar')
                            ->label('Content (AR)')
                            ->toolbarButtons([
                                'attachFiles',
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'blockquote',
                                'codeBlock',
                                'h2',
                                'h3',
                                'redo',
                                'undo',
                            ])
                            ->fileAttachmentsDisk('public_uploads')
                            ->fileAttachmentsDirectory('pages')
                            ->fileAttachmentsVisibility('public')
                            ->columnSpanFull(),
                    ]),

                Section::make('Page content (English)')
                    ->visible(fn ($get) => $get('type') === SitePage::TYPE_PAGE)
                    ->schema([
                        RichEditor::make('content_html_en')
                            ->label('Content (EN)')
                            ->toolbarButtons([
                                'attachFiles',
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'blockquote',
                                'codeBlock',
                                'h2',
                                'h3',
                                'redo',
                                'undo',
                            ])
                            ->fileAttachmentsDisk('public_uploads')
                            ->fileAttachmentsDirectory('pages')
                            ->fileAttachmentsVisibility('public')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title_ar')
                    ->label('Title (AR)')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('route_name')
                    ->label('Route')
                    ->toggleable(),

                TextColumn::make('slug')
                    ->toggleable(),

                TextColumn::make('url')
                    ->label('URL')
                    ->formatStateUsing(fn (SitePage $record): string => $record->url())
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('show_in_header')
                    ->boolean()
                    ->label('Header'),

                IconColumn::make('show_in_footer')
                    ->boolean()
                    ->label('Footer'),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SitePage $record): string => $record->url())
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('editContent')
                    ->label('Edit content')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (SitePage $record): ?string => static::getContentEditorUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (SitePage $record): bool => (bool) static::getContentEditorUrl($record)),

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
            'index' => Pages\ListSitePages::route('/'),
            'create' => Pages\CreateSitePage::route('/create'),
            'edit' => Pages\EditSitePage::route('/{record}/edit'),
        ];
    }
}
