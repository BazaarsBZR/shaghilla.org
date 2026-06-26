@props([
    'item',
])

@php
    $isHosted = $item instanceof \App\Models\HostedVideo;

    if ($isHosted) {
        $title = (string) ($item->title ?? '');
        $href = route('hosted-videos.show', $item->slug);
        $thumb = method_exists($item, 'posterUrl') ? $item->posterUrl() : null;
        $publishedAt = $item->published_at ?? null;
    } else {
        $title = (string) ($item->title ?? '');
        $youtubeUrl = (string) ($item->youtube_url ?? '');
        $youtubeId = \App\Support\YouTube::extractId($youtubeUrl);
        $href = $youtubeId ? route('live', ['v' => $youtubeId]) : route('live');
        $thumb = ($item->thumbnail_url ?? null) ?: ($youtubeId ? "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg" : null);
        $publishedAt = $item->published_at ?? null;
    }
@endphp

<a
    href="{{ $href }}"
    class="group block overflow-hidden rounded-card bg-surface shadow-surface ring-1 ring-line hover:bg-surface-soft"
>
    <div class="relative aspect-[16/9] w-full overflow-hidden bg-surface-soft">
        @if ($thumb)
            <img
                src="{{ $thumb }}"
                alt=""
                loading="lazy"
                decoding="async"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
            />
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-surface-soft to-canvas"></div>
        @endif

        <div class="absolute inset-0 flex items-center justify-center">
            <div class="flex h-12 w-12 items-center justify-center rounded-pill bg-night/55 ring-1 ring-white/15 transition group-hover:bg-night/70">
                <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M8 5v14l11-7z" />
                </svg>
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-2 p-4">
        <div class="line-clamp-2 text-right text-sm font-extrabold text-ink">
            {{ $title }}
        </div>

        <div class="mt-auto flex items-center justify-between gap-2 text-xs font-semibold text-ink-muted">
            <x-news.share-menu :url="$href" :title="$title" />

            @if (! empty($publishedAt))
                <div class="text-right">
                    {{ optional($publishedAt)->format('Y-m-d') }}
                </div>
            @endif
        </div>
    </div>
</a>
