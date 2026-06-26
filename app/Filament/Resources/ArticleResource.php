<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use Livewire\Component as Livewire;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'News';

    public static function form(Form $form): Form
    {
        $hasShowOnHome = Schema::hasColumn('articles', 'show_on_home');

        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('display_destination')
                            ->label('Display')
                            ->options([
                                'home' => 'Home page only',
                                'both' => 'Use on both (home + breaking)',
                                'breaking' => 'Breaking ticker only',
                            ])
                            ->default('home')
                            ->visible($hasShowOnHome)
                            ->live()
                            ->afterStateHydrated(function (Select $component, $state, Livewire $livewire): void {
                                if (! method_exists($livewire, 'getRecord')) {
                                    return;
                                }

                                $record = $livewire->getRecord();
                                if (! $record instanceof Article) {
                                    return;
                                }

                                $showOnHome = Schema::hasColumn('articles', 'show_on_home')
                                    ? (bool) ($record->show_on_home ?? true)
                                    : true;

                                $isBreaking = (bool) ($record->is_breaking ?? false);

                                $component->state(
                                    $showOnHome
                                        ? ($isBreaking ? 'both' : 'home')
                                        : 'breaking',
                                );
                            })
                            ->afterStateUpdated(function (?string $state, ?string $old, Set $set): void {
                                $state = $state ?: 'home';

                                if ($state === 'breaking') {
                                    $set('show_on_home', false);
                                    $set('is_breaking', true);
                                    return;
                                }

                                if ($state === 'both') {
                                    $set('show_on_home', true);
                                    $set('is_breaking', true);
                                    return;
                                }

                                $set('show_on_home', true);
                                $set('is_breaking', false);
                            })
                            ->helperText('Choose where this article appears.'),

                        Hidden::make('show_on_home')
                            ->default(true)
                            ->visible($hasShowOnHome),

                        TextInput::make('title')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),

                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name_ar')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('feed_source_id')
                            ->label('Feed source')
                            ->relationship('feedSource', 'name')
                            ->searchable()
                            ->preload(),

                        Toggle::make('is_breaking')
                            ->label('Breaking')
                            ->default(false)
                            ->hidden($hasShowOnHome),

                        Select::make('status')
                            ->options([
                                'published' => 'published',
                                'draft' => 'draft',
                            ])
                            ->default('published')
                            ->required(),

                        TextInput::make('language')
                            ->maxLength(5)
                            ->default('ar'),

                        DateTimePicker::make('published_at')
                            ->label('Published at')
                            ->required(),

                        DateTimePicker::make('imported_at')
                            ->label('Imported at')
                            ->disabled(),

                        Textarea::make('excerpt')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('content')
                            ->rows(10)
                            ->columnSpanFull(),

                        Textarea::make('canonical_url')
                            ->label('Canonical URL')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('image_url')
                            ->label('Image URL')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $hasShowOnHome = Schema::hasColumn('articles', 'show_on_home');

        return $table
            ->columns([
                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable(),

                IconColumn::make('show_on_home')
                    ->label('Home')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible($hasShowOnHome),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('category.name_ar')
                    ->label('Category')
                    ->sortable(),

                TextColumn::make('feedSource.name')
                    ->label('Feed')
                    ->toggleable(),

                IconColumn::make('is_breaking')
                    ->label('Breaking')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('language')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_breaking')
                    ->label('Breaking'),
                TernaryFilter::make('show_on_home')
                    ->label('Home')
                    ->visible($hasShowOnHome),
                SelectFilter::make('status')
                    ->options([
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
        return [
        ];
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
