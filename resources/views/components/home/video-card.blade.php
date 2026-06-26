@props([
    'item',
])

@php
    $youtubeId = \App\Support\YouTube::extractId($item->youtube_url);
    $thumbnail = $item->thumbnail_url ?: ($youtubeId ? "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg" : null);
    $liveUrl = $youtubeId ? route('live', ['v' => $youtubeId]) : route('live');
@endphp

<a
    href="{{ $liveUrl }}"
    class="group block overflow-hidden rounded-card bg-surface shadow-surface ring-1 ring-line hover:bg-surface-soft"
>
    <div class="relative aspect-[16/9] w-full overflow-hidden bg-surface-soft">
        @if ($thumbnail)
            <img
                src="{{ $thumbnail }}"
                alt=""
                loading="lazy"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
            />
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-surface-soft to-canvas"></div>
        @endif
    </div>

    <div class="flex flex-col gap-2 p-4">
        <div class="line-clamp-2 text-sm font-extrabold text-ink">
            {{ $item->title }}
        </div>

        <div class="mt-auto flex items-center justify-between gap-2 text-xs font-semibold text-ink-muted">
            <x-news.share-menu :url="$liveUrl" :title="(string) $item->title" />

            @if ($item->published_at)
                <div>
                    {{ $item->published_at->format('Y-m-d') }}
                </div>
            @endif
        </div>
    </div>
</a>
