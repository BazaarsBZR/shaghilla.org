@props([
    'article',
])

@php
    $mediaUrl = trim((string) ($article->image_url ?? ''));
    $isVideo = preg_match('/\.(mp4|webm|mov|m4v)(?:$|[?#])/i', $mediaUrl) === 1;
@endphp

<article class="group h-full overflow-hidden rounded-card border border-line bg-surface shadow-surface hover:bg-surface-soft">
    <a href="{{ route('news.show', $article->slug) }}" class="flex h-full flex-col">
        <div class="relative aspect-[16/9] w-full shrink-0 overflow-hidden bg-surface-soft">
            <x-news.image-fallback compact />

            @if ($isVideo)
                <span class="absolute inset-0 flex items-center justify-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-night/85 text-white shadow-overlay ring-4 ring-white/70">
                        <svg class="ms-0.5 h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                    </span>
                </span>
            @elseif ($mediaUrl !== '')
                <img
                    src="{{ $mediaUrl }}"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    referrerpolicy="no-referrer"
                    onerror="this.style.display='none'"
                    class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.01]"
                />
            @endif
        </div>

        <div class="flex flex-1 flex-col gap-2 p-3">
            <h3 class="line-clamp-2 text-right text-[15px] font-extrabold leading-snug text-ink">
                {{ $article->title }}
            </h3>

            <div class="mt-auto flex flex-wrap items-center justify-between gap-2 text-[11px] font-semibold text-ink-muted">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2 w-2 rounded-full bg-accent"></span>
                    <span>{{ optional($article->published_at)->format('H:i') }}</span>
                </div>
                <x-news.share-menu :article="$article" />
            </div>
        </div>
    </a>
</article>
