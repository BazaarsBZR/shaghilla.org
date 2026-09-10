<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'News';

    protected static ?string $navigationLabel = 'Articles';

    protected static ?string $modelLabel = 'article';

    protected static ?string $pluralModelLabel = 'articles';

    public static function form(Form $form): Form
    {
        $hasShowOnHome = Schema::hasColumn('articles', 'show_on_home');

        return $form
            ->schema([
                Section::make('Story')
                    ->description('Write the story exactly as visitors should see it on the website.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Headline')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('Type a clear headline')
                            ->helperText('Keep it short and specific. This is the first thing readers will see.')
                            ->columnSpanFull(),

                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name_ar')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('status')
                            ->label('Visibility')
                            ->options([
                                'draft' => 'Draft - only staff can see it',
                                'published' => 'Published - visible to everyone',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false),

                        DateTimePicker::make('published_at')
                            ->label('Publication date and time')
                            ->default(now())
                            ->seconds(false)
                            ->required(),

                        Textarea::make('excerpt')
                            ->label('Summary')
                            ->rows(4)
                            ->maxLength(1000)
                            ->helperText('A short summary for cards, search results, and social previews.')
                            ->columnSpanFull(),

                        RichEditor::make('content')
                            ->label('Full story')
                            ->required()
                            ->toolbarButtons([
                                'blockquote',
                                'bold',
                                'bulletList',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'underline',
                                'undo',
                            ])
                            ->fileAttachmentsDisk('public_uploads')
                            ->fileAttachmentsDirectory('news/content')
                            ->fileAttachmentsVisibility('public')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Main photo or video')
                    ->description('Drag a file here. It will appear on the story card and at the top of the article.')
                    ->schema([
                        FileUpload::make('manual_image')
                            ->label('Upload image')
                            ->hidden(fn (): bool => (bool) env('VERCEL'))
                            ->disk('public_uploads')
                            ->directory('news/manual')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3',
                                null,
                            ])
                            ->maxSize(10240)
                            ->helperText('JPG, PNG, or WebP up to 10 MB. A new upload replaces the image URL below.')
                            ->columnSpanFull(),

                        ViewField::make('blob_upload')
                            ->label('Upload image or video')
                            ->view('filament.forms.components.blob-image-upload')
                            ->visible(fn (): bool => (bool) env('VERCEL'))
                            ->columnSpanFull(),

                        TextInput::make('image_url')
                            ->label('Media URL')
                            ->url()
                            ->helperText('Advanced fallback for local development. Production editors should use the upload box above.')
                            ->hidden(fn (): bool => (bool) env('VERCEL'))
                            ->columnSpanFull(),
                    ]),

                Section::make('Where should this story appear?')
                    ->description('Choose the important places where visitors should see this story.')
                    ->schema([
                        Toggle::make('show_on_home')
                            ->label('Show on the home page')
                            ->helperText('Turn this off only when the story should stay away from the homepage.')
                            ->default(true)
                            ->visible($hasShowOnHome),

                        Toggle::make('is_breaking')
                            ->label('Add to the breaking news bar')
                            ->helperText('Use this only for urgent or especially important news.')
                            ->default(false),
                    ])
                    ->columns(2),

                Hidden::make('language')
                    ->default('ar'),

                Section::make('Imported story details')
                    ->description('Source information added automatically by the news importer.')
                    ->schema([

                        Select::make('feed_source_id')
                            ->label('Imported from')
                            ->relationship('feedSource', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled(),

                        TextInput::make('canonical_url')
                            ->label('Original source URL')
                            ->url()
                            ->helperText('The original publisher link for an imported story. Original Shaghilla articles do not need this.')
                            ->visible(fn (?Article $record): bool => filled($record?->canonical_url))
                            ->columnSpanFull(),

                        DateTimePicker::make('imported_at')
                            ->label('Imported on')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->visible(fn (?Article $record): bool => filled($record?->feed_source_id) || filled($record?->canonical_url))
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $hasShowOnHome = Schema::hasColumn('articles', 'show_on_home');

        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Media')
                    ->getStateUsing(fn (Article $record): ?string => preg_match('/\.(mp4|webm|mov|m4v)(?:$|[?#])/i', (string) $record->image_url) === 1
                        ? null
                        : $record->image_url)
                    ->square()
                    ->size(44),

                TextColumn::make('published_at')
                    ->label('Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Headline')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('category.name_ar')
                    ->label('Category')
                    ->sortable(),

                TextColumn::make('origin')
                    ->label('Added by')
                    ->getStateUsing(fn (Article $record): string => $record->feed_source_id ? 'News feed' : 'Shaghilla staff')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Shaghilla staff' ? 'success' : 'gray'),

                IconColumn::make('show_on_home')
                    ->label('Homepage')
                    ->boolean()
                    ->sortable()
                    ->visible($hasShowOnHome),

                IconColumn::make('is_breaking')
                    ->label('Breaking bar')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Visibility')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Published' : 'Draft')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'warning')
                    ->sortable(),

                TextColumn::make('feedSource.name')
                    ->label('Feed')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('language')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_breaking')->label('Breaking news bar'),
                TernaryFilter::make('show_on_home')->label('Homepage')->visible($hasShowOnHome),
                SelectFilter::make('status')->label('Visibility')->options([
                    'published' => 'Published',
                    'draft' => 'Draft',
                ]),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name_ar'),
            ])
            ->defaultSort('published_at', 'desc')
            ->emptyStateHeading('No stories yet')
            ->emptyStateDescription('Create the first Shaghilla story. You can save it as a draft before publishing.')
            ->emptyStateIcon('heroicon-o-document-plus')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()->label('Write the first story'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Open'),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
