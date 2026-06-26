@props([
    'items' => collect(),
])

<div class="space-y-3">
    <div class="flex items-center justify-between">
        <div class="text-sm font-extrabold text-white">{{ __('ui.sections.news_reports') }}</div>
    </div>

    <div class="-mx-2 flex gap-3 overflow-x-auto px-2 pb-2">
        @foreach ($items as $item)
            <div class="w-72 shrink-0">
                <x-live.video-card :item="$item" />
            </div>
        @endforeach
    </div>
</div>

