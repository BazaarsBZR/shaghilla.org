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

<style id="financial-status-pro-overrides">
    :root {
        --fs-navy: #092f39;
        --fs-navy-deep: #06242d;
        --fs-emerald: #0b8b60;
        --fs-emerald-bright: #22b57b;
        --fs-mint: #eaf6f0;
        --fs-cloud: #f4f8f7;
        --fs-line: #d8e5e1;
        --fs-muted: #6c8087;
        --fs-red: #d92834;
    }

    .fs-hero {
        position: relative;
        isolation: isolate;
        min-height: auto;
        overflow: hidden;
        padding: clamp(58px, 7vw, 92px) 0 clamp(126px, 12vw, 168px);
        background:
            radial-gradient(circle at 13% 16%, rgba(34, 181, 123, .28), transparent 31%),
            radial-gradient(circle at 84% 82%, rgba(255, 255, 255, .11), transparent 30%),
            linear-gradient(128deg, var(--fs-navy-deep) 0%, #0b4245 54%, #0b714f 120%);
    }

    .fs-hero::before {
        content: "";
        position: absolute;
        z-index: -1;
        inset: 0;
        opacity: .26;
        background-image: radial-gradient(rgba(255, 255, 255, .6) 1px, transparent 1px);
        background-size: 34px 34px;
        mask-image: linear-gradient(to left, #000, transparent 82%);
    }

    .fs-hero::after {
        content: "";
        position: absolute;
        z-index: -1;
        inline-size: min(45vw, 620px);
        aspect-ratio: 1;
        inset-inline-start: -7vw;
        inset-block-end: -70%;
        border: 80px solid rgba(255, 255, 255, .055);
        border-radius: 50%;
    }

    .fs-hero-inner,
    .fs-wrap {
        width: min(1240px, calc(100% - 40px));
        margin-inline: auto;
    }

    .fs-hero-inner {
        position: relative;
        z-index: 1;
    }

    .fs-kicker {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        margin: 0 0 18px;
        padding: 8px 14px;
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 999px;
        background: rgba(255, 255, 255, .09);
        color: #bff5db;
        font-size: 14px;
        font-weight: 800;
        letter-spacing: .01em;
        backdrop-filter: blur(10px);
    }

    .fs-kicker::before {
        content: "";
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #49dfa0;
        box-shadow: 0 0 0 5px rgba(73, 223, 160, .13);
    }

    .fs-hero h1 {
        max-width: 760px;
        margin: 0;
        color: #fff;
        font-size: clamp(46px, 6.2vw, 78px);
        font-weight: 900;
        line-height: .98;
        letter-spacing: -.045em;
        text-wrap: balance;
    }

    .fs-hero p {
        max-width: 900px;
        margin: 24px 0 0;
        color: rgba(238, 249, 246, .82);
        font-size: clamp(17px, 1.8vw, 21px);
        line-height: 1.85;
        font-weight: 500;
    }

    .fs-dashboard {
        position: relative;
        z-index: 2;
        margin-top: -96px;
        padding-bottom: 18px;
    }

    .fs-meter-panel {
        display: grid;
        grid-template-columns: minmax(330px, .82fr) minmax(400px, 1.18fr);
        align-items: stretch;
        gap: clamp(22px, 3vw, 42px);
        padding: clamp(24px, 3.2vw, 42px);
        border: 1px solid rgba(11, 139, 96, .16);
        border-radius: 34px;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 28px 80px rgba(5, 42, 49, .14), 0 3px 10px rgba(5, 42, 49, .05);
        backdrop-filter: blur(16px);
    }

    .fs-gauge-box {
        display: flex;
        min-width: 0;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: clamp(22px, 3vw, 34px);
        border: 1px solid #dbe9e4;
        border-radius: 26px;
        background:
            radial-gradient(circle at 50% 42%, #fff 0 36%, transparent 37%),
            linear-gradient(145deg, #f5faf8, #eaf4f0);
    }

    .fs-gauge-box > h2 {
        margin: 0 0 18px;
        color: var(--fs-navy);
        font-size: clamp(22px, 2vw, 29px);
        font-weight: 900;
    }

    .fs-gauge {
        width: min(100%, 330px);
        margin-inline: auto;
        filter: drop-shadow(0 16px 26px rgba(5, 42, 49, .1));
    }

    .fs-gauge-value {
        color: var(--fs-navy);
        font-size: clamp(50px, 5vw, 68px);
        font-weight: 950;
        line-height: .9;
        letter-spacing: -.05em;
    }

    .fs-gauge-value small {
        display: block;
        margin-top: 14px;
        color: var(--fs-muted);
        font-size: 17px;
        font-weight: 800;
        letter-spacing: 0;
    }

    .fs-zones {
        width: 100%;
        gap: 6px;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #dce9e4;
    }

    .fs-zones > * {
        min-width: 0;
        padding: 8px 5px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .72);
        font-size: 12px;
    }

    .fs-meter-copy {
        display: flex;
        min-width: 0;
        flex-direction: column;
        justify-content: center;
        padding: clamp(8px, 1.8vw, 20px);
    }

    .fs-period {
        width: fit-content;
        margin: 0 0 12px;
        padding: 7px 12px;
        border-radius: 999px;
        background: var(--fs-mint);
        color: var(--fs-emerald);
        font-size: 14px;
        font-weight: 900;
    }

    .fs-trend {
        margin: 0;
        color: var(--fs-navy);
        font-size: clamp(34px, 4.2vw, 58px);
        font-weight: 950;
        line-height: 1.12;
        letter-spacing: -.035em;
    }

    .fs-explanation {
        margin: 22px 0 0;
        padding: 18px 20px;
        border: 0;
        border-inline-start: 4px solid var(--fs-emerald);
        border-radius: 16px;
        background: #f0f8f4;
        color: #25424a;
        font-size: 17px;
        line-height: 1.8;
        box-shadow: none;
    }

    .fs-disclaimer {
        margin: 18px 0 0;
        color: var(--fs-muted);
        font-size: 14px;
        line-height: 1.75;
    }

    .fs-button {
        width: fit-content;
        margin-top: 22px;
        padding: 12px 20px;
        border: 1px solid var(--fs-emerald);
        border-radius: 13px;
        background: #fff;
        color: var(--fs-emerald);
        font-size: 15px;
        font-weight: 900;
        transition: transform .2s ease, background .2s ease, color .2s ease, box-shadow .2s ease;
    }

    .fs-button:hover {
        transform: translateY(-2px);
        background: var(--fs-emerald);
        color: #fff;
        box-shadow: 0 10px 22px rgba(11, 139, 96, .2);
    }

    .fs-freshness {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-top: 16px;
        padding: 14px 18px;
        border: 1px solid #bce3d2;
        border-radius: 16px;
        background: #edf9f3;
        color: #176448;
        font-size: 14px;
        font-weight: 700;
        box-shadow: none;
    }

    .fs-section {
        margin-top: 28px;
        padding: clamp(24px, 3vw, 38px);
        border: 1px solid var(--fs-line);
        border-radius: 28px;
        background: #fff;
        box-shadow: 0 15px 44px rgba(7, 45, 52, .065);
    }

    .fs-section-head {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 24px;
        padding-bottom: 18px;
        border-bottom: 1px solid #e1ebe7;
    }

    .fs-section-head h2 {
        margin: 0;
        color: var(--fs-navy);
        font-size: clamp(25px, 3vw, 36px);
        font-weight: 950;
        letter-spacing: -.025em;
    }

    .fs-section-head p {
        max-width: 620px;
        margin: 0;
        color: var(--fs-muted);
        font-size: 14px;
        line-height: 1.7;
    }

    .fs-cards {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .fs-card {
        min-height: 154px;
        padding: 22px;
        border: 1px solid #dce8e4;
        border-radius: 20px;
        background: linear-gradient(145deg, #fff, #f6faf8);
        box-shadow: none;
        transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
    }

    .fs-card:hover {
        transform: translateY(-3px);
        border-color: #9ad1b9;
        box-shadow: 0 14px 28px rgba(7, 70, 57, .09);
    }

    .fs-insights,
    .fs-method-grid {
        gap: 14px;
    }

    .fs-panel,
    .fs-method,
    .fs-history-item {
        border-color: #dce8e4;
        border-radius: 20px;
        box-shadow: none;
    }

    .fs-killer,
    .fs-breakdown,
    .fs-history,
    .fs-methodology {
        margin-top: 28px;
    }

    .fs-bar,
    .fs-history-bar {
        overflow: hidden;
        border-radius: 999px;
        background: #e6efec;
    }

    .fs-meter-panel,
    .fs-freshness,
    .fs-section {
        animation: fs-rise .55s cubic-bezier(.22, 1, .36, 1) both;
    }

    .fs-freshness { animation-delay: .06s; }
    .fs-section:nth-of-type(2) { animation-delay: .1s; }
    .fs-section:nth-of-type(3) { animation-delay: .14s; }

    @keyframes fs-rise {
        from { opacity: 0; transform: translateY(16px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 960px) {
        .fs-meter-panel {
            grid-template-columns: 1fr;
        }

        .fs-gauge {
            width: min(100%, 300px);
        }

        .fs-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .fs-hero {
            padding: 44px 0 112px;
        }

        .fs-hero-inner,
        .fs-wrap {
            width: min(100% - 24px, 1240px);
        }

        .fs-hero h1 {
            font-size: 43px;
        }

        .fs-hero p {
            margin-top: 18px;
            font-size: 16px;
            line-height: 1.75;
        }

        .fs-dashboard {
            margin-top: -76px;
        }

        .fs-meter-panel,
        .fs-section {
            padding: 16px;
            border-radius: 22px;
        }

        .fs-gauge-box {
            padding: 20px 14px;
            border-radius: 18px;
        }

        .fs-gauge {
            width: min(100%, 255px);
        }

        .fs-meter-copy {
            padding: 8px 2px 4px;
        }

        .fs-trend {
            font-size: 35px;
        }

        .fs-explanation {
            padding: 15px 16px;
            font-size: 15px;
        }

        .fs-freshness,
        .fs-section-head {
            align-items: flex-start;
            flex-direction: column;
        }

        .fs-cards,
        .fs-insights,
        .fs-method-grid {
            grid-template-columns: 1fr;
        }

        .fs-card {
            min-height: 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .fs-meter-panel,
        .fs-freshness,
        .fs-section {
            animation: none;
        }

        .fs-card,
        .fs-button {
            transition: none;
        }
    }
</style>
@endsection
