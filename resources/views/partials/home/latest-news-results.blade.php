@php
    $latestArticles = collect($latestArticles ?? collect());
@endphp

@if ($latestArticles->isNotEmpty())
    <div class="sh-latest-grid grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
        @foreach ($latestArticles as $article)
            <x-news.article-card :article="$article" />
        @endforeach
    </div>
@else
    <div class="rounded-card border border-line bg-surface p-4 text-right text-sm font-semibold text-ink-muted">
        {{ __('ui.search.no_results') }}
    </div>
@endif

@if (isset($latestPaginator) && $latestPaginator->lastPage() > 1)
    @php
        $currentPage = $latestPaginator->currentPage();
        $lastPage = $latestPaginator->lastPage();
        $prevPage = max(1, $currentPage - 1);
        $nextPage = min($lastPage, $currentPage + 1);
    @endphp

    <nav class="flex flex-wrap items-center justify-center gap-2 pt-4" aria-label="صفحات آخر الأخبار">
        @if ($currentPage > 1)
            <a
                href="{{ route('home', ['latest_page' => $prevPage]) }}#latest-news"
                data-latest-page="{{ $prevPage }}"
                class="inline-flex h-9 items-center justify-center rounded-control border border-line bg-surface px-3 text-sm font-extrabold text-ink-muted transition hover:bg-surface-soft hover:text-ink"
            >
                <span aria-hidden="true">→</span>
                {{ __('ui.actions.previous') }}
            </a>
        @else
            <span class="inline-flex h-9 items-center justify-center rounded-control border border-line bg-surface-soft px-3 text-sm font-extrabold text-ink-faint">
                {{ __('ui.actions.previous') }}
            </span>
        @endif

        @foreach (collect(range(max(1, $currentPage - 2), min($lastPage, $currentPage + 2)))->unique() as $page)
            <a href="{{ route('home', ['latest_page' => $page]) }}#latest-news" data-latest-page="{{ $page }}" @class([
                'inline-flex h-10 w-10 items-center justify-center rounded-full border text-sm font-extrabold transition',
                'border-night bg-night text-white shadow-sm' => $page === $currentPage,
                'border-line bg-surface text-ink-muted hover:border-night hover:text-ink' => $page !== $currentPage,
            ]) aria-current="{{ $page === $currentPage ? 'page' : 'false' }}">{{ $page }}</a>
        @endforeach

        @if ($currentPage < $lastPage)
            <a
                href="{{ route('home', ['latest_page' => $nextPage]) }}#latest-news"
                data-latest-page="{{ $nextPage }}"
                class="inline-flex h-9 items-center justify-center rounded-control border border-line bg-surface px-3 text-sm font-extrabold text-ink-muted transition hover:bg-surface-soft hover:text-ink"
            >
                {{ __('ui.actions.next') }}
                <span aria-hidden="true">←</span>
            </a>
        @else
            <span class="inline-flex h-9 items-center justify-center rounded-control border border-line bg-surface-soft px-3 text-sm font-extrabold text-ink-faint">
                {{ __('ui.actions.next') }}
            </span>
        @endif
    </nav>
@endif
