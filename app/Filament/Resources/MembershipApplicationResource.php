<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MembershipApplicationResource\Pages;
use App\Models\MembershipApplication;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class MembershipApplicationResource extends Resource
{
    protected static ?string $model = MembershipApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Membership';

    protected static ?string $navigationLabel = 'Applications';

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable('membership_applications');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Review')
                    ->schema([
                        Select::make('status')
                            ->options([
                                MembershipApplication::STATUS_PENDING => 'pending',
                                MembershipApplication::STATUS_APPROVED => 'approved',
                                MembershipApplication::STATUS_REJECTED => 'rejected',
                            ])
                            ->required()
                            ->default(MembershipApplication::STATUS_PENDING),

                        Textarea::make('admin_notes')
                            ->label('Admin notes')
                            ->rows(6)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('full_name')
                    ->label('Full name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        MembershipApplication::STATUS_PENDING => 'pending',
                        MembershipApplication::STATUS_APPROVED => 'approved',
                        MembershipApplication::STATUS_REJECTED => 'rejected',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('viewIdDocument')
                    ->label('ID')
                    ->icon('heroicon-o-identification')
                    ->url(fn (MembershipApplication $record): string => route('admin.membership-applications.id-document', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (MembershipApplication $record): bool => ! empty($record->id_document_path)),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\TextEntry::make('full_name')->label('الاسم الثلاثي'),
                \Filament\Infolists\Components\TextEntry::make('mother_name')->label('اسم الأم'),
                \Filament\Infolists\Components\TextEntry::make('birth_date')->label('تاريخ الولادة')->date(),
                \Filament\Infolists\Components\TextEntry::make('registry_number')->label('رقم السجل'),
                \Filament\Infolists\Components\TextEntry::make('registration_place')->label('مكان القيد'),
                \Filament\Infolists\Components\TextEntry::make('phone')->label('رقم الهاتف'),
                \Filament\Infolists\Components\TextEntry::make('emergency_phone')->label('هاتف للطوارئ'),
                \Filament\Infolists\Components\TextEntry::make('email')->label('البريد الإلكتروني'),
                \Filament\Infolists\Components\TextEntry::make('address')->label('العنوان')->columnSpanFull(),
                \Filament\Infolists\Components\TextEntry::make('profession')->label('المهنة'),
                \Filament\Infolists\Components\TextEntry::make('marital_status')
                    ->label('الحالة الاجتماعية')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        MembershipApplication::MARITAL_MARRIED => 'متزوّج',
                        MembershipApplication::MARITAL_SINGLE => 'غير متزوّج',
                        default => '—',
                    }),
                \Filament\Infolists\Components\TextEntry::make('children_count')->label('عدد الأولاد'),
                \Filament\Infolists\Components\TextEntry::make('blood_type')->label('فئة الدم'),
                \Filament\Infolists\Components\TextEntry::make('volunteer_areas')
                    ->label('مجالات التطوّع')
                    ->formatStateUsing(function ($state, MembershipApplication $record): string {
                        if (! is_array($state) || $state === []) {
                            return '—';
                        }

                        $labels = [
                            'organizational' => 'تنظيمي',
                            'social' => 'اجتماعي',
                            'relief' => 'إغاثي',
                            'health' => 'صحي',
                            'media' => 'إعلامي',
                            'education' => 'تربوي / تعليمي',
                            'logistics' => 'لوجستي',
                            'administrative' => 'إداري',
                            'field' => 'ميداني',
                            'other' => 'أخرى',
                        ];

                        $parts = [];
                        foreach ($state as $value) {
                            if ($value === 'other') {
                                $parts[] = filled($record->volunteer_other)
                                    ? ('أخرى: '.$record->volunteer_other)
                                    : 'أخرى';
                                continue;
                            }

                            $parts[] = $labels[$value] ?? (string) $value;
                        }

                        return implode('، ', $parts);
                    }),
                \Filament\Infolists\Components\TextEntry::make('has_volunteer_experience')
                    ->label('خبرة تطوّعية سابقة؟')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        true => 'نعم',
                        false => 'لا',
                        default => '—',
                    }),
                \Filament\Infolists\Components\TextEntry::make('volunteer_experience_details')
                    ->label('تفاصيل الخبرة التطوّعية')
                    ->columnSpanFull(),
                \Filament\Infolists\Components\TextEntry::make('signature_name')->label('التوقيع (الاسم)'),
                \Filament\Infolists\Components\TextEntry::make('status')->badge(),
                \Filament\Infolists\Components\TextEntry::make('admin_notes')->label('Admin notes')->columnSpanFull(),
                \Filament\Infolists\Components\TextEntry::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
                \Filament\Infolists\Components\TextEntry::make('user_agent')->label('User agent')->toggleable(isToggledHiddenByDefault: true)->columnSpanFull(),
                \Filament\Infolists\Components\TextEntry::make('created_at')->label('Submitted')->dateTime(),
            ])
            ->columns(2);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembershipApplications::route('/'),
            'view' => Pages\ViewMembershipApplication::route('/{record}'),
            'edit' => Pages\EditMembershipApplication::route('/{record}/edit'),
        ];
    }
}
