<x-layouts.site :title="$record->title.' | '.config('app.name')">
    <a href="{{ route('public-money.procurements') }}" class="text-sm font-black text-accent">{{ __('ui.public_money.back_to_procurements') }}</a>
    <article class="mt-4 overflow-hidden rounded-[2rem] border border-line bg-surface">
        <header class="bg-[#102c2a] p-6 text-white sm:p-10"><span class="rounded-full bg-white/10 px-3 py-1 text-xs font-black">{{ __('ui.public_money.stage_'.$record->stage) }}</span><h1 class="mt-5 max-w-4xl text-3xl font-black leading-tight sm:text-5xl">{{ $record->title }}</h1><p class="mt-4 text-white/70">{{ $record->authority }}</p></header>
        <div class="grid gap-8 p-6 sm:p-10 lg:grid-cols-[1fr_.6fr]">
            <dl class="grid gap-5 sm:grid-cols-2">
                @foreach ([__('ui.public_money.authority') => $record->authority, __('ui.public_money.supplier') => $record->supplier, __('ui.public_money.amount') => $record->amount !== null ? number_format((float) $record->amount, 2).' '.$record->currency : null, __('ui.public_money.event_date') => optional($record->event_on)->format('Y-m-d'), __('ui.public_money.procurement_id') => $record->procurement_id, __('ui.public_money.status') => $record->status_normalized] as $label => $value)
                    <div><dt class="text-xs font-black text-ink-muted">{{ $label }}</dt><dd class="mt-1 font-bold">{{ $value ?: __('ui.public_money.not_reported') }}</dd></div>
                @endforeach
            </dl>
            <aside class="rounded-3xl bg-[#eef3e8] p-5"><h2 class="font-black">{{ __('ui.public_money.provenance') }}</h2><p class="mt-3 text-sm leading-7 text-ink-muted">{{ $record->source->name_ar }}<br>{{ __('ui.public_money.retrieved') }}: {{ optional($record->document?->retrieved_at)->format('Y-m-d H:i') ?: '—' }}<br>{{ __('ui.public_money.revision') }}: {{ $record->revision }}</p><a href="{{ $record->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex rounded-full bg-[#102c2a] px-4 py-2 text-sm font-black text-white">{{ __('ui.public_money.open_official_source') }}</a></aside>
        </div>
    </article>
</x-layouts.site>
