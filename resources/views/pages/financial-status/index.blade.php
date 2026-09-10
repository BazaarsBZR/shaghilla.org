@extends('layouts.financial-status')

@section('content')
@php
    $unitLabels = ['LBP billion' => 'مليار ليرة لبنانية', 'percent' => '%', 'score 0-100' => 'نقطة'];
    $format = function ($observation) use ($unitLabels) {
        if (!$observation) return 'غير متوفر';
        return $observation->amount_text.' '.($unitLabels[$observation->original_unit] ?? $observation->original_unit);
    };
    $period = fn ($observation) => $observation?->fiscal_period ? 'الفترة: '.$observation->fiscal_period : 'الفترة غير مذكورة';
    $lastUpdate = $lastTrustedUpdate ? \Illuminate\Support\Carbon::parse($lastTrustedUpdate)->timezone('Asia/Beirut') : null;
    $componentData = data_get($score?->evidence, 'components', []);
    $totalRevenue = $cards['revenue'] ? (float) $cards['revenue']->amount * (float) $cards['revenue']->scale : 0;
    $totalSpending = $cards['expenditure'] ? (float) $cards['expenditure']->amount * (float) $cards['expenditure']->scale : 0;
    $revenueLabels = [
        'actual_tax_revenue' => 'إيرادات ضريبية',
        'actual_non_tax_revenue' => 'إيرادات غير ضريبية',
        'actual_treasury_revenue' => 'إيرادات الخزينة',
    ];
    $spendingLabels = [
        'Current Expenditures' => 'النفقات الجارية',
        'Capital Expenditures' => 'النفقات الرأسمالية',
        'Budget Advances' => 'سلفات الموازنة',
        'Treasury Expenditures' => 'نفقات الخزينة',
    ];
@endphp

<section class="fs-hero">
    <div class="fs-wrap fs-hero-inner">
        <p class="fs-kicker">بيانات مالية رسمية مبسّطة</p>
        <h1>الوضع المالي</h1>
        <p>لوحة لفهم الدين والإيرادات والإنفاق والرصيد المالي في لبنان، مع فصل واضح بين الأرقام الفعلية وتقديرات الموازنة وبين الفترات والعملات المختلفة.</p>
    </div>
</section>

<main class="fs-dashboard">
    <div class="fs-wrap">
        <section class="fs-meter-panel">
            <div class="fs-gauge-box">
                <h2>مؤشر الضغط المالي</h2>
                <div class="fs-gauge {{ $scoreValue === null ? 'is-empty' : '' }}" style="--score: {{ $scoreValue ?? 0 }}">
                    @if($scoreValue !== null)<span class="fs-gauge-needle" aria-hidden="true"></span>@endif
                    <div class="fs-gauge-value">
                        <strong>{{ $scoreValue ?? '—' }}</strong>
                        <span>{{ $scoreLabel ?? 'بيانات غير كافية' }}</span>
                    </div>
                </div>
                <div class="fs-zones">
                    <span><b>0–25</b> منخفض</span>
                    <span><b>26–50</b> متوسط</span>
                    <span><b>51–75</b> مرتفع</span>
                    <span><b>76–100</b> حرج</span>
                </div>
            </div>
            <div class="fs-meter-copy">
                @if($score)
                    <span class="fs-period">فترة المؤشر: {{ $score->fiscal_period }}</span>
                    <div class="fs-trend">
                        @if($scoreChange === null)
                            <strong>لا تتوفر مقارنة</strong>
                        @elseif($scoreChange > 0)
                            <strong>تراجع</strong><span>▲ {{ abs($scoreChange) }} نقاط عن الفترة السابقة</span>
                        @elseif($scoreChange < 0)
                            <strong>تحسن</strong><span>▼ {{ abs($scoreChange) }} نقاط عن الفترة السابقة</span>
                        @else
                            <strong>دون تغيير</strong><span>0 نقطة</span>
                        @endif
                    </div>
                @endif
                <p class="fs-explanation">{{ $scoreExplanation }}</p>
                <p class="fs-disclaimer">مؤشر من إعداد شغيلة استنادًا إلى بيانات مالية منشورة، وليس تصنيفًا رسميًا للدولة اللبنانية.</p>
                <a class="fs-button" href="#methodology">كيف نحسب المؤشر؟</a>
            </div>
        </section>

        <div class="fs-freshness {{ $hasSourceError ? 'warning' : '' }}">
            <span>{{ $lastUpdate ? 'آخر تحديث موثوق: '.$lastUpdate->format('Y-m-d H:i') : 'لم يكتمل أول تحديث موثوق بعد' }}</span>
            <span>تظل آخر القيم الموثقة ظاهرة إذا تعذّر المصدر، من دون إنشاء قيم بديلة.</span>
        </div>

        <section class="fs-section">
            <div class="fs-section-head"><div><p>آخر قيمة موثقة لكل مفهوم</p><h2>الصورة المالية بالأرقام</h2></div><p>الفترات موضحة على كل بطاقة</p></div>
            <div class="fs-cards">
                <article class="fs-card"><small>{{ $period($cards['debt']) }}</small><h3>الدين العام</h3><strong>{{ $format($cards['debt']) }}</strong><p>رصيد الدين الإجمالي في نهاية الفترة، وليس إنفاقًا سنويًا.</p></article>
                <article class="fs-card"><small>{{ $period($cards['revenue']) }}</small><h3>إيرادات الدولة</h3><strong>{{ $format($cards['revenue']) }}</strong><p>إجمالي المقبوضات الفعلية للموازنة والخزينة.</p></article>
                <article class="fs-card"><small>{{ $period($cards['taxRevenue']) }}</small><h3>الإيرادات الضريبية</h3><strong>{{ $format($cards['taxRevenue']) }}</strong><p>تحصيل ضريبي فعلي للفترة المنشورة.</p></article>
                <article class="fs-card"><small>{{ $period($cards['expenditure']) }}</small><h3>الإنفاق العام</h3><strong>{{ $format($cards['expenditure']) }}</strong><p>مدفوعات فعلية للموازنة والخزينة وليست اعتمادًا مخططًا.</p></article>
                <article class="fs-card is-balance"><small>{{ $period($cards['balance']) }}</small><h3>العجز / الفائض</h3><strong class="{{ $cards['balance'] && (float) $cards['balance']->amount < 0 ? 'negative' : '' }}">{{ $format($cards['balance']) }}</strong><p>{{ !$cards['balance'] ? 'غير متوفر' : ((float) $cards['balance']->amount >= 0 ? 'فائض مالي فعلي' : 'عجز مالي فعلي') }}</p></article>
                <article class="fs-card"><small>{{ $debtRevenue ? 'فترة المقارنة: '.$debtRevenue['period'] : 'لا توجد فترة متوافقة' }}</small><h3>الدين مقابل الإيرادات</h3><strong>{{ $debtRevenue ? number_format($debtRevenue['ratio'], 1).' مرة' : 'غير متوفر' }}</strong><p>{{ $debtRevenue ? 'يعادل الدين نحو '.number_format($debtRevenue['ratio'], 1).' سنوات من إيرادات الدولة للفترة نفسها.' : 'لا نحسب النسبة من فترات أو عملات غير متوافقة.' }}</p></article>
            </div>
        </section>

        <section class="fs-section fs-insights">
            <article class="fs-panel">
                <h3>من أين تأتي أموال الدولة؟</h3>
                <p>{{ $cards['revenue'] ? 'تفصيل الإيرادات الفعلية للفترة '.$cards['revenue']->fiscal_period : 'لا تتوفر بيانات فعلية كافية' }}</p>
                @if($totalRevenue > 0 && $revenueBreakdown->isNotEmpty())
                    @php
                        $taxRevenue = $revenueBreakdown->get('actual_tax_revenue');
                        $taxPerHundred = $taxRevenue
                            ? ((float) $taxRevenue->amount * (float) $taxRevenue->scale) / $totalRevenue * 100
                            : 0;
                    @endphp
                    <div class="fs-killer"><span>من كل 100 ليرة تجمعها الدولة</span><strong>{{ number_format($taxPerHundred, 1) }} ليرة إيرادات ضريبية</strong></div>
                    <div class="fs-breakdown">
                        @foreach($revenueLabels as $measure => $label)
                            @php
                                $item = $revenueBreakdown->get($measure);
                                $share = $item ? ((float) $item->amount * (float) $item->scale) / $totalRevenue * 100 : 0;
                            @endphp
                            @if($item)
                                <div class="fs-break-row"><span>{{ $label }}</span><div class="fs-bar"><i style="--width: {{ min(100, $share) }}%"></i></div><b>{{ number_format($share, 1) }}%</b></div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="fs-empty">لا تتوفر تفاصيل إيرادات فعلية قابلة للمقارنة حاليًا.</div>
                @endif
            </article>

            <article class="fs-panel">
                <h3>أين تذهب الأموال؟</h3>
                <p>{{ $cards['expenditure'] ? 'تصنيف اقتصادي للإنفاق الفعلي في '.$cards['expenditure']->fiscal_period : 'لا تتوفر بيانات فعلية كافية' }}</p>
                @if($totalSpending > 0 && $spending->isNotEmpty())
                    <div class="fs-breakdown">
                        @foreach($spending as $item)
                            @php
                                $share = ((float) $item->amount * (float) $item->scale) / $totalSpending * 100;
                            @endphp
                            <div class="fs-break-row"><span>{{ $spendingLabels[$item->category] ?? $item->category }}</span><div class="fs-bar"><i style="--width: {{ min(100, $share) }}%"></i></div><b>{{ number_format($share, 1) }}%</b></div>
                        @endforeach
                    </div>
                @else
                    <div class="fs-empty">لم ينشر المصدر بعد تفصيلاً فعليًا قابلاً للعرض لهذه الفترة.</div>
                @endif
            </article>
        </section>

        @if($debtRevenue)
            <section class="fs-section">
                <article class="fs-panel">
                    <h3>مقابل كل 100 ليرة من الإيرادات</h3>
                    <div class="fs-killer"><strong>تبلغ قيمة الدين {{ number_format($debtRevenue['ratio'] * 100, 0) }} ليرة</strong><span>حساب للفترة {{ $debtRevenue['period'] }} وبالليرة اللبنانية في المصدرين.</span></div>
                </article>
            </section>
        @endif

        <section class="fs-section">
            <article class="fs-panel">
                <h3>تاريخ مؤشر الضغط المالي</h3>
                @if($scoreHistory->count() >= 2)
                    <div class="fs-history" aria-label="تاريخ المؤشر">
                        @foreach($scoreHistory as $point)
                            <div class="fs-history-item"><div class="fs-history-bar" style="--height: {{ min(100, max(0, (float) $point->amount)) }}"></div><span>{{ $point->fiscal_period }}<br>{{ number_format((float) $point->amount, 0) }}</span></div>
                        @endforeach
                    </div>
                @else
                    <div class="fs-empty">لا تتوفر بعد فترتان مكتملتان ومتوافقتان لرسم اتجاه تاريخي موثوق.</div>
                @endif
            </article>
        </section>

        <section class="fs-section">
            <details class="fs-panel fs-methodology" id="methodology">
                <summary><h3>كيف نحسب المؤشر؟</h3><p>فتح المنهجية الكاملة والأوزان</p></summary>
                <p>الإصدار 1.0.0. لا يُحسب المؤشر إلا عندما تتوفر قيم الدين والإيرادات والرصيد المالي والتضخم للفترة نفسها، ولا يزيد عمر الفترة عن 24 شهرًا.</p>
                <div class="fs-method-grid">
                    <div class="fs-method"><strong>45% عبء الدين</strong><p>الدين العام ÷ الإيرادات السنوية. صفر عندما تكون النسبة 1 أو أقل، و100 عندما تبلغ 8 أو أكثر، مع احتساب خطي بينهما.</p></div>
                    <div class="fs-method"><strong>30% الرصيد المالي</strong><p>العجز أو الفائض ÷ الإيرادات. صفر عند فائض 5% أو أكثر، و100 عند عجز 20% أو أكثر، مع احتساب خطي بينهما.</p></div>
                    <div class="fs-method"><strong>25% ضغط التضخم</strong><p>صفر عند تضخم سنوي متوسط 3% أو أقل، و100 عند 50% أو أكثر، مع احتساب خطي بينهما.</p></div>
                </div>
                @if($componentData)
                    <p>القيم الداخلة في الفترة {{ $score->fiscal_period }}:</p>
                    <ul>
                        @foreach($componentData as $component)
                            <li>{{ $component['label_ar'] }}: قيمة معيارية {{ number_format($component['score'], 1) }} ووزن {{ number_format($component['weight'] * 100, 0) }}%</li>
                        @endforeach
                    </ul>
                @endif
            </details>
        </section>

        <section class="fs-section" id="sources">
            <article class="fs-panel">
                <h3>المصادر والبيانات الأساسية</h3>
                <p>كل رقم يحتفظ بالقيمة والوحدة والفترة والوثيقة والصف أو الجدول ووقت الاسترجاع والإصدار.</p>
                <ul class="fs-sources">
                    @forelse($sources as $source)
                        <li>
                            <div><strong>{{ $source->name_ar }}</strong><br><small>{{ $source->last_success_at ? 'آخر تحقق ناجح: '.\Illuminate\Support\Carbon::parse($source->last_success_at)->format('Y-m-d') : 'لا يوجد تحقق ناجح بعد' }}</small></div>
                            <a href="{{ $source->discovery_url }}" target="_blank" rel="noopener noreferrer">فتح الوثيقة الرسمية</a>
                        </li>
                    @empty
                        <li>لا توجد مصادر مستوردة بعد.</li>
                    @endforelse
                </ul>
                <a class="fs-button" href="{{ route('public-money.sources') }}">فتح سجل المصادر الكامل</a>
            </article>
        </section>
    </div>
</main>
@endsection
