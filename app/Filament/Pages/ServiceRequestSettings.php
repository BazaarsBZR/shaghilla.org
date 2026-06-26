<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ContactMessageResource;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ServiceRequestSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static string $view = 'filament.pages.service-request-settings';

    protected static ?string $navigationGroup = 'Pages';

    protected static ?string $navigationLabel = 'Service Requests';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'contact_page_title_ar' => SiteSetting::getValue(
                'contact_page_title_ar',
                __('ui.pages.contact'),
            ),
            'contact_page_intro_ar' => SiteSetting::getValue(
                'contact_page_intro_ar',
                'اكتب لنا تفاصيل طلب الخدمة وسنقوم بالمتابعة بأقرب وقت.',
            ),
            'contact_page_success_ar' => SiteSetting::getValue(
                'contact_page_success_ar',
                'تم إرسال رسالتك بنجاح.',
            ),
            'contact_form_name_label_ar' => SiteSetting::getValue('contact_form_name_label_ar', __('ui.contact.name')),
            'contact_form_email_label_ar' => SiteSetting::getValue('contact_form_email_label_ar', 'رقم الهاتف'),
            'contact_form_subject_label_ar' => SiteSetting::getValue('contact_form_subject_label_ar', __('ui.contact.subject')),
            'contact_form_message_label_ar' => SiteSetting::getValue('contact_form_message_label_ar', __('ui.contact.message')),
            'contact_form_submit_label_ar' => SiteSetting::getValue('contact_form_submit_label_ar', __('ui.contact.send')),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Contact page content (Arabic)')
                    ->description('These texts are shown on the public "طلب الخدمة" page.')
                    ->schema([
                        Textarea::make('contact_page_title_ar')
                            ->label('Page title (AR)')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('contact_page_intro_ar')
                            ->label('Intro text (AR)')
                            ->rows(4)
                            ->columnSpanFull(),

                        Textarea::make('contact_page_success_ar')
                            ->label('Success message (AR)')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Contact form labels (Arabic)')
                    ->description('Controls the labels and button text on the contact form.')
                    ->schema([
                        Textarea::make('contact_form_name_label_ar')
                            ->label('Name label (AR)')
                            ->rows(2),

                        Textarea::make('contact_form_email_label_ar')
                            ->label('Phone label (AR)')
                            ->rows(2),

                        Textarea::make('contact_form_subject_label_ar')
                            ->label('Subject label (AR)')
                            ->rows(2),

                        Textarea::make('contact_form_message_label_ar')
                            ->label('Message label (AR)')
                            ->rows(2),

                        Textarea::make('contact_form_submit_label_ar')
                            ->label('Submit button (AR)')
                            ->rows(2)
                            ->columnSpanFull(),
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

            Action::make('viewMessages')
                ->label('View messages')
                ->url(fn (): string => ContactMessageResource::getUrl('index'))
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
