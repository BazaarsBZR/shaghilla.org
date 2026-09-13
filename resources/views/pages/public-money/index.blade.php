<x-layouts.site :title="__('ui.public_money.title').' | '.config('app.name')">
    @php
        $stageTotal = max(1, (int) $stageCounts->sum());
        $awardPct = round(((int) ($stageCounts['award'] ?? 0) / $stageTotal) * 100, 1);
        $contractPct = round(((int) ($stageCounts['contract'] ?? 0) / $stageTotal) * 100, 1);
        $implementationPct = max(0, 100 - $awardPct - $contractPct);
        $budgetMax = max(1, (float) ($budgetBreakdown->max('amount') ?? 1));
        $activityMax = max(1, (int) ($monthlyActivity->max() ?? 1));
        $budgetTotal = $financial->firstWhere('measure_type', 'allocation');
        $spentTotal = $financial->firstWhere('measure_type', 'reported_expenditure');
        $lbpPerUsd = 89500;
        $mapPlaces = $placeMentions->map(fn (array $place): array => collect($place)->only(['name', 'lat', 'lng', 'count'])->all())->values();
        $budgetLabels = [
            'Ministry of National Defense' => 'وزارة الدفاع الوطني',
            'Ministry of Interior and Municipalities' => 'وزارة الداخلية والبلديات',
            'Ministry of Education and Higher Education' => 'وزارة التربية والتعليم العالي',
            'Ministry of Public Health' => 'وزارة الصحة العامة',
            'Presidency of the Council of Ministers' => 'رئاسة مجلس الوزراء',
            'Ministry of Public Works and Transport' => 'وزارة الأشغال العامة والنقل',
            'Ministry of Telecommunications' => 'وزارة الاتصالات',
        ];
    @endphp

    <style>
        .pm-dashboard { background-image: radial-gradient(circle at 15% 22%, rgba(35, 157, 105, .07), transparent 24rem); }
        .pm-hero { min-height: 430px; }
        .pm-subnav a, .pm-subnav span { padding: 8px 12px; border-radius: 999px; }
        .pm-subnav a { transition: color 160ms ease, background 160ms ease; }
        .pm-subnav a:hover { color: #fff; background: rgba(255,255,255,.09); }
        .pm-subnav span { color: #071d2d; background: #55cc95; }
        .pm-hero-visual::before, .pm-hero-visual::after { position: absolute; border: 1px solid rgba(85,204,149,.2); border-radius: 50%; content: ''; }
        .pm-hero-visual::before { inset: -25px 20px 5px 30px; }
        .pm-hero-visual::after { inset: 20px 65px 45px 75px; }
        .pm-stat-card { position: relative; overflow: hidden; transition: transform 180ms ease, box-shadow 180ms ease; }
        .pm-stat-card::after { position: absolute; inset: auto -25px -35px auto; width: 92px; height: 92px; border: 16px solid rgba(31,154,104,.07); border-radius: 50%; content: ''; }
        .pm-stat-card:hover { z-index: 1; transform: translateY(-4px); box-shadow: 0 18px 40px rgba(16,39,53,.1); }
        .pm-dashboard article { transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease; }
        .pm-dashboard article:hover { border-color: rgba(31,154,104,.28); box-shadow: 0 18px 45px rgba(16,39,53,.075); }
        .pm-activity-chart { display: flex; align-items: stretch; gap: 10px; height: 245px; margin-top: 24px; padding: 8px 4px 0; border-bottom: 1px solid #dce4e7; }
        .pm-activity-item { display: flex; min-width: 0; flex: 1; flex-direction: column; align-items: center; justify-content: flex-end; }
        .pm-activity-month { order: 3; margin-top: 9px; color: #6e7f87; font-size: 10px; font-weight: 800; white-space: nowrap; }
        .pm-activity-track { order: 2; display: flex; width: min(42px, 72%); height: 178px; align-items: flex-end; overflow: hidden; border-radius: 11px 11px 4px 4px; background: #eef3f2; }
        .pm-activity-track i { display: block; width: 100%; height: var(--activity); min-height: 7px; border-radius: 10px 10px 3px 3px; background: linear-gradient(180deg, #3aba86, #135967); box-shadow: 0 8px 16px rgba(19,89,103,.17); }
        .pm-activity-count { order: 1; margin-bottom: 7px; padding: 3px 7px; border-radius: 999px; color: #173745; background: #e8f4ef; font-size: 10px; font-weight: 950; }
        .pm-authority-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 12px; }
        .pm-authority-row { display: grid !important; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 12px; min-height: 48px; padding: 9px 13px !important; border: 1px solid #e4eaec; border-radius: 13px !important; background: #f5f8f7 !important; }
        .pm-authority-name { min-width: 0; color: #465d66; font-size: 12px; font-weight: 750; line-height: 1.55; overflow-wrap: anywhere; }
        .pm-authority-count { min-width: 34px; padding: 5px 7px; border-radius: 9px; color: #fff !important; background: #155d65; text-align: center; }
        @media (max-width: 1023px) { .pm-hero { min-height: 0; } }
        @media (max-width: 640px) {
            .pm-dashboard { overflow-x: clip; }
            .pm-subnav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; border-bottom: 0; }
            .pm-subnav a, .pm-subnav span { display: flex; align-items: center; justify-content: center; min-height: 40px; padding: 7px 8px; text-align: center; }
            .pm-hero h1 { font-size: clamp(2.35rem, 13vw, 3.5rem); }
            .pm-hero p { font-size: .92rem; line-height: 1.85; }
            .pm-stat-grid { margin-inline: 12px; border: 1px solid #dbe3e7; border-radius: 20px; }
            .pm-stat-card { padding: 16px; }
            .pm-dashboard table { min-width: 720px; }
            .pm-activity-panel { padding: 18px 14px; }
            .pm-activity-chart { display: grid; height: auto; gap: 9px; margin-top: 18px; padding: 0; border-bottom: 0; }
            .pm-activity-item { display: grid; grid-template-columns: 68px minmax(0, 1fr) 34px; gap: 9px; align-items: center; }
            .pm-activity-month { order: initial; margin: 0; color: #425962; font-size: 10px; text-align: right; }
            .pm-activity-track { order: initial; width: 100%; height: 10px; border-radius: 999px; }
            .pm-activity-track i { width: var(--activity); height: 100%; min-width: 7px; border-radius: inherit; }
            .pm-activity-count { order: initial; margin: 0; padding: 2px 5px; text-align: center; }
            .pm-authority-grid { grid-template-columns: 1fr; }
            .pm-authority-row { min-height: 44px; }
        }
    </style>

    <div class="pm-dashboard -mx-4 -mt-5 overflow-hidden bg-[#edf2f5] pb-12 sm:mx-0 sm:mt-0 sm:rounded-[2.5rem]">
        <section class="pm-hero relative isolate overflow-hidden bg-[#071d2d] px-5 pb-10 pt-6 text-white sm:px-10 lg:px-14">
            <div class="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_72%_35%,rgba(38,106,128,.75),transparent_34%),linear-gradient(105deg,#071d2d_5%,#0d3044_55%,#102737_100%)]"></div>
            <div class="absolute inset-x-0 bottom-0 -z-10 h-44 opacity-30" style="background: linear-gradient(165deg, transparent 35%, #1c4b58 36% 48%, transparent 49%), linear-gradient(195deg, transparent 48%, #183d4a 49% 62%, transparent 63%)"></div>
            <img src="{{ asset('website-logo.png') }}" alt="" class="pointer-events-none absolute -left-5 bottom-5 -z-10 w-56 opacity-[.08] sm:w-80" />

            <nav class="pm-subnav flex flex-wrap items-center gap-x-2 gap-y-2 border-b border-white/10 pb-5 text-xs font-black text-white/65" aria-label="{{ __('ui.public_money.title') }}">
                <span class="text-[#49c38a]">نظرة عامة</span>
                <a href="{{ route('public-money.procurements') }}" class="transition hover:text-white">التلزيمات والعقود</a>
                <a href="{{ route('public-money.budget') }}" class="transition hover:text-white">الموازنة والإنفاق</a>
                <a href="{{ route('public-money.sources') }}" class="transition hover:text-white">المصادر والمنهجية</a>
            </nav>

            <div class="grid items-center gap-8 pb-4 pt-10 lg:grid-cols-[1.1fr_.9fr]">
                <div>
                    <p class="text-xs font-black tracking-[.18em] text-[#49c38a]">مرصد المال العام</p>
                    <h1 class="mt-3 text-4xl font-black leading-[1.08] sm:text-6xl">أين يذهب<br><span class="text-[#e6bd55]">مالنا العام؟</span></h1>
                    <p class="mt-5 max-w-xl text-base leading-8 text-white/70">تتبّع التلزيمات والعقود والموازنة والإنفاق من المصدر الرسمي إلى سجل واضح قابل للتدقيق.</p>
                    <div class="mt-7 flex flex-wrap gap-3">
                        <a href="{{ route('public-money.procurements') }}" class="sh-action sh-action--primary">تصفّح السجلات الرسمية</a>
                        <a href="{{ route('public-money.sources') }}" class="sh-action sh-action--glass">منهجية التحقق</a>
                    </div>
                </div>
                <div class="pm-hero-visual relative mx-auto hidden h-64 w-full max-w-md lg:block">
                    <div class="absolute inset-x-0 bottom-4 mx-auto h-44 w-72 rotate-[-7deg] rounded-[2rem] border border-white/15 bg-white/5 p-5 shadow-2xl backdrop-blur">
                        <div class="flex items-center justify-between"><span class="text-xs text-white/50">آخر تحديث رسمي</span><span class="h-2 w-2 rounded-full bg-[#49c38a] shadow-[0_0_15px_#49c38a]"></span></div>
                        <p class="mt-5 text-4xl font-black text-[#e6bd55]">{{ number_format($publishedCount) }}</p>
                        <p class="mt-1 text-sm font-bold">سجل منشور ومراجع</p>
                        <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-l from-[#49c38a] to-[#e6bd55]" style="width: {{ min(100, $publishedCount) }}%"></div></div>
                    </div>
                    <div class="absolute right-0 top-0 flex h-28 w-44 rotate-[8deg] items-center justify-center rounded-3xl border border-white/15 bg-[#9d302b]/90 shadow-2xl"><img src="{{ asset('website-logo.png') }}" alt="" class="h-20 w-20 object-contain brightness-0 invert" /></div>
                </div>
            </div>
        </section>

        <section class="pm-stat-grid relative z-10 -mt-5 grid gap-px overflow-hidden border-y border-[#dbe3e7] bg-[#dbe3e7] sm:mx-6 sm:grid-cols-2 sm:rounded-3xl sm:border lg:grid-cols-4">
            <article class="pm-stat-card bg-white p-5"><p class="text-xs font-bold text-[#708087]">اعتمادات موازنة 2026</p><p class="mt-2 text-2xl font-black text-[#102735]">{{ $budgetTotal ? number_format((float) $budgetTotal->amount) : '—' }}</p><p class="mt-1 text-xs text-[#8a979c]">{{ $budgetTotal ? 'مليار ليرة لبنانية' : 'بانتظار المصدر' }}</p>@if ($budgetTotal)<p class="mt-2 text-sm font-black text-[#18784e]">≈ ${{ number_format((float) $budgetTotal->amount / $lbpPerUsd, 2) }} مليار</p>@endif</article>
            <article class="pm-stat-card bg-white p-5"><p class="text-xs font-bold text-[#708087]">الإنفاق المبلّغ 2025</p><p class="mt-2 text-2xl font-black text-[#102735]">{{ $spentTotal ? number_format((float) $spentTotal->amount) : '—' }}</p><p class="mt-1 text-xs text-[#8a979c]">{{ $spentTotal ? 'مليار ليرة لبنانية' : 'بانتظار المصدر' }}</p>@if ($spentTotal)<p class="mt-2 text-sm font-black text-[#18784e]">≈ ${{ number_format((float) $spentTotal->amount / $lbpPerUsd, 2) }} مليار</p>@endif</article>
            <article class="pm-stat-card bg-white p-5"><p class="text-xs font-bold text-[#708087]">السجلات المنشورة</p><p class="mt-2 text-2xl font-black text-[#102735]">{{ number_format($publishedCount) }}</p><p class="mt-1 text-xs text-[#8a979c]">عبر مراحل الشراء الثلاث</p></article>
            <article class="pm-stat-card bg-white p-5"><p class="text-xs font-bold text-[#708087]">الجهات الشارية</p><p class="mt-2 text-2xl font-black text-[#102735]">{{ number_format($authorityCount) }}</p><p class="mt-1 text-xs text-[#8a979c]">جهة عامة مميّزة</p></article>
        </section>

        <section class="grid gap-5 px-4 pt-8 sm:px-6 lg:grid-cols-[1.1fr_.9fr]">
            <article class="rounded-[1.75rem] border border-[#dbe3e7] bg-white p-5 sm:p-7">
                <div class="flex items-end justify-between gap-4"><div><p class="text-xs font-black text-[#a2342c]">الموازنة</p><h2 class="mt-1 text-2xl font-black text-[#102735]">أكبر الاعتمادات حسب الإدارة</h2></div><a href="{{ route('public-money.budget') }}" class="text-xs font-black text-[#187352]">التفاصيل ←</a></div>
                @if ($budgetBreakdown->isNotEmpty())
                    <div class="mt-7 space-y-4">
                        @foreach ($budgetBreakdown as $item)
                            <div>
                                <div class="mb-1.5 flex items-end justify-between gap-4 text-xs"><span class="truncate font-bold text-[#344a55]">{{ $budgetLabels[$item->category] ?? $item->category }}</span><span class="shrink-0 text-left"><strong class="block font-black text-[#102735]" dir="rtl">{{ number_format((float) $item->amount) }} مليار ل.ل.</strong><small class="mt-0.5 block font-bold text-[#18784e]" dir="rtl">≈ {{ number_format((float) $item->amount / $lbpPerUsd, 2) }} مليار دولار</small></span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-[#edf2f3]"><div class="h-full rounded-full bg-gradient-to-l from-[#1f9a68] to-[#73c991]" style="width: {{ max(3, ((float) $item->amount / $budgetMax) * 100) }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-5 rounded-xl bg-[#f3f7f5] px-3 py-2 text-[11px] leading-5 text-[#60736b]">كل قيمة أصلية معروضة بمليار ليرة لبنانية، وتحتها تحويل تقريبي بمليار دولار على أساس 89,500 ليرة للدولار.</p>
                @else
                    <div class="mt-8 rounded-2xl border border-dashed border-[#cfdadd] p-8 text-center text-sm text-[#7e8b91]">ستظهر الأعمدة بعد اكتمال مزامنة وثيقة الموازنة ومراجعتها.</div>
                @endif
            </article>

            <article class="rounded-[1.75rem] border border-[#dbe3e7] bg-white p-5 sm:p-7">
                <p class="text-xs font-black text-[#a2342c]">دورة الشراء</p><h2 class="mt-1 text-2xl font-black text-[#102735]">توزيع السجلات حسب المرحلة</h2>
                <div class="mt-7 grid items-center gap-7 sm:grid-cols-[170px_1fr] lg:grid-cols-1 xl:grid-cols-[170px_1fr]">
                    <div class="relative mx-auto h-40 w-40 rounded-full" style="background: conic-gradient(#2aa66f 0 {{ $awardPct }}%, #e0b54c {{ $awardPct }}% {{ $awardPct + $contractPct }}%, #3d7890 {{ $awardPct + $contractPct }}% 100%)">
                        <div class="absolute inset-6 flex flex-col items-center justify-center rounded-full bg-white"><span class="text-3xl font-black text-[#102735]">{{ number_format($publishedCount) }}</span><span class="text-[11px] text-[#7f8d93]">سجل مراجع</span></div>
                    </div>
                    <div class="space-y-3">
                        @foreach ([['award', 'نتائج التلزيم', '#2aa66f', $awardPct], ['contract', 'العقود الموقعة', '#e0b54c', $contractPct], ['implementation', 'قيد التنفيذ', '#3d7890', $implementationPct]] as [$key, $label, $color, $pct])
                            <div class="flex items-center justify-between gap-3 rounded-xl bg-[#f5f7f8] px-3 py-2.5"><span class="flex items-center gap-2 text-xs font-bold text-[#42565f]"><i class="h-2.5 w-2.5 rounded-full" style="background: {{ $color }}"></i>{{ $label }}</span><strong class="text-sm text-[#102735]">{{ number_format((int) ($stageCounts[$key] ?? 0)) }} <small class="font-normal text-[#89959a]">({{ $pct }}%)</small></strong></div>
                        @endforeach
                    </div>
                </div>
            </article>
        </section>

        <section class="grid gap-5 px-4 pt-5 sm:px-6 lg:grid-cols-[.9fr_1.1fr]">
            <article class="overflow-hidden rounded-[1.75rem] border border-[#dbe3e7] bg-white">
                <div class="bg-[#0e2d3b] p-6 text-white"><p class="text-xs font-black text-[#55c592]">خريطة التغطية الفعلية</p><h2 class="mt-1 text-2xl font-black">الإشارات الجغرافية في السجلات</h2><p class="mt-2 max-w-xl text-xs leading-6 text-white/65">خريطة لبنان الحقيقية. العلامات تعني أن اسم المنطقة ورد في عنوان السجل أو الجهة، ولا تدّعي تحديد موقع المشروع.</p></div>
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
                <div id="public-money-map" class="h-[390px] w-full" aria-label="خريطة لبنان التفاعلية"></div>
                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                <script>
                    (() => {
                        const mapElement = document.getElementById('public-money-map');
                        if (!mapElement || typeof L === 'undefined') return;
                        const map = L.map(mapElement, { scrollWheelZoom: false }).setView([33.8547, 35.8623], 8);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
                        const places = @json($mapPlaces);
                        places.forEach((place) => L.circleMarker([place.lat, place.lng], { radius: Math.min(13, 6 + place.count), color: '#ffffff', weight: 2, fillColor: '#c99a28', fillOpacity: 0.95 }).bindPopup(`<strong>${place.name}</strong><br>${place.count} سجل`).addTo(map));
                    })();
                </script>
            </article>

            <article class="pm-activity-panel rounded-[1.75rem] border border-[#dbe3e7] bg-white p-5 sm:p-7">
                <p class="text-xs font-black text-[#a2342c]">النشاط الزمني</p><h2 class="mt-1 text-2xl font-black text-[#102735]">السجلات حسب شهر الحدث</h2><p class="mt-2 text-xs leading-6 text-[#728188]">عدد السجلات الرسمية التي تحمل تاريخاً في كل شهر.</p>
                @if ($monthlyActivity->isNotEmpty())
                    <div class="pm-activity-chart" role="img" aria-label="عدد السجلات الرسمية حسب الشهر">
                        @foreach ($monthlyActivity as $month => $count)
                            <div class="pm-activity-item">
                                <span class="pm-activity-month" dir="ltr">{{ $month }}</span>
                                <span class="pm-activity-track"><i style="--activity: {{ max(6, ($count / $activityMax) * 100) }}%"></i></span>
                                <strong class="pm-activity-count">{{ $count }}</strong>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-8 rounded-2xl border border-dashed border-[#cfdadd] p-8 text-center text-sm text-[#7e8b91]">يظهر الرسم بعد نشر سجلات تحمل تاريخاً رسمياً.</div>
                @endif
                <h3 class="mt-10 text-sm font-black text-[#102735]">أكثر الجهات وروداً</h3>
                <div class="pm-authority-grid">@forelse ($topAuthorities as $authority)<div class="pm-authority-row"><span class="pm-authority-name" dir="auto" title="{{ $authority->authority ?: 'الجهة غير مذكورة في المصدر' }}">{{ $authority->authority ?: 'الجهة غير مذكورة في المصدر' }}</span><strong class="pm-authority-count">{{ $authority->total }}</strong></div>@empty<p class="text-xs text-[#829096]">بانتظار البيانات المنشورة.</p>@endforelse</div>
            </article>
        </section>

        <section class="px-4 pt-5 sm:px-6">
            <article class="overflow-hidden rounded-[1.75rem] border border-[#dbe3e7] bg-white">
                <div class="flex flex-wrap items-end justify-between gap-4 border-b border-[#e4eaec] p-5 sm:p-7"><div><p class="text-xs font-black text-[#a2342c]">السجل العام</p><h2 class="mt-1 text-2xl font-black text-[#102735]">أحدث التلزيمات والعقود</h2></div><a href="{{ route('public-money.procurements') }}" class="rounded-full border border-[#b8c8ce] px-4 py-2 text-xs font-black text-[#214a59]">عرض السجل الكامل</a></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm"><thead class="bg-[#f4f7f8] text-[#708087]"><tr><th class="px-5 py-3 text-start">العملية</th><th class="px-5 py-3 text-start">الجهة</th><th class="px-5 py-3 text-start">المورّد / آخر موعد</th><th class="px-5 py-3 text-start">القيمة / المرجع</th><th class="px-5 py-3 text-start">الحالة</th></tr></thead><tbody class="divide-y divide-[#e8edef]">
                    @forelse ($procurements as $record)
                        @php($isTender = $record->stage === 'tender')
                        <tr class="hover:bg-[#f8fafb]"><td class="max-w-xs px-5 py-4"><a href="{{ route('public-money.procurement', $record) }}" class="font-black text-[#172f3a] hover:text-[#187352]">{{ $record->title }}</a><p class="mt-1 text-[11px] text-[#8a979c]">{{ optional($record->event_on)->format('Y-m-d') ?: 'دون تاريخ' }}</p></td><td class="px-5 py-4 text-xs text-[#53676f]">{{ $record->authority }}</td><td class="px-5 py-4 text-xs font-bold text-[#53676f]">@if($isTender)<span class="block text-[10px] text-[#849399]">آخر موعد للتقديم</span><span dir="ltr">{{ $record->effectiveTenderDeadline()?->timezone('Asia/Beirut')->format('Y-m-d H:i') ?: 'غير محدد' }}</span>@else{{ $record->supplierDisplayLabel() }}@endif</td><td class="px-5 py-4 font-black text-[#172f3a]" dir="auto">{{ $isTender ? ($record->reference_number ?: $record->source_record_id ?: 'غير مذكور') : $record->amountDisplayLabel() }}</td><td class="px-5 py-4"><span class="whitespace-nowrap rounded-full bg-[#e7f3ec] px-3 py-1 text-[11px] font-black text-[#21704f]">{{ $isTender ? $record->tenderStatusLabel() : $record->publicMoneyStageLabel() }}</span></td></tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-[#7e8b91]">لا توجد سجلات مراجعة ومنشورة بعد. تظهر الواردات الجديدة في لوحة الإدارة أولاً.</td></tr>
                    @endforelse
                    </tbody></table>
                </div>
            </article>
        </section>

        <section class="mx-4 mt-5 flex flex-col gap-4 rounded-[1.75rem] bg-[#e8efe9] p-5 sm:mx-6 sm:flex-row sm:items-center sm:justify-between sm:p-7">
            <div><p class="text-xs font-black text-[#187352]">تحديث مستمر</p><h2 class="mt-1 text-xl font-black text-[#102735]">خمسة مصادر رسمية، خمس عمليات مستقلة كل يوم</h2><p class="mt-2 text-xs leading-6 text-[#65756e]">آخر نجاح مسجّل: {{ $latestSourceUpdate ? date('Y-m-d H:i', strtotime($latestSourceUpdate)) : 'بانتظار اكتمال الدورة' }}. أي تغيير يعود إلى المراجعة قبل النشر.</p></div>
            <a href="{{ route('public-money.sources') }}" class="shrink-0 rounded-full bg-[#102f2c] px-5 py-3 text-center text-sm font-black text-white">المصادر وسجل التحديث</a>
        </section>
    </div>
</x-layouts.site>
