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

    <div class="-mx-4 -mt-5 overflow-hidden bg-[#edf2f5] pb-12 sm:mx-0 sm:mt-0 sm:rounded-[2.5rem]">
        <section class="relative isolate overflow-hidden bg-[#071d2d] px-5 pb-10 pt-6 text-white sm:px-10 lg:px-14">
            <div class="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_72%_35%,rgba(38,106,128,.75),transparent_34%),linear-gradient(105deg,#071d2d_5%,#0d3044_55%,#102737_100%)]"></div>
            <div class="absolute inset-x-0 bottom-0 -z-10 h-44 opacity-30" style="background: linear-gradient(165deg, transparent 35%, #1c4b58 36% 48%, transparent 49%), linear-gradient(195deg, transparent 48%, #183d4a 49% 62%, transparent 63%)"></div>
            <img src="{{ asset('website-logo.png') }}" alt="" class="pointer-events-none absolute -left-5 bottom-5 -z-10 w-56 opacity-[.08] sm:w-80" />

            <nav class="flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-white/10 pb-5 text-xs font-black text-white/65" aria-label="{{ __('ui.public_money.title') }}">
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
                        <a href="{{ route('public-money.procurements') }}" class="rounded-full bg-[#25a869] px-5 py-3 text-sm font-black text-white shadow-lg shadow-black/15">استكشف السجلات</a>
                        <a href="{{ route('public-money.sources') }}" class="rounded-full border border-white/25 bg-white/5 px-5 py-3 text-sm font-black text-white">كيف نتحقق؟</a>
                    </div>
                </div>
                <div class="relative mx-auto hidden h-64 w-full max-w-md lg:block">
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

        <section class="relative z-10 -mt-5 grid gap-px overflow-hidden border-y border-[#dbe3e7] bg-[#dbe3e7] sm:mx-6 sm:grid-cols-2 sm:rounded-3xl sm:border lg:grid-cols-4">
            <article class="bg-white p-5"><p class="text-xs font-bold text-[#708087]">اعتمادات موازنة 2026</p><p class="mt-2 text-2xl font-black text-[#102735]">{{ $budgetTotal ? number_format((float) $budgetTotal->amount) : '—' }}</p><p class="mt-1 text-xs text-[#8a979c]">{{ $budgetTotal ? 'مليار ليرة لبنانية' : 'بانتظار المصدر' }}</p>@if ($budgetTotal)<p class="mt-2 text-sm font-black text-[#18784e]">≈ ${{ number_format((float) $budgetTotal->amount / $lbpPerUsd, 2) }} مليار</p>@endif</article>
            <article class="bg-white p-5"><p class="text-xs font-bold text-[#708087]">الإنفاق المبلّغ 2025</p><p class="mt-2 text-2xl font-black text-[#102735]">{{ $spentTotal ? number_format((float) $spentTotal->amount) : '—' }}</p><p class="mt-1 text-xs text-[#8a979c]">{{ $spentTotal ? 'مليار ليرة لبنانية' : 'بانتظار المصدر' }}</p>@if ($spentTotal)<p class="mt-2 text-sm font-black text-[#18784e]">≈ ${{ number_format((float) $spentTotal->amount / $lbpPerUsd, 2) }} مليار</p>@endif</article>
            <article class="bg-white p-5"><p class="text-xs font-bold text-[#708087]">السجلات المنشورة</p><p class="mt-2 text-2xl font-black text-[#102735]">{{ number_format($publishedCount) }}</p><p class="mt-1 text-xs text-[#8a979c]">عبر مراحل الشراء الثلاث</p></article>
            <article class="bg-white p-5"><p class="text-xs font-bold text-[#708087]">الجهات الشارية</p><p class="mt-2 text-2xl font-black text-[#102735]">{{ number_format($authorityCount) }}</p><p class="mt-1 text-xs text-[#8a979c]">جهة عامة مميّزة</p></article>
        </section>

        <section class="grid gap-5 px-4 pt-8 sm:px-6 lg:grid-cols-[1.1fr_.9fr]">
            <article class="rounded-[1.75rem] border border-[#dbe3e7] bg-white p-5 sm:p-7">
                <div class="flex items-end justify-between gap-4"><div><p class="text-xs font-black text-[#a2342c]">الموازنة</p><h2 class="mt-1 text-2xl font-black text-[#102735]">أكبر الاعتمادات حسب الإدارة</h2></div><a href="{{ route('public-money.budget') }}" class="text-xs font-black text-[#187352]">التفاصيل ←</a></div>
                @if ($budgetBreakdown->isNotEmpty())
                    <div class="mt-7 space-y-4">
                        @foreach ($budgetBreakdown as $item)
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-4 text-xs"><span class="truncate font-bold text-[#344a55]">{{ $budgetLabels[$item->category] ?? $item->category }}</span><span class="shrink-0 font-black text-[#102735]">{{ number_format((float) $item->amount) }}</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-[#edf2f3]"><div class="h-full rounded-full bg-gradient-to-l from-[#1f9a68] to-[#73c991]" style="width: {{ max(3, ((float) $item->amount / $budgetMax) * 100) }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-5 text-[11px] text-[#829096]">القيم بوحدة «مليار ليرة لبنانية» كما وردت في الوثيقة الرسمية.</p>
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

            <article class="rounded-[1.75rem] border border-[#dbe3e7] bg-white p-5 sm:p-7">
                <p class="text-xs font-black text-[#a2342c]">النشاط الزمني</p><h2 class="mt-1 text-2xl font-black text-[#102735]">السجلات حسب شهر الحدث</h2>
                @if ($monthlyActivity->isNotEmpty())
                    <div class="mt-8 flex h-52 items-end gap-2 border-b border-[#dce4e7]">
                        @foreach ($monthlyActivity as $month => $count)
                            <div class="group flex h-full min-w-0 flex-1 flex-col justify-end text-center">
                                <span class="mb-2 text-[10px] font-black text-[#4e646d] opacity-0 transition group-hover:opacity-100">{{ $count }}</span>
                                <div class="mx-auto w-full max-w-10 rounded-t-lg bg-gradient-to-t from-[#155d65] to-[#35a77a]" style="height: {{ max(6, ($count / $activityMax) * 100) }}%"></div>
                                <span class="mt-2 -rotate-45 whitespace-nowrap text-[9px] text-[#829096]">{{ $month }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-8 rounded-2xl border border-dashed border-[#cfdadd] p-8 text-center text-sm text-[#7e8b91]">يظهر الرسم بعد نشر سجلات تحمل تاريخاً رسمياً.</div>
                @endif
                <h3 class="mt-10 text-sm font-black text-[#102735]">أكثر الجهات وروداً</h3>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">@forelse ($topAuthorities as $authority)<div class="flex items-center justify-between rounded-xl bg-[#f3f6f7] px-3 py-2 text-xs"><span class="truncate text-[#53676f]">{{ $authority->authority }}</span><strong class="mr-2 text-[#102735]">{{ $authority->total }}</strong></div>@empty<p class="text-xs text-[#829096]">بانتظار البيانات المنشورة.</p>@endforelse</div>
            </article>
        </section>

        <section class="px-4 pt-5 sm:px-6">
            <article class="overflow-hidden rounded-[1.75rem] border border-[#dbe3e7] bg-white">
                <div class="flex flex-wrap items-end justify-between gap-4 border-b border-[#e4eaec] p-5 sm:p-7"><div><p class="text-xs font-black text-[#a2342c]">السجل العام</p><h2 class="mt-1 text-2xl font-black text-[#102735]">أحدث التلزيمات والعقود</h2></div><a href="{{ route('public-money.procurements') }}" class="rounded-full border border-[#b8c8ce] px-4 py-2 text-xs font-black text-[#214a59]">عرض السجل الكامل</a></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm"><thead class="bg-[#f4f7f8] text-[#708087]"><tr><th class="px-5 py-3 text-start">العملية</th><th class="px-5 py-3 text-start">الجهة</th><th class="px-5 py-3 text-start">المورّد</th><th class="px-5 py-3 text-start">القيمة</th><th class="px-5 py-3 text-start">المرحلة</th></tr></thead><tbody class="divide-y divide-[#e8edef]">
                    @forelse ($procurements as $record)
                        <tr class="hover:bg-[#f8fafb]"><td class="max-w-xs px-5 py-4"><a href="{{ route('public-money.procurement', $record) }}" class="font-black text-[#172f3a] hover:text-[#187352]">{{ $record->title }}</a><p class="mt-1 text-[11px] text-[#8a979c]">{{ optional($record->event_on)->format('Y-m-d') ?: 'دون تاريخ' }}</p></td><td class="px-5 py-4 text-xs text-[#53676f]">{{ $record->authority }}</td><td class="px-5 py-4 text-xs text-[#53676f]">{{ $record->supplier ?: 'غير مذكور' }}</td><td class="whitespace-nowrap px-5 py-4 font-black text-[#172f3a]">{{ $record->amount !== null ? number_format((float) $record->amount, 2).' '.$record->currency : 'غير مذكور' }}</td><td class="px-5 py-4"><span class="rounded-full bg-[#e7f3ec] px-3 py-1 text-[11px] font-black text-[#21704f]">{{ __('ui.public_money.stage_'.$record->stage) }}</span></td></tr>
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
