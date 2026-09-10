<x-layouts.site :title="__('ui.public_money.sources_method').' | '.config('app.name')">
    @php
        $latestSuccess = $sources->max('last_success_at');
        $healthy = $sources->filter(fn ($source) => $source->last_success_at && (! $source->last_error_at || $source->last_success_at->gte($source->last_error_at)))->count();
        $sourceMeta = [
            'ppa_awards' => ['label' => 'نتائج التلزيم', 'description' => 'الجهة الشارية، موضوع التلزيم، الفائز، القيمة، تاريخ النتيجة وفترة التجميد.', 'tone' => 'bg-[#e8f3ec] text-[#176447]'],
            'ppa_contracts' => ['label' => 'العقود الموقعة', 'description' => 'العقود المنشورة، الجهة العامة، المتعاقد وتاريخ توقيع العقد.', 'tone' => 'bg-[#e7eef7] text-[#1f4f7c]'],
            'ppa_implementation' => ['label' => 'تنفيذ العقود', 'description' => 'السجلات التي انتقلت إلى التنفيذ وربطها بعملية الشراء والعقد الأصلي.', 'tone' => 'bg-[#f7eedc] text-[#8a5a08]'],
            'mof_budget_2026' => ['label' => 'اعتمادات الموازنة', 'description' => 'الاعتمادات المقدّرة حسب الإدارة، كما وردت في موازنة المواطن ومن دون تحويل العملة.', 'tone' => 'bg-[#f3e9e6] text-[#8f3e2c]'],
            'mof_finance_2025' => ['label' => 'الإنفاق والإيرادات', 'description' => 'الإنفاق الفعلي والإيرادات المبلّغ عنها في تقرير المالية العامة، منفصلة عن الاعتمادات.', 'tone' => 'bg-[#eceaf5] text-[#514382]'],
        ];
    @endphp

    <div class="-mx-4 -mt-5 overflow-hidden bg-[#f4f1e9] pb-14 sm:mx-0 sm:mt-0 sm:rounded-[2.5rem]">
        <section class="relative overflow-hidden bg-[#102f2c] px-5 py-9 text-white sm:px-10 sm:py-12 lg:px-14">
            <div class="pointer-events-none absolute -left-24 -top-28 h-72 w-72 rounded-full border-[44px] border-[#d2a840]/10"></div>
            <div class="pointer-events-none absolute bottom-0 right-0 h-40 w-80 opacity-10" style="background-image: radial-gradient(#f0c75e 1.5px, transparent 1.5px); background-size: 18px 18px"></div>
            <div class="relative">
                <nav class="mb-10 flex flex-wrap gap-2 text-xs font-black" aria-label="{{ __('ui.public_money.title') }}">
                    <a href="{{ route('public-money.index') }}" class="rounded-full border border-white/20 px-4 py-2 text-white/70 transition hover:bg-white/10 hover:text-white">نظرة عامة</a>
                    <a href="{{ route('public-money.procurements') }}" class="rounded-full border border-white/20 px-4 py-2 text-white/70 transition hover:bg-white/10 hover:text-white">التلزيمات والعقود</a>
                    <a href="{{ route('public-money.budget') }}" class="rounded-full border border-white/20 px-4 py-2 text-white/70 transition hover:bg-white/10 hover:text-white">الموازنة والإنفاق</a>
                    <span class="rounded-full bg-[#e8bb4c] px-4 py-2 text-[#102f2c]">المصادر والمنهجية</span>
                </nav>

                <div class="grid items-end gap-8 lg:grid-cols-[1fr_auto]">
                    <div class="max-w-3xl">
                        <p class="text-xs font-black tracking-[.2em] text-[#e8bb4c]">من الرقم إلى دليله</p>
                        <h1 class="mt-3 text-4xl font-black leading-[1.15] sm:text-6xl">نعرض المصدر،<br><span class="text-[#e8bb4c]">لا رقماً بلا سياق.</span></h1>
                        <p class="mt-5 max-w-2xl text-base leading-8 text-white/70">كل سجل في مرصد المال العام يبدأ من وثيقة رسمية، يُحفظ ببصمة رقمية، ويُراجع قبل ظهوره للناس. التحديث آلي، أمّا النشر فقرار تحريري موثّق.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-px overflow-hidden rounded-3xl bg-white/15 text-center sm:min-w-[310px]">
                        <div class="bg-white/5 px-5 py-5"><p class="text-3xl font-black text-[#e8bb4c]">{{ $sources->count() }}</p><p class="mt-1 text-xs text-white/60">مصادر رسمية</p></div>
                        <div class="bg-white/5 px-5 py-5"><p class="text-3xl font-black text-[#e8bb4c]">{{ $healthy }}</p><p class="mt-1 text-xs text-white/60">مصادر متزامنة</p></div>
                        <div class="col-span-2 bg-white/5 px-5 py-4"><p class="text-xs text-white/50">آخر مزامنة ناجحة</p><p class="mt-1 font-black">{{ $latestSuccess ? $latestSuccess->timezone(config('app.timezone'))->format('Y-m-d · H:i') : 'بانتظار اكتمال أول دورة' }}</p></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-4 pt-9 sm:px-8 lg:px-10">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div><p class="text-xs font-black text-[#9b2f27]">سجل المصادر</p><h2 class="mt-1 text-3xl font-black text-[#132825]">من أين تأتي البيانات؟</h2></div>
                <p class="max-w-md text-sm leading-7 text-[#5d6966]">نحافظ على المراحل منفصلة: نتيجة التلزيم ليست عقداً، والعقد ليس دليلاً على التنفيذ.</p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($sources as $source)
                    @php
                        $meta = $sourceMeta[$source->adapter] ?? ['label' => $source->name_ar, 'description' => '', 'tone' => 'bg-gray-100 text-gray-700'];
                        $lastRun = $source->runs->first();
                        $hasError = $source->last_error_at && (! $source->last_success_at || $source->last_error_at->gt($source->last_success_at));
                        $isHealthy = $source->last_success_at && ! $hasError;
                    @endphp
                    <article class="group relative overflow-hidden rounded-[1.75rem] border border-[#d8d6ce] bg-white p-5 shadow-[0_12px_35px_rgba(23,42,38,.05)] sm:p-6">
                        <div class="absolute inset-y-0 right-0 w-1.5 {{ $isHealthy ? 'bg-[#37a274]' : ($hasError ? 'bg-[#b44336]' : 'bg-[#d3a83f]') }}"></div>
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-black {{ $meta['tone'] }}">{{ $meta['label'] }}</span>
                                <h3 class="mt-4 text-xl font-black leading-7 text-[#132825]">{{ $source->name_ar }}</h3>
                            </div>
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#102f2c] text-[#e8bb4c]">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19.5h16M6.5 17V9.5M11 17V9.5M15.5 17V9.5M20 7H4l8-4 8 4Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-7 text-[#65706d]">{{ $meta['description'] }}</p>
                        <div class="mt-5 grid grid-cols-2 gap-3 border-t border-[#ebe8df] pt-5 text-xs">
                            <div><p class="text-[#87908e]">الحالة</p><p class="mt-1 flex items-center gap-2 font-black text-[#263936]"><span class="h-2 w-2 rounded-full {{ $isHealthy ? 'bg-[#37a274]' : ($hasError ? 'bg-[#b44336]' : 'bg-[#d3a83f]') }}"></span>{{ $isHealthy ? 'متزامن' : ($hasError ? 'تعذّر آخر فحص' : 'بانتظار أول مزامنة') }}</p></div>
                            <div><p class="text-[#87908e]">آخر نجاح</p><p class="mt-1 font-black text-[#263936]">{{ optional($source->last_success_at)->format('Y-m-d H:i') ?: 'لم يكتمل بعد' }}</p></div>
                            @if ($lastRun)
                                <div><p class="text-[#87908e]">آخر دورة</p><p class="mt-1 font-black text-[#263936]">{{ $lastRun->discovered_count }} سجل مكتشف</p></div>
                                <div><p class="text-[#87908e]">النتيجة</p><p class="mt-1 font-black text-[#263936]">{{ $lastRun->status === 'succeeded' ? 'اكتملت بنجاح' : ($lastRun->status === 'partial' ? 'اكتملت جزئياً' : 'تحتاج متابعة') }}</p></div>
                            @endif
                        </div>
                        <a href="{{ $source->discovery_url }}" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex items-center gap-2 text-sm font-black text-[#9b2f27] transition group-hover:gap-3">فتح الوثيقة أو السجل الرسمي <span aria-hidden="true">←</span></a>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="mx-4 mt-10 rounded-[2rem] bg-white px-5 py-7 sm:mx-8 sm:px-8 lg:mx-10">
            <div class="grid gap-8 lg:grid-cols-[.7fr_1.3fr]">
                <div><p class="text-xs font-black text-[#9b2f27]">سلسلة الدليل</p><h2 class="mt-2 text-3xl font-black text-[#132825]">كيف يصل السجل إلى الموقع؟</h2><p class="mt-4 text-sm leading-7 text-[#65706d]">إذا تغيّرت وثيقة أو قيمة، لا نستبدل التاريخ بصمت. ننشئ مراجعة جديدة ونُعيد السجل إلى التدقيق.</p></div>
                <ol class="grid gap-3 sm:grid-cols-2">
                    @foreach ([['01', 'اكتشاف', 'فحص صفحات الجهات الرسمية يومياً.'], ['02', 'حفظ', 'نسخة مؤرشفة وبصمة رقمية للوثيقة.'], ['03', 'استخراج', 'حقول منظمة مع الوحدة والنص الأصلي.'], ['04', 'مراجعة ونشر', 'لا يظهر السجل قبل الموافقة التحريرية.']] as [$number, $title, $body])
                        <li class="rounded-2xl bg-[#f4f1e9] p-4"><span class="font-mono text-xs font-black text-[#b28a28]">{{ $number }}</span><h3 class="mt-2 font-black text-[#132825]">{{ $title }}</h3><p class="mt-1 text-xs leading-6 text-[#65706d]">{{ $body }}</p></li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="mx-4 mt-6 flex flex-col gap-4 rounded-3xl border border-[#d3a83f]/40 bg-[#fff9e8] p-5 sm:mx-8 sm:flex-row sm:items-center sm:justify-between lg:mx-10">
            <p class="max-w-3xl text-sm leading-7 text-[#5b5034]"><strong class="text-[#302816]">قاعدة أساسية:</strong> اعتماد الموازنة لا يعني أن المبلغ صُرف. لذلك نعرض الاعتمادات والإنفاق الفعلي والإيرادات في مجموعات مستقلة، ونفصل العملات دائماً.</p>
            <a href="{{ route('public-money.budget') }}" class="shrink-0 rounded-full bg-[#102f2c] px-5 py-3 text-center text-sm font-black text-white">استكشاف الأرقام</a>
        </section>
    </div>
</x-layouts.site>
