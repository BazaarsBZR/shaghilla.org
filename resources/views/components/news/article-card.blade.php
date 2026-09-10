@props([
    'article',
])

@php
    $mediaUrl = trim((string) ($article->image_url ?? ''));
    $isVideo = preg_match('/\.(mp4|webm|mov|m4v)(?:$|[?#])/i', $mediaUrl) === 1;
    $summary = \Illuminate\Support\Str::limit(
        trim((string) preg_replace('/[[:space:]]+/u', ' ', strip_tags((string) ($article->excerpt ?: $article->content ?: '')))),
        150,
    );
    $sourceName = trim((string) ($article->feedSource?->name ?? ''));
@endphp

<article @class([
    'group h-full overflow-hidden rounded-card border border-line bg-surface shadow-surface transition hover:-translate-y-0.5 hover:shadow-overlay',
    'border-t-4 border-t-accent' => $mediaUrl === '',
])>
    <a href="{{ route('news.show', $article->slug) }}" class="flex h-full flex-col">
        @if ($mediaUrl !== '')
            <div class="relative aspect-[16/9] w-full shrink-0 overflow-hidden bg-surface-soft">
                <x-news.image-fallback compact />

                @if ($isVideo)
                    <span class="absolute inset-0 flex items-center justify-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-night/85 text-white shadow-overlay ring-4 ring-white/70">
                            <svg class="ms-0.5 h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                        </span>
                    </span>
                @else
                    <img
                        src="{{ $mediaUrl }}"
                        alt="{{ $article->title }}"
                        width="640"
                        height="360"
                        loading="lazy"
                        decoding="async"
                        fetchpriority="low"
                        referrerpolicy="no-referrer"
                        onerror="this.style.display='none'"
                        class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                    />
                @endif
            </div>
        @endif

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div class="flex items-center justify-between gap-3 text-[11px] font-black text-accent">
                <span>{{ $sourceName !== '' ? $sourceName : 'آخر الأخبار' }}</span>
                <span class="font-semibold text-ink-faint">{{ optional($article->published_at)->format('d/m · H:i') }}</span>
            </div>

            <h3 class="line-clamp-3 text-right text-[17px] font-extrabold leading-snug text-ink">
                {{ $article->title }}
            </h3>

            @if ($summary !== '')
                <p class="line-clamp-3 text-right text-sm font-medium leading-6 text-ink-muted">{{ $summary }}</p>
            @endif

            <div class="mt-auto flex flex-wrap items-center justify-between gap-2 text-[11px] font-semibold text-ink-muted">
                <span class="inline-flex items-center gap-1 font-extrabold text-ink">اقرأ الخبر <span aria-hidden="true">←</span></span>
                <x-news.share-menu :article="$article" />
            </div>
        </div>
    </a>
</article>
