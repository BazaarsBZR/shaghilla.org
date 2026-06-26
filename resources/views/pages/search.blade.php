<x-layouts.site :title="__('ui.pages.search')">
    <div class="space-y-6">
        <form method="get" action="{{ route('search') }}" class="space-y-2">
            <label for="q" class="text-sm font-bold text-gray-900">{{ __('ui.actions.search') }}</label>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $query }}"
                    placeholder="{{ __('ui.search.placeholder') }}"
                    class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-gray-900 shadow-sm outline-none focus:border-gray-900/30 focus:ring-2 focus:ring-gray-900/10 sm:flex-1"
                />
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-2xl bg-gray-900 px-5 py-3 text-sm font-extrabold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900/20"
                >
                    {{ __('ui.actions.search') }}
                </button>
            </div>
        </form>

        @if ($query !== '')
            <div class="space-y-4">
                <h2 class="text-base font-extrabold text-gray-900">
                    {{ __('ui.search.results_for', ['query' => $query]) }}
                </h2>

                @if ($articles->count() > 0)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($articles as $article)
                            <x-news.article-card :article="$article" />
                        @endforeach
                    </div>

                    <div class="pt-4">
                        {{ $articles->links() }}
                    </div>
                @else
                    <p class="text-sm font-semibold text-gray-600">{{ __('ui.search.no_results') }}</p>
                @endif
            </div>
        @else
            <p class="text-sm font-semibold text-gray-600">{{ __('ui.search.hint') }}</p>
        @endif
    </div>
</x-layouts.site>

