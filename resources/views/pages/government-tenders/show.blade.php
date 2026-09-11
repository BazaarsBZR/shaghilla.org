@extends('layouts.government-tenders', ['pageTitle' => $tender->title ?: 'تفاصيل المناقصة'])

@section('content')
@php
    $missing = 'غير مذكور في المصدر';
    $sourceValues = data_get($tender->evidence, 'detail_page.source_values', []);
    $sourceDate = fn (string $key, $date) => data_get($sourceValues, $key)
        ?: ($date?->timezone('Asia/Beirut')->format('Y-m-d H:i') ?: $missing);
    $estimate = $missing;
    if ($tender->estimated_value_confidential) {
        $estimate = 'القيمة التقديرية سرّية وفق المصدر الرسمي';
    } elseif (data_get($sourceValues, 'estimated_value_min')) {
        $estimate = data_get($sourceValues, 'estimated_value_min');
        if (data_get($sourceValues, 'estimated_value_max') && data_get($sourceValues, 'estimated_value_max') !== $estimate) {
            $estimate .= ' - '.data_get($sourceValues, 'estimated_value_max');
        }
        if ($tender->currency) {
            $estimate .= ' '.$tender->currency;
        }
    }
    $stageLabels = [
        'award' => 'نتيجة التقييم أو التلزيم',
        'contract' => 'العقد الموقّع',
        'implementation' => 'تنفيذ العقد',
    ];
    $whatsAppUrl = static function (?string $request = null) use ($tender, $missing): string {
        $message = "مرحباً شغيلة، أريد المساعدة بخصوص المناقصة التالية:\n";
        $message .= ($tender->title ?: $missing)."\n";
        $message .= 'المرجع: '.($tender->reference_number ?: $missing)."\n";
        if (filled($request)) {
            $message .= 'الطلب: '.$request."\n";
        }
        $message .= 'صفحة المناقصة على شغيلة: '.route('government-tenders.show', $tender);

        return 'https://wa.me/96179333415?text='.rawurlencode($message);
    };
@endphp

<section class="gt-hero gt-detail-hero">
    <div class="gt-wrap">
        <a class="gt-back" href="{{ route('government-tenders.index') }}">العودة إلى المناقصات</a>
        <div class="gt-card-top">
            <span class="gt-status {{ $tender->tenderStatusKey() }}">{{ $tender->tenderStatusLabel() }}</span>
            <span class="gt-reference">{{ $tender->reference_number ?: $missing }}</span>
        </div>
        <h1 class="gt-detail-title">{{ $tender->title ?: $missing }}</h1>
        <p>{{ $tender->authority ?: $missing }}</p>
    </div>
</section>

<main class="gt-main">
    <div class="gt-wrap">
        <section class="gt-detail-summary">
            <article class="gt-panel gt-deadline-panel">
                <small>{{ filled(data_get($tender->evidence, 'detail_page.source_values.submission_deadline')) ? 'آخر موعد لتقديم العروض' : 'موعد فتح العروض الرسمي' }}</small>
                <strong>{{ $sourceDate('submission_deadline', $tender->effectiveTenderDeadline()) }}</strong>
            </article>
            <article class="gt-panel">
                <h2>هل تريد المشاركة؟</h2>
                <p>راجع الشروط والمستندات الرسمية قبل التوجّه إلى مسار التقديم المحدد من الجهة الشارية.</p>
                <a class="gt-button" href="#participation">كيف أشارك؟</a>
            </article>
        </section>

        <section class="gt-panel" style="margin-top: 18px">
            <h2>بيانات المناقصة</h2>
            <div class="gt-fields">
                <div class="gt-field"><small>الجهة الشارية</small><strong>{{ $tender->authority ?: $missing }}</strong></div>
                <div class="gt-field"><small>رقم / مرجع الشراء</small><strong>{{ $tender->reference_number ?: $missing }}</strong></div>
                <div class="gt-field"><small>نوع الشراء</small><strong>{{ $tender->procurement_type ?: $missing }}</strong></div>
                <div class="gt-field"><small>القطاع</small><strong>{{ $tender->sector ?: $missing }}</strong></div>
                <div class="gt-field"><small>طريقة الشراء</small><strong>{{ $tender->procurement_method ?: $missing }}</strong></div>
                <div class="gt-field"><small>معايير التلزيم</small><strong>{{ $tender->award_criteria ?: $missing }}</strong></div>
                <div class="gt-field"><small>القيمة التقديرية</small><strong>{{ $estimate }}</strong></div>
                <div class="gt-field"><small>قيمة ضمان العرض</small><strong>{{ $tender->offer_guarantee_text ?: $missing }}</strong></div>
                <div class="gt-field"><small>العملة</small><strong>{{ $tender->currency ?: $missing }}</strong></div>
                <div class="gt-field"><small>تاريخ الإعلان</small><strong>{{ $sourceDate('announcement_date', $tender->announcement_at) }}</strong></div>
                <div class="gt-field"><small>آخر موعد للاستفسارات</small><strong>{{ $sourceDate('clarification_deadline', $tender->clarification_deadline_at) }}</strong></div>
                <div class="gt-field"><small>فتح العروض الإدارية والتقنية</small><strong>{{ $sourceDate('administrative_opening', $tender->administrative_opening_at) }}</strong></div>
                <div class="gt-field"><small>فتح العروض المالية</small><strong>{{ $sourceDate('financial_opening', $tender->financial_opening_at) }}</strong></div>
                <div class="gt-field"><small>اسم المسؤول</small><strong>{{ $tender->responsible_name ?: $missing }}</strong></div>
                <div class="gt-field"><small>هاتف المسؤول</small><strong dir="ltr">{{ $tender->responsible_phone ?: $missing }}</strong></div>
                <div class="gt-field"><small>البريد الإلكتروني</small><strong dir="ltr">{{ $tender->responsible_email ?: $missing }}</strong></div>
            </div>
        </section>

        <div class="gt-content-grid">
            <div class="gt-stack">
                <section class="gt-panel gt-participation" id="participation">
                    <h2>كيف أشارك؟</h2>
                    <dl class="gt-participation-list">
                        <div class="gt-participation-row"><dt>شروط الأهلية والمشاركة</dt><dd>{{ $tender->eligibility_requirements ?: $missing }}</dd></div>
                        <div class="gt-participation-row"><dt>المستندات المطلوبة</dt><dd>{{ $tender->required_documents ?: $missing }}</dd></div>
                        <div class="gt-participation-row"><dt>ضمان العرض</dt><dd>{{ $tender->offer_guarantee_text ?: $missing }}</dd></div>
                        <div class="gt-participation-row"><dt>طريقة أو مكان تقديم العرض</dt><dd>{{ $tender->submission_location ?: $missing }}</dd></div>
                        <div class="gt-participation-row"><dt>{{ filled(data_get($tender->evidence, 'detail_page.source_values.submission_deadline')) ? 'آخر موعد لتقديم العرض' : 'موعد فتح العروض الرسمي' }}</dt><dd>{{ $sourceDate('submission_deadline', $tender->effectiveTenderDeadline()) }}</dd></div>
                        <div class="gt-participation-row"><dt>آخر موعد للاستفسارات</dt><dd>{{ $sourceDate('clarification_deadline', $tender->clarification_deadline_at) }}</dd></div>
                    </dl>
                    <p class="gt-source-note">يعرض شغيلة فقط المتطلبات المنشورة في المصدر الرسمي. يمكن لفريقنا مساعدتك على فهم الخطوات، لكن تقديم العروض لا يتم عبر شغيلة.</p>
                    <a class="gt-button is-whatsapp" href="{{ $whatsAppUrl('معلومات عن المشاركة وطريقة التقديم') }}" target="_blank" rel="noopener noreferrer">
                        اطلب معلومات عبر واتساب
                    </a>
                </section>

                <section class="gt-panel">
                    <h2>مستندات المناقصة</h2>
                    @if($tender->tender_documents)
                        <ul class="gt-documents">
                            @foreach($tender->tender_documents as $document)
                                <li><a href="{{ $whatsAppUrl('مستند المناقصة: '.($document['name'] ?? $missing)) }}" target="_blank" rel="noopener noreferrer"><span>{{ $document['name'] }}</span><b>اطلبه عبر واتساب</b></a></li>
                            @endforeach
                        </ul>
                    @else
                        <p>{{ $missing }}</p>
                    @endif
                </section>
            </div>

            <aside class="gt-stack">
                <section class="gt-panel">
                    <h2>مسار الشراء</h2>
                    <div class="gt-stage-line">
                        <div class="gt-stage"><strong>المناقصة</strong><span>الإعلان الرسمي الحالي</span></div>
                        @foreach($tender->procurement_stages ?: [] as $stage)
                            <div class="gt-stage"><strong>{{ $stage['label'] }}</strong><a href="{{ $whatsAppUrl('سجل مرحلة الشراء: '.($stage['label'] ?? $missing)) }}" target="_blank" rel="noopener noreferrer">استفسر عبر واتساب</a></div>
                        @endforeach
                        @foreach($relatedStages as $stage)
                            <div class="gt-stage">
                                <strong>{{ $stageLabels[$stage->stage] ?? $stage->stage }}</strong>
                                <span>{{ $stage->supplier ?: $stage->title ?: 'سجل رسمي مرتبط' }}</span>
                                <a href="{{ $whatsAppUrl('المرحلة اللاحقة: '.($stageLabels[$stage->stage] ?? $stage->stage)) }}" target="_blank" rel="noopener noreferrer">استفسر عبر واتساب</a>
                            </div>
                        @endforeach
                        @if(empty($tender->procurement_stages) && $relatedStages->isEmpty())
                            <div class="gt-stage"><strong>المراحل اللاحقة</strong><span>لم ينشر المصدر سجلاً مرتبطاً بعد.</span></div>
                        @endif
                    </div>
                </section>

                <section class="gt-panel">
                    <h2>تتبّع المصدر</h2>
                    <p class="gt-source-note">رقم المصدر: <bdi>{{ $tender->source_record_id }}</bdi></p>
                    <p class="gt-source-note">آخر تحقق: <bdi>{{ $tender->last_verified_at?->timezone('Asia/Beirut')->format('Y-m-d H:i') ?: $missing }}</bdi></p>
                    <p class="gt-source-note">حالة المصدر: {{ $tender->source_status ?: $missing }}</p>
                    <a class="gt-button is-outline" href="{{ route('public-money.index') }}">عرض التفاصيل في المال العام</a>
                </section>
            </aside>
        </div>
    </div>
</main>
@endsection
