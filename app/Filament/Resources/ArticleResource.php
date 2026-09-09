<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
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
                Section::make('Write article')
                    ->description('Create an original story for Shaghilla or edit an imported article.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Headline')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('Write the article headline')
                            ->columnSpanFull(),

                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name_ar')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('status')
                            ->label('Publication status')
                            ->options([
                                'published' => 'Publish now',
                                'draft' => 'Save as draft',
                            ])
                            ->default('published')
                            ->required()
                            ->native(false),

                        DateTimePicker::make('published_at')
                            ->label('Published at')
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
                            ->label('Article body')
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

                Section::make('Featured image')
                    ->description('Upload a lead image for the homepage card and article page.')
                    ->schema([
                        FileUpload::make('manual_image')
                            ->label('Upload image')
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

                        TextInput::make('image_url')
                            ->label('Image URL')
                            ->url()
                            ->helperText('Optional for imported stories or externally hosted images.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Placement and source')
                    ->schema([
                        Toggle::make('show_on_home')
                            ->label('Show on home page')
                            ->default(true)
                            ->visible($hasShowOnHome),

                        Toggle::make('is_breaking')
                            ->label('Show in breaking ticker')
                            ->default(false),

                        Select::make('feed_source_id')
                            ->label('Feed source')
                            ->relationship('feedSource', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave blank for an original Shaghilla article.'),

                        TextInput::make('language')
                            ->maxLength(5)
                            ->default('ar'),

                        TextInput::make('slug')
                            ->label('URL slug')
                            ->helperText('Optional. A unique URL is generated from the headline when left blank.')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),

                        TextInput::make('canonical_url')
                            ->label('Canonical URL')
                            ->url()
                            ->helperText('Leave blank for original Shaghilla articles.')
                            ->columnSpanFull(),

                        DateTimePicker::make('imported_at')
                            ->label('Imported at')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $hasShowOnHome = Schema::hasColumn('articles', 'show_on_home');

        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Image')
                    ->square()
                    ->size(44),

                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('category.name_ar')
                    ->label('Category')
                    ->sortable(),

                TextColumn::make('origin')
                    ->label('Origin')
                    ->getStateUsing(fn (Article $record): string => $record->feed_source_id ? 'Imported' : 'Manual')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Manual' ? 'success' : 'gray'),

                IconColumn::make('show_on_home')
                    ->label('Home')
                    ->boolean()
                    ->sortable()
                    ->visible($hasShowOnHome),

                IconColumn::make('is_breaking')
                    ->label('Breaking')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
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
                TernaryFilter::make('is_breaking')->label('Breaking'),
                TernaryFilter::make('show_on_home')->label('Home')->visible($hasShowOnHome),
                SelectFilter::make('status')->options([
                    'published' => 'published',
                    'draft' => 'draft',
                ]),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name_ar'),
            ])
            ->defaultSort('published_at', 'desc')
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
