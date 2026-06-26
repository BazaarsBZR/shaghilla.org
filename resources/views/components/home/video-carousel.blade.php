@props([
    'title' => null,
    'items' => collect(),
])

@if (($items ?? collect())->isNotEmpty())
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-extrabold tracking-tight text-gray-900">
                {{ $title ?: 'الفيديو' }}
            </h2>
        </div>

        <div class="-mx-4 flex gap-4 overflow-x-auto px-4 pb-2">
            @foreach ($items as $item)
                <div class="w-72 shrink-0">
                    <x-home.video-card :item="$item" />
                </div>
            @endforeach
        </div>
    </section>
@endif

