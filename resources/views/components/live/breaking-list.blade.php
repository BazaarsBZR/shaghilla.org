@props([
    'items' => collect(),
])

<div class="rounded-2xl bg-white/5 ring-1 ring-white/10">
    <div class="border-b border-white/10 px-4 py-3 text-sm font-extrabold text-white">
        {{ __('ui.sections.breaking_news') }}
    </div>

    <div class="max-h-[520px] overflow-y-auto p-2">
        @forelse ($items as $article)
            @php
                $tickerOnly = $article->feedSource?->destination === \App\Models\FeedSource::DESTINATION_BREAKING
                    || ($article->feed_source_id === null
                        && empty($article->canonical_url)
                        && str_starts_with((string) $article->guid, 'https://almanar.com.lb/'));
            @endphp
            @if($tickerOnly)
                <div class="block rounded-xl px-3 py-3 text-sm text-white/90">
                    <div class="text-xs text-white/60">
                        {{ optional($article->published_at)->format('H:i') }}
                    </div>
                    <div class="mt-1 line-clamp-2 font-semibold">
                        {{ $article->title }}
                    </div>
                </div>
            @else
                <a
                    href="{{ route('news.show', $article->slug) }}"
                    class="block rounded-xl px-3 py-3 text-sm text-white/90 hover:bg-white/5"
                >
                    <div class="text-xs text-white/60">
                        {{ optional($article->published_at)->format('H:i') }}
                    </div>
                    <div class="mt-1 line-clamp-2 font-semibold">
                        {{ $article->title }}
                    </div>
                </a>
            @endif
        @empty
            <div class="px-4 py-6 text-center text-sm text-white/60">
                {{ __('ui.messages.no_breaking_news') }}
            </div>
        @endforelse
    </div>
</div>
