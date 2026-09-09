@props([
    'title' => null,
    'items' => collect(),
    'layout' => 'grid', // grid | carousel
])

@php
    $items = $items ?? collect();
    $layout = in_array($layout, ['grid', 'carousel'], true) ? $layout : 'grid';
@endphp

@if ($items->isNotEmpty())
    <section class="space-y-4">
        <div class="flex items-center justify-between border-b border-line pb-2">
            <h2 class="text-lg font-extrabold tracking-tight text-ink">
                {{ $title ?: 'الفيديو' }}
            </h2>
        </div>

        @if ($layout === 'carousel')
            <div class="-mx-4 flex gap-4 overflow-x-auto px-4 pb-2">
                @foreach ($items as $item)
                    <div class="w-72 shrink-0">
                        <x-home.media-card :item="$item" />
                    </div>
                @endforeach
                <div class="w-72 shrink-0">
                    <x-home.almanar-live-card />
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    <x-home.media-card :item="$item" />
                @endforeach
                <x-home.almanar-live-card />
            </div>
        @endif
    </section>
@endif
