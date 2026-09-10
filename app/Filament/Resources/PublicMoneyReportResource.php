<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PublicMoneyReportResource\Pages;
use App\Models\PublicMoneyReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PublicMoneyReportResource extends Resource
{
    protected static ?string $model = PublicMoneyReport::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Public Money';
    protected static ?string $navigationLabel = 'Analysis reports';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title_ar')->required()->columnSpanFull(),
            Forms\Components\TextInput::make('title_en')->columnSpanFull(),
            Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true)->columnSpanFull(),
            Forms\Components\RichEditor::make('body_ar')->required()->columnSpanFull(),
            Forms\Components\RichEditor::make('body_en')->columnSpanFull(),
            Forms\Components\TagsInput::make('evidence_links')->placeholder('https://official-source.example/document')->columnSpanFull(),
            Forms\Components\Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])->required()->default('draft'),
            Forms\Components\DateTimePicker::make('published_at'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title_ar')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPublicMoneyReports::route('/'), 'create' => Pages\CreatePublicMoneyReport::route('/create'), 'edit' => Pages\EditPublicMoneyReport::route('/{record}/edit')];
    }
}
