@extends('layouts.government-tenders', ['pageTitle' => 'المناقصات الحكومية'])

@section('content')
@php
    $lastSuccess = $source?->last_success_at
        ? \Illuminate\Support\Carbon::parse($source->last_success_at)->timezone('Asia/Beirut')
        : null;
    $statusOptions = [
        'open' => 'المناقصات المفتوحة',
        'closing' => 'تغلق قريباً',
        'expired' => 'انتهى موعد التقديم',
        'cancelled' => 'ملغاة',
        'awarded' => 'تم التلزيم',
        'all' => 'جميع المناقصات',
    ];
@endphp

<section class="gt-hero">
    <div class="gt-wrap">
        <p class="gt-kicker">فرص الشراء العام في لبنان</p>
        <h1>المناقصات الحكومية</h1>
        <p>اكتشف فرص التعاقد المنشورة رسمياً، راجع المهل والمستندات، ثم تابع إجراءات المشاركة عبر الموقع الرسمي لهيئة الشراء العام.</p>
    </div>
</section>

<div class="gt-wrap">
    <section class="gt-counters" aria-label="ملخص المناقصات">
        <article class="gt-counter"><span>مناقصات مفتوحة</span><strong>{{ number_format($counters['open']) }}</strong></article>
        <article class="gt-counter"><span>تغلق هذا الأسبوع</span><strong>{{ number_format($counters['closing_week']) }}</strong></article>
        <article class="gt-counter"><span>أضيفت حديثاً</span><strong>{{ number_format($counters['recent']) }}</strong></article>
    </section>
</div>

<main class="gt-main">
    <div class="gt-wrap">
        <div class="gt-freshness {{ $source?->last_error ? 'is-warning' : '' }}">
            <span>
                @if($lastSuccess)
                    آخر تحديث ناجح: <bdi>{{ $lastSuccess->format('Y-m-d H:i') }}</bdi>
                @else
                    لم يكتمل أول استيراد موثوق بعد.
                @endif
            </span>
            <a href="https://www.ppa.gov.lb/en/tenders" target="_blank" rel="noopener noreferrer">المصدر: هيئة الشراء العام</a>
        </div>

        <form class="gt-filter-panel" method="get" action="{{ route('government-tenders.index') }}">
            <div class="gt-search-row">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="ابحث في المناقصات" aria-label="ابحث في المناقصات">
                <button class="gt-button" type="submit">بحث</button>
            </div>
            <div class="gt-filter-grid">
                <select name="status" aria-label="حالة المناقصة">
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="authority" aria-label="الجهة الشارية">
                    <option value="">كل الجهات الشارية</option>
                    @foreach($filterOptions['authorities'] as $value)
                        <option value="{{ $value }}" @selected(request('authority') === $value)>{{ $value }}</option>
                    @endforeach
                </select>
                <select name="sector" aria-label="القطاع">
                    <option value="">كل القطاعات</option>
                    @foreach($filterOptions['sectors'] as $value)
                        <option value="{{ $value }}" @selected(request('sector') === $value)>{{ $value }}</option>
                    @endforeach
                </select>
                <select name="method" aria-label="طريقة الشراء">
                    <option value="">كل طرق الشراء</option>
                    @foreach($filterOptions['methods'] as $value)
                        <option value="{{ $value }}" @selected(request('method') === $value)>{{ $value }}</option>
                    @endforeach
                </select>
                <input type="date" name="closing_date" value="{{ request('closing_date') }}" aria-label="تاريخ الإقفال">
                <select name="currency" aria-label="العملة">
                    <option value="">كل العملات</option>
                    @foreach($filterOptions['currencies'] as $value)
                        <option value="{{ $value }}" @selected(request('currency') === $value)>{{ $value }}</option>
                    @endforeach
                </select>
                <select name="sort" aria-label="ترتيب النتائج">
                    <option value="closing" @selected(request('sort', 'closing') === 'closing')>الأقرب إقفالاً</option>
                    <option value="newest" @selected(request('sort') === 'newest')>الأحدث أولاً</option>
                </select>
                <a class="gt-button is-outline" href="{{ route('government-tenders.index') }}">إلغاء الفلاتر</a>
            </div>
        </form>

        <div class="gt-section-heading">
            <div>
                <p>النتائج الرسمية المستوردة</p>
                <h2>{{ $statusOptions[$status] ?? 'المناقصات المفتوحة' }}</h2>
            </div>
            <p>{{ number_format($tenders->total()) }} مناقصة</p>
        </div>

        <section class="gt-grid">
            @forelse($tenders as $tender)
                @php
                    $statusKey = $tender->tenderStatusKey();
                    $deadlineRaw = data_get($tender->evidence, 'detail_page.source_values.submission_deadline');
                    $announcementRaw = data_get($tender->evidence, 'detail_page.source_values.announcement_date')
                        ?: data_get($tender->evidence, 'list_page.announcement_date');
                @endphp
                <article class="gt-card">
                    <div class="gt-card-top">
                        <span class="gt-status {{ $statusKey }}">{{ $tender->tenderStatusLabel() }}</span>
                        <span class="gt-reference">{{ $tender->reference_number ?: 'مرجع غير مذكور' }}</span>
                    </div>
                    <h3>
                        <a href="{{ route('government-tenders.show', $tender->source_record_id) }}">
                            {{ $tender->title ?: 'غير مذكور في المصدر' }}
                        </a>
                    </h3>
                    <p class="gt-entity">{{ $tender->authority ?: 'غير مذكور في المصدر' }}</p>
                    <div class="gt-card-details">
                        <div class="gt-deadline">
                            <small>آخر موعد لتقديم العروض</small>
                            <strong>{{ $deadlineRaw ?: ($tender->submission_deadline_at?->timezone('Asia/Beirut')->format('Y-m-d H:i') ?: 'غير مذكور في المصدر') }}</strong>
                        </div>
                        <div>
                            <small>تاريخ الإعلان</small>
                            <strong>{{ $announcementRaw ?: ($tender->announcement_at?->timezone('Asia/Beirut')->format('Y-m-d') ?: 'غير مذكور في المصدر') }}</strong>
                        </div>
                        <div>
                            <small>طريقة الشراء</small>
                            <strong>{{ $tender->procurement_method ?: 'غير مذكور في المصدر' }}</strong>
                        </div>
                        <div>
                            <small>موعد فتح العروض</small>
                            <strong>{{ $tender->administrative_opening_at?->timezone('Asia/Beirut')->format('Y-m-d H:i') ?: 'غير مذكور في المصدر' }}</strong>
                        </div>
                    </div>
                    <a class="gt-card-link" href="{{ route('government-tenders.show', $tender->source_record_id) }}">عرض التفاصيل وكيفية المشاركة ←</a>
                </article>
            @empty
                <div class="gt-empty" style="grid-column: 1 / -1">
                    <h3>لا توجد مناقصات مطابقة حالياً</h3>
                    <p>جرّب تغيير الفلاتر. لا يعرض شغيلة سجلات تجريبية أو بديلة عند تعذّر المصدر.</p>
                </div>
            @endforelse
        </section>

        @if($tenders->hasPages())
            <nav class="gt-pagination" aria-label="صفحات المناقصات">
                @if($tenders->previousPageUrl())
                    <a href="{{ $tenders->previousPageUrl() }}">الصفحة السابقة</a>
                @endif
                <span>صفحة {{ $tenders->currentPage() }} من {{ $tenders->lastPage() }}</span>
                @if($tenders->nextPageUrl())
                    <a href="{{ $tenders->nextPageUrl() }}">الصفحة التالية</a>
                @endif
            </nav>
        @endif
    </div>
</main>
@endsection
