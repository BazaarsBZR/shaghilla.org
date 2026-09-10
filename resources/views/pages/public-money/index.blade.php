<x-layouts.site :title="__('ui.public_money.title').' | '.config('app.name')">
    <section class="relative overflow-hidden rounded-[2rem] border border-line bg-[#102c2a] px-6 py-10 text-white shadow-surface sm:px-10">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 20% 20%, #f5c451 0 2px, transparent 3px); background-size: 26px 26px"></div>
        <div class="relative max-w-3xl">
            <p class="mb-3 text-sm font-black tracking-[.18em] text-[#f5c451]">{{ __('ui.public_money.eyebrow') }}</p>
            <h1 class="text-4xl font-black leading-tight sm:text-6xl">{{ __('ui.public_money.title') }}</h1>
            <p class="mt-5 max-w-2xl text-base leading-8 text-white/75">{{ __('ui.public_money.intro') }}</p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ route('public-money.procurements') }}" class="rounded-full bg-[#f5c451] px-5 py-3 text-sm font-black text-[#102c2a]">{{ __('ui.public_money.browse_procurements') }}</a>
                <a href="{{ route('public-money.budget') }}" class="rounded-full border border-white/30 px-5 py-3 text-sm font-black text-white">{{ __('ui.public_money.budget_spending') }}</a>
                <a href="{{ route('public-money.sources') }}" class="rounded-full border border-white/30 px-5 py-3 text-sm font-black text-white">{{ __('ui.public_money.sources_method') }}</a>
            </div>
        </div>
    </section>

    <section class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (['award', 'contract', 'implementation'] as $stage)
            <div class="rounded-3xl border border-line bg-surface p-5">
                <p class="text-sm font-bold text-ink-muted">{{ __('ui.public_money.stage_'.$stage) }}</p>
                <p class="mt-2 text-3xl font-black text-ink">{{ number_format((int) ($stageCounts[$stage] ?? 0)) }}</p>
            </div>
        @endforeach
        <div class="rounded-3xl border border-line bg-[#f5c451]/20 p-5">
            <p class="text-sm font-bold text-ink-muted">{{ __('ui.public_money.last_update') }}</p>
            <p class="mt-2 text-lg font-black text-ink">{{ optional($procurements->max('updated_at'))->format('Y-m-d H:i') ?: '—' }}</p>
        </div>
    </section>

    @if ($currencyTotals->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-2xl font-black text-ink">{{ __('ui.public_money.procurement_amounts') }}</h2>
            <p class="mt-2 text-sm text-ink-muted">{{ __('ui.public_money.no_currency_mix') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach ($currencyTotals as $currency => $total)
                    <div class="rounded-3xl border border-line bg-surface p-5"><span class="text-xs font-black text-ink-muted">{{ $currency }}</span><p class="mt-1 text-2xl font-black">{{ number_format((float) $total, 2) }}</p></div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-12">
        <div class="flex items-end justify-between gap-4"><div><p class="text-sm font-black text-accent">{{ __('ui.public_money.official_records') }}</p><h2 class="mt-1 text-3xl font-black">{{ __('ui.public_money.latest_procurements') }}</h2></div><a class="font-black text-accent" href="{{ route('public-money.procurements') }}">{{ __('ui.public_money.view_all') }}</a></div>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @forelse ($procurements as $record)
                <a href="{{ route('public-money.procurement', $record) }}" class="group rounded-3xl border border-line bg-surface p-5 transition hover:-translate-y-1 hover:border-accent/30">
                    <div class="flex items-center justify-between gap-3"><span class="rounded-full bg-surface-soft px-3 py-1 text-xs font-black">{{ __('ui.public_money.stage_'.$record->stage) }}</span><time class="text-xs text-ink-muted">{{ optional($record->event_on)->format('Y-m-d') ?: '—' }}</time></div>
                    <h3 class="mt-4 text-lg font-black leading-7 group-hover:text-accent">{{ $record->title }}</h3>
                    <p class="mt-2 text-sm text-ink-muted">{{ $record->authority }}</p>
                    @if ($record->amount !== null)<p class="mt-4 font-black">{{ number_format((float) $record->amount, 2) }} <span class="text-sm text-ink-muted">{{ $record->currency }}</span></p>@endif
                </a>
            @empty
                <div class="rounded-3xl border border-dashed border-line p-8 text-ink-muted">{{ __('ui.public_money.no_published_records') }}</div>
            @endforelse
        </div>
    </section>

    @if ($financial->isNotEmpty())
        <section class="mt-12 rounded-[2rem] bg-[#eef3e8] p-6 sm:p-8">
            <h2 class="text-3xl font-black">{{ __('ui.public_money.budget_spending') }}</h2>
            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                @foreach ($financial as $item)
                    <div class="rounded-2xl bg-white p-5"><p class="text-sm font-bold text-ink-muted">{{ __('ui.public_money.measure_'.$item->measure_type) }} · {{ $item->fiscal_period }}</p><p class="mt-2 text-2xl font-black">{{ number_format((float) $item->amount) }}</p><p class="text-xs text-ink-muted">{{ $item->original_unit }}</p></div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.site>
