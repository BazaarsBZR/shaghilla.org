<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class HeaderBuilder extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static string $view = 'filament.pages.header-builder';

    protected static ?string $navigationGroup = 'Pages';

    protected static ?string $navigationLabel = 'Header';

    public ?array $data = [];

    public function mount(): void
    {
        $decoded = $this->decodeLayout(SiteSetting::getValue('header_layout_json', ''));
        $defaults = self::defaultState();

        $this->form->fill(array_merge($defaults, $decoded, [
            'site_brand_name' => SiteSetting::getValue('site_brand_name', config('app.name')),
            'header_logo_enabled' => SiteSetting::getBool('header_logo_enabled', true),
            'header_logo_url' => SiteSetting::getValue('header_logo_url', ''),

            'header_show_live_button' => SiteSetting::getBool('header_show_live_button', true),
            'header_search_enabled' => SiteSetting::getBool('header_search_enabled', false),
            'header_language_chips_enabled' => SiteSetting::getBool('header_language_chips_enabled', false),
            'header_weather_enabled' => SiteSetting::getBool('header_weather_enabled', true),
            'header_weather_label_ar' => SiteSetting::getValue('header_weather_label_ar', 'لبنان'),
            'header_weather_lat' => SiteSetting::getValue('header_weather_lat', '33.8938'),
            'header_weather_lon' => SiteSetting::getValue('header_weather_lon', '35.5018'),
        ]));
    }

    public function form(Form $form): Form
    {
        $blocks = [
            Block::make('logo')->label('Logo / Brand'),
            Block::make('menu')->label('Menu (desktop)'),
            Block::make('live')->label('LIVE button'),
            Block::make('weather')->label('Weather widget'),
            Block::make('search')->label('Search'),
            Block::make('language_chips')->label('Language chips'),
            Block::make('hamburger')->label('Hamburger (mobile)'),
            Block::make('spacer')->label('Spacer'),
        ];

        $zoneBuilder = fn (string $name): Builder => Builder::make($name)
            ->label('')
            ->blocks($blocks)
            ->collapsible()
            ->blockNumbers(false)
            ->reorderableWithButtons()
            ->addActionLabel('Add item')
            ->columnSpanFull();

        return $form
            ->schema([
                Section::make('Visibility')
                    ->description('Turn items on/off globally (placement is controlled below).')
                    ->schema([
                        TextInput::make('site_brand_name')
                            ->label('Brand name')
                            ->maxLength(120)
                            ->required(),

                        Toggle::make('header_logo_enabled')
                            ->label('Show logo image')
                            ->default(true),

                        TextInput::make('header_logo_url')
                            ->label('Logo URL (optional)')
                            ->helperText('Leave empty to use the default website logo.')
                            ->maxLength(500)
                            ->columnSpanFull(),

                        Toggle::make('header_show_live_button')
                            ->label('Show LIVE button')
                            ->default(true),

                        Toggle::make('header_search_enabled')
                            ->label('Enable search block')
                            ->default(false),

                        Toggle::make('header_language_chips_enabled')
                            ->label('Enable language chips block')
                            ->default(false),

                        Toggle::make('header_weather_enabled')
                            ->label('Show weather widget')
                            ->helperText('Shows a small Lebanon weather widget in the header (powered by Open‑Meteo).')
                            ->default(true),

                        TextInput::make('header_weather_label_ar')
                            ->label('Weather label (AR)')
                            ->helperText('Example: لبنان')
                            ->maxLength(80),

                        TextInput::make('header_weather_lat')
                            ->label('Weather latitude')
                            ->helperText('Default: Beirut (33.8938)')
                            ->maxLength(40),

                        TextInput::make('header_weather_lon')
                            ->label('Weather longitude')
                            ->helperText('Default: Beirut (35.5018)')
                            ->maxLength(40),
                    ])
                    ->columns(3),

                Section::make('Top row (optional)')
                    ->description('Controls the thin utility row above the main navigation.')
                    ->schema([
                        Toggle::make('row1_enabled')
                            ->label('Enable top row')
                            ->default(false)
                            ->columnSpanFull(),

                        Section::make('Right')
                            ->schema([$zoneBuilder('row1_right')])
                            ->collapsed()
                            ->visible(fn ($get) => (bool) $get('row1_enabled')),

                        Section::make('Center')
                            ->schema([$zoneBuilder('row1_center')])
                            ->collapsed()
                            ->visible(fn ($get) => (bool) $get('row1_enabled')),

                        Section::make('Left')
                            ->schema([$zoneBuilder('row1_left')])
                            ->collapsed()
                            ->visible(fn ($get) => (bool) $get('row1_enabled')),
                    ]),

                Section::make('Main navigation row')
                    ->description('Logo should be on the right (RTL). LIVE button should typically be on the far left.')
                    ->schema([
                        Section::make('Right')
                            ->schema([$zoneBuilder('row2_right')]),

                        Section::make('Center')
                            ->schema([$zoneBuilder('row2_center')])
                            ->collapsed(),

                        Section::make('Left')
                            ->schema([$zoneBuilder('row2_left')]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->color('primary')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $state = (array) $this->form->getState();

        $layout = [
            'version' => 1,
            'row1_enabled' => (bool) ($state['row1_enabled'] ?? false),
            'row1' => [
                'right' => $state['row1_right'] ?? [],
                'center' => $state['row1_center'] ?? [],
                'left' => $state['row1_left'] ?? [],
            ],
            'row2' => [
                'right' => $state['row2_right'] ?? [],
                'center' => $state['row2_center'] ?? [],
                'left' => $state['row2_left'] ?? [],
            ],
        ];

        SiteSetting::setValues([
            'site_brand_name' => (string) ($state['site_brand_name'] ?? config('app.name')),
            'header_logo_enabled' => (bool) ($state['header_logo_enabled'] ?? true),
            'header_logo_url' => (string) ($state['header_logo_url'] ?? ''),

            'header_show_live_button' => (bool) ($state['header_show_live_button'] ?? true),
            'header_search_enabled' => (bool) ($state['header_search_enabled'] ?? false),
            'header_language_chips_enabled' => (bool) ($state['header_language_chips_enabled'] ?? false),
            'header_weather_enabled' => (bool) ($state['header_weather_enabled'] ?? false),
            'header_weather_label_ar' => (string) ($state['header_weather_label_ar'] ?? 'لبنان'),
            'header_weather_lat' => (string) ($state['header_weather_lat'] ?? '33.8938'),
            'header_weather_lon' => (string) ($state['header_weather_lon'] ?? '35.5018'),
            'header_layout_json' => json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeLayout(?string $json): array
    {
        $json = trim((string) $json);
        if ($json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $row1 = is_array($decoded['row1'] ?? null) ? $decoded['row1'] : [];
        $row2 = is_array($decoded['row2'] ?? null) ? $decoded['row2'] : [];

        return [
            'row1_enabled' => (bool) ($decoded['row1_enabled'] ?? false),
            'row1_right' => is_array($row1['right'] ?? null) ? $row1['right'] : [],
            'row1_center' => is_array($row1['center'] ?? null) ? $row1['center'] : [],
            'row1_left' => is_array($row1['left'] ?? null) ? $row1['left'] : [],
            'row2_right' => is_array($row2['right'] ?? null) ? $row2['right'] : [],
            'row2_center' => is_array($row2['center'] ?? null) ? $row2['center'] : [],
            'row2_left' => is_array($row2['left'] ?? null) ? $row2['left'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultState(): array
    {
        return [
            'row1_enabled' => false,
            'row1_right' => [],
            'row1_center' => [],
            'row1_left' => [],
            'row2_right' => [
                ['type' => 'logo', 'data' => []],
                ['type' => 'menu', 'data' => []],
            ],
            'row2_center' => [],
            'row2_left' => [
                ['type' => 'live', 'data' => []],
                ['type' => 'hamburger', 'data' => []],
            ],
        ];
    }
}
