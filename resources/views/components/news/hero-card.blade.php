@props([
    'article',
    'fill' => false,
])

@php
    $mediaUrl = trim((string) ($article->image_url ?? ''));
    $isVideo = preg_match('/\.(mp4|webm|mov|m4v)(?:$|[?#])/i', $mediaUrl) === 1;
@endphp

<a
    href="{{ route('news.show', $article->slug) }}"
    @class([
        'group relative block overflow-hidden rounded-card border border-line bg-surface-soft shadow-surface',
        'h-full' => (bool) $fill,
    ])
>
    <div
        @class([
            'relative aspect-[16/9] w-full overflow-hidden bg-surface-soft',
            'md:aspect-auto md:h-full' => (bool) $fill,
        ])
    >
        <x-news.image-fallback />

        @if ($isVideo)
            <span class="absolute inset-0 flex items-center justify-center">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-night/85 text-white shadow-overlay ring-4 ring-white/70">
                    <svg class="ms-1 h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                </span>
            </span>
        @elseif ($mediaUrl !== '')
            <img
                src="{{ $mediaUrl }}"
                alt=""
                loading="eager"
                decoding="async"
                fetchpriority="high"
                referrerpolicy="no-referrer"
                onerror="this.style.display='none'"
                class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.01]"
            />
        @endif
    </div>

    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent"></div>

    <div class="absolute bottom-0 w-full p-4">
        <div class="mb-2 flex items-center justify-between gap-3 text-xs text-white/85">
            <div class="flex items-center gap-2">
                <span class="inline-block h-2 w-2 rounded-full bg-accent"></span>
                <span class="rounded bg-night/45 px-2 py-1 font-semibold">
                    {{ optional($article->published_at)->format('H:i') }}
                </span>
            </div>

            <x-news.share-menu :article="$article" variant="dark" />
        </div>

        <h2 class="text-right text-xl font-extrabold leading-snug text-white sm:text-2xl lg:text-3xl">
            {{ $article->title }}
        </h2>
    </div>
</a>
