<x-layouts.site :title="__('ui.public_money.procurements').' | '.config('app.name')">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm font-black text-accent">{{ __('ui.public_money.title') }}</p><h1 class="mt-1 text-4xl font-black">{{ __('ui.public_money.procurements') }}</h1></div><a href="{{ route('public-money.index') }}" class="font-bold text-ink-muted">{{ __('ui.public_money.overview') }}</a></div>
    <form class="mt-7 grid gap-3 rounded-3xl border border-line bg-surface p-4 md:grid-cols-4">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('ui.public_money.search') }}" class="rounded-xl border-line bg-surface-soft md:col-span-2" />
        <select name="stage" class="rounded-xl border-line bg-surface-soft"><option value="">{{ __('ui.public_money.all_stages') }}</option>@foreach ($filters['stages'] as $value)<option value="{{ $value }}" @selected(request('stage') === $value)>{{ __('ui.public_money.stage_'.$value) }}</option>@endforeach</select>
        <select name="currency" class="rounded-xl border-line bg-surface-soft"><option value="">{{ __('ui.public_money.all_currencies') }}</option>@foreach ($filters['currencies'] as $value)<option value="{{ $value }}" @selected(request('currency') === $value)>{{ $value }}</option>@endforeach</select>
        <button class="rounded-xl bg-accent px-5 py-3 font-black text-white">{{ __('ui.actions.search') }}</button>
    </form>
    <div class="mt-6 overflow-hidden rounded-3xl border border-line bg-surface">
        <div class="divide-y divide-line md:hidden">
            @forelse ($records as $record)
                @php($isTender = $record->stage === 'tender')
                <article class="p-5">
                    <div class="flex items-center justify-between gap-3">
                        <span class="rounded-full bg-[#e7f3ec] px-3 py-1 text-[11px] font-black text-[#21704f]">{{ $isTender ? $record->tenderStatusLabel() : $record->publicMoneyStageLabel() }}</span>
                        <time class="text-xs font-bold text-ink-muted" dir="ltr">{{ optional($record->event_on)->format('Y-m-d') ?: '—' }}</time>
                    </div>
                    <a class="mt-4 block text-lg font-black leading-8 text-ink hover:text-accent" href="{{ route('public-money.procurement', $record) }}">{{ $record->title }}</a>
                    <p class="mt-2 text-sm leading-6 text-ink-muted">{{ $record->authority }}</p>
                    <dl class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-surface-soft p-4 text-sm">
                        <div><dt class="text-xs font-bold text-ink-muted">{{ $isTender ? 'آخر موعد للتقديم' : __('ui.public_money.supplier') }}</dt><dd class="mt-1 font-black text-ink" @if($isTender) dir="ltr" @endif>{{ $isTender ? ($record->effectiveTenderDeadline()?->timezone('Asia/Beirut')->format('Y-m-d H:i') ?: 'غير محدد') : $record->supplierDisplayLabel() }}</dd></div>
                        <div><dt class="text-xs font-bold text-ink-muted">{{ $isTender ? 'رقم المرجع' : __('ui.public_money.amount') }}</dt><dd class="mt-1 font-black text-ink" dir="auto">{{ $isTender ? ($record->reference_number ?: $record->source_record_id ?: 'غير مذكور') : $record->amountDisplayLabel() }}</dd></div>
                    </dl>
                </article>
            @empty
                <p class="px-5 py-12 text-center text-ink-muted">{{ __('ui.public_money.no_published_records') }}</p>
            @endforelse
        </div>
        <div class="hidden overflow-x-auto md:block"><table class="min-w-full text-sm"><thead class="bg-surface-soft text-ink-muted"><tr><th class="px-4 py-3 text-start">{{ __('ui.public_money.record') }}</th><th class="px-4 py-3 text-start">{{ __('ui.public_money.authority') }}</th><th class="px-4 py-3 text-start">المورّد / آخر موعد</th><th class="px-4 py-3 text-start">القيمة / المرجع</th></tr></thead><tbody class="divide-y divide-line">@forelse ($records as $record)@php($isTender = $record->stage === 'tender')<tr><td class="px-4 py-4"><a class="font-black hover:text-accent" href="{{ route('public-money.procurement', $record) }}">{{ $record->title }}</a><div class="mt-1 text-xs text-ink-muted">{{ $isTender ? $record->tenderStatusLabel() : $record->publicMoneyStageLabel() }} · {{ optional($record->event_on)->format('Y-m-d') ?: '—' }}</div></td><td class="px-4 py-4">{{ $record->authority }}</td><td class="px-4 py-4 font-bold">@if($isTender)<span class="block text-xs text-ink-muted">آخر موعد للتقديم</span><span dir="ltr">{{ $record->effectiveTenderDeadline()?->timezone('Asia/Beirut')->format('Y-m-d H:i') ?: 'غير محدد' }}</span>@else{{ $record->supplierDisplayLabel() }}@endif</td><td class="px-4 py-4 font-black" dir="auto">{{ $isTender ? ($record->reference_number ?: $record->source_record_id ?: 'غير مذكور') : $record->amountDisplayLabel() }}</td></tr>@empty<tr><td colspan="4" class="px-4 py-10 text-center text-ink-muted">{{ __('ui.public_money.no_published_records') }}</td></tr>@endforelse</tbody></table></div>
    </div>
    <div class="mt-6">{{ $records->links() }}</div>
</x-layouts.site>
