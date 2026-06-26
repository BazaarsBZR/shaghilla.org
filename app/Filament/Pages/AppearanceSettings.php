<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SitePageResource;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AppearanceSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static string $view = 'filament.pages.appearance-settings';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Appearance';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'site_brand_name' => SiteSetting::getValue('site_brand_name', config('app.name')),

            'live_youtube_url' => SiteSetting::getValue('live_youtube_url', ''),
            'live_youtube_playlist_limit' => SiteSetting::getInt('live_youtube_playlist_limit', 12),

            'breaking_ticker_enabled' => SiteSetting::getBool('breaking_ticker_enabled', true),
            'breaking_ticker_engine' => SiteSetting::getValue('breaking_ticker_engine', 'js'),
            'breaking_ticker_direction' => SiteSetting::getValue('breaking_ticker_direction', 'left'),
            'breaking_ticker_speed_px_per_sec' => SiteSetting::getInt('breaking_ticker_speed_px_per_sec', 90),
            'breaking_ticker_gap_px' => SiteSetting::getInt('breaking_ticker_gap_px', 20),
            'breaking_ticker_speed_seconds' => SiteSetting::getInt('breaking_ticker_speed_seconds', 35),
            'breaking_ticker_start_offset_seconds' => SiteSetting::getInt('breaking_ticker_start_offset_seconds', 0),
            'breaking_ticker_pause_on_hover' => SiteSetting::getBool('breaking_ticker_pause_on_hover', true),
            'breaking_ticker_divider_logo_enabled' => SiteSetting::getBool('breaking_ticker_divider_logo_enabled', true),
            'breaking_ticker_divider_logo_url' => SiteSetting::getValue('breaking_ticker_divider_logo_url', ''),
            'breaking_ticker_poll_enabled' => SiteSetting::getBool('breaking_ticker_poll_enabled', false),
            'breaking_ticker_poll_interval_seconds' => SiteSetting::getInt('breaking_ticker_poll_interval_seconds', 45),

            'header_show_live_button' => SiteSetting::getBool('header_show_live_button', true),
            'header_logo_enabled' => SiteSetting::getBool('header_logo_enabled', true),
            'header_logo_url' => SiteSetting::getValue('header_logo_url', ''),

            'footer_show_socials' => SiteSetting::getBool('footer_show_socials', false),
            'footer_facebook_url' => SiteSetting::getValue('footer_facebook_url', ''),
            'footer_x_url' => SiteSetting::getValue('footer_x_url', ''),
            'footer_instagram_url' => SiteSetting::getValue('footer_instagram_url', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Brand')
                    ->description('Controls the public header/footer brand text.')
                    ->schema([
                        TextInput::make('site_brand_name')
                            ->label('Site name')
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Live page (YouTube)')
                    ->description('Controls the /live page YouTube player and playlist.')
                    ->schema([
                        TextInput::make('live_youtube_url')
                            ->label('YouTube live URL')
                            ->helperText('Paste a YouTube live URL (or any YouTube video URL).')
                            ->maxLength(2048)
                            ->columnSpanFull(),

                        TextInput::make('live_youtube_playlist_limit')
                            ->label('Playlist items limit (max 12)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12)
                            ->default(12)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Breaking ticker')
                    ->description('Controls the breaking news bar on the home page.')
                    ->schema([
                        Toggle::make('breaking_ticker_enabled')
                            ->label('Enable ticker')
                            ->default(true),

                        Select::make('breaking_ticker_engine')
                            ->label('Ticker engine')
                            ->options([
                                'js' => 'JS ticker (recommended)',
                                'legacy' => 'Legacy (no animation)',
                            ])
                            ->default('js')
                            ->helperText('JS ticker is smoother and more reliable on shared hosting. Legacy mode keeps a static list.')
                            ->live(),

                        Select::make('breaking_ticker_direction')
                            ->label('Direction')
                            ->options([
                                'left' => 'Right → Left (common)',
                                'right' => 'Left → Right',
                            ])
                            ->default('left')
                            ->visible(fn ($get) => (string) $get('breaking_ticker_engine') === 'js'),

                        TextInput::make('breaking_ticker_speed_px_per_sec')
                            ->label('Speed (px/second)')
                            ->numeric()
                            ->minValue(20)
                            ->maxValue(600)
                            ->default(90)
                            ->helperText('Higher = faster.')
                            ->visible(fn ($get) => (string) $get('breaking_ticker_engine') === 'js'),

                        TextInput::make('breaking_ticker_gap_px')
                            ->label('Gap (px)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(80)
                            ->default(20)
                            ->helperText('Space used when rotating items (prevents jitter).')
                            ->visible(fn ($get) => (string) $get('breaking_ticker_engine') === 'js'),

                        TextInput::make('breaking_ticker_speed_seconds')
                            ->label('Legacy: speed (seconds per loop)')
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(240)
                            ->default(35)
                            ->visible(fn ($get) => (string) $get('breaking_ticker_engine') === 'legacy'),

                        TextInput::make('breaking_ticker_start_offset_seconds')
                            ->label('Start offset (seconds)')
                            ->helperText('Helps the ticker start moving immediately. Use 0 to start from the beginning.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(240)
                            ->default(0)
                            ->required()
                            ->visible(fn ($get) => (string) $get('breaking_ticker_engine') === 'legacy'),

                        Toggle::make('breaking_ticker_pause_on_hover')
                            ->label('Pause on hover')
                            ->default(true),

                        Toggle::make('breaking_ticker_divider_logo_enabled')
                            ->label('Show logo divider between items')
                            ->default(true)
                            ->live(),

                        TextInput::make('breaking_ticker_divider_logo_url')
                            ->label('Divider logo URL (optional)')
                            ->helperText('Leave blank to use the default logo-fav.png in public/.')
                            ->maxLength(2048)
                            ->visible(fn ($get) => (bool) $get('breaking_ticker_divider_logo_enabled')),

                        Toggle::make('breaking_ticker_poll_enabled')
                            ->label('Auto refresh (AJAX poll)')
                            ->helperText('If enabled, the ticker refreshes breaking items periodically without reloading the page.')
                            ->default(false)
                            ->live(),

                        TextInput::make('breaking_ticker_poll_interval_seconds')
                            ->label('Refresh interval (seconds)')
                            ->numeric()
                            ->minValue(15)
                            ->maxValue(300)
                            ->default(45)
                            ->required()
                            ->visible(fn ($get) => (bool) $get('breaking_ticker_poll_enabled')),
                    ])
                    ->columns(2),

                Section::make('Header')
                    ->description('Controls the public header.')
                    ->schema([
                        Toggle::make('header_show_live_button')
                            ->label('Show Live button')
                            ->default(true),

                        Toggle::make('header_logo_enabled')
                            ->label('Show header logo')
                            ->default(true)
                            ->live(),

                        TextInput::make('header_logo_url')
                            ->label('Header logo URL (optional)')
                            ->helperText('Leave blank to use the default website-logo.png in public/.')
                            ->maxLength(2048)
                            ->visible(fn ($get) => (bool) $get('header_logo_enabled')),
                    ]),

                Section::make('Footer')
                    ->description('Controls the public footer.')
                    ->schema([
                        Toggle::make('footer_show_socials')
                            ->label('Show social links')
                            ->default(false)
                            ->live(),

                        TextInput::make('footer_facebook_url')
                            ->label('Facebook URL')
                            ->url()
                            ->maxLength(2048)
                            ->visible(fn ($get) => (bool) $get('footer_show_socials')),

                        TextInput::make('footer_x_url')
                            ->label('X (Twitter) URL')
                            ->url()
                            ->maxLength(2048)
                            ->visible(fn ($get) => (bool) $get('footer_show_socials')),

                        TextInput::make('footer_instagram_url')
                            ->label('Instagram URL')
                            ->url()
                            ->maxLength(2048)
                            ->visible(fn ($get) => (bool) $get('footer_show_socials')),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action('save')
                ->color('primary'),

            Action::make('editMenus')
                ->label('Edit header/footer menus')
                ->url(fn (): string => SitePageResource::getUrl('index'))
                ->openUrlInNewTab(),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::setValues($data);

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }
}
