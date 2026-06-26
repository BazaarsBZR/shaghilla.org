@props([
    'video',
])

@php
    $posterUrl = method_exists($video, 'posterUrl') ? $video->posterUrl() : null;
    $title = (string) ($video->title ?? '');
    $videoUrl = route('hosted-videos.show', $video->slug);
@endphp

<a
    href="{{ $videoUrl }}"
    class="group block overflow-hidden rounded-card bg-surface shadow-surface ring-1 ring-line hover:bg-surface-soft"
>
    <div class="relative aspect-[16/9] w-full overflow-hidden bg-surface-soft">
        @if ($posterUrl)
            <img
                src="{{ $posterUrl }}"
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
            <x-news.share-menu :url="$videoUrl" :title="$title" />

            @if (! empty($video->published_at))
                <div class="text-right">
                    {{ optional($video->published_at)->format('Y-m-d') }}
                </div>
            @endif
        </div>
    </div>
</a>
