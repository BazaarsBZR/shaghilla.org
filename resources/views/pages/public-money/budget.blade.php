@php
    $lbpPerUsd = 89500;
    $categoryLabels = [
        'Total estimated expenditure' => 'إجمالي النفقات المقدّرة',
        'Ministry of National Defense' => 'وزارة الدفاع الوطني',
        'Ministry of Interior and Municipalities' => 'وزارة الداخلية والبلديات',
        'Ministry of Education and Higher Education' => 'وزارة التربية والتعليم العالي',
        'Ministry of Public Health' => 'وزارة الصحة العامة',
        'Presidency of the Council of Ministers' => 'رئاسة مجلس الوزراء',
        'Ministry of Public Works and Transport' => 'وزارة الأشغال العامة والنقل',
        'Ministry of Telecommunications' => 'وزارة الاتصالات',
        'Ministry of Social Affairs' => 'وزارة الشؤون الاجتماعية',
        'Ministry of Labor' => 'وزارة العمل',
        'Ministry of Energy and Water' => 'وزارة الطاقة والمياه',
        'Ministry of Finance' => 'وزارة المالية',
        'Ministry of Foreign Affairs and Emigrants' => 'وزارة الخارجية والمغتربين',
        'Ministry of Justice' => 'وزارة العدل',
        'Ministry of Agriculture' => 'وزارة الزراعة',
        'Total estimated revenue' => 'إجمالي الإيرادات المقدّرة',
        'Tax revenue' => 'الإيرادات الضريبية',
        'Non-tax revenue' => 'الإيرادات غير الضريبية',
        'Total expenditures (budget and treasury)' => 'إجمالي النفقات (الموازنة والخزينة)',
        'Total revenues (budget and treasury)' => 'إجمالي الإيرادات (الموازنة والخزينة)',
        'Budget revenues' => 'إيرادات الموازنة',
        'Tax revenues' => 'الإيرادات الضريبية',
        'Non-tax revenues' => 'الإيرادات غير الضريبية',
        'Treasury receipts' => 'مقبوضات الخزينة',
    ];
@endphp

<x-layouts.site :title="__('ui.public_money.budget_spending').' | '.config('app.name')">
    <div class="max-w-3xl">
        <p class="text-sm font-black text-accent">{{ __('ui.public_money.title') }}</p>
        <h1 class="mt-1 text-4xl font-black">{{ __('ui.public_money.budget_spending') }}</h1>
        <p class="mt-4 leading-8 text-ink-muted">{{ __('ui.public_money.budget_warning') }}</p>
        <p class="mt-2 text-sm font-bold text-emerald-700">القيمة بالدولار تقديرية على أساس 89,500 ليرة لبنانية لكل دولار.</p>
    </div>

    @foreach ($observations->groupBy(fn ($row) => $row->fiscal_period.'|'.$row->measure_type) as $key => $items)
        @php([$period, $measure] = explode('|', $key, 2))
        <section class="mt-9">
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-black">{{ __('ui.public_money.measure_'.$measure) }}</h2>
                <span class="rounded-full bg-surface-soft px-3 py-1 text-sm font-black">{{ $period }}</span>
            </div>
            <div class="mt-4 overflow-x-auto rounded-3xl border border-line bg-surface">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface-soft"><tr><th class="px-4 py-3 text-start">{{ __('ui.public_money.category') }}</th><th class="px-4 py-3 text-start">{{ __('ui.public_money.amount') }}</th><th class="px-4 py-3 text-start">القيمة التقديرية بالدولار</th><th class="px-4 py-3 text-start">{{ __('ui.public_money.evidence') }}</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($items as $item)
                            @php($pageReference = str_replace(['Table ', 'p. ', ' and ', '; '], ['الجدول ', 'ص. ', ' و', '؛ '], $item->page_reference ?? ''))
                            <tr class="{{ $item->is_total ? 'font-black' : '' }}">
                                <td class="px-4 py-4">{{ $categoryLabels[$item->category] ?? $item->category }}</td>
                                <td class="whitespace-nowrap px-4 py-4">{{ number_format((float) $item->amount) }} مليار ليرة لبنانية</td>
                                <td class="whitespace-nowrap px-4 py-4 text-emerald-700">≈ ${{ number_format((float) $item->amount / $lbpPerUsd, 3) }} مليار</td>
                                <td class="px-4 py-4"><a class="font-bold text-accent" href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer">{{ $pageReference ?: __('ui.public_money.source') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</x-layouts.site>
