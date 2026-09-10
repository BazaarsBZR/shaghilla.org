<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MembershipApplicationResource;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

class MembershipSettings extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static string $view = 'filament.pages.membership-settings';

    protected static ?string $navigationGroup = 'Pages';

    protected static ?string $navigationLabel = 'Membership';

    public ?array $data = [];

    public function mount(): void
    {
        $defaultIntro = 'يرجى تعبئة نموذج الانتساب والتطوّع كاملًا ثم الضغط على «تحقّق».';
        $currentIntro = SiteSetting::getValue('membership_page_intro_ar');
        if (blank($currentIntro)) {
            $currentIntro = $defaultIntro;
        }

        $this->form->fill([
            'membership_page_title_ar' => SiteSetting::getValue('membership_page_title_ar', 'طلب انتساب إلى رابطة الشغيلــة'),
            'membership_page_intro_ar' => $currentIntro,
            'membership_page_letter_html_ar' => SiteSetting::getValue('membership_page_letter_html_ar', ''),
            'membership_form_success_ar' => SiteSetting::getValue('membership_form_success_ar', 'تم إرسال طلب الانتساب بنجاح.'),
            'membership_form_submit_label_ar' => SiteSetting::getValue('membership_form_submit_label_ar', 'إرسال الطلب'),
            'membership_form_id_label_ar' => SiteSetting::getValue('membership_form_id_label_ar', 'صورة الهوية / جواز السفر'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Membership page content (Arabic)')
                    ->description('These texts are shown on the public "انتساب" page.')
                    ->schema([
                        TextInput::make('membership_page_title_ar')
                            ->label('Page title (AR)')
                            ->maxLength(160)
                            ->columnSpanFull(),

                        Textarea::make('membership_page_intro_ar')
                            ->label('Intro text (AR)')
                            ->rows(5)
                            ->columnSpanFull(),

                        Textarea::make('membership_form_success_ar')
                            ->label('Form success message (AR)')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('membership_form_submit_label_ar')
                            ->label('Submit button (AR)')
                            ->maxLength(80),

                        TextInput::make('membership_form_id_label_ar')
                            ->label('ID upload label (AR)')
                            ->maxLength(160)
                            ->columnSpanFull(),
                    ]),

                Section::make('Membership letter (Arabic)')
                    ->description('Optional rich text shown above the membership form. Leave it empty to hide it.')
                    ->schema([
                        \Filament\Forms\Components\RichEditor::make('membership_page_letter_html_ar')
                            ->label('Letter content (AR)')
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

            Action::make('viewApplications')
                ->label('View membership forms')
                ->url(fn (): string => MembershipApplicationResource::getUrl('index'))
                ->openUrlInNewTab()
                ->visible(function (): bool {
                    try {
                        return Schema::hasTable('membership_applications');
                    } catch (\Throwable) {
                        return false;
                    }
                }),
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
