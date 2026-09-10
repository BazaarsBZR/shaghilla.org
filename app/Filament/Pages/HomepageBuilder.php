<?php

namespace App\Filament\Pages;

use App\Filament\Resources\HostedVideoResource;
use App\Filament\Resources\VideoItemResource;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class HomepageBuilder extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.pages.homepage-builder';

    protected static ?string $navigationGroup = 'Pages';

    protected static ?string $navigationLabel = 'Home page';

    public ?array $data = [];

    public function mount(): void
    {
        $membershipButtonMode = (string) SiteSetting::getValue('home_membership_button_mode', 'membership');
        if (! in_array($membershipButtonMode, ['membership', 'custom'], true)) {
            $membershipButtonMode = 'membership';
        }

        $this->form->fill([
            'home_news_layout' => SiteSetting::getValue('home_news_layout', 'mosaic'),
            'home_news_top_small_count' => SiteSetting::getInt('home_news_top_small_count', 4),
            'home_news_latest_limit' => min(9, SiteSetting::getInt('home_news_latest_limit', 9)),

            'home_hosted_videos_enabled' => SiteSetting::getBool('home_hosted_videos_enabled', true),
            'home_hosted_videos_title_ar' => SiteSetting::getValue('home_hosted_videos_title_ar', 'الفيديو'),
            'home_hosted_videos_limit' => SiteSetting::getInt('home_hosted_videos_limit', 12),
            'home_hosted_videos_layout' => SiteSetting::getValue('home_hosted_videos_layout', 'grid'),
            'home_hosted_videos_include_youtube' => SiteSetting::getBool('home_hosted_videos_include_youtube', true),
            'home_hosted_videos_placeholders_enabled' => SiteSetting::getBool('home_hosted_videos_placeholders_enabled', true),
            'home_hosted_videos_placeholder_count' => SiteSetting::getInt('home_hosted_videos_placeholder_count', 8),
            'home_hosted_videos_placeholder_title_ar' => SiteSetting::getValue('home_hosted_videos_placeholder_title_ar', 'رابطة الشغيلة'),
            'home_hosted_videos_placeholder_youtube_url' => SiteSetting::getValue(
                'home_hosted_videos_placeholder_youtube_url',
                'https://www.youtube.com/watch?v=QyR01ZMIIqE&t=10690s',
            ),

            'home_membership_enabled' => SiteSetting::getBool('home_membership_enabled', true),
            'home_membership_style' => SiteSetting::getValue('home_membership_style', 'dark'),
            'home_membership_title_ar' => SiteSetting::getValue('home_membership_title_ar', 'انتساب'),
            'home_membership_body_ar' => SiteSetting::getValue(
                'home_membership_body_ar',
                SiteSetting::getValue('membership_page_intro_ar', __('ui.membership.intro_default')),
            ),
            'home_membership_button_label_ar' => SiteSetting::getValue('home_membership_button_label_ar', __('ui.nav.membership')),
            'home_membership_button_mode' => $membershipButtonMode,
            'home_membership_button_url' => SiteSetting::getValue('home_membership_button_url', ''),
            'home_membership_image_url' => SiteSetting::getValue('home_membership_image_url', ''),

            'home_contact_enabled' => SiteSetting::getBool('home_contact_enabled', true),
            'home_contact_style' => SiteSetting::getValue('home_contact_style', 'light'),
            'home_contact_title_ar' => SiteSetting::getValue('home_contact_title_ar', 'طلب الخدمة'),
            'home_contact_body_ar' => SiteSetting::getValue(
                'home_contact_body_ar',
                SiteSetting::getValue('contact_page_intro_ar', __('ui.contact.intro_default')),
            ),
            'home_contact_button_label_ar' => SiteSetting::getValue('home_contact_button_label_ar', __('ui.nav.contact')),
            'home_contact_button_mode' => SiteSetting::getValue('home_contact_button_mode', 'contact'),
            'home_contact_button_url' => SiteSetting::getValue('home_contact_button_url', ''),
            'home_contact_image_url' => SiteSetting::getValue('home_contact_image_url', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('News')
                    ->description('Controls the news layout on the home page.')
                    ->schema([
                        Select::make('home_news_layout')
                            ->label('Layout')
                            ->options([
                                'mosaic' => 'Mosaic (hero + 4 cards + grid)',
                                'classic' => 'Classic (hero + grid)',
                                'grid' => 'Grid only',
                            ])
                            ->required(),

                        TextInput::make('home_news_top_small_count')
                            ->label('Mosaic small cards')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(12)
                            ->default(4)
                            ->helperText('Used only for the Mosaic layout.'),

                        TextInput::make('home_news_latest_limit')
                            ->label('Latest articles limit')
                            ->numeric()
                            ->minValue(6)
                            ->maxValue(9)
                            ->default(9)
                            ->helperText('Kept intentionally compact for speed and readability.')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Membership hero')
                    ->description('Controls the membership call-to-action section on the home page.')
                    ->schema([
                        Toggle::make('home_membership_enabled')
                            ->label('Enable membership section')
                            ->default(true),

                        Select::make('home_membership_style')
                            ->label('Style preset')
                            ->options([
                                'light' => 'Light',
                                'dark' => 'Dark',
                                'red' => 'Red',
                            ])
                            ->default('dark')
                            ->required(),

                        TextInput::make('home_membership_title_ar')
                            ->label('Title (AR)')
                            ->maxLength(160)
                            ->default('انتساب')
                            ->columnSpanFull(),

                        Textarea::make('home_membership_body_ar')
                            ->label('Body (AR)')
                            ->rows(4)
                            ->columnSpanFull(),

                        TextInput::make('home_membership_button_label_ar')
                            ->label('Button label (AR)')
                            ->maxLength(80)
                            ->default(__('ui.nav.membership')),

                        Select::make('home_membership_button_mode')
                            ->label('Button link')
                            ->options([
                                'membership' => 'Membership page (/membership)',
                                'custom' => 'Custom URL',
                            ])
                            ->default('membership')
                            ->required()
                            ->live(),

                        TextInput::make('home_membership_button_url')
                            ->label('Custom button URL')
                            ->url()
                            ->maxLength(2048)
                            ->visible(fn ($get) => (string) $get('home_membership_button_mode') === 'custom')
                            ->columnSpanFull(),

                        TextInput::make('home_membership_image_url')
                            ->label('Image URL (optional)')
                            ->url()
                            ->maxLength(2048)
                            ->helperText('If set, the image will appear beside the text on large screens.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Contact hero')
                    ->description('Controls the service request call-to-action section on the home page.')
                    ->schema([
                        Toggle::make('home_contact_enabled')
                            ->label('Enable contact section')
                            ->default(true),

                        Select::make('home_contact_style')
                            ->label('Style preset')
                            ->options([
                                'light' => 'Light',
                                'dark' => 'Dark',
                                'red' => 'Red',
                            ])
                            ->default('light')
                            ->required(),

                        TextInput::make('home_contact_title_ar')
                            ->label('Title (AR)')
                            ->maxLength(160)
                            ->default('طلب الخدمة')
                            ->columnSpanFull(),

                        Textarea::make('home_contact_body_ar')
                            ->label('Body (AR)')
                            ->rows(4)
                            ->columnSpanFull(),

                        TextInput::make('home_contact_button_label_ar')
                            ->label('Button label (AR)')
                            ->maxLength(80)
                            ->default(__('ui.nav.contact')),

                        Select::make('home_contact_button_mode')
                            ->label('Button link')
                            ->options([
                                'contact' => 'Contact page (/contact)',
                                'custom' => 'Custom URL',
                            ])
                            ->default('contact')
                            ->required()
                            ->live(),

                        TextInput::make('home_contact_button_url')
                            ->label('Custom button URL')
                            ->url()
                            ->maxLength(2048)
                            ->visible(fn ($get) => (string) $get('home_contact_button_mode') === 'custom')
                            ->columnSpanFull(),

                        TextInput::make('home_contact_image_url')
                            ->label('Image URL (optional)')
                            ->url()
                            ->maxLength(2048)
                            ->helperText('If set, the image will appear beside the text on large screens.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Hosted videos (bottom)')
                    ->description('Shows uploaded (self-hosted) videos and optional YouTube links as a grid or carousel at the bottom of the home page.')
                    ->schema([
                        Toggle::make('home_hosted_videos_enabled')
                            ->label('Enable section')
                            ->default(true),

                        TextInput::make('home_hosted_videos_title_ar')
                            ->label('Section title (AR)')
                            ->maxLength(120)
                            ->default('الفيديو')
                            ->columnSpanFull(),

                        TextInput::make('home_hosted_videos_limit')
                            ->label('Items limit (max 12)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12)
                            ->default(12)
                            ->required(),

                        Select::make('home_hosted_videos_layout')
                            ->label('Layout')
                            ->options([
                                'grid' => 'Grid',
                                'carousel' => 'Carousel',
                            ])
                            ->default('grid')
                            ->required(),

                        Toggle::make('home_hosted_videos_include_youtube')
                            ->label('Include YouTube videos (from Video Items: type=home)')
                            ->default(true),

                        Toggle::make('home_hosted_videos_placeholders_enabled')
                            ->label('Show placeholders when empty')
                            ->default(true)
                            ->live(),

                        TextInput::make('home_hosted_videos_placeholder_title_ar')
                            ->label('Placeholder title (AR)')
                            ->maxLength(255)
                            ->default('رابطة الشغيلة')
                            ->visible(fn ($get) => (bool) $get('home_hosted_videos_placeholders_enabled')),

                        TextInput::make('home_hosted_videos_placeholder_youtube_url')
                            ->label('Placeholder YouTube URL')
                            ->maxLength(2048)
                            ->default('https://www.youtube.com/watch?v=QyR01ZMIIqE&t=10690s')
                            ->visible(fn ($get) => (bool) $get('home_hosted_videos_placeholders_enabled'))
                            ->columnSpanFull(),

                        TextInput::make('home_hosted_videos_placeholder_count')
                            ->label('Placeholder boxes (max 12)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(12)
                            ->default(8)
                            ->visible(fn ($get) => (bool) $get('home_hosted_videos_placeholders_enabled')),
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

            Action::make('manageHostedVideos')
                ->label('Manage hosted videos')
                ->url(fn (): string => HostedVideoResource::getUrl('index'))
                ->openUrlInNewTab(),

            Action::make('manageYoutubeVideos')
                ->label('Manage YouTube videos')
                ->url(fn (): string => VideoItemResource::getUrl('index'))
                ->openUrlInNewTab(),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $data['home_news_top_small_count'] = (string) max(0, min(12, (int) ($data['home_news_top_small_count'] ?? 4)));
        $data['home_news_latest_limit'] = (string) max(6, min(40, (int) ($data['home_news_latest_limit'] ?? 18)));
        $data['home_hosted_videos_limit'] = (string) max(1, min(12, (int) ($data['home_hosted_videos_limit'] ?? 12)));
        $data['home_hosted_videos_placeholder_count'] = (string) max(0, min(12, (int) ($data['home_hosted_videos_placeholder_count'] ?? 8)));

        $layout = (string) ($data['home_hosted_videos_layout'] ?? 'grid');
        $data['home_hosted_videos_layout'] = in_array($layout, ['grid', 'carousel'], true) ? $layout : 'grid';

        SiteSetting::setValues($data);

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }
}
