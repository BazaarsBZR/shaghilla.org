@extends('layouts.government-tenders', ['pageTitle' => 'المناقصات الحكومية'])

@section('content')
@php
    $lastSuccess = $source?->last_success_at
        ? \Illuminate\Support\Carbon::parse($source->last_success_at)->timezone('Asia/Beirut')
        : null;
    $lastChecked = $source?->last_checked_at
        ? \Illuminate\Support\Carbon::parse($source->last_checked_at)->timezone('Asia/Beirut')
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
                @elseif($lastChecked && $tenders->total() > 0)
                    السجلات المستوردة متاحة، وآخر محاولة تحديث: <bdi>{{ $lastChecked->format('Y-m-d H:i') }}</bdi>
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
                    <a class="gt-page-control" href="{{ $tenders->previousPageUrl() }}" rel="prev" aria-label="الصفحة السابقة">
                        <span aria-hidden="true">→</span>
                        <span>السابق</span>
                    </a>
                @else
                    <span class="gt-page-control is-disabled" aria-disabled="true">
                        <span aria-hidden="true">→</span>
                        <span>السابق</span>
                    </span>
                @endif

                @php
                    $pageStart = max(1, $tenders->currentPage() - 2);
                    $pageEnd = min($tenders->lastPage(), $tenders->currentPage() + 2);
                @endphp
                <div class="gt-page-numbers" aria-label="اختيار الصفحة">
                    @if($pageStart > 1)
                        <a href="{{ $tenders->url(1) }}">1</a>
                        @if($pageStart > 2)<span aria-hidden="true">…</span>@endif
                    @endif
                    @for($page = $pageStart; $page <= $pageEnd; $page++)
                        <a href="{{ $tenders->url($page) }}"
                           class="{{ $page === $tenders->currentPage() ? 'is-current' : '' }}"
                           @if($page === $tenders->currentPage()) aria-current="page" @endif>
                            {{ $page }}
                        </a>
                    @endfor
                    @if($pageEnd < $tenders->lastPage())
                        @if($pageEnd < $tenders->lastPage() - 1)<span aria-hidden="true">…</span>@endif
                        <a href="{{ $tenders->url($tenders->lastPage()) }}">{{ $tenders->lastPage() }}</a>
                    @endif
                </div>

                <span class="gt-page-summary">صفحة {{ $tenders->currentPage() }} من {{ $tenders->lastPage() }}</span>

                @if($tenders->nextPageUrl())
                    <a class="gt-page-control" href="{{ $tenders->nextPageUrl() }}" rel="next" aria-label="الصفحة التالية">
                        <span>التالي</span>
                        <span aria-hidden="true">←</span>
                    </a>
                @else
                    <span class="gt-page-control is-disabled" aria-disabled="true">
                        <span>التالي</span>
                        <span aria-hidden="true">←</span>
                    </span>
                @endif
            </nav>
        @endif
    </div>
</main>

<style id="government-tenders-pro-overrides">
    :root {
        --gt-ink: #0a3039;
        --gt-deep: #062730;
        --gt-green: #07865b;
        --gt-green-bright: #20b779;
        --gt-mint: #edf8f3;
        --gt-paper: #f5f8f7;
        --gt-line: #d8e5e1;
        --gt-muted: #6b7f86;
        --gt-red: #d92834;
        --gt-amber: #c67a00;
    }

    .gt-hero {
        position: relative;
        isolation: isolate;
        min-height: auto;
        overflow: hidden;
        padding: clamp(58px, 7vw, 88px) 0 clamp(118px, 11vw, 154px);
        background:
            radial-gradient(circle at 15% 12%, rgba(32, 183, 121, .25), transparent 30%),
            radial-gradient(circle at 88% 80%, rgba(255, 255, 255, .1), transparent 27%),
            linear-gradient(126deg, var(--gt-deep), #0b4344 57%, #116b4f 120%);
    }

    .gt-hero::before {
        content: "";
        position: absolute;
        z-index: -1;
        inset: 0;
        opacity: .24;
        background-image: radial-gradient(rgba(255, 255, 255, .62) 1px, transparent 1px);
        background-size: 34px 34px;
        mask-image: linear-gradient(to left, #000, transparent 82%);
    }

    .gt-hero::after {
        content: "";
        position: absolute;
        z-index: -1;
        inline-size: min(42vw, 570px);
        aspect-ratio: 1;
        inset-inline-start: -6vw;
        inset-block-end: -72%;
        border: 78px solid rgba(255, 255, 255, .05);
        border-radius: 50%;
    }

    .gt-wrap {
        width: min(1240px, calc(100% - 40px));
        margin-inline: auto;
    }

    .gt-kicker {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        margin: 0 0 17px;
        padding: 8px 14px;
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 999px;
        background: rgba(255, 255, 255, .09);
        color: #c3f5dd;
        font-size: 14px;
        font-weight: 850;
        backdrop-filter: blur(10px);
    }

    .gt-kicker::before {
        content: "";
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #4be0a1;
        box-shadow: 0 0 0 5px rgba(75, 224, 161, .13);
    }

    .gt-hero h1 {
        max-width: 850px;
        margin: 0;
        color: #fff;
        font-size: clamp(48px, 6.2vw, 78px);
        font-weight: 950;
        line-height: .98;
        letter-spacing: -.045em;
        text-wrap: balance;
    }

    .gt-hero p:last-child {
        max-width: 920px;
        margin: 24px 0 0;
        color: rgba(238, 249, 246, .84);
        font-size: clamp(17px, 1.75vw, 21px);
        font-weight: 500;
        line-height: 1.85;
    }

    .gt-counters {
        position: relative;
        z-index: 2;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-top: -78px;
    }

    .gt-counter {
        display: flex;
        min-height: 142px;
        flex-direction: column;
        justify-content: center;
        padding: 25px 28px;
        border: 1px solid rgba(7, 134, 91, .16);
        border-radius: 23px;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 24px 50px rgba(5, 42, 49, .12);
        backdrop-filter: blur(14px);
    }

    .gt-counter span {
        color: var(--gt-muted);
        font-size: 16px;
        font-weight: 850;
    }

    .gt-counter strong {
        margin-top: 6px;
        color: var(--gt-green);
        font-size: clamp(38px, 4vw, 54px);
        font-weight: 950;
        line-height: 1;
        letter-spacing: -.04em;
    }

    .gt-main {
        margin-top: 0;
        padding: 28px 0 64px;
        background:
            radial-gradient(circle at 92% 8%, rgba(14, 143, 97, .07), transparent 22%),
            var(--gt-paper);
    }

    .gt-freshness {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin: 0 0 18px;
        padding: 13px 17px;
        border: 1px solid #bce2d2;
        border-radius: 15px;
        background: #edf9f3;
        color: #195f48;
        font-size: 14px;
        font-weight: 700;
        box-shadow: none;
    }

    .gt-freshness span::before {
        content: "";
        display: inline-block;
        width: 8px;
        height: 8px;
        margin-inline-end: 9px;
        border-radius: 50%;
        background: var(--gt-green-bright);
        box-shadow: 0 0 0 4px rgba(32, 183, 121, .13);
    }

    .gt-freshness.is-warning span::before {
        background: #e3a018;
        box-shadow: 0 0 0 4px rgba(227, 160, 24, .14);
    }

    .gt-freshness a {
        color: var(--gt-green);
        font-weight: 900;
        text-decoration: none;
    }

    .gt-filter-panel {
        padding: clamp(20px, 2.7vw, 32px);
        border: 1px solid var(--gt-line);
        border-radius: 25px;
        background: rgba(255, 255, 255, .98);
        box-shadow: 0 16px 42px rgba(7, 45, 52, .07);
    }

    .gt-search-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px;
        margin-bottom: 12px;
    }

    .gt-search-row input,
    .gt-filter-grid select,
    .gt-filter-grid input {
        min-height: 52px;
        border: 1px solid #ceded9;
        border-radius: 13px;
        background-color: #fbfdfc;
        color: var(--gt-ink);
        font-size: 15px;
        font-weight: 650;
        outline: none;
        transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .gt-search-row input {
        padding-inline: 18px;
        font-size: 17px;
    }

    .gt-search-row input:focus,
    .gt-filter-grid select:focus,
    .gt-filter-grid input:focus {
        border-color: var(--gt-green);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(7, 134, 91, .1);
    }

    .gt-button {
        display: inline-flex;
        min-height: 52px;
        align-items: center;
        justify-content: center;
        padding: 0 24px;
        border: 1px solid var(--gt-green);
        border-radius: 13px;
        background: var(--gt-green);
        color: #fff;
        font-size: 15px;
        font-weight: 900;
        text-decoration: none;
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .gt-button:hover {
        transform: translateY(-2px);
        background: #066e4b;
        box-shadow: 0 10px 22px rgba(7, 134, 91, .18);
    }

    .gt-button.is-outline {
        background: #fff;
        color: var(--gt-green);
    }

    .gt-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .gt-section-heading {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 18px;
        margin: 35px 0 18px;
        padding: 0 3px 17px;
        border-bottom: 1px solid var(--gt-line);
    }

    .gt-section-heading p {
        margin: 0 0 4px;
        color: var(--gt-green);
        font-size: 13px;
        font-weight: 900;
    }

    .gt-section-heading h2 {
        margin: 0;
        color: var(--gt-ink);
        font-size: clamp(28px, 3.4vw, 40px);
        font-weight: 950;
        letter-spacing: -.03em;
    }

    .gt-section-heading > p:last-child {
        margin: 0;
        padding: 7px 12px;
        border-radius: 999px;
        background: #e7f4ee;
        color: #1c6b50;
        font-size: 13px;
    }

    .gt-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .gt-card {
        position: relative;
        display: flex;
        min-width: 0;
        min-height: 330px;
        flex-direction: column;
        padding: 24px;
        overflow: hidden;
        border: 1px solid var(--gt-line);
        border-radius: 22px;
        background: #fff;
        box-shadow: 0 10px 34px rgba(7, 45, 52, .055);
        transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
    }

    .gt-card::before {
        content: "";
        position: absolute;
        inset: 0 0 auto;
        height: 4px;
        background: linear-gradient(90deg, var(--gt-green-bright), var(--gt-green));
    }

    .gt-card:hover {
        transform: translateY(-4px);
        border-color: #9acdb8;
        box-shadow: 0 20px 42px rgba(7, 55, 50, .11);
    }

    .gt-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .gt-status,
    .gt-reference {
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 850;
    }

    .gt-reference {
        overflow: hidden;
        max-width: 56%;
        background: #f1f5f4;
        color: var(--gt-muted);
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .gt-card h3 {
        margin: 20px 0 8px;
        font-size: clamp(20px, 2vw, 25px);
        font-weight: 950;
        line-height: 1.5;
    }

    .gt-card h3 a {
        color: var(--gt-ink);
        text-decoration: none;
    }

    .gt-card h3 a:hover {
        color: var(--gt-green);
    }

    .gt-entity {
        margin: 0 0 19px;
        color: var(--gt-muted);
        font-size: 14px;
        font-weight: 700;
    }

    .gt-card-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .gt-card-details > div {
        min-width: 0;
        padding: 12px 13px;
        border-radius: 12px;
        background: #f6f9f8;
    }

    .gt-card-details small {
        display: block;
        margin-bottom: 5px;
        color: #7a8b90;
        font-size: 11px;
        font-weight: 750;
    }

    .gt-card-details strong {
        display: block;
        overflow: hidden;
        color: #24434a;
        font-size: 13px;
        font-weight: 850;
        line-height: 1.5;
        text-overflow: ellipsis;
    }

    .gt-card-details .gt-deadline {
        border: 1px solid #f3d2d5;
        background: #fff3f4;
    }

    .gt-card-details .gt-deadline small,
    .gt-card-details .gt-deadline strong {
        color: #ac2731;
    }

    .gt-card-link {
        display: inline-flex;
        width: fit-content;
        align-items: center;
        margin-top: auto;
        padding-top: 18px;
        color: var(--gt-green);
        font-size: 14px;
        font-weight: 950;
        text-decoration: none;
    }

    .gt-pagination {
        display: grid;
        grid-template-columns: auto 1fr auto auto;
        align-items: center;
        gap: 12px;
        margin-top: 28px;
        padding: 15px;
        border: 1px solid var(--gt-line);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 12px 30px rgba(7, 45, 52, .06);
    }

    .gt-page-control {
        display: inline-flex;
        min-height: 44px;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 15px;
        border: 1px solid var(--gt-green);
        border-radius: 12px;
        background: var(--gt-green);
        color: #fff;
        font-size: 14px;
        font-weight: 900;
        text-decoration: none;
    }

    .gt-page-control span[aria-hidden="true"] {
        font-family: sans-serif;
        font-size: 20px;
        line-height: 1;
    }

    .gt-page-control.is-disabled {
        border-color: #e1e9e6;
        background: #f2f5f4;
        color: #a5b2ae;
        cursor: not-allowed;
    }

    .gt-page-numbers {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .gt-page-numbers a,
    .gt-page-numbers > span {
        display: inline-flex;
        width: 40px;
        height: 40px;
        align-items: center;
        justify-content: center;
        border: 1px solid #dce6e3;
        border-radius: 11px;
        background: #fff;
        color: #38555b;
        font-family: sans-serif;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
    }

    .gt-page-numbers a:hover,
    .gt-page-numbers a.is-current {
        border-color: var(--gt-green);
        background: var(--gt-mint);
        color: var(--gt-green);
    }

    .gt-page-numbers a.is-current {
        box-shadow: inset 0 0 0 1px var(--gt-green);
    }

    .gt-page-summary {
        color: var(--gt-muted);
        font-size: 13px;
        font-weight: 750;
        white-space: nowrap;
    }

    .gt-counter,
    .gt-freshness,
    .gt-filter-panel,
    .gt-card {
        animation: gt-rise .5s cubic-bezier(.22, 1, .36, 1) both;
    }

    .gt-counter:nth-child(2) { animation-delay: .05s; }
    .gt-counter:nth-child(3) { animation-delay: .1s; }
    .gt-freshness { animation-delay: .12s; }
    .gt-filter-panel { animation-delay: .16s; }

    @keyframes gt-rise {
        from { opacity: 0; transform: translateY(14px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 960px) {
        .gt-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .gt-grid {
            grid-template-columns: 1fr;
        }

        .gt-pagination {
            grid-template-columns: auto 1fr auto;
        }

        .gt-page-summary {
            display: none;
        }
    }

    @media (max-width: 640px) {
        .gt-wrap {
            width: min(100% - 24px, 1240px);
        }

        .gt-hero {
            padding: 44px 0 108px;
        }

        .gt-hero h1 {
            font-size: 42px;
        }

        .gt-hero p:last-child {
            margin-top: 18px;
            font-size: 16px;
            line-height: 1.7;
        }

        .gt-counters {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 7px;
            margin-top: -64px;
        }

        .gt-counter {
            min-height: 104px;
            padding: 14px 9px;
            border-radius: 16px;
        }

        .gt-counter span {
            min-height: 38px;
            font-size: 11px;
            line-height: 1.45;
        }

        .gt-counter strong {
            font-size: 32px;
        }

        .gt-main {
            padding-top: 20px;
        }

        .gt-freshness {
            align-items: flex-start;
            flex-direction: column;
            font-size: 12px;
        }

        .gt-filter-panel,
        .gt-card {
            padding: 16px;
            border-radius: 18px;
        }

        .gt-search-row {
            grid-template-columns: 1fr;
        }

        .gt-filter-grid {
            grid-template-columns: 1fr;
        }

        .gt-section-heading {
            align-items: flex-start;
        }

        .gt-card {
            min-height: 0;
        }

        .gt-card-details {
            grid-template-columns: 1fr;
        }

        .gt-pagination {
            grid-template-columns: 1fr 1fr;
            padding: 10px;
        }

        .gt-page-numbers {
            grid-column: 1 / -1;
            grid-row: 1;
            overflow-x: auto;
            padding-bottom: 3px;
        }

        .gt-page-control {
            grid-row: 2;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .gt-counter,
        .gt-freshness,
        .gt-filter-panel,
        .gt-card {
            animation: none;
        }
    }
</style>
@endsection
