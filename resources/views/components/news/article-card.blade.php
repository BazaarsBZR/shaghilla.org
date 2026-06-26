@props([
    'article',
])

<article class="group h-full overflow-hidden rounded-card border border-line bg-surface shadow-surface hover:bg-surface-soft">
    <a href="{{ route('news.show', $article->slug) }}" class="flex h-full flex-col">
        <div class="relative aspect-[16/9] w-full shrink-0 overflow-hidden bg-surface-soft">
            @if (! empty($article->image_url))
                <img
                    src="{{ $article->image_url }}"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.01]"
                />
            @else
                <div class="absolute inset-0 bg-gradient-to-br from-surface-soft to-canvas"></div>
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
