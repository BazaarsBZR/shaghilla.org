@push('head')
    <link rel="canonical" href="{{ request()->url() }}" />
@endpush

<x-layouts.site :title="$video->title">
    <article class="mx-auto max-w-4xl space-y-6">
        <header class="space-y-2">
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl">
                {{ $video->title }}
            </h1>
            @if ($video->published_at)
                <div class="text-sm font-semibold text-gray-600">
                    {{ $video->published_at->format('Y-m-d') }}
                </div>
            @endif
        </header>

        <div class="overflow-hidden rounded-2xl bg-black ring-1 ring-black/10">
            <div class="aspect-video w-full">
                @php($embedUrl = \App\Support\YouTube::embedUrl($video->youtube_url))
                @if ($embedUrl)
                    <iframe
                        class="h-full w-full"
                        src="{{ $embedUrl }}"
                        title="{{ $video->title }}"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen
                    ></iframe>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('home') }}" class="text-sm font-extrabold text-gray-900 hover:underline">
                {{ __('ui.nav.home') }}
            </a>

            <div class="flex items-center gap-3">
                <x-news.share-menu :url="request()->url()" :title="$video->title" />

                <a
                    href="{{ $video->youtube_url }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center rounded-2xl border border-gray-300 bg-white px-5 py-3 text-sm font-extrabold text-gray-900 shadow-sm hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900/20"
                >
                    YouTube
                </a>
            </div>
        </div>
    </article>
</x-layouts.site>
