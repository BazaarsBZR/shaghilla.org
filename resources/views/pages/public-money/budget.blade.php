<x-layouts.site :title="__('ui.public_money.budget_spending').' | '.config('app.name')">
    <div class="max-w-3xl"><p class="text-sm font-black text-accent">{{ __('ui.public_money.title') }}</p><h1 class="mt-1 text-4xl font-black">{{ __('ui.public_money.budget_spending') }}</h1><p class="mt-4 leading-8 text-ink-muted">{{ __('ui.public_money.budget_warning') }}</p></div>
    @foreach ($observations->groupBy(fn ($row) => $row->fiscal_period.'|'.$row->measure_type) as $key => $items)
        @php([$period, $measure] = explode('|', $key, 2))
        <section class="mt-9"><div class="flex items-center gap-3"><h2 class="text-2xl font-black">{{ __('ui.public_money.measure_'.$measure) }}</h2><span class="rounded-full bg-surface-soft px-3 py-1 text-sm font-black">{{ $period }}</span></div>
            <div class="mt-4 overflow-hidden rounded-3xl border border-line bg-surface"><table class="min-w-full text-sm"><thead class="bg-surface-soft"><tr><th class="px-4 py-3 text-start">{{ __('ui.public_money.category') }}</th><th class="px-4 py-3 text-start">{{ __('ui.public_money.amount') }}</th><th class="px-4 py-3 text-start">{{ __('ui.public_money.evidence') }}</th></tr></thead><tbody class="divide-y divide-line">@foreach ($items as $item)<tr class="{{ $item->is_total ? 'font-black' : '' }}"><td class="px-4 py-4">{{ $item->category }}</td><td class="px-4 py-4 whitespace-nowrap">{{ number_format((float) $item->amount) }} {{ $item->original_unit }}</td><td class="px-4 py-4"><a class="font-bold text-accent" href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer">{{ $item->page_reference ?: __('ui.public_money.source') }}</a></td></tr>@endforeach</tbody></table></div>
        </section>
    @endforeach
</x-layouts.site>
