@props([
    'title' => null,
    'items' => collect(),
])

@php
    $items = $items ?? collect();
@endphp

@if ($items->isNotEmpty())
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-extrabold tracking-tight text-gray-900">
                {{ $title ?: 'الفيديو' }}
            </h2>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $item)
                <x-home.video-card :item="$item" />
            @endforeach
        </div>
    </section>
@endif

