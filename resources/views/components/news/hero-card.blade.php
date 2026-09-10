@props([
    'article',
    'fill' => false,
])

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

        @if (! empty($article->image_url))
            <img
                src="{{ $article->image_url }}"
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
